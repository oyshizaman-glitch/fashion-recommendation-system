<?php
session_start();
include_once __DIR__ . '/auth.php';
require_admin();
include_once __DIR__ . '/db.php';
include_once __DIR__ . '/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error'=>'method']);
    exit;
}

$token = $_POST['csrf_token'] ?? null;
if (!verify_csrf_token($token)) {
    http_response_code(400);
    echo json_encode(['error'=>'invalid_csrf']);
    exit;
}

$item_id = intval($_POST['item_id'] ?? 0);
if ($item_id <= 0) {
    http_response_code(400);
    echo json_encode(['error'=>'missing_item']);
    exit;
}

// fetch current image path
$stmt = $conn->prepare("SELECT image FROM items WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $item_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
if (!$row) {
    http_response_code(404);
    echo json_encode(['error'=>'not_found']);
    exit;
}
$img = $row['image'] ?? '';
$deleted = [];
$errors = [];

if ($img && strpos($img, 'uploads/') === 0) {
    $baseDir = __DIR__ . '/uploads/items/' . $item_id;
    // remove thumbnail if exists
    $thumb = $baseDir . '/thumb.jpg';
    if (file_exists($thumb)) {
        if (@unlink($thumb)) $deleted[] = str_replace(__DIR__.'/', '', $thumb);
        else $errors[] = 'thumb_unlink_failed';
    }
    // remove the specific file indicated by DB (if inside that dir)
    $basename = basename($img);
    $filePath = $baseDir . '/' . $basename;
    if (file_exists($filePath)) {
        if (@unlink($filePath)) $deleted[] = str_replace(__DIR__.'/', '', $filePath);
        else $errors[] = 'file_unlink_failed';
    }
    // also attempt to remove any other img_* files to clean up (best-effort)
    foreach (glob($baseDir . '/img_*') as $f) {
        if (file_exists($f)) {
            if (@unlink($f)) $deleted[] = str_replace(__DIR__.'/', '', $f);
        }
    }
}

// clear DB field
$update = $conn->prepare("UPDATE items SET image = '' WHERE id = ?");
$update->bind_param('i', $item_id);
$update->execute();

if (count($errors) === 0) {
    echo json_encode(['success'=>true,'deleted'=>$deleted]);
    exit;
} else {
    http_response_code(500);
    echo json_encode(['error'=>'unlink_errors','deleted'=>$deleted,'errors'=>$errors]);
    exit;
}

?>