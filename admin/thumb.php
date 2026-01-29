<?php
/**
 * thumb.php
 * Simple thumbnail generator and cache.
 * Usage: thumb.php?src=path_or_url&w=80&h=80&q=80
 *
 * Security note: local files are allowed only if they are inside the application directory.
 * Remote URLs are optionally supported (requires allow_url_fopen) and will be cached.
 */

ini_set('display_errors', 0);
error_reporting(0);

$src = isset($_GET['src']) ? $_GET['src'] : '';
$w = isset($_GET['w']) ? (int)$_GET['w'] : 80;
$h = isset($_GET['h']) ? (int)$_GET['h'] : 80;
$q = isset($_GET['q']) ? (int)$_GET['q'] : 80;
if ($w <= 0) $w = 80;
if ($h <= 0) $h = 80;
if ($q <= 0 || $q > 100) $q = 80;

$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'thumb_cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$default = 'https://via.placeholder.com/'.$w.'x'.$h;
if (empty($src)) {
    header('Location: ' . $default);
    exit;
}

$srcRaw = $src;
// decode if it was encoded
$src = urldecode($src);

$isRemote = (strpos($src, 'http://') === 0 || strpos($src, 'https://') === 0);
$cacheKey = md5($srcRaw . "|{$w}x{$h}|q={$q}");
// prefer webp if possible
$useWebp = function_exists('imagewebp');
$ext = $useWebp ? 'webp' : 'jpg';
$cachedFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.' . $ext;

// If cached file exists and is fresh, serve it
if (file_exists($cachedFile)) {
    header('Content-Type: ' . ($useWebp ? 'image/webp' : 'image/jpeg'));
    header('Cache-Control: public, max-age=604800'); // 1 week
    readfile($cachedFile);
    exit;
}

// Obtain the source image (either local or remote)
$tempSrc = null;
if ($isRemote) {
    // Try to fetch remote file (only if allow_url_fopen or using curl)
    if (ini_get('allow_url_fopen')) {
        $data = @file_get_contents($src);
        if ($data === false) {
            header('Location: ' . $default);
            exit;
        }
        $tempSrc = tempnam(sys_get_temp_dir(), 'thumb_');
        file_put_contents($tempSrc, $data);
    } elseif (function_exists('curl_version')) {
        $ch = curl_init($src);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data === false) {
            header('Location: ' . $default);
            exit;
        }
        $tempSrc = tempnam(sys_get_temp_dir(), 'thumb_');
        file_put_contents($tempSrc, $data);
    } else {
        header('Location: ' . $default);
        exit;
    }
} else {
    // Local file: ensure it's inside the app directory for safety
    $real = @realpath($src);
    $base = realpath(__DIR__);
    if ($real === false || strpos($real, $base) !== 0 || !is_file($real)) {
        // Not allowed or not found
        header('Location: ' . $default);
        exit;
    }
    $tempSrc = $real;
}

if (!$tempSrc || !is_file($tempSrc)) {
    header('Location: ' . $default);
    exit;
}

$info = @getimagesize($tempSrc);
if (!$info) {
    header('Location: ' . $default);
    exit;
}
$mime = $info['mime'];

switch ($mime) {
    case 'image/jpeg': $srcImg = @imagecreatefromjpeg($tempSrc); break;
    case 'image/png': $srcImg = @imagecreatefrompng($tempSrc); break;
    case 'image/gif': $srcImg = @imagecreatefromgif($tempSrc); break;
    case 'image/webp': $srcImg = @imagecreatefromwebp($tempSrc); break;
    default: $srcImg = null; break;
}

if (!$srcImg) {
    header('Location: ' . $default);
    exit;
}

$origW = imagesx($srcImg);
$origH = imagesy($srcImg);

// Calculate new size while preserving aspect ratio
$ratio = min($w / $origW, $h / $origH);
$newW = max(1, (int)($origW * $ratio));
$newH = max(1, (int)($origH * $ratio));

$dst = imagecreatetruecolor($newW, $newH);
// preserve PNG transparency
if ($mime === 'image/png' || $mime === 'image/gif') {
    imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
}

imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

// Save to cache
if ($useWebp) {
    imagewebp($dst, $cachedFile, $q);
    header('Content-Type: image/webp');
} else {
    imagejpeg($dst, $cachedFile, $q);
    header('Content-Type: image/jpeg');
}
header('Cache-Control: public, max-age=604800');
readfile($cachedFile);

// cleanup
imagedestroy($srcImg);
imagedestroy($dst);
if ($isRemote && file_exists($tempSrc)) @unlink($tempSrc);
exit;
