<?php
/**
 * EventCrawler Local Filesystem Image Proxy
 *
 * Serves images from local filesystem for EventCrawler fallback.
 * Loads images from /mnt/backup-disk/eventcrawler-media/events/{event_id}/
 *
 * Usage: https://share.arkturian.com/events.php?event_id=1199&width=800&format=webp&quality=85
 *
 * Parameters:
 *   - event_id (required): Event ID
 *   - width (optional): Target width in pixels
 *   - height (optional): Target height in pixels
 *   - format (optional): jpg, png, webp (default: original)
 *   - quality (optional): 1-100 (default: 85)
 */

// Configuration
$BASE_DIR = '/mnt/backup-disk/eventcrawler-media/events';
$CACHE_DIR = '/tmp/events-cache';
$DEFAULT_QUALITY = 85;
$MAX_WIDTH = 4000;
$MAX_HEIGHT = 4000;

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
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$width = isset($_GET['width']) ? min(intval($_GET['width']), $MAX_WIDTH) : 0;
$height = isset($_GET['height']) ? min(intval($_GET['height']), $MAX_HEIGHT) : 0;
$format = isset($_GET['format']) ? strtolower($_GET['format']) : null;
$quality = isset($_GET['quality']) ? max(1, min(100, intval($_GET['quality']))) : $DEFAULT_QUALITY;

// Validate event_id
if (!$eventId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing event_id parameter']);
    exit;
}

// Validate format
if ($format && !in_array($format, ['jpg', 'jpeg', 'png', 'webp'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid format. Supported: jpg, png, webp']);
    exit;
}

// Load manifest
$eventDir = $BASE_DIR . '/' . $eventId;
$manifestPath = $eventDir . '/manifest.json';

if (!file_exists($manifestPath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Event not found', 'event_id' => $eventId]);
    exit;
}

$manifestJson = file_get_contents($manifestPath);
$manifest = json_decode($manifestJson, true);

if (!$manifest) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid manifest']);
    exit;
}

// Find hero or screenshot
$mediaItem = null;
$mediaFile = null;

if (isset($manifest['media']) && is_array($manifest['media'])) {
    // Priority: hero > screenshot
    foreach ($manifest['media'] as $item) {
        if (isset($item['role']) && $item['role'] === 'hero') {
            $mediaItem = $item;
            break;
        }
    }

    // Fallback to screenshot
    if (!$mediaItem) {
        foreach ($manifest['media'] as $item) {
            if (isset($item['role']) && $item['role'] === 'screenshot') {
                $mediaItem = $item;
                break;
            }
        }
    }
}

if (!$mediaItem || !isset($mediaItem['filename'])) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No media found for event']);
    exit;
}

$sourceFile = $eventDir . '/' . $mediaItem['filename'];

// Check if file exists
if (!file_exists($sourceFile)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Media file not found', 'filename' => $mediaItem['filename']]);
    exit;
}

// Check if transformation needed
$needsTransform = $width > 0 || $height > 0 || $format;

if ($needsTransform) {
    // Generate cache key
    $cacheKey = md5($eventId . '-' . $mediaItem['filename'] . '-' . $width . '-' . $height . '-' . $format . '-' . $quality);
    $ext = $format ?: pathinfo($sourceFile, PATHINFO_EXTENSION);
    $cacheFile = $CACHE_DIR . '/' . $cacheKey . '.' . $ext;

    // Ensure cache directory exists
    if (!is_dir($CACHE_DIR)) {
        mkdir($CACHE_DIR, 0755, true);
    }

    // Check cache
    if (file_exists($cacheFile)) {
        serveImage($cacheFile, true);
        exit;
    }

    // Transform image
    $transformed = transformImage($sourceFile, $width, $height, $format, $quality);
    if ($transformed) {
        file_put_contents($cacheFile, $transformed);
        header('Content-Type: ' . getMimeType($ext));
        header('Content-Length: ' . strlen($transformed));
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Proxy: share.arkturian.com/events');
        header('X-Cache: MISS');
        echo $transformed;
    } else {
        // Transformation failed, serve original
        serveImage($sourceFile, false);
    }
} else {
    // Serve original
    serveImage($sourceFile, false);
}

function serveImage($file, $fromCache) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mime = getMimeType($ext);

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Proxy: share.arkturian.com/events');
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

function transformImage($sourceFile, $width, $height, $format, $quality) {
    // Check if GD is available
    if (!extension_loaded('gd')) {
        return false;
    }

    // Get source image info
    $imageInfo = @getimagesize($sourceFile);
    if (!$imageInfo) {
        return false;
    }

    list($srcWidth, $srcHeight, $srcType) = $imageInfo;

    // Load source image
    switch ($srcType) {
        case IMAGETYPE_JPEG:
            $srcImage = @imagecreatefromjpeg($sourceFile);
            break;
        case IMAGETYPE_PNG:
            $srcImage = @imagecreatefrompng($sourceFile);
            break;
        case IMAGETYPE_WEBP:
            $srcImage = @imagecreatefromwebp($sourceFile);
            break;
        case IMAGETYPE_GIF:
            $srcImage = @imagecreatefromgif($sourceFile);
            break;
        default:
            return false;
    }

    if (!$srcImage) {
        return false;
    }

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
    if ($format === 'png' || $format === 'webp' || $srcType === IMAGETYPE_PNG) {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparent = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
        imagefilledrectangle($dstImage, 0, 0, $dstWidth, $dstHeight, $transparent);
    }

    // Resize
    imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

    // Output to buffer
    ob_start();

    $outputFormat = $format ?: pathinfo($sourceFile, PATHINFO_EXTENSION);

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
