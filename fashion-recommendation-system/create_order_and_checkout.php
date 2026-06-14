<?php
// Creates an order record and (if Stripe configured) a Checkout session, returning JSON with redirect URL.
include_once __DIR__ . '/auth.php';
require_login();
include_once __DIR__ . '/db.php';
include_once __DIR__ . '/csrf.php';
include_once __DIR__ . '/stripe_config.php';

header('Content-Type: application/json');

$user = get_logged_in_user();
$user_id = $user['id'];

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { echo json_encode(['error'=>'invalid_payload']); exit(); }

$token = $data['csrf_token'] ?? null;
if (!verify_csrf_token($token)) { echo json_encode(['error'=>'invalid_csrf']); exit(); }

$total = floatval($data['total'] ?? 0);
$use_points = intval($data['use_points'] ?? 0);

// compute discount
$discount = 0;
if ($use_points > 0) {
    $pts = intval($user['points'] ?? 0);
    $to_use = min($pts, $use_points);
    $discount = ($to_use / 100.0);
} else $to_use = 0;

$final = max(0, $total - $discount);

// create order
$stmt = $conn->prepare("INSERT INTO orders (user_id, total, status, payment_info) VALUES (?, ?, 'pending', ?)");
$payinfo = json_encode(['use_points' => $to_use, 'discount' => $discount]);
$stmt->bind_param('ids', $user_id, $final, $payinfo);
$stmt->execute();
$order_id = $stmt->insert_id;

// If no Stripe configured, return a mock payment url
if (empty(STRIPE_SECRET)) {
    $mock = '/mock_payment.php?order_id=' . $order_id . '&use_points=' . intval($to_use);
    echo json_encode(['url' => $mock, 'order_id' => $order_id]);
    exit();
}

// Create Stripe Checkout Session
$host = $_SERVER['HTTP_HOST'];
$success = "http://{$host}/stripe_success.php?order_id={$order_id}&session_id={CHECKOUT_SESSION_ID}";
$cancel = "http://{$host}/checkout.php";

$params = [
    'payment_method_types[]' => 'card',
    'mode' => 'payment',
    'line_items[0][price_data][currency]' => 'usd',
    'line_items[0][price_data][product_data][name]' => 'Order '.$order_id,
    'line_items[0][price_data][unit_amount]' => intval(round($final*100)),
    'line_items[0][quantity]' => 1,
    'success_url' => $success,
    'cancel_url' => $cancel
    ,'metadata[order_id]' => strval($order_id)
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET . ':');
curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
$resp = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['error'=>'stripe_error','detail'=>$err]);
    exit();
}

$json = json_decode($resp, true);
if (!$json || empty($json['url'])) {
    echo json_encode(['error'=>'stripe_no_url','resp'=>$json]);
    exit();
}

echo json_encode(['url' => $json['url'], 'order_id' => $order_id, 'session' => $json]);
exit();

?>
