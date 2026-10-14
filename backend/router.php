<?php

$requestPath = '/' . ltrim(rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
$requestedFile = realpath(__DIR__ . $requestPath);
$backendRoot = __DIR__ . DIRECTORY_SEPARATOR;
$publicAssetPrefixes = ['/assets/css/', '/assets/js/', '/assets/images/', '/assets/fonts/', '/assets/favicon/'];
$isPublicAsset = false;
foreach ($publicAssetPrefixes as $prefix) {
    if (str_starts_with($requestPath, $prefix)) {
        $isPublicAsset = true;
        break;
    }
}

if ($isPublicAsset && $requestedFile !== false &&
    str_starts_with($requestedFile, $backendRoot) && is_file($requestedFile)) {
    return false;
}

if ($requestPath === '/') {
    unset($_GET['uri']);
} else {
    $_GET['uri'] = trim($requestPath, '/');
}
require __DIR__ . '/index.php';
