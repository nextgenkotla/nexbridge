<?php
declare(strict_types=1);

// Front controller: every page of the website is rendered here from the content
// managed in /admin/. Static files (css, js, images) are served directly by the web server.

define('NX_ROOT', __DIR__);
require NX_ROOT . '/app/icons.php';
require NX_ROOT . '/app/content.php';
require NX_ROOT . '/app/render.php';
require NX_ROOT . '/app/pages.php';
require NX_ROOT . '/app/router.php';

// The PHP built-in server (local testing) passes static files through.
if (PHP_SAPI === 'cli-server') {
    $file = NX_ROOT . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
    $script = is_file($file) ? $file : rtrim($file, '/') . '/index.php';
    if (realpath($script) !== realpath(__FILE__) && is_file($script)) {
        chdir(dirname($script));
        require $script;
        return;
    }
}

try {
    if (($_SERVER['REDIRECT_STATUS'] ?? '') === '404') {
        // Called as the web server's error page for a blocked or missing file.
        nx_send(404, 'text/html', render_page('/404/', pg_404()));
        exit;
    }
    nx_dispatch($_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    error_log('Nexbridge page error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Temporarily unavailable</title></head><body style="font-family:system-ui,sans-serif;text-align:center;padding:60px 20px">'
        . '<h1>We will be right back</h1><p>The website is temporarily unavailable. Please try again in a few minutes.</p></body></html>';
}
