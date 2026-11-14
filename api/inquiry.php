<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

function clean(string $key, int $max): string
{
    $value = isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
    $value = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr(trim($value), 0, $max);
}

function safe_path(string $path): string
{
    return preg_match('#^/[A-Za-z0-9/_\-]*$#', $path) ? $path : '/contact/';
}

function redirect(string $location): void
{
    header('Location: ' . $location, true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('/contact/');
}

$page = safe_path(clean('page', 200));

// Bots fill the hidden "website" field; pretend success and discard.
if (clean('website', 200) !== '') {
    redirect('/thank-you/');
}

$data = [
    'received' => gmdate('Y-m-d H:i:s') . ' UTC',
    'type' => in_array(clean('form_type', 20), ['inquiry', 'booking'], true) ? clean('form_type', 20) : 'inquiry',
    'name' => clean('name', 100),
    'phone' => clean('phone', 30),
    'email' => clean('email', 120),
    'service' => clean('service', 120),
    'date' => clean('date', 10),
    'time' => clean('time', 40),
    'mode' => clean('mode', 40),
    'message' => clean('message', 2000),
    'page' => $page,
];

$valid = $data['name'] !== ''
    && preg_match('/^[0-9+()\-\s]{7,30}$/', $data['phone'])
    && filter_var($data['email'], FILTER_VALIDATE_EMAIL)
    && clean('consent', 5) === 'yes'
    && ($data['date'] === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date']));

if (!$valid) {
    redirect($page . '?form_error=1#inquiry');
}

$dir = __DIR__ . '/data';
if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}
$file = $dir . '/inquiries.csv';
$isNew = !file_exists($file);
$fh = fopen($file, 'ab');
if ($fh && flock($fh, LOCK_EX)) {
    if ($isNew) {
        fputcsv($fh, array_keys($data));
    }
    // Prefix values that spreadsheet apps would treat as formulas (but not phone numbers like +92...).
    fputcsv($fh, array_map(fn ($v) => preg_match('/^(=|@|[+\-](?![\d\s(]))/', $v) ? "'" . $v : $v, array_values($data)));
    flock($fh, LOCK_UN);
    fclose($fh);
}

$to = $config['notify_email'] ?? '';
$from = $config['from_email'] ?? '';
if ($to !== '' && $from !== '') {
    $label = $data['type'] === 'booking' ? 'Appointment request' : 'Website inquiry';
    $subject = $label . ': ' . ($data['service'] !== '' ? $data['service'] : 'General') . ' - ' . $data['name'];
    $lines = [];
    foreach ($data as $k => $v) {
        if ($v !== '') {
            $lines[] = ucfirst($k) . ': ' . $v;
        }
    }
    $headers = [
        'From: Nexbridge Website <' . $from . '>',
        'Reply-To: ' . $data['email'],
        'Content-Type: text/plain; charset=UTF-8',
    ];
    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', implode("\n", $lines), implode("\r\n", $headers));
}

redirect('/thank-you/');
