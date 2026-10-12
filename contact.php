<?php
/**
 * Contact form handler.
 * Every message is (1) saved to the dashboard inbox and (2) emailed to the address set in the
 * dashboard (Site info > "Send contact messages to"). If the email can't be sent, the message
 * is still safe in the inbox.
 */
require __DIR__ . '/inc/bootstrap.php';

$MAX_PER_HOUR = 5; // anti-spam: messages per visitor (IP address) per hour

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond($ok, $error = null, $status = 200)
{
    http_response_code($status);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(false, 'method', 405);
}

// Only accept submissions coming from this website.
$host = ms_host();
foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $hk) {
    if (!empty($_SERVER[$hk])) {
        $from_host = strtolower(preg_replace('/^www\./', '', (string) parse_url($_SERVER[$hk], PHP_URL_HOST)));
        if ($from_host !== $host) {
            respond(false, 'origin', 403);
        }
        break;
    }
}

// Spam trap: real visitors never fill this hidden field.
if (!empty($_POST['website_url'])) {
    respond(true); // pretend success, keep nothing
}

$name    = ms_line($_POST['name'] ?? '', 120);
$email   = ms_email($_POST['email'] ?? '');
$topic   = ms_line($_POST['topic'] ?? '', 120);
$lang    = (($_POST['language'] ?? 'es') === 'en') ? 'en' : 'es';
$message = ms_block($_POST['message'] ?? '', 5000);

if ($name === '' || $message === '' || $email === '') {
    respond(false, 'invalid', 422);
}
if ($topic === '') {
    $topic = ($lang === 'en') ? 'General inquiry' : 'Consulta general';
}

// Simple per-IP rate limit (kept in the server's temp folder, outside the website).
$ip   = ms_ip();
$file = sys_get_temp_dir() . '/ms_contact_' . md5($ip) . '.json';
$now  = time();
$hits = [];
if (is_readable($file)) {
    $hits = json_decode((string) @file_get_contents($file), true);
    $hits = is_array($hits) ? $hits : [];
}
$hits = array_values(array_filter($hits, function ($t) use ($now) { return ($now - (int) $t) < 3600; }));
if (count($hits) >= $MAX_PER_HOUR) {
    respond(false, 'rate', 429);
}

$S      = ms_settings();
$to     = ms_notify_email($S);
$site   = ms_brand($S);
$subject = '[' . $site . '] ' . $topic . ' — ' . $name;
$body   = "New message from the website contact form\n"
        . "----------------------------------------\n"
        . "Name:     $name\n"
        . "Email:    $email\n"
        . "Topic:    $topic\n"
        . "Language: " . strtoupper($lang) . "\n"
        . "Sent:     " . gmdate('Y-m-d H:i') . " UTC\n"
        . "----------------------------------------\n\n"
        . $message . "\n\n"
        . "(Also saved in your dashboard: " . (ms_is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . "/admin/)\n";

$mailed = ms_mail($to, $subject, $body, $name, $email);

$stored = ms_add_message([
    'id'      => bin2hex(random_bytes(6)),
    't'       => $now,
    'name'    => $name,
    'email'   => $email,
    'topic'   => $topic,
    'lang'    => $lang,
    'message' => $message,
    'mailed'  => $mailed,
    'read'    => false,
]);

if (!$stored && !$mailed) {
    respond(false, 'send', 500);
}

$hits[] = $now;
@file_put_contents($file, json_encode($hits), LOCK_EX);

respond(true);
