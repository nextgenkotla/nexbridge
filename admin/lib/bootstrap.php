<?php
declare(strict_types=1);

if (!defined('NX_ADMIN')) {
    http_response_code(404);
    exit;
}

const NX_REPO = 'nextgenkotla/nexbridge';
const NX_BRANCH = 'master';
const NX_SESSION_IDLE = 7200;          // log out after 2 hours without activity
const NX_MAX_FAILS = 5;                // failed logins allowed per IP...
const NX_FAIL_WINDOW = 900;            // ...within 15 minutes

define('NX_ROOT', dirname(__DIR__, 2));
define('NX_DATA', NX_ROOT . '/api/data');

require __DIR__ . '/github.php';
require __DIR__ . '/schema.php';

function nx_secure_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https://raw.githubusercontent.com; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
}

function nx_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function nx_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('nxadmin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin/',
        'secure' => nx_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    if (!empty($_SESSION['user']) && (time() - ($_SESSION['seen'] ?? 0)) > NX_SESSION_IDLE) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['seen'] = time();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

// ---------------------------------------------------------------- private data files
function nx_data_dir(): string
{
    if (!is_dir(NX_DATA)) {
        mkdir(NX_DATA, 0750, true);
    }
    return NX_DATA;
}

function nx_config_path(): string
{
    return nx_data_dir() . '/admin-config.php';
}

/** Admin settings live in a PHP file inside the web-blocked api/data folder and are never committed to git. */
function nx_config(): array
{
    $file = nx_config_path();
    if (!is_file($file)) {
        return [];
    }
    $cfg = include $file;
    return is_array($cfg) ? $cfg : [];
}

function nx_save_config(array $cfg): void
{
    $php = "<?php\n// Nexbridge admin settings. Private: keep out of git and never share this file.\nreturn " . var_export($cfg, true) . ";\n";
    $tmp = nx_config_path() . '.tmp';
    file_put_contents($tmp, $php, LOCK_EX);
    chmod($tmp, 0640);
    rename($tmp, nx_config_path());
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate(nx_config_path(), true);
    }
}

function nx_is_configured(): bool
{
    $cfg = nx_config();
    return !empty($cfg['users']) && !empty($cfg['github_token']);
}

function nx_read_json(string $name, $default)
{
    $file = nx_data_dir() . '/' . $name;
    if (!is_file($file)) {
        return $default;
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

function nx_write_json(string $name, $data): void
{
    file_put_contents(nx_data_dir() . '/' . $name, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ---------------------------------------------------------------- login throttling
function nx_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function nx_throttled(): bool
{
    $log = nx_read_json('admin-throttle.json', []);
    $key = hash('sha256', nx_client_ip());
    $recent = array_filter($log[$key] ?? [], fn ($t) => $t > time() - NX_FAIL_WINDOW);
    return count($recent) >= NX_MAX_FAILS;
}

function nx_record_fail(): void
{
    $log = nx_read_json('admin-throttle.json', []);
    $key = hash('sha256', nx_client_ip());
    foreach ($log as $k => $times) {
        $log[$k] = array_values(array_filter($times, fn ($t) => $t > time() - NX_FAIL_WINDOW));
        if (!$log[$k]) {
            unset($log[$k]);
        }
    }
    $log[$key][] = time();
    nx_write_json('admin-throttle.json', $log);
}

function nx_clear_fails(): void
{
    $log = nx_read_json('admin-throttle.json', []);
    unset($log[hash('sha256', nx_client_ip())]);
    nx_write_json('admin-throttle.json', $log);
}

// ---------------------------------------------------------------- inquiries (api/data/inquiries.csv)
function nx_inquiries_file(): string
{
    return nx_data_dir() . '/inquiries.csv';
}

/** Returns [header, rows]; each row gets a stable id derived from its contents. */
function nx_read_inquiries(): array
{
    $file = nx_inquiries_file();
    if (!is_file($file)) {
        return [[], []];
    }
    $fh = fopen($file, 'rb');
    flock($fh, LOCK_SH);
    $header = fgetcsv($fh, 0, ',', '"', '\\') ?: [];
    $rows = [];
    while (($line = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if ($line === [null]) {
            continue;
        }
        $row = [];
        foreach ($header as $i => $h) {
            $v = $line[$i] ?? '';
            $row[$h] = (str_starts_with($v, "'") && preg_match('/^\'[=@+\-]/', $v)) ? substr($v, 1) : $v;
        }
        $row['id'] = substr(hash('sha256', implode("\x1f", $line)), 0, 16);
        $rows[] = $row;
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return [$header, $rows];
}

function nx_delete_inquiries(array $ids): int
{
    $file = nx_inquiries_file();
    if (!is_file($file) || !$ids) {
        return 0;
    }
    $fh = fopen($file, 'c+b');
    flock($fh, LOCK_EX);
    $header = fgetcsv($fh, 0, ',', '"', '\\') ?: [];
    $keep = [];
    $removed = 0;
    while (($line = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if ($line === [null]) {
            continue;
        }
        if (in_array(substr(hash('sha256', implode("\x1f", $line)), 0, 16), $ids, true)) {
            $removed++;
            continue;
        }
        $keep[] = $line;
    }
    ftruncate($fh, 0);
    rewind($fh);
    fputcsv($fh, $header, ',', '"', '\\');
    foreach ($keep as $line) {
        fputcsv($fh, $line, ',', '"', '\\');
    }
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $removed;
}
