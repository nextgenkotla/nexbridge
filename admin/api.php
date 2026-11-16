<?php
declare(strict_types=1);

define('NX_ADMIN', true);
require __DIR__ . '/lib/bootstrap.php';

nx_secure_headers();
nx_start_session();

function out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $status = 400): void
{
    out(['error' => $message], $status);
}

function input(): array
{
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents('php://input', false, null, 0, 12 * 1024 * 1024);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = [];
        }
    }
    return $data;
}

function str_in(string $key, int $max = 500): string
{
    $v = input()[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function require_user(): string
{
    if (empty($_SESSION['user']) || !isset(nx_config()['users'][$_SESSION['user']])) {
        fail('Your session has ended. Please log in again.', 401);
    }
    return $_SESSION['user'];
}

function valid_username(string $u): bool
{
    return (bool) preg_match('/^[A-Za-z0-9._@\-]{3,60}$/', $u);
}

function valid_password(string $p): string
{
    if (mb_strlen($p) < 10) {
        return 'Use a password of at least 10 characters.';
    }
    return '';
}

$action = $_GET['a'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'], $sent)) {
        fail('This page has expired. Reload it and try again.', 403);
    }
}

try {
    switch ($action) {
        // ------------------------------------------------------------ session
        case 'state':
            out([
                'configured' => nx_is_configured(),
                'user' => !empty($_SESSION['user']) && isset(nx_config()['users'][$_SESSION['user']]) ? $_SESSION['user'] : null,
                'csrf' => $_SESSION['csrf'],
            ]);

        case 'setup':
            if ($method !== 'POST') fail('Method not allowed', 405);
            if (nx_is_configured()) fail('The admin panel is already set up.', 409);
            if (nx_throttled()) fail('Too many attempts. Try again in 15 minutes.', 429);
            $key = strtoupper(str_replace(' ', '', str_in('setup_key', 60)));
            $user = str_in('username', 60);
            $pass = (string) (input()['password'] ?? '');
            $hash = include __DIR__ . '/lib/setup-key.php';
            if (!is_string($hash) || !password_verify($key, $hash)) {
                nx_record_fail();
                fail('That setup key is not correct.', 403);
            }
            if (!valid_username($user)) fail('Choose a username of 3 to 60 letters, numbers, dots or dashes.');
            if ($msg = valid_password($pass)) fail($msg);
            nx_clear_fails();
            nx_save_config(['users' => [$user => password_hash($pass, PASSWORD_DEFAULT)], 'notify' => ['notify_email' => '', 'from_email' => '']]);
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            out(['ok' => true, 'user' => $user]);

        case 'login':
            if ($method !== 'POST') fail('Method not allowed', 405);
            if (nx_throttled()) fail('Too many failed attempts. Try again in 15 minutes.', 429);
            $user = str_in('username', 60);
            $pass = (string) (input()['password'] ?? '');
            $users = nx_config()['users'] ?? [];
            $hash = $users[$user] ?? null;
            if (!$hash || !password_verify($pass, $hash)) {
                nx_record_fail();
                fail('That username and password do not match.', 401);
            }
            nx_clear_fails();
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                $cfg = nx_config();
                $cfg['users'][$user] = password_hash($pass, PASSWORD_DEFAULT);
                nx_save_config($cfg);
            }
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            out(['ok' => true, 'user' => $user]);

        case 'logout':
            if ($method !== 'POST') fail('Method not allowed', 405);
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            out(['ok' => true, 'csrf' => $_SESSION['csrf']]);

        // ------------------------------------------------------------ content
        case 'schema':
            require_user();
            $cols = [];
            foreach (nx_collections() as $key => $c) {
                unset($c['file']);
                $cols[$key] = $c;
            }
            out(['collections' => $cols, 'flags' => nx_flags(), 'icons' => NX_ICONS]);

        case 'load':
            require_user();
            $cols = nx_collections();
            $key = $_GET['c'] ?? '';
            if (!isset($cols[$key])) fail('Unknown section', 404);
            $file = nx_read_content($cols[$key]['file']);
            $data = json_decode($file['raw'], true);
            if (!is_array($data)) fail('This content file is damaged and could not be read. Restore an earlier version from the history.', 500);
            out(['data' => $data, 'version' => $file['version']]);

        case 'history':
            require_user();
            $cols = nx_collections();
            $key = $_GET['c'] ?? '';
            if (!isset($cols[$key])) fail('Unknown section', 404);
            out(['versions' => nx_history($key)]);

        case 'version':
            require_user();
            $cols = nx_collections();
            $key = $_GET['c'] ?? '';
            if (!isset($cols[$key])) fail('Unknown section', 404);
            $raw = nx_history_version($key, (string) ($_GET['id'] ?? ''));
            $data = $raw === null ? null : json_decode($raw, true);
            if (!is_array($data)) fail('That version could not be found.', 404);
            out(['data' => $data]);

        case 'backup':
            require_user();
            $all = [];
            foreach (nx_collections() as $col) {
                $all[$col['file']] = json_decode(nx_read_content($col['file'])['raw'], true);
            }
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="nexbridge-content-' . gmdate('Y-m-d') . '.json"');
            echo json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;

        case 'save':
            if ($method !== 'POST') fail('Method not allowed', 405);
            $user = require_user();
            $cols = nx_collections();
            $key = (string) (input()['c'] ?? '');
            if (!isset($cols[$key])) fail('Unknown section', 404);
            $col = $cols[$key];
            try {
                $clean = nx_validate_collection($col, input()['data'] ?? null);
            } catch (ValidationError $e) {
                fail($e->getMessage(), 422);
            }
            if ($col['type'] === 'list' && !empty($col['fixed'])) {
                // Categories can be edited but not added, removed or reordered (their web addresses are fixed).
                $current = json_decode(nx_read_content($col['file'])['raw'], true) ?: [];
                if (array_column($current, 'slug') !== array_column($clean, 'slug')) {
                    fail('Service categories cannot be added, removed or reordered here.', 422);
                }
            }
            $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            $version = nx_write_content($key, $col['file'], $json, str_in('version', 64), $user);
            if ($version === null) {
                fail('Someone else saved this section a moment ago. Reload the page to get the latest version, then make your change again.', 409);
            }
            out(['ok' => true, 'version' => $version, 'data' => $clean]);

        case 'upload':
            if ($method !== 'POST') fail('Method not allowed', 405);
            $user = require_user();
            $raw = base64_decode((string) (input()['data'] ?? ''), true);
            if ($raw === false || $raw === '') fail('The image could not be read.');
            if (strlen($raw) > 4 * 1024 * 1024) fail('This image is too large even after resizing. Try a smaller photo.');
            $info = @getimagesizefromstring($raw);
            $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
            if (!$info || !isset($types[$info[2]])) fail('Please upload a JPG, PNG or WebP image.');
            $base = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', pathinfo(str_in('name', 120), PATHINFO_FILENAME)) ?? '');
            $base = trim(substr($base, 0, 50), '-') ?: 'image';
            $rel = 'uploads/' . gmdate('Y/m') . '/' . $base . '-' . bin2hex(random_bytes(3)) . '.' . $types[$info[2]];
            $dir = dirname(NX_ROOT . '/' . $rel);
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) fail('The image folder could not be created on the server.', 500);
            if (file_put_contents(NX_ROOT . '/' . $rel, $raw) === false) fail('The image could not be saved on the server.', 500);
            out(['ok' => true, 'path' => '/' . $rel, 'width' => $info[0], 'height' => $info[1]]);

        // ------------------------------------------------------------ inquiries
        case 'inquiries':
            require_user();
            [, $rows] = nx_read_inquiries();
            $status = nx_read_json('inquiry-status.json', []);
            foreach ($rows as &$r) {
                $r['status'] = $status[$r['id']] ?? 'new';
            }
            unset($r);
            out(['rows' => array_reverse($rows)]);

        case 'inquiry_status':
            if ($method !== 'POST') fail('Method not allowed', 405);
            require_user();
            $ids = array_filter((array) (input()['ids'] ?? []), fn ($i) => is_string($i) && preg_match('/^[a-f0-9]{16}$/', $i));
            $new = str_in('status', 20);
            if (!in_array($new, ['new', 'contacted', 'closed'], true)) fail('Unknown status');
            $status = nx_read_json('inquiry-status.json', []);
            foreach ($ids as $id) {
                if ($new === 'new') unset($status[$id]); else $status[$id] = $new;
            }
            nx_write_json('inquiry-status.json', $status);
            out(['ok' => true]);

        case 'inquiry_delete':
            if ($method !== 'POST') fail('Method not allowed', 405);
            require_user();
            $ids = array_values(array_filter((array) (input()['ids'] ?? []), fn ($i) => is_string($i) && preg_match('/^[a-f0-9]{16}$/', $i)));
            $n = nx_delete_inquiries($ids);
            $status = nx_read_json('inquiry-status.json', []);
            foreach ($ids as $id) unset($status[$id]);
            nx_write_json('inquiry-status.json', $status);
            out(['ok' => true, 'deleted' => $n]);

        case 'inquiries_csv':
            require_user();
            [$header, $rows] = nx_read_inquiries();
            $status = nx_read_json('inquiry-status.json', []);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="nexbridge-inquiries-' . gmdate('Y-m-d') . '.csv"');
            $fh = fopen('php://output', 'wb');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, array_merge($header, ['status']), ',', '"', '\\');
            foreach ($rows as $r) {
                $line = [];
                foreach ($header as $h) {
                    $v = (string) ($r[$h] ?? '');
                    $line[] = preg_match('/^(=|@|[+\-](?![\d\s(]))/', $v) ? "'" . $v : $v;
                }
                $line[] = $status[$r['id']] ?? 'new';
                fputcsv($fh, $line, ',', '"', '\\');
            }
            exit;

        // ------------------------------------------------------------ account & settings
        case 'account':
            $me = require_user();
            $cfg = nx_config();
            out([
                'me' => $me,
                'users' => array_keys($cfg['users'] ?? []),
                'notify' => $cfg['notify'] ?? ['notify_email' => '', 'from_email' => ''],
                'mail_available' => function_exists('mail'),
            ]);

        case 'password':
            if ($method !== 'POST') fail('Method not allowed', 405);
            $me = require_user();
            $cfg = nx_config();
            if (!password_verify((string) (input()['current'] ?? ''), $cfg['users'][$me])) fail('Your current password is not correct.', 403);
            $new = (string) (input()['new'] ?? '');
            if ($msg = valid_password($new)) fail($msg);
            $cfg['users'][$me] = password_hash($new, PASSWORD_DEFAULT);
            nx_save_config($cfg);
            out(['ok' => true]);

        case 'user_add':
            if ($method !== 'POST') fail('Method not allowed', 405);
            require_user();
            $user = str_in('username', 60);
            $pass = (string) (input()['password'] ?? '');
            if (!valid_username($user)) fail('Choose a username of 3 to 60 letters, numbers, dots or dashes.');
            if ($msg = valid_password($pass)) fail($msg);
            $cfg = nx_config();
            if (isset($cfg['users'][$user])) fail('That username already exists.', 409);
            $cfg['users'][$user] = password_hash($pass, PASSWORD_DEFAULT);
            nx_save_config($cfg);
            out(['ok' => true]);

        case 'user_delete':
            if ($method !== 'POST') fail('Method not allowed', 405);
            $me = require_user();
            $user = str_in('username', 60);
            if ($user === $me) fail('You cannot remove your own account.');
            $cfg = nx_config();
            unset($cfg['users'][$user]);
            nx_save_config($cfg);
            out(['ok' => true]);

        case 'notify':
            if ($method !== 'POST') fail('Method not allowed', 405);
            require_user();
            $to = str_in('notify_email', 200);
            $from = str_in('from_email', 200);
            foreach ([$to, $from] as $addr) {
                if ($addr !== '' && !filter_var($addr, FILTER_VALIDATE_EMAIL)) fail("\"$addr\" is not a valid email address.");
            }
            $cfg = nx_config();
            $cfg['notify'] = ['notify_email' => $to, 'from_email' => $from];
            nx_save_config($cfg);
            out(['ok' => true]);

        default:
            fail('Not found', 404);
    }
} catch (Throwable $e) {
    error_log('Nexbridge admin error: ' . $e->getMessage());
    fail('Something went wrong on the server. Please try again.', 500);
}
