<?php
session_start();
include_once __DIR__ . '/../db.php';
// mark order as failed if tran_id provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tran_id'])) {
    $tran_id = $_POST['tran_id'];
    $order_id = intval($tran_id);
    $stmt = $conn->prepare("UPDATE orders SET status = 'failed', payment_info = 'SSLCommerz' WHERE id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
}
echo "Payment Failed. Please try again.";

?>
