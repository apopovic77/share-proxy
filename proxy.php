<?php
/**
 * Public Proxy for External Storage Objects
 *
 * Serves images from Storage API with proper authentication.
 * Product Finder uses this to load O'Neal product images.
 *
 * Usage: https://share.arkturian.com/proxy.php?id={object_id}&width=400&format=webp&quality=75
 */

// Configuration
$apiBase = 'https://api-storage.arkturian.com';
$apiKey = 'oneal_demo_token';  // Default to O'Neal tenant

// Get object ID from query parameter
$objectId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$objectId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing object ID']);
    exit;
}

// Build URL with transformation parameters
$params = [];
if (isset($_GET['width'])) $params[] = 'width=' . intval($_GET['width']);
if (isset($_GET['height'])) $params[] = 'height=' . intval($_GET['height']);
if (isset($_GET['format'])) $params[] = 'format=' . urlencode($_GET['format']);
if (isset($_GET['quality'])) $params[] = 'quality=' . intval($_GET['quality']);
$queryString = $params ? '?' . implode('&', $params) : '';

$url = $apiBase . '/storage/media/' . $objectId . $queryString;

// Fetch from Storage API with authentication
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: ' . $apiKey]);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$fileData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// Check for errors
if ($httpCode !== 200 || !$fileData) {
    http_response_code($httpCode ?: 500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Failed to fetch image',
        'object_id' => $objectId,
        'http_code' => $httpCode
    ]);
    exit;
}

// Serve file
header('Content-Type: ' . $contentType);
header('Content-Length: ' . strlen($fileData));
header('Cache-Control: public, max-age=86400, immutable');
header('X-Proxy: share.arkturian.com');

echo $fileData;
