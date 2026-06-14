<?php
session_start();
include_once __DIR__ . '/../db.php';
include_once __DIR__ . '/../stripe_config.php';

// Handle SSLCommerz (POST) or Stripe (GET ?session_id=)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tran_id'])) {
    // SSLCommerz callback
    $tran_id = $_POST['tran_id'];
    $amount = $_POST['amount'] ?? null;
    // mark order paid: tran_id is merchant tran id (we set it to order id when initiating),
    // use int cast for WHERE id and keep the raw tran as transaction_id
    $order_id = intval($tran_id);
    $stmt = $conn->prepare("UPDATE orders SET status = 'paid', payment_info = 'SSLCommerz', transaction_id = ? WHERE id = ?");
    $stmt->bind_param('si', $tran_id, $order_id);
    $stmt->execute();
    echo "Payment Successful via SSLCommerz!";
    exit;
}

// Stripe success (session_id in GET)
if (isset($_GET['session_id']) && defined('STRIPE_SECRET') && STRIPE_SECRET) {
    $session_id = $_GET['session_id'];
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($session_id));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET . ':');
    $res = curl_exec($ch);
    curl_close($ch);
    $js = json_decode($res, true);
    if (!empty($js['payment_status']) && $js['payment_status'] === 'paid') {
        $order_id = intval($js['client_reference_id'] ?? 0);
        if ($order_id > 0) {
            $stmt = $conn->prepare("UPDATE orders SET status = 'paid', payment_info = 'stripe', transaction_id = ? WHERE id = ?");
            $txn = $js['payment_intent'] ?? $session_id;
            $stmt->bind_param('si', $txn, $order_id);
            $stmt->execute();
        }
        echo "Payment Successful via Stripe!";
        exit;
    }
}

// Local debug simulation: ?order=ID
if (isset($_GET['order'])) {
    $order_id = intval($_GET['order']);
    if ($order_id > 0) {
        $stmt = $conn->prepare("UPDATE orders SET status = 'paid', payment_info = 'local_debug', transaction_id = ? WHERE id = ?");
        $txn = 'local-' . $order_id;
        $stmt->bind_param('si', $txn, $order_id);
        $stmt->execute();
        echo "Payment Successful (local debug) for order " . $order_id;
        exit;
    }
}

echo "Payment verification failed or missing data.";

?>
