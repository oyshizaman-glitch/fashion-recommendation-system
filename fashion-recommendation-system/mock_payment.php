<?php
include_once 'auth.php';
require_login();
include_once 'db.php';

$order_id = intval($_GET['order_id'] ?? 0);
$use_points = intval($_GET['use_points'] ?? 0);

if ($order_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

// Mark order as paid (mock) and deduct points if requested
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$ord = $stmt->get_result()->fetch_assoc();
if (!$ord) {
    header('Location: dashboard.php?msg=Order+not+found');
    exit();
}

// Deduct points
if ($use_points > 0) {
    $user = get_logged_in_user();
    $available = intval($user['points'] ?? 0);
    $to_use = min($available, $use_points);
    if ($to_use > 0) {
        $dstmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ?");
        $dstmt->bind_param('ii', $to_use, $user['id']);
        $dstmt->execute();
        $tx = $conn->prepare("INSERT INTO points_tx (user_id, delta, reason) VALUES (?, ?, ?)");
        $reason = 'Used on order '.$order_id;
        $neg = -$to_use;
        $tx->bind_param('iis', $user['id'], $neg, $reason);
        $tx->execute();
    }
}

// Mark paid
$pstmt = $conn->prepare("UPDATE orders SET status='paid' WHERE id = ?");
$pstmt->bind_param('i', $order_id);
$pstmt->execute();

// Award points for purchase (1 point per currency unit)
$award = intval(round(floatval($ord['total'])));
if ($award > 0) {
    $ap = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $ap->bind_param('ii', $award, $ord['user_id']);
    $ap->execute();
    $tx = $conn->prepare("INSERT INTO points_tx (user_id, delta, reason) VALUES (?, ?, 'Purchase reward')");
    $tx->bind_param('ii', $ord['user_id'], $award);
    $tx->execute();
}

header('Location: dashboard.php?msg=Payment+successful');
exit();

?>
