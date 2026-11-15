<?php
declare(strict_types=1);

define('NX_ADMIN', true);
require __DIR__ . '/lib/bootstrap.php';

nx_secure_headers();
nx_start_session();

$v = static fn (string $f): string => '/admin/assets/' . $f . '?v=' . substr(md5((string) @filemtime(__DIR__ . '/assets/' . $f)), 0, 8);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Nexbridge Admin</title>
<link rel="icon" href="/assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700&family=Source+Sans+3:wght@400;600;700&display=swap">
<link rel="stylesheet" href="<?= htmlspecialchars($v('admin.css')) ?>">
</head>
<body>
<div id="app" class="boot"><div class="boot-msg">Loading the admin panel…</div></div>
<noscript><p class="noscript">The admin panel needs JavaScript. Please enable it in your browser.</p></noscript>
<script src="<?= htmlspecialchars($v('admin.js')) ?>" defer></script>
</body>
</html>
