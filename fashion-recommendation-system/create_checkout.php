<?php
// Create a Stripe Checkout session (minimal, uses curl)
include_once __DIR__ . '/stripe_config.php';
include_once __DIR__ . '/auth.php';
require_login();
include_once __DIR__ . '/db.php';

if (empty(STRIPE_SECRET)) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe secret not configured']);
    exit();
}

// Accept total and order_id via POST
$payload = json_decode(file_get_contents('php://input'), true);
$total = isset($payload['total']) ? floatval($payload['total']) : 0;
$order_id = isset($payload['order_id']) ? intval($payload['order_id']) : 0;

// create session
$host = $_SERVER['HTTP_HOST'];
$success = "http://{$host}/dashboard.php?msg=paid";
$cancel = "http://{$host}/checkout.php";

$params = [
    'payment_method_types[]' => 'card',
    'mode' => 'payment',
    'line_items[0][price_data][currency]' => 'usd',
    'line_items[0][price_data][product_data][name]' => 'Order '.$order_id,
    'line_items[0][price_data][unit_amount]' => intval(round($total*100)),
    'line_items[0][quantity]' => 1,
    'success_url' => $success,
    'cancel_url' => $cancel
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
    http_response_code(500);
    echo json_encode(['error' => $err]);
    exit();
}

header('Content-Type: application/json');
echo $resp;

?>
