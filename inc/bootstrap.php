<?php
/**
 * Shared core: used by the public site (index.php), the contact form (contact.php)
 * and the admin dashboard (admin/index.php).
 *
 * Plain PHP 7.4+, no database. Everything the dashboard saves lives in the /data folder
 * as small JSON files. Each file starts with a one-line PHP guard, so even if the server
 * ignored .htaccess, nobody could read the contents through the browser.
 *
 * DO NOT delete or overwrite the /data and /uploads folders when you re-upload the site:
 * they hold your settings, team photos and received messages.
 */
if (defined('MS_BOOT')) {
    return;
}
define('MS_BOOT', true);
define('MS_ROOT', dirname(__DIR__));
define('MS_DATA', MS_ROOT . '/data');
define('MS_PHOTOS', MS_ROOT . '/uploads/team');
define('MS_GUARD', "<?php http_response_code(404); exit; ?>\n");

if (is_readable(__DIR__ . '/setup.php')) {
    require_once __DIR__ . '/setup.php';
}
if (!defined('SETUP_CODE')) {
    define('SETUP_CODE', '');
}

/* ----------------------------------------------------------------- small helpers */

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ms_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function ms_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function ms_host(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);
    $host = preg_replace('/^www\./', '', $host);
    return preg_match('/^[a-z0-9.-]+$/', $host) ? $host : 'localhost';
}

/** One line of plain text, control characters removed, length limited. */
function ms_line($s, int $max): string
{
    $s = str_replace("\0", '', (string) $s);
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    $s = preg_replace('/[\r\n\t]+/', ' ', $s);
    $s = trim(preg_replace('/ {2,}/', ' ', $s));
    return mb_substr($s, 0, $max, 'UTF-8');
}

/** Multi-line plain text. */
function ms_block($s, int $max): string
{
    $s = str_replace("\0", '', (string) $s);
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    return mb_substr(trim($s), 0, $max, 'UTF-8');
}

/** Returns a safe http(s) URL, or '' when empty/invalid. "@name" becomes a Telegram link. */
function ms_url($u, string $kind = ''): string
{
    $u = ms_line($u, 300);
    if ($u === '') {
        return '';
    }
    if ($kind === 'telegram' && $u[0] === '@') {
        $u = 'https://t.me/' . substr($u, 1);
    }
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $u)) {
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $u) && !preg_match('~^[a-z0-9.-]+\.[a-z]{2,}(:\d+)?(/|$)~i', $u)) {
            return ''; // something like javascript:...
        }
        $u = 'https://' . ltrim($u, '/');
    }
    $p = parse_url($u);
    if (!$p || empty($p['host']) || empty($p['scheme'])) {
        return '';
    }
    if (!in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
        return '';
    }
    if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/i', $p['host'])) {
        return '';
    }
    return $u;
}

function ms_email($e): string
{
    $e = ms_line($e, 160);
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : '';
}

function ms_tel($phone): string
{
    return preg_replace('/[^\d+]/', '', (string) $phone);
}

/* ----------------------------------------------------------------- guarded JSON storage */

function ms_data_dir(): string
{
    if (!is_dir(MS_DATA)) {
        @mkdir(MS_DATA, 0755, true);
    }
    $ht = MS_DATA . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n");
    }
    return MS_DATA;
}

function ms_read(string $name, array $default = []): array
{
    if (!preg_match('/^[a-z0-9_]+$/', $name)) {
        return $default;
    }
    $f = MS_DATA . '/' . $name . '.php';
    if (!is_readable($f)) {
        return $default;
    }
    $raw = @file_get_contents($f);
    if ($raw === false) {
        return $default;
    }
    if (strpos($raw, MS_GUARD) === 0) {
        $raw = substr($raw, strlen(MS_GUARD));
    }
    $d = json_decode($raw, true);
    return is_array($d) ? $d : $default;
}

function ms_write(string $name, array $data): bool
{
    if (!preg_match('/^[a-z0-9_]+$/', $name)) {
        return false;
    }
    ms_data_dir();
    $f = MS_DATA . '/' . $name . '.php';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, MS_GUARD . $json, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $f)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/** Read, change and save a file while holding a lock, so two visitors can't overwrite each other. */
function ms_update(string $name, callable $fn, array $default = []): bool
{
    ms_data_dir();
    $lock = @fopen(MS_DATA . '/.lock', 'c');
    if (!$lock) {
        return false;
    }
    flock($lock, LOCK_EX);
    $new = $fn(ms_read($name, $default));
    $ok = is_array($new) ? ms_write($name, $new) : true;
    flock($lock, LOCK_UN);
    fclose($lock);
    return $ok;
}

/* ----------------------------------------------------------------- site settings */

function ms_defaults(): array
{
    return [
        'brand' => ['a' => 'Mora', 'b' => 'Solano'],
        'contact' => [
            'address1' => 'Avenida Central, Edificio [Nombre], Piso 4',
            'address2' => 'San José, Costa Rica',
            'phone' => '+506 2000 0000',
            'email' => 'info@moraysolano.cr',
            'notify_email' => '',
            'hours_es' => 'Lun–Vie, 8:00 a. m. – 5:00 p. m. (hora de Costa Rica)',
            'hours_en' => 'Mon–Fri, 8:00 am – 5:00 pm (Costa Rica time)',
        ],
        'social' => ['linkedin' => '', 'telegram' => ''],
        'team' => [
            ['name' => 'Michael Mora', 'role_es' => 'Socio Fundador', 'role_en' => 'Founding Partner', 'photo' => ''],
            ['name' => 'Charles Solano', 'role_es' => 'Socio Fundador', 'role_en' => 'Founding Partner', 'photo' => ''],
        ],
        'texts' => ['es' => [], 'en' => []],
    ];
}

function ms_settings(bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $d = ms_defaults();
    $s = ms_read('settings', []);
    foreach (['brand', 'contact', 'social'] as $sec) {
        if (isset($s[$sec]) && is_array($s[$sec])) {
            foreach ($d[$sec] as $k => $v) {
                if (isset($s[$sec][$k]) && is_string($s[$sec][$k])) {
                    $d[$sec][$k] = $s[$sec][$k];
                }
            }
        }
    }
    if (isset($s['team']) && is_array($s['team'])) {
        $team = [];
        foreach ($s['team'] as $m) {
            if (is_array($m)) {
                $team[] = array_replace(['name' => '', 'role_es' => '', 'role_en' => '', 'photo' => ''], array_intersect_key($m, ['name' => 1, 'role_es' => 1, 'role_en' => 1, 'photo' => 1]));
            }
        }
        $d['team'] = $team;
    }
    if (isset($s['texts']) && is_array($s['texts'])) {
        foreach (['es', 'en'] as $l) {
            if (isset($s['texts'][$l]) && is_array($s['texts'][$l])) {
                $d['texts'][$l] = $s['texts'][$l];
            }
        }
    }
    return $cache = $d;
}

function ms_brand(array $S): string
{
    $a = trim($S['brand']['a']);
    $b = trim($S['brand']['b']);
    if ($a === '' && $b === '') {
        return 'Law Firm';
    }
    if ($b === '') {
        return $a;
    }
    if ($a === '') {
        return $b;
    }
    return $a . ' & ' . $b;
}

function ms_notify_email(array $S): string
{
    $e = ms_email($S['contact']['notify_email']);
    return $e !== '' ? $e : 'info@' . ms_host();
}

/* ----------------------------------------------------------------- page texts */

function ms_texts_default(): array
{
    static $d = null;
    if ($d === null) {
        $j = @file_get_contents(__DIR__ . '/texts.default.json');
        $d = $j ? json_decode($j, true) : null;
        if (!is_array($d) || !isset($d['es'], $d['en'])) {
            $d = ['es' => [], 'en' => []];
        }
    }
    return $d;
}

/** Keys the dashboard must not edit as free text (they come from other forms). */
function ms_managed_text_keys(): array
{
    return ['hours_val', 'role_partner'];
}

/** The final ES/EN text sets the public page uses: defaults + dashboard edits + live settings. */
function ms_texts_for_site(array $S): array
{
    $def = ms_texts_default();
    $brand = ms_brand($S);
    $out = [];
    foreach (['es', 'en'] as $l) {
        $t = $def[$l];
        foreach ($S['texts'][$l] as $k => $v) {
            if (isset($t[$k]) && is_string($v) && trim($v) !== '') {
                $t[$k] = $v;
            }
        }
        if (trim($S['contact']['hours_' . $l]) !== '') {
            $t['hours_val'] = $S['contact']['hours_' . $l];
        }
        foreach ($S['team'] as $i => $m) {
            $t['team_role_' . $i] = $m['role_' . $l];
        }
        foreach ($t as $k => $v) {
            $t[$k] = str_replace('{brand}', $brand, $v);
        }
        $out[$l] = $t;
    }
    return $out;
}

/** Puts the Spanish text into every empty data-i18n element, so search engines and no-JS visitors see content. */
function ms_prefill_i18n(string $html, array $texts): string
{
    return preg_replace_callback(
        '/(<([a-z0-9]+)\b[^>]*\bdata-i18n="([a-z0-9_]+)"[^>]*>)(<\/\2>)/i',
        function ($m) use ($texts) {
            $v = $texts[$m[3]] ?? '';
            return $m[1] . h($v) . $m[4];
        },
        $html
    );
}

/** JSON that is safe to place inside a <script> tag. */
function ms_json_for_script($v): string
{
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/* ----------------------------------------------------------------- messages inbox */

function ms_add_message(array $m): bool
{
    return ms_update('messages', function ($list) use ($m) {
        array_unshift($list, $m);
        return array_slice($list, 0, 2000);
    }, []);
}

function ms_mail(string $to, string $subject, string $body, string $replyName = '', string $replyEmail = ''): bool
{
    $S = ms_settings();
    $from = 'no-reply@' . ms_host();
    $site = ms_brand($S);
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . mb_encode_mimeheader($site, 'UTF-8') . ' <' . $from . '>',
        'X-Mailer: PHP/' . phpversion(),
    ];
    if ($replyEmail !== '') {
        $headers[] = 'Reply-To: ' . ($replyName !== '' ? mb_encode_mimeheader($replyName, 'UTF-8') . ' ' : '') . '<' . $replyEmail . '>';
    }
    return (bool) @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, implode("\r\n", $headers), '-f' . $from);
}
