<?php

// Development router: mirror cPanel's compression and cache headers for public assets.
// This file is used by artisan serve; Apache uses public/.htaccess in production.
$public = __DIR__.'/public';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = realpath($public.$path);
$root = str_replace('\\', '/', realpath($public)).'/';
$types = ['css'=>'text/css; charset=UTF-8','js'=>'application/javascript; charset=UTF-8','json'=>'application/json; charset=UTF-8','svg'=>'image/svg+xml','avif'=>'image/avif','webp'=>'image/webp','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','woff2'=>'font/woff2','ico'=>'image/x-icon'];
$extension = strtolower(pathinfo($file ?: '', PATHINFO_EXTENSION));
if ($file && is_file($file) && str_starts_with(str_replace('\\', '/', $file), $root) && isset($types[$extension])) {
    $gzip = in_array($extension, ['css','js','json','svg']) && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip');
    $etag = '"'.dechex(filemtime($file)).'-'.dechex(filesize($file)).($gzip ? '-gzip' : '').'"';
    header('Content-Type: '.$types[$extension]);
    $fingerprinted = str_contains($path, '/assets/optimized/') && in_array($extension, ['webp', 'avif']);
    header('Cache-Control: public, max-age='.($fingerprinted || isset($_GET['v']) || isset($_GET['rev']) ? '31536000, immutable' : '604800'));
    header('ETag: '.$etag);
    header('Vary: Accept-Encoding');
    header('X-Content-Type-Options: nosniff');
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); return; }
    $body = file_get_contents($file);
    if ($gzip) { $body = gzencode($body, 6); header('Content-Encoding: gzip'); }
    header('Content-Length: '.strlen($body));
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') echo $body;
    return;
}
if ($file && is_file($file) && str_starts_with(str_replace('\\', '/', $file), $root) && !in_array($extension, ['php', 'env'])) return false;
require $public.'/index.php';
