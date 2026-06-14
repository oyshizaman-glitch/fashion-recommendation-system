<?php
// Handle return from Stripe Checkout: verify session and mark order paid
include_once __DIR__ . '/auth.php';
include_once __DIR__ . '/db.php';
include_once __DIR__ . '/stripe_config.php';

$order_id = intval($_GET['order_id'] ?? 0);
$session_id = $_GET['session_id'] ?? '';

if (empty(STRIPE_SECRET) || $order_id <= 0 || empty($session_id)) {
    header('Location: dashboard.php?msg=Invalid+return');
    exit();
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/checkout/sessions/' . urlencode($session_id));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET . ':');
$resp = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    header('Location: dashboard.php?msg=stripe_error');
    exit();
}

$json = json_decode($resp, true);
$paid = false;
if ($json && isset($json['payment_status']) && $json['payment_status'] === 'paid') $paid = true;

if ($paid) {
    // mark order paid and award points like mock_payment
    $pstmt = $conn->prepare("UPDATE orders SET status='paid' WHERE id = ?");
    $pstmt->bind_param('i', $order_id);
    $pstmt->execute();

    // award points equal to rounded total (same logic as mock)
    $ost = $conn->prepare("SELECT total, user_id FROM orders WHERE id = ? LIMIT 1");
    $ost->bind_param('i', $order_id);
    $ost->execute();
    $or = $ost->get_result()->fetch_assoc();
    if ($or) {
        $award = intval(round(floatval($or['total'])));
        if ($award > 0) {
            $ap = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
            $ap->bind_param('ii', $award, $or['user_id']);
            $ap->execute();
            $tx = $conn->prepare("INSERT INTO points_tx (user_id, delta, reason) VALUES (?, ?, 'Purchase reward')");
            $tx->bind_param('ii', $or['user_id'], $award);
            $tx->execute();
        }
    }

    header('Location: dashboard.php?msg=Payment+successful');
    exit();
} else {
    header('Location: dashboard.php?msg=Payment+not+paid');
    exit();
}

?>
