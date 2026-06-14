<?php
// upload_test.php
// Simple test endpoint to verify server can write into uploads directory and accept a file.
// WARNING: This endpoint intentionally bypasses auth/CSRF for local testing only. Remove when done.

header('Content-Type: application/json');

$baseDir = __DIR__ . '/uploads/test';
if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0755, true)) {
        echo json_encode(['success'=>false,'error'=>'mkdir_failed','path'=>$baseDir]);
        exit;
    }
}

// Try to write a small test file
$testFile = $baseDir . '/probe_' . time() . '.txt';
$w = @file_put_contents($testFile, "probe " . date('c') . "\n");
if ($w === false) {
    echo json_encode(['success'=>false,'error'=>'write_failed','path'=>$testFile]);
    exit;
}

$result = ['success'=>true, 'probe'=>$testFile];

// If a file was POSTed under 'file', try best-effort move/copy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $f = $_FILES['file'];
    $dest = $baseDir . '/' . basename($f['name']);
    $moved = false;
    if (is_uploaded_file($f['tmp_name'])) {
        $moved = @move_uploaded_file($f['tmp_name'], $dest);
    }
    if (!$moved) {
        $copied = @copy($f['tmp_name'], $dest);
        if ($copied) { $moved = true; @unlink($f['tmp_name']); }
    }
    if (!$moved) {
        $data = @file_get_contents($f['tmp_name']);
        if ($data !== false) { $ok = @file_put_contents($dest, $data); if ($ok !== false) { $moved = true; @unlink($f['tmp_name']); } }
    }
    $result['uploaded'] = $moved ? ('uploads/test/' . basename($f['name'])) : false;
    $result['file_info'] = $f;
}

echo json_encode($result);

?>
