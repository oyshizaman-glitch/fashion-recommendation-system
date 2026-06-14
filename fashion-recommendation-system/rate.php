<?php
include_once __DIR__ . '/auth.php';
require_login();
include_once __DIR__ . '/db.php';
include_once __DIR__ . '/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php'); exit();
}

$token = $_POST['csrf_token'] ?? null;
if (!verify_csrf_token($token)) {
    header('Location: dashboard.php?msg=invalid_csrf'); exit();
}

$user = get_logged_in_user();
$user_id = intval($user['id']);
$item_id = intval($_POST['item_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$review = trim($_POST['review'] ?? '');

if ($item_id <= 0 || $rating <= 0) {
    header('Location: dashboard.php?msg=invalid_input'); exit();
}

$stmt = $conn->prepare("INSERT INTO ratings (user_id, item_id, rating, review) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)");
$stmt->bind_param('iiis', $user_id, $item_id, $rating, $review);
$ok = $stmt->execute();

if ($ok) {
    header('Location: dashboard.php?msg=rating_saved'); exit();
} else {
    header('Location: dashboard.php?msg=rating_error'); exit();
}

?>

<?php
session_start();
include("../config/db.php");

$user_id = $_SESSION['user_id'];
$item_id = $_POST['item_id'];
$rating  = $_POST['rating'];
$review  = $_POST['review'];

$sql = "INSERT INTO ratings (user_id, item_id, rating, review)
        VALUES ($user_id, $item_id, $rating, '$review')
        ON DUPLICATE KEY UPDATE
        rating=$rating, review='$review'";

if ($conn->query($sql)) {
    echo "Rating saved";
} else {
    echo "Error";
}
?>