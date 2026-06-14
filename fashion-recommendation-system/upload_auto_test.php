<?php
// upload_auto_test.php - generate a tiny JPEG and POST to upload_item_image.php
// Usage: php upload_auto_test.php

$targetUrl = 'http://localhost/CSE%20370%20PROJECT/upload_item_image.php';
$item_id = 1;

// small 1x1 JPEG base64
$jpeg_b64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAgP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwD7AAB//Z';
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'auto_test_' . uniqid() . '.jpg';
file_put_contents($tmp, base64_decode($jpeg_b64));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
// build multipart form
$boundary = '----WebKitFormBoundary' . md5(time());
$eol = "\r\n";
$fields = [];
$fields[] = "--$boundary" . $eol . 'Content-Disposition: form-data; name="item_id"' . $eol . $eol . $item_id . $eol;
$fields[] = "--$boundary" . $eol . 'Content-Disposition: form-data; name="csrf_token"' . $eol . $eol . '' . $eol;
$filedata = file_get_contents($tmp);
$fields[] = "--$boundary" . $eol . 'Content-Disposition: form-data; name="image"; filename="auto.jpg"' . $eol . 'Content-Type: image/jpeg' . $eol . $eol . $filedata . $eol;
$fields[] = "--$boundary--" . $eol;
$body = implode('', $fields);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: multipart/form-data; boundary=' . $boundary,
    'Content-Length: ' . strlen($body)
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

$res = curl_exec($ch);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "Request to: $targetUrl\n";
if ($err) {
    echo "CURL error: $err\n";
}
if ($info) {
    echo "HTTP code: " . ($info['http_code'] ?? 'n/a') . "\n";
}

echo "Response:\n" . $res . "\n\n";

// show last 120 lines of upload_debug.log if present
$log = __DIR__ . '/upload_debug.log';
if (file_exists($log)) {
    $lines = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $tail = array_slice($lines, -120);
    echo "--- upload_debug.log (last " . count($tail) . " lines) ---\n";
    echo implode("\n", $tail) . "\n";
} else {
    echo "No upload_debug.log found at $log\n";
}

@unlink($tmp);

?>