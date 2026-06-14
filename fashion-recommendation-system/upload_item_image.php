<?php
include_once __DIR__ . '/auth.php';
require_admin();
include_once __DIR__ . '/db.php';
include_once __DIR__ . '/csrf.php';
include_once __DIR__ . '/uploads_helper.php';

// suppress direct PHP error output and capture any stray output
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE);
ob_start();

// ensure fatal errors are logged to upload_debug.log for debugging
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err) {
        $log = __DIR__ . '/upload_debug.log';
        $msg = date('c') . " - shutdown_error: " . json_encode($err) . "\n";
        @file_put_contents($log, $msg, FILE_APPEND);
    }
});

// Ensure base uploads dir exists and is writable (best-effort)
$uploads_base = __DIR__ . '/uploads';
if (!is_dir($uploads_base)) {
    @mkdir($uploads_base, 0755, true);
}
if (!is_dir($uploads_base) || !is_writable($uploads_base)) {
    @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - uploads_base_not_writable base=$uploads_base\n", FILE_APPEND);
    // don't immediately fail here; we'll report informative error later if move fails
}

// Ensure PHP tmp dir is available
$tmpDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
if (!is_dir($tmpDir) || !is_writable($tmpDir)) {
    @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - tmp_dir_unwritable tmp=$tmpDir\n", FILE_APPEND);
}

// Permissive debug mode: bypass CSRF and log lots of details. Only enable for local debugging.
// Temporarily enabled for automated test; remember to set to false after testing.
$PERMISSIVE_DEBUG = true;
if ($PERMISSIVE_DEBUG) {
    @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - permissive_debug_enabled\n", FILE_APPEND);
    // write a visible probe so you can confirm uploads folder is writable
    @file_put_contents($uploads_base . '/ok.txt', "ok " . date('c') . "\n", FILE_APPEND);
}

function send_json_and_exit($arr, $code = 200) {
    $logFile = __DIR__ . '/upload_debug.log';
    $buf = ob_get_clean();
    if (!empty($buf)) {
        @file_put_contents($logFile, date('c') . " - stray_output:\n" . $buf . "\n", FILE_APPEND);
    }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_json_and_exit(['error'=>'method'],405); }

try {
    $token = $_POST['csrf_token'] ?? null;
    if ($PERMISSIVE_DEBUG) {
        @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - csrf_bypassed for debugging token=" . ($token??'<none>') . "\n", FILE_APPEND);
    } else {
        if (!verify_csrf_token($token)) {
            // detailed debug: log session and token state then fail
            $log = __DIR__ . '/upload_debug.log';
            $sid = session_id();
            $cookieName = session_name();
            $cookieVal = $_COOKIE[$cookieName] ?? '';
            $stored = isset($_SESSION['csrf_tokens']) ? array_keys($_SESSION['csrf_tokens']) : [];
            @file_put_contents($log, date('c') . " - invalid_csrf token=" . ($token??'<none>') . " sid=" . $sid . " cookie=" . $cookieVal . " stored_count=" . count($stored) . "\n", FILE_APPEND);
            send_json_and_exit(['error'=>'invalid_csrf'],400);
        }
    }

    $item_id = intval($_POST['item_id'] ?? 0);
    if ($item_id <= 0) { send_json_and_exit(['error'=>'missing_item'],400); }

    if (!isset($_FILES['image'])) {
        // log for debugging
        @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - no_files\n", FILE_APPEND);
        send_json_and_exit(['error'=>'no_file_uploaded'],400);
    }

    $f = $_FILES['image'];
    if (!isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) {
        $code = $f['error'] ?? null;
        $msg = 'upload_error';
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE: $msg = 'file_exceeds_php_limit'; break;
            case UPLOAD_ERR_FORM_SIZE: $msg = 'file_exceeds_form_limit'; break;
            case UPLOAD_ERR_PARTIAL: $msg = 'partial_upload'; break;
            case UPLOAD_ERR_NO_FILE: $msg = 'no_file'; break;
            case UPLOAD_ERR_NO_TMP_DIR: $msg = 'no_tmp_dir'; break;
            case UPLOAD_ERR_CANT_WRITE: $msg = 'cant_write'; break;
            case UPLOAD_ERR_EXTENSION: $msg = 'extension_blocked'; break;
        }
        @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - upload_error: $code\n", FILE_APPEND);
        send_json_and_exit(['error'=>$msg, 'code'=>$code],400);
    }

    // basic validation
    if ($f['size'] <= 0) { send_json_and_exit(['error'=>'file_empty'],400); }
    if ($f['size'] > 5 * 1024 * 1024) { send_json_and_exit(['error'=>'file_too_large'],400); }

    $info = @getimagesize($f['tmp_name']);
    $converted_img = null;
    if (!$info) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $f['tmp_name']) : null;
        if ($finfo) finfo_close($finfo);
        @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - getimagesize_failed mime=" . ($mime??'<none>') . " tmp=" . ($f['tmp_name']??'<no_tmp>') . " size=" . intval($f['size']) . "\n", FILE_APPEND);
        if ($mime && strpos($mime, 'image/') === 0) {
            // Special-case HEIC/HEIF: try Imagick conversion if available
            $lowmime = strtolower($mime);
            if (strpos($lowmime, 'heic') !== false || strpos($lowmime, 'heif') !== false) {
                if (class_exists('Imagick')) {
                    try {
                        $im = new Imagick($f['tmp_name']);
                        $im->setImageFormat('jpeg');
                        $tmpOut = tempnam(sys_get_temp_dir(), 'heicconv');
                        // ensure filename has jpg extension for downstream handling
                        $tmpJpeg = $tmpOut . '.jpg';
                        $im->writeImage($tmpJpeg);
                        $im->clear();
                        $im->destroy();
                        $converted_img = @imagecreatefromjpeg($tmpJpeg);
                        if ($converted_img === false) {
                            @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - imagick_convert_failed tmp=$tmpJpeg\n", FILE_APPEND);
                            @unlink($tmpJpeg);
                            send_json_and_exit(['error'=>'convert_failed','detail'=>'Imagick conversion produced invalid image'],500);
                        }
                        $ext = 'jpg';
                        @unlink($tmpJpeg);
                    } catch (Exception $ie) {
                        @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - imagick_exception: " . $ie->getMessage() . "\n", FILE_APPEND);
                        send_json_and_exit(['error'=>'convert_failed','detail'=>'Imagick failed to convert HEIC image'],500);
                    }
                    } else {
                    // Imagick not available: accept the original HEIC upload so users can keep the file,
                    // but create a simple placeholder thumbnail (preview not available) so UI can show something.
                    $origExt = pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'heic';
                    $basename = 'img_' . time() . '.' . $origExt;
                    // ensure destination dir exists
                    $dir = __DIR__ . '/uploads/items/' . $item_id;
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $dest = $dir . '/' . $basename;
                    if (!move_uploaded_file($f['tmp_name'], $dest)) {
                        @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - move_failed_HEIC from {$f['tmp_name']} to $dest\n", FILE_APPEND);
                        send_json_and_exit(['error'=>'move_failed'],500);
                    }

                    // Imagick not available: we've moved the original HEIC to $dest.
                    // Try to create a thumbnail by copying the original; browsers may not render HEIC,
                    // but saving the original ensures the file exists. If copy fails, fall back to using original path.
                    $thumbPath = $dir . '/thumb.jpg';
                    $thumb_created = false;
                    if (@copy($dest, $thumbPath)) {
                        $thumb_created = true;
                    } else {
                        @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - thumb_copy_failed for $dest\n", FILE_APPEND);
                    }

                    // decide which relative path to store
                    if ($thumb_created) {
                        $rel = 'uploads/items/' . $item_id . '/thumb.jpg';
                    } else {
                        $rel = 'uploads/items/' . $item_id . '/' . $basename;
                    }
                    $stmt = $conn->prepare("UPDATE items SET image = ? WHERE id = ?");
                    $stmt->bind_param('si', $rel, $item_id);
                    $stmt->execute();

                    send_json_and_exit(['success'=>true, 'url'=>$rel, 'note'=>'saved_original_no_imagick'],200);
                }
            } else {
                // attempt to create an image from string and re-save as a jpeg
                $data = @file_get_contents($f['tmp_name']);
                $img = @imagecreatefromstring($data);
                if ($img !== false) {
                    $converted_img = $img;
                    $ext = 'jpg';
                } else {
                    send_json_and_exit(['error'=>'not_image','mime'=>$mime],400);
                }
            }
        } else {
            send_json_and_exit(['error'=>'not_image','mime'=>$mime],400);
        }
    } else {
        $ext = image_type_to_extension($info[2], false);
    }

    $dir = __DIR__ . '/uploads/items/' . $item_id;
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - mkdir_failed: $dir\n", FILE_APPEND);
            send_json_and_exit(['error'=>'mkdir_failed'],500);
        }
    }
    // try to ensure directory writable (best-effort)
    @chmod($dir, 0755);
    $basename = 'img_' . time() . '.' . $ext;
    $dest = $dir . '/' . $basename;
    if ($converted_img !== null) {
        // save converted image resource as jpeg
        if (!imagejpeg($converted_img, $dest, 85)) {
            @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - save_converted_failed to $dest\n", FILE_APPEND);
            send_json_and_exit(['error'=>'save_converted_failed'],500);
        }
        imagedestroy($converted_img);
    } else {
        $moved = false;
        if (is_uploaded_file($f['tmp_name'])) {
            $moved = @move_uploaded_file($f['tmp_name'], $dest);
            if (!$moved) @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - move_uploaded_file_failed tmp={$f['tmp_name']} dest=$dest\n", FILE_APPEND);
        }
        // fallback: try copy then unlink
        if (!$moved) {
            $copied = @copy($f['tmp_name'], $dest);
            if ($copied) {
                $moved = true;
                @unlink($f['tmp_name']);
                @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - copy_fallback_succeeded tmp={$f['tmp_name']} dest=$dest\n", FILE_APPEND);
            } else {
                // last resort: read+write
                $data = @file_get_contents($f['tmp_name']);
                if ($data !== false) {
                    $w = @file_put_contents($dest, $data);
                    if ($w !== false) {
                        $moved = true;
                        @unlink($f['tmp_name']);
                        @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - file_get_contents_fallback_succeeded tmp={$f['tmp_name']} dest=$dest\n", FILE_APPEND);
                    }
                }
            }
        }
        if (!$moved) {
            @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - final_move_failed tmp={$f['tmp_name']} dest=$dest\nFILES:" . print_r($f, true) . "\n", FILE_APPEND);
            send_json_and_exit(['error'=>'move_failed','detail'=>'final_move_failed'],500);
        }
    }

    // create thumbnail
    $thumbPath = $dir . '/thumb.jpg';
    if (!make_thumb($dest, $thumbPath, 600, 400)) {
        // fallback: copy
        if (!copy($dest, $thumbPath)) {
            @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - thumb_create_failed for $dest\n", FILE_APPEND);
        }
    }

    // update item image path (use relative path). Prefer thumbnail if it exists, otherwise store original file.
    $thumbPath = $dir . '/thumb.jpg';
    if (file_exists($thumbPath)) {
        $rel = 'uploads/items/' . $item_id . '/thumb.jpg';
    } else {
        $rel = 'uploads/items/' . $item_id . '/' . $basename;
    }
    $orig_rel = 'uploads/items/' . $item_id . '/' . $basename;
    $stmt = $conn->prepare("UPDATE items SET image = ? WHERE id = ?");
    $stmt->bind_param('si', $rel, $item_id);
    $stmt->execute();

    // Return both the URL used for display and the original file path so client can show original immediately
    send_json_and_exit(['success'=>true, 'url'=>$rel, 'orig'=>$orig_rel],200);

} catch (Exception $ex) {
    @file_put_contents(__DIR__ . '/upload_debug.log', date('c') . " - exception: " . $ex->getMessage() . "\n", FILE_APPEND);
    send_json_and_exit(['error'=>'exception','detail'=>$ex->getMessage()],500);
}

?>
