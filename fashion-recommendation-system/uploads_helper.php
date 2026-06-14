<?php
// helper: create thumbnail for uploaded images
function make_thumb($srcPath, $dstPath, $maxW=400, $maxH=300){
    $info = @getimagesize($srcPath); if (!$info) return false;
    list($w,$h) = $info;
    $ratio = min($maxW/$w, $maxH/$h, 1);
    $nw = max(1,(int)($w*$ratio)); $nh = max(1,(int)($h*$ratio));

    // Prefer GD if available
    if (function_exists('imagecreatetruecolor')) {
        $thumb = imagecreatetruecolor($nw,$nh);
        switch ($info[2]){
            case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($srcPath); break;
            case IMAGETYPE_PNG: $src = imagecreatefrompng($srcPath); break;
            case IMAGETYPE_GIF: $src = imagecreatefromgif($srcPath); break;
            case IMAGETYPE_WEBP: $src = imagecreatefromwebp($srcPath); break;
            default: return false;
        }
        imagecopyresampled($thumb, $src, 0,0,0,0, $nw, $nh, $w, $h);
        // ensure dest dir exists
        $d = dirname($dstPath); if (!is_dir($d)) mkdir($d, 0755, true);
        $ok = imagejpeg($thumb, $dstPath, 85);
        imagedestroy($thumb); imagedestroy($src);
        return $ok;
    }

    // If GD not available, try Imagick if present
    if (class_exists('Imagick')) {
        try {
            $im = new Imagick($srcPath);
            $im->thumbnailImage($nw, $nh, true);
            $d = dirname($dstPath); if (!is_dir($d)) mkdir($d, 0755, true);
            $ok = $im->writeImage($dstPath);
            $im->clear(); $im->destroy();
            return $ok;
        } catch (Exception $e) {
            return false;
        }
    }

    // No viable image library available
    return false;
}

?>
