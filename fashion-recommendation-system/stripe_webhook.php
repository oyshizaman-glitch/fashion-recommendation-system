<?php
// Stripe webhook receiver to mark orders paid.
include_once __DIR__ . '/db.php';
include_once __DIR__ . '/stripe_config.php';

// Read payload
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// If webhook secret provided, verify signature (simple v1 check)
if (!empty(getenv('STRIPE_WEBHOOK_SECRET')) || !empty(defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : false)) {
    $wh_secret = getenv('STRIPE_WEBHOOK_SECRET') ?: (defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '');
    if ($sig_header && $wh_secret) {
        // parse t=..., v1=...
        $parts = explode(',', $sig_header);
        $vals = [];
        foreach ($parts as $p) {
            [$k, $v] = array_map('trim', explode('=', $p, 2) + [1 => '']);
            $vals[$k] = $v;
        }
        $t = $vals['t'] ?? '';
        $v1 = $vals['v1'] ?? '';
        if (!$t || !$v1) {
            http_response_code(400);
            echo 'missing_sig_fields';
            exit();
        }
        $signed_payload = $t . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $wh_secret);
        if (!hash_equals($expected, $v1)) {
            http_response_code(400);
            echo 'invalid_signature';
            exit();
        }
    }
}

$event = json_decode($payload, true);
if (!$event) { http_response_code(400); echo 'invalid_json'; exit(); }

$type = $event['type'] ?? '';

// Support checkout.session.completed or payment_intent.succeeded
if ($type === 'checkout.session.completed' || $type === 'payment_intent.succeeded') {
    $obj = $event['data']['object'] ?? [];
    // Try to obtain order_id from metadata
    $order_id = 0;
    if (!empty($obj['metadata']['order_id'])) $order_id = intval($obj['metadata']['order_id']);
    // fallback: check client_reference_id or reference
    if ($order_id <= 0 && !empty($obj['client_reference_id'])) $order_id = intval($obj['client_reference_id']);

    if ($order_id > 0) {
        // mark order paid
        $stmt = $conn->prepare("UPDATE orders SET status='paid' WHERE id = ? AND status != 'paid'");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();

        // award points based on total
        $ost = $conn->prepare("SELECT total, user_id FROM orders WHERE id = ? LIMIT 1");
        $ost->bind_param('i', $order_id);
        $ost->execute();
        $res = $ost->get_result()->fetch_assoc();
        if ($res) {
            $award = intval(round(floatval($res['total'])));
            if ($award > 0) {
                $ap = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                $ap->bind_param('ii', $award, $res['user_id']);
                $ap->execute();
                $tx = $conn->prepare("INSERT INTO points_tx (user_id, delta, reason) VALUES (?, ?, 'Purchase reward')");
                $tx->bind_param('ii', $res['user_id'], $award);
                $tx->execute();
            }
        }
    }
}

// Return 200 to acknowledge
http_response_code(200);
echo 'ok';

?>
