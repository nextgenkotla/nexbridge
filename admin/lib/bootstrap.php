<?php
declare(strict_types=1);

if (!defined('NX_ADMIN')) {
    http_response_code(404);
    exit;
}

const NX_HISTORY_KEEP = 30;           // saved versions kept per section
const NX_SESSION_IDLE = 7200;          // log out after 2 hours without activity
const NX_MAX_FAILS = 5;                // failed logins allowed per IP...
const NX_FAIL_WINDOW = 900;            // ...within 15 minutes

defined('NX_ROOT') || define('NX_ROOT', dirname(__DIR__, 2));
define('NX_DATA', NX_ROOT . '/api/data');

require __DIR__ . '/schema.php';

function nx_secure_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
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
    return !empty(nx_config()['users']);
}

// ---------------------------------------------------------------- content storage
/** Live content is kept in api/data/content/; /content/ in the repository is the starting point. */
function nx_content_path(string $file, bool $forWrite = false): string
{
    $live = nx_data_dir() . '/content/' . $file;
    if ($forWrite || is_file($live)) {
        return $live;
    }
    return NX_ROOT . '/content/' . $file;
}

function nx_read_content(string $file): array
{
    $raw = (string) @file_get_contents(nx_content_path($file));
    return ['raw' => $raw, 'version' => sha1($raw)];
}

/** Saves a section, keeping the previous version in the history. Returns the new version id, or null if someone saved in between. */
function nx_write_content(string $key, string $file, string $json, string $expectedVersion, string $user): ?string
{
    $dir = nx_data_dir() . '/content';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $lock = fopen($dir . '/.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        $current = nx_read_content($file);
        if ($expectedVersion !== '' && !hash_equals($current['version'], $expectedVersion)) {
            return null;
        }
        $tmp = nx_content_path($file, true) . '.tmp';
        file_put_contents($tmp, $json);
        rename($tmp, nx_content_path($file, true));
        nx_add_history($key, $json, $user);
        return sha1($json);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function nx_history_dir(string $key): string
{
    $dir = nx_data_dir() . '/history/' . $key;
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

function nx_add_history(string $key, string $json, string $user): void
{
    $dir = nx_history_dir($key);
    $name = gmdate('Ymd-His') . '-' . substr(bin2hex(random_bytes(2)), 0, 4) . '-' . preg_replace('/[^A-Za-z0-9._@-]/', '', $user) . '.json';
    file_put_contents("$dir/$name", $json);
    $files = glob("$dir/*.json") ?: [];
    rsort($files);
    foreach (array_slice($files, NX_HISTORY_KEEP) as $old) {
        @unlink($old);
    }
}

function nx_history(string $key): array
{
    $files = glob(nx_history_dir($key) . '/*.json') ?: [];
    rsort($files);
    $out = [];
    foreach ($files as $f) {
        $base = basename($f, '.json');
        if (preg_match('/^(\d{8})-(\d{6})-[0-9a-f]{4}-(.*)$/', $base, $m)) {
            $dt = DateTime::createFromFormat('Ymd His', $m[1] . ' ' . $m[2], new DateTimeZone('UTC'));
            $out[] = ['id' => $base, 'saved' => $dt ? $dt->format('Y-m-d\TH:i:s\Z') : '', 'user' => $m[3]];
        }
    }
    return $out;
}

function nx_history_version(string $key, string $id): ?string
{
    if (!preg_match('/^\d{8}-\d{6}-[0-9a-f]{4}-[A-Za-z0-9._@-]*$/', $id)) {
        return null;
    }
    $f = nx_history_dir($key) . '/' . $id . '.json';
    return is_file($f) ? (string) file_get_contents($f) : null;
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
