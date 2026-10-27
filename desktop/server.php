<?php

$token = getenv('GALAXY_DESKTOP_TOKEN') ?: '';
if (!$token || !hash_equals($token, $_SERVER['HTTP_X_GALAXY_DESKTOP_TOKEN'] ?? '')) {
    http_response_code(403);
    exit('Akses melalui aplikasi Galaxy POS.');
}
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if ($uri === '/__desktop/health') {
    header('Content-Type: application/json');
    echo json_encode(['application'=>'galaxy-pos','instance'=>getenv('GALAXY_DESKTOP_INSTANCE')]);
    exit;
}
$backend = dirname(__FILE__);
$dataPublic = getenv('GALAXY_DESKTOP_DATA') . (str_starts_with($uri,'/storage/') ? '/storage/app/public' : '/public');
$relativeUri = str_starts_with($uri,'/storage/') ? substr($uri,8) : $uri;
$file = realpath($dataPublic . $relativeUri);
$publicRoot = realpath($dataPublic);
if ($file && $publicRoot && str_starts_with(str_replace('\\','/',$file), str_replace('\\','/',$publicRoot) . '/') && is_file($file)) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml','pdf'=>'application/pdf'];
    if (isset($types[$extension])) {
        header('Content-Type: ' . $types[$extension]);
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }
}
$asset = realpath($backend . '/public' . $uri);
$assetRoot = realpath($backend . '/public');
if ($uri !== '/' && $asset && is_file($asset) && str_starts_with(str_replace('\\','/',$asset), str_replace('\\','/',$assetRoot) . '/') && !in_array(strtolower(pathinfo($asset, PATHINFO_EXTENSION)), ['php','phtml','phar'], true)) return false;
require $backend . '/public/index.php';
