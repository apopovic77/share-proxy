<?php

function get_app_config(): array {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');

    // Extract base domain from current host
    // Examples:
    // - share.arkturian.com -> arkturian.com
    // - share.arkserver.arkturian.com -> arkserver.arkturian.com
    // - share.kunde1.com -> kunde1.com
    $baseDomain = $host;
    if (strpos($host, 'share.') === 0) {
        $baseDomain = substr($host, 6); // Remove "share." prefix
    }

    // Build API URLs based on base domain
    $defaults = [
        'api_storage_base_url' => getenv('API_STORAGE_BASE_URL') ?: "https://api-storage.{$baseDomain}",
    ];

    // Special overrides for known environments (optional)
    // NOTE: Order matters! More specific patterns must come first
    $hostOverrides = [
        'gsgbot' => [
            // O'Neal aiserver - uses local nginx reverse proxy
            'api_storage_base_url' => getenv('API_STORAGE_BASE_URL_GSGBOT') ?: 'https://gsgbot.arkturian.com/storage-api',
        ],
        'arkserver' => [
            'api_storage_base_url' => getenv('API_STORAGE_BASE_URL_ARKSERVER') ?: 'https://api-storage.arkserver.arkturian.com',
        ],
        'arkturian.com' => [
            'api_storage_base_url' => getenv('API_STORAGE_BASE_URL') ?: 'https://api-storage.arkturian.com',
        ],
    ];

    foreach ($hostOverrides as $needle => $override) {
        if ($host && strpos($host, $needle) !== false) {
            $config = array_merge($defaults, array_filter($override, 'strlen'));
            return $config;
        }
    }

    $config = $defaults;
    return $config;
}

function app_config(string $key, string $fallback = ''): string {
    $config = get_app_config();
    return $config[$key] ?? $fallback;
}

function js_config(string $key, string $fallback = ''): string {
    return htmlspecialchars(app_config($key, $fallback), ENT_QUOTES);
}
