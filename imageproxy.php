<?php
/**
 * Generic Image Proxy - CORS Bypass
 *
 * Loads images from any URL and serves them with CORS headers.
 * Supports image transformation (resize, format conversion).
 *
 * Usage: https://share.arkturian.com/imageproxy.php?url={image_url}&width=800&format=webp&quality=85
 *
 * Parameters:
 *   - url (required): Image URL to fetch
 *   - width (optional): Target width in pixels
 *   - height (optional): Target height in pixels
 *   - format (optional): jpg, png, webp (default: original)
 *   - quality (optional): 1-100 (default: 85)
 */

// Configuration
$CACHE_DIR = '/tmp/imageproxy-cache';
$DEFAULT_QUALITY = 85;
$MAX_WIDTH = 4000;
$MAX_HEIGHT = 4000;
$CACHE_TTL = 86400; // 24 hours

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Get parameters
$url = isset($_GET['url']) ? trim($_GET['url']) : '';
$width = isset($_GET['width']) ? min(intval($_GET['width']), $MAX_WIDTH) : 0;
$height = isset($_GET['height']) ? min(intval($_GET['height']), $MAX_HEIGHT) : 0;
$format = isset($_GET['format']) ? strtolower($_GET['format']) : null;
$quality = isset($_GET['quality']) ? max(1, min(100, intval($_GET['quality']))) : $DEFAULT_QUALITY;

// Validate URL
if (!$url) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing url parameter']);
    exit;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid URL']);
    exit;
}

// Validate format
if ($format && !in_array($format, ['jpg', 'jpeg', 'png', 'webp'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid format. Supported: jpg, png, webp']);
    exit;
}

// Generate cache key
$cacheKey = md5($url . '-' . $width . '-' . $height . '-' . $format . '-' . $quality);
$ext = $format ?: 'jpg';
$cacheFile = $CACHE_DIR . '/' . $cacheKey . '.' . $ext;

// Ensure cache directory exists
if (!is_dir($CACHE_DIR)) {
    mkdir($CACHE_DIR, 0755, true);
}

// Check cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $CACHE_TTL) {
    serveImage($cacheFile, true);
    exit;
}

// Fetch image from URL
$imageData = fetchImage($url);
if (!$imageData) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to fetch image', 'url' => $url]);
    exit;
}

// Check if transformation needed
$needsTransform = $width > 0 || $height > 0 || $format;

if ($needsTransform) {
    // Transform image
    $transformed = transformImage($imageData, $width, $height, $format, $quality);
    if ($transformed) {
        file_put_contents($cacheFile, $transformed);
        header('Content-Type: ' . getMimeType($ext));
        header('Content-Length: ' . strlen($transformed));
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Proxy: share.arkturian.com');
        header('X-Cache: MISS');
        echo $transformed;
    } else {
        // Transformation failed, serve original
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . strlen($imageData));
        header('Cache-Control: public, max-age=86400');
        header('X-Proxy: share.arkturian.com');
        echo $imageData;
    }
} else {
    // Serve original
    file_put_contents($cacheFile, $imageData);
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . strlen($imageData));
    header('Cache-Control: public, max-age=86400');
    header('X-Proxy: share.arkturian.com');
    header('X-Cache: MISS');
    echo $imageData;
}

function fetchImage($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36');

    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$data) {
        return false;
    }

    return $data;
}

function serveImage($file, $fromCache = false) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mime = getMimeType($ext);

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Proxy: share.arkturian.com');
    header('X-Cache: ' . ($fromCache ? 'HIT' : 'MISS'));

    readfile($file);
}

function getMimeType($ext) {
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    return $mimeTypes[strtolower($ext)] ?? 'application/octet-stream';
}

function transformImage($imageData, $width, $height, $format, $quality) {
    // Check if GD is available
    if (!extension_loaded('gd')) {
        return false;
    }

    // Load image from data
    $srcImage = @imagecreatefromstring($imageData);
    if (!$srcImage) {
        return false;
    }

    $srcWidth = imagesx($srcImage);
    $srcHeight = imagesy($srcImage);

    // Calculate dimensions
    if ($width && $height) {
        // Both specified - maintain aspect ratio, fit within bounds
        $ratio = min($width / $srcWidth, $height / $srcHeight);
        $dstWidth = intval($srcWidth * $ratio);
        $dstHeight = intval($srcHeight * $ratio);
    } elseif ($width) {
        // Width only
        $ratio = $width / $srcWidth;
        $dstWidth = $width;
        $dstHeight = intval($srcHeight * $ratio);
    } elseif ($height) {
        // Height only
        $ratio = $height / $srcHeight;
        $dstWidth = intval($srcWidth * $ratio);
        $dstHeight = $height;
    } else {
        // No resize, just format conversion
        $dstWidth = $srcWidth;
        $dstHeight = $srcHeight;
    }

    // Create destination image
    $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);

    // Preserve transparency for PNG/WebP
    if ($format === 'png' || $format === 'webp') {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparent = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
        imagefilledrectangle($dstImage, 0, 0, $dstWidth, $dstHeight, $transparent);
    }

    // Resize
    imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

    // Output to buffer
    ob_start();

    $outputFormat = $format ?: 'jpg';

    switch ($outputFormat) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($dstImage, null, $quality);
            break;
        case 'png':
            // PNG quality: 0 (best) to 9 (worst compression)
            $pngQuality = intval((100 - $quality) / 11);
            imagepng($dstImage, null, $pngQuality);
            break;
        case 'webp':
            imagewebp($dstImage, null, $quality);
            break;
        default:
            imagejpeg($dstImage, null, $quality);
    }

    $output = ob_get_clean();

    // Cleanup
    imagedestroy($srcImage);
    imagedestroy($dstImage);

    return $output;
}
