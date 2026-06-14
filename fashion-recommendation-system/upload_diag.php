<?php
// Diagnostic endpoint to help debug upload issues. Safe for local development only.
header('Content-Type: application/json');
$response = [];
$response['php_ini'] = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
    'max_file_uploads' => ini_get('max_file_uploads')
];
$uploadsDir = __DIR__ . '/uploads';
$response['uploads_dir'] = str_replace('\\', '/', $uploadsDir);
$response['uploads_exists'] = is_dir($uploadsDir);
$response['uploads_writable'] = is_writable($uploadsDir);
$response['php_user'] = (function(){
    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $uid = posix_geteuid();
        $pw = posix_getpwuid($uid);
        return $pw ?: null;
    }
    return null;
})();

// List small sample of uploads tree (first-level)
$response['uploads_sample'] = [];
if (is_dir($uploadsDir)) {
    $it = new DirectoryIterator($uploadsDir);
    foreach ($it as $f) {
        if ($f->isDot()) continue;
        $response['uploads_sample'][] = ['name'=>$f->getFilename(), 'is_dir'=>$f->isDir()];
    }
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>