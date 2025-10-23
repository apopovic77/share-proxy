<?php
/**
 * Public Proxy for External Storage Objects
 *
 * Fetches external URIs from storage objects and serves them as a transparent proxy.
 * Only works for public objects (is_public=true).
 *
 * Usage: https://share.arkturian.com/proxy.php?id={object_id}
 *
 * Features:
 * - LRU cache (24h TTL)
 * - Proper MIME type detection
 * - HTTP cache headers
 * - Access control (public objects only)
 */

// Configuration
$apiBase = 'https://api.arkturian.com';
$apiKey = 'Inetpass1';
$cacheDir = '/tmp/share_proxy_cache';
$cacheTTL = 86400; // 24 hours
$maxCacheSize = 500 * 1024 * 1024; // 500 MB

// Get object ID from query parameter
$objectId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$objectId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing object ID. Usage: proxy.php?id={object_id}']);
    exit;
}

// Create cache directory if needed
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Cache file path
$cacheFile = $cacheDir . '/obj_' . $objectId;
$metaFile = $cacheFile . '.meta';

// Check if cached and still valid
$useCache = false;
if (file_exists($cacheFile) && file_exists($metaFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age < $cacheTTL) {
        $useCache = true;
    }
}

// Serve from cache if valid
if ($useCache && !isset($_GET['no_cache'])) {
    $meta = json_decode(file_get_contents($metaFile), true);

    header('Content-Type: ' . $meta['mime_type']);
    header('Content-Length: ' . filesize($cacheFile));
    header('Cache-Control: public, max-age=86400');
    header('X-Cache: HIT');
    header('X-External-URI: ' . $meta['external_uri']);

    readfile($cacheFile);
    exit;
}

// Fetch object metadata from API
$ch = curl_init($apiBase . '/storage/objects/' . $objectId . '/public');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: ' . $apiKey]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    http_response_code($httpCode ?: 500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to fetch object metadata', 'object_id' => $objectId]);
    exit;
}

$object = json_decode($response, true);

// Check if object is public
if (!isset($object['is_public']) || !$object['is_public']) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied. This object is not public.', 'object_id' => $objectId]);
    exit;
}

// Check if object has external URI
if (!isset($object['external_uri']) || !$object['external_uri']) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'This object does not have an external URI', 'object_id' => $objectId]);
    exit;
}

$externalUri = $object['external_uri'];
$mimeType = $object['mime_type'] ?? 'application/octet-stream';

// Fetch external file
$ch = curl_init($externalUri);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$fileData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effectiveMimeType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpCode !== 200 || !$fileData) {
    http_response_code($httpCode ?: 500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to fetch external file', 'external_uri' => $externalUri, 'http_code' => $httpCode]);
    exit;
}

// Use effective MIME type from external server if available
if ($effectiveMimeType) {
    // Strip charset if present
    $mimeType = explode(';', $effectiveMimeType)[0];
}

// Save to cache
file_put_contents($cacheFile, $fileData);
file_put_contents($metaFile, json_encode([
    'mime_type' => $mimeType,
    'external_uri' => $externalUri,
    'cached_at' => time()
]));

// Clean up old cache files if cache is too large
cleanupCache($cacheDir, $maxCacheSize);

// Serve file
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($fileData));
header('Cache-Control: public, max-age=86400');
header('X-Cache: MISS');
header('X-External-URI: ' . $externalUri);

echo $fileData;
exit;

/**
 * LRU cache cleanup - removes oldest files when cache exceeds max size
 */
function cleanupCache($cacheDir, $maxSize) {
    $files = glob($cacheDir . '/obj_*');
    if (!$files) return;

    // Calculate total size
    $totalSize = 0;
    $fileInfo = [];

    foreach ($files as $file) {
        if (substr($file, -5) === '.meta') continue; // Skip meta files

        $size = filesize($file);
        $atime = fileatime($file);
        $totalSize += $size;

        $fileInfo[] = [
            'path' => $file,
            'size' => $size,
            'atime' => $atime
        ];
    }

    // If under max size, no cleanup needed
    if ($totalSize <= $maxSize) return;

    // Sort by access time (oldest first)
    usort($fileInfo, function($a, $b) {
        return $a['atime'] - $b['atime'];
    });

    // Remove oldest files until under limit
    foreach ($fileInfo as $info) {
        if ($totalSize <= $maxSize * 0.8) break; // Remove until 80% of max

        $metaPath = $info['path'] . '.meta';

        if (file_exists($info['path'])) {
            unlink($info['path']);
        }
        if (file_exists($metaPath)) {
            unlink($metaPath);
        }

        $totalSize -= $info['size'];
    }
}
