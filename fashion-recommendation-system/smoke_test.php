<?php
// smoke_test.php - run quick checks to help diagnose environment issues
header('Content-Type: application/json');
$out = [];

// DB connectivity
require_once __DIR__ . '/db.php';
$dbok = false;
try {
    $res = @mysqli_query($conn, "SELECT 1");
    $dbok = $res !== false;
} catch (Exception $e) {
    $dbok = false;
}
$out['db'] = ['ok' => $dbok, 'host' => getenv('DB_HOST') ?: 'localhost', 'database' => isset($db) ? $db : null];

// PHP settings relevant to uploads
$out['php'] = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
    'sapi' => PHP_SAPI,
    'gd' => function_exists('imagecreatetruecolor'),
    'imagick' => class_exists('Imagick'),
    'curl' => function_exists('curl_version')
];

// file system checks
$uploads = __DIR__ . '/uploads';
$out['uploads'] = [
    'path' => $uploads,
    'exists' => is_dir($uploads),
    'writable' => is_writable($uploads),
    'sample' => []
];
if (is_dir($uploads)) {
    $d = opendir($uploads);
    if ($d) {
        $count = 0;
        while (($f = readdir($d)) !== false && $count < 10) {
            if ($f === '.' || $f === '..') continue;
            $out['uploads']['sample'][] = $f;
            $count++;
        }
        closedir($d);
    }
}

// Check payment config presence
require_once __DIR__ . '/stripe_config.php';
$out['payments'] = [
    'stripe_configured' => defined('STRIPE_SECRET') && STRIPE_SECRET !== '',
    'sslcommerz' => true // sandbox credentials used in code; merchant should replace for production
];

// Quick write test: attempt to create a probe file
$probe = $uploads . '/probe_' . uniqid() . '.txt';
try {
    $w = @file_put_contents($probe, "probe " . date('c'));
    $out['probe_write'] = ($w !== false) ? 'ok' : 'failed';
    if ($w !== false) @unlink($probe);
} catch (Exception $e) {
    $out['probe_write'] = 'exception';
}

echo json_encode($out, JSON_PRETTY_PRINT);

?>
<?php
// Basic smoke-test skeleton. Edit credentials before running.
$base = 'http://localhost:8000';
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Example: registration (if registration available)
// $post = ['username'=>'testuser','email'=>'test@example.com','password'=>'pass123'];
// curl_setopt($ch, CURLOPT_URL, $base . '/registration.php');
// curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
// echo "Registration: ", curl_exec($ch), "\n";

echo "Smoke test skeleton created. Customize and run locally.\n";
curl_close($ch);

?>
