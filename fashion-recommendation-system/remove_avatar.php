<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
include 'db.php';
include_once 'csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: profile.php?msg=invalid_csrf');
    exit;
}
$user_id = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$avatar = $row['avatar'] ?? '';

$errors = [];
if ($avatar && strpos($avatar, 'uploads/') === 0) {
    $path = __DIR__ . '/' . $avatar;
    $thumb = dirname($path) . '/thumb_' . basename($path);
    if (file_exists($path)) {
        if (!@unlink($path)) $errors[] = 'file_unlink_failed';
    }
    if (file_exists($thumb)) {
        if (!@unlink($thumb)) $errors[] = 'thumb_unlink_failed';
    }
}
// clear DB
$u = $conn->prepare("UPDATE users SET avatar = '' WHERE id = ?");
$u->bind_param('i', $user_id);
$u->execute();

if (empty($errors)) {
    header('Location: profile.php?msg=avatar_removed');
} else {
    header('Location: profile.php?msg=avatar_remove_error');
}
exit;
?>