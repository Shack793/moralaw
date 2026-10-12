<?php
/**
 * Admin dashboard — edit the website without touching any files.
 * Address: https://yourdomain.com/admin/
 */
require dirname(__DIR__) . '/inc/bootstrap.php';

date_default_timezone_set('America/Costa_Rica');

$nonce = bin2hex(random_bytes(12));
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; script-src 'nonce-$nonce'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

/* ------------------------------------------------------------------ session */

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php')), '/') . '/';
session_name('ms_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $base,
    'secure'   => ms_is_https(),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
if (isset($_SESSION['last']) && time() - (int) $_SESSION['last'] > 7200) { // 2 hours idle
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last'] = time();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$self  = strtok($_SERVER['REQUEST_URI'] ?? 'index.php', '?');
$admin = ms_read('admin', []);
$has_admin = !empty($admin['hash']) && !empty($admin['id']);
$logged = $has_admin && !empty($_SESSION['uid']) && hash_equals($admin['id'], (string) $_SESSION['uid']);

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function go(string $tab = ''): void
{
    global $self;
    header('Location: ' . $self . ($tab !== '' ? '?tab=' . $tab : ''));
    exit;
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}

/* ------------------------------------------------------------------ login throttling */

function throttle_blocked(string $key, int $max = 6, int $window = 900): bool
{
    $d = ms_read('throttle', []);
    $k = md5($key);
    return isset($d[$k]) && time() - (int) $d[$k]['t'] < $window && (int) $d[$k]['n'] >= $max;
}

function throttle_hit(string $key, int $window = 900): void
{
    $k = md5($key);
    ms_update('throttle', function ($d) use ($k, $window) {
        $now = time();
        foreach ($d as $kk => $v) {
            if ($now - (int) $v['t'] >= $window) {
                unset($d[$kk]);
            }
        }
        if (isset($d[$k])) {
            $d[$k]['n'] = (int) $d[$k]['n'] + 1;
        } else {
            $d[$k] = ['n' => 1, 't' => $now];
        }
        return $d;
    }, []);
}

function throttle_clear(string $key): void
{
    $k = md5($key);
    ms_update('throttle', function ($d) use ($k) {
        unset($d[$k]);
        return $d;
    }, []);
}

/* ------------------------------------------------------------------ photos */

function save_photo(string $tmp, int $size): array
{
    if (!function_exists('imagecreatetruecolor')) {
        return [null, 'Photo uploads need the PHP "GD" extension, which is not enabled on this server.'];
    }
    if ($size > 6 * 1024 * 1024) {
        return [null, 'That photo is larger than 6 MB.'];
    }
    $info = @getimagesize($tmp);
    if (!$info) {
        return [null, 'That file is not a valid image.'];
    }
    $w = (int) $info[0];
    $hh = (int) $info[1];
    $type = $info[2];
    if ($w * $hh > 36000000) {
        return [null, 'That image has too many pixels. Please use a smaller photo.'];
    }
    $src = false;
    if ($type === IMAGETYPE_JPEG) {
        $src = @imagecreatefromjpeg($tmp);
    } elseif ($type === IMAGETYPE_PNG) {
        $src = @imagecreatefrompng($tmp);
    } elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        $src = @imagecreatefromwebp($tmp);
    } else {
        return [null, 'Please use a JPG, PNG or WebP photo.'];
    }
    if (!$src) {
        return [null, 'That image could not be read.'];
    }
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($tmp);
        $o = is_array($ex) ? (int) ($ex['Orientation'] ?? 1) : 1;
        $angle = $o === 3 ? 180 : ($o === 6 ? -90 : ($o === 8 ? 90 : 0));
        if ($angle !== 0) {
            $rot = @imagerotate($src, $angle, 0);
            if ($rot) {
                imagedestroy($src);
                $src = $rot;
            }
        }
    }
    $w = imagesx($src);
    $hh = imagesy($src);
    $scale = min(1, 1000 / max($w, $hh));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($hh * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $hh);

    if (!is_dir(MS_PHOTOS)) {
        @mkdir(MS_PHOTOS, 0755, true);
    }
    $ht = MS_PHOTOS . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|php[0-9])$\">\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n</FilesMatch>\n");
    }
    $name = 't_' . bin2hex(random_bytes(8)) . '.jpg';
    $ok = @imagejpeg($dst, MS_PHOTOS . '/' . $name, 86);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok ? [$name, null] : [null, 'The photo could not be saved (the uploads folder is not writable).'];
}

function photo_ok(string $f): bool
{
    return (bool) preg_match('/^t_[0-9a-f]{16}\.jpg$/', $f);
}

function delete_photo(string $f): void
{
    if (photo_ok($f) && is_file(MS_PHOTOS . '/' . $f)) {
        @unlink(MS_PHOTOS . '/' . $f);
    }
}

/* ------------------------------------------------------------------ text editor structure */

function text_groups(array $def): array
{
    $groups = [
        'Menu and header'               => ['nav_about', 'nav_services', 'nav_team', 'nav_contact', 'logo_sub', 'skip'],
        'Home banner'                   => ['hero_title', 'hero_lead', 'hero_cta1', 'hero_cta2', 'hero_quote'],
        'About the firm'                => ['about_label', 'about_title', 'about_p1', 'about_p2', 'about_p3'],
        'Practice areas — section'      => ['services_label', 'services_title', 'read_more'],
    ];
    for ($n = 1; $n <= 9; $n++) {
        $groups['Practice area ' . $n . ' — ' . ($def['en']['s' . $n . '_t'] ?? '')] = ['s' . $n . '_t', 's' . $n . '_teaser', 's' . $n . '_body'];
    }
    $groups += [
        'Team — section'                => ['team_label', 'team_title'],
        'Contact — section and form'    => ['contact_label', 'contact_title', 'office', 'phone', 'hours', 't_general', 'f_name', 'f_email', 'f_topic', 'f_msg', 'f_human', 'f_send', 'f_consent'],
        'Contact form — messages'       => ['m_ok', 'm_fill', 'm_email', 'm_human', 'm_sending', 'm_fail', 'm_rate', 'm_mailto'],
        'Footer'                        => ['f_tag', 'f_firm', 'back_top', 'footer_rights', 'footer_legal', 'footer_privacy', 'footer_terms', 'footer_disclaimer'],
        'Google / search results'       => ['meta_desc'],
    ];
    // anything new that is not listed yet still shows up, so nothing becomes uneditable
    $listed = [];
    foreach ($groups as $keys) {
        $listed = array_merge($listed, $keys);
    }
    $rest = array_diff(array_keys($def['es']), $listed, ms_managed_text_keys());
    if ($rest) {
        $groups['Other'] = array_values($rest);
    }
    return $groups;
}

function key_label(string $k): string
{
    static $L = [
        'nav_about' => 'Menu: About', 'nav_services' => 'Menu: Practice areas', 'nav_team' => 'Menu: Team', 'nav_contact' => 'Menu: Contact',
        'logo_sub' => 'Small line under the logo', 'skip' => 'Skip-to-content link (accessibility)',
        'hero_title' => 'Headline', 'hero_lead' => 'Intro paragraph', 'hero_cta1' => 'Main button', 'hero_cta2' => 'Second button', 'hero_quote' => 'Quote in the green box',
        'about_label' => 'Small label above the title', 'about_title' => 'Title', 'about_p1' => 'Paragraph 1', 'about_p2' => 'Paragraph 2', 'about_p3' => 'Paragraph 3',
        'services_label' => 'Small label above the title', 'services_title' => 'Section title', 'read_more' => 'Card link ("Read more")',
        'team_label' => 'Small label above the title', 'team_title' => 'Section title',
        'contact_label' => 'Small label above the title', 'contact_title' => 'Section title', 'office' => 'Label: Office', 'phone' => 'Label: Phone', 'hours' => 'Label: Hours',
        't_general' => 'Topic list: first option', 'f_name' => 'Form: name label', 'f_email' => 'Form: email label', 'f_topic' => 'Form: topic label', 'f_msg' => 'Form: message label',
        'f_human' => 'Form: checkbox text', 'f_send' => 'Form: send button',
        'm_ok' => 'Success message', 'm_fill' => 'Error: missing fields', 'm_email' => 'Error: invalid email', 'm_human' => 'Error: checkbox not ticked',
        'm_sending' => 'Message while sending', 'm_fail' => 'Error: sending failed', 'm_rate' => 'Error: too many messages', 'm_mailto' => 'Message when the page is opened as a local file',
        'f_tag' => 'Short firm description', 'f_firm' => 'Column heading: the firm', 'back_top' => 'Back-to-top link', 'footer_rights' => 'Copyright line',
        'footer_legal' => 'Legal notice link text', 'footer_privacy' => 'Privacy policy link text', 'footer_terms' => 'Terms & conditions link text', 'f_consent' => 'Line under the form (ends right before the Privacy Policy link)', 'footer_disclaimer' => 'Disclaimer',
        'meta_desc' => 'Description shown under your site name in Google',
    ];
    if (isset($L[$k])) {
        return $L[$k];
    }
    if (preg_match('/^s\d+_t$/', $k)) {
        return 'Title';
    }
    if (preg_match('/^s\d+_teaser$/', $k)) {
        return 'Short description (on the card)';
    }
    if (preg_match('/^s\d+_body$/', $k)) {
        return 'Full description (when opened)';
    }
    return $k;
}

/* ------------------------------------------------------------------ actions (POST) */

$def = ms_texts_default();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $tok = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $tok)) {
        flash('err', 'Your session expired. Please try again.');
        go();
    }

    /* ---- not logged in ---- */
    if (!$logged) {
        $tkey = 'auth:' . ms_ip();
        if ($action === 'setup' && !$has_admin) {
            if (throttle_blocked($tkey)) {
                flash('err', 'Too many attempts. Please wait 15 minutes and try again.');
                go();
            }
            $code = trim((string) ($_POST['code'] ?? ''));
            $user = ms_line($_POST['user'] ?? '', 60);
            $p1 = (string) ($_POST['p1'] ?? '');
            $p2 = (string) ($_POST['p2'] ?? '');
            if (SETUP_CODE === '' || !hash_equals(SETUP_CODE, $code)) {
                throttle_hit($tkey);
                flash('err', 'That setup code is not correct.');
                go();
            }
            if (mb_strlen($user) < 3) {
                flash('err', 'Choose a username with at least 3 characters.');
                go();
            }
            if (mb_strlen($p1) < 10) {
                flash('err', 'Choose a password with at least 10 characters.');
                go();
            }
            if ($p1 !== $p2) {
                flash('err', 'The two passwords do not match.');
                go();
            }
            $rec = ['id' => bin2hex(random_bytes(8)), 'user' => $user, 'hash' => password_hash($p1, PASSWORD_DEFAULT), 'created' => time()];
            if (!ms_write('admin', $rec)) {
                flash('err', 'Could not save your account: the "data" folder is not writable. Check its permissions in cPanel.');
                go();
            }
            throttle_clear($tkey);
            session_regenerate_id(true);
            $_SESSION['uid'] = $rec['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            flash('ok', 'Account created. Welcome! Start with "Site info" to enter the real details.');
            go('site');
        }
        if ($action === 'login' && $has_admin) {
            if (throttle_blocked($tkey)) {
                flash('err', 'Too many failed attempts. Please wait 15 minutes and try again.');
                go();
            }
            $user = ms_line($_POST['user'] ?? '', 60);
            $pass = (string) ($_POST['pass'] ?? '');
            $okUser = strcasecmp($user, (string) $admin['user']) === 0;
            $okPass = password_verify($pass, (string) $admin['hash']);
            if ($okUser && $okPass) {
                throttle_clear($tkey);
                session_regenerate_id(true);
                $_SESSION['uid'] = $admin['id'];
                $_SESSION['csrf'] = bin2hex(random_bytes(16));
                go();
            }
            throttle_hit($tkey);
            flash('err', 'Wrong username or password.');
            go();
        }
        go();
    }

    /* ---- logged in ---- */
    switch ($action) {
        case 'logout':
            $_SESSION = [];
            session_regenerate_id(true);
            flash('ok', 'You have been logged out.');
            go();

        case 'site_save':
            $a = ms_line($_POST['brand_a'] ?? '', 60);
            $b = ms_line($_POST['brand_b'] ?? '', 60);
            $errs = [];
            if ($a === '') {
                $errs[] = 'The firm name cannot be empty.';
            }
            $email = ms_email($_POST['email'] ?? '');
            if ($email === '') {
                $errs[] = 'Please enter a valid public email address.';
            }
            $notify_raw = ms_line($_POST['notify_email'] ?? '', 160);
            $notify = ms_email($notify_raw);
            if ($notify_raw !== '' && $notify === '') {
                $errs[] = 'The address for contact messages is not a valid email.';
            }
            $li_raw = ms_line($_POST['linkedin'] ?? '', 300);
            $tg_raw = ms_line($_POST['telegram'] ?? '', 300);
            $li = ms_url($li_raw);
            $tg = ms_url($tg_raw, 'telegram');
            if ($li_raw !== '' && $li === '') {
                $errs[] = 'The LinkedIn link is not valid. Paste the full address from your browser.';
            }
            if ($tg_raw !== '' && $tg === '') {
                $errs[] = 'The Telegram link is not valid. Use https://t.me/name or @name.';
            }
            if ($errs) {
                foreach ($errs as $e) {
                    flash('err', $e);
                }
                go('site');
            }
            $contact = [
                'address1'     => ms_line($_POST['address1'] ?? '', 120),
                'address2'     => ms_line($_POST['address2'] ?? '', 120),
                'phone'        => ms_line($_POST['phone'] ?? '', 40),
                'email'        => $email,
                'notify_email' => $notify,
                'hours_es'     => ms_line($_POST['hours_es'] ?? '', 140),
                'hours_en'     => ms_line($_POST['hours_en'] ?? '', 140),
            ];
            $ok = ms_update('settings', function ($st) use ($a, $b, $contact, $li, $tg) {
                $st['brand'] = ['a' => $a, 'b' => $b];
                $st['contact'] = $contact;
                $st['social'] = ['linkedin' => $li, 'telegram' => $tg];
                return $st;
            });
            $ok ? flash('ok', 'Site info saved. It is live now.') : flash('err', 'Could not save: the "data" folder is not writable.');
            go('site');

        case 'team_save':
            $S = ms_settings(true);
            $names = (array) ($_POST['name'] ?? []);
            $rows = [];
            $errs = [];
            $keep = [];
            foreach ($names as $i => $nm) {
                $i = (int) $i;
                $name = ms_line($nm, 80);
                $old = (string) (((array) ($_POST['photo_old'] ?? []))[$i] ?? '');
                $old = photo_ok($old) ? $old : '';
                $remove = !empty(((array) ($_POST['remove'] ?? []))[$i]);
                $up = $_FILES['photo'] ?? null;
                $has_up = $up && isset($up['error'][$i]) && $up['error'][$i] === UPLOAD_ERR_OK && is_uploaded_file($up['tmp_name'][$i]);
                if ($up && isset($up['error'][$i]) && !in_array($up['error'][$i], [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
                    $errs[] = 'A photo could not be uploaded (it may be too large for the server).';
                }
                if ($remove || ($name === '' && !$has_up)) {
                    if ($old !== '') {
                        delete_photo($old);
                    }
                    continue;
                }
                if ($name === '') {
                    $errs[] = 'Please give every person a name.';
                    if ($old !== '') {
                        $keep[] = $old;
                    }
                    continue;
                }
                $photo = $old;
                if ($has_up) {
                    list($newf, $perr) = save_photo($up['tmp_name'][$i], (int) $up['size'][$i]);
                    if ($newf) {
                        if ($old !== '') {
                            delete_photo($old);
                        }
                        $photo = $newf;
                    } else {
                        $errs[] = $name . ': ' . $perr;
                    }
                } elseif (!empty(((array) ($_POST['remove_photo'] ?? []))[$i])) {
                    if ($old !== '') {
                        delete_photo($old);
                    }
                    $photo = '';
                }
                $rows[] = [
                    'order'   => (int) (((array) ($_POST['order'] ?? []))[$i] ?? 99),
                    'idx'     => $i,
                    'name'    => $name,
                    'role_es' => ms_line(((array) ($_POST['role_es'] ?? []))[$i] ?? '', 80),
                    'role_en' => ms_line(((array) ($_POST['role_en'] ?? []))[$i] ?? '', 80),
                    'photo'   => $photo,
                ];
            }
            if (count($rows) > 8) {
                $errs[] = 'The site shows up to 8 people. Remove someone first.';
            }
            if ($errs) {
                foreach (array_unique($errs) as $e) {
                    flash('err', $e);
                }
                if (count($rows) > 8) {
                    go('team');
                }
            }
            usort($rows, function ($x, $y) {
                return [$x['order'], $x['idx']] <=> [$y['order'], $y['idx']];
            });
            $team = [];
            foreach ($rows as $r) {
                $team[] = ['name' => $r['name'], 'role_es' => $r['role_es'], 'role_en' => $r['role_en'], 'photo' => $r['photo']];
            }
            $ok = ms_update('settings', function ($st) use ($team) {
                $st['team'] = $team;
                return $st;
            });
            if ($ok) {
                flash('ok', 'Team saved.' . ($team ? '' : ' With nobody listed, the Team section is hidden on the site.'));
            } else {
                flash('err', 'Could not save: the "data" folder is not writable.');
            }
            go('team');

        case 'legal_save':
            $legal = [];
            $defl = ms_legal_defaults();
            $in = (array) ($_POST['l'] ?? []);
            foreach (array_keys(ms_legal_pages()) as $k) {
                $row = (array) ($in[$k] ?? []);
                $e = ['show' => !empty($row['show'])];
                foreach (['es', 'en'] as $l) {
                    $v = ms_block($row[$l] ?? '', 40000);
                    $e[$l] = ($v !== '' && $v !== trim((string) ($defl[$k][$l] ?? ''))) ? $v : '';
                }
                $legal[$k] = $e;
            }
            $ok = ms_update('settings', function ($st) use ($legal) {
                $st['legal'] = $legal;
                return $st;
            });
            $ok ? flash('ok', 'Legal pages saved.') : flash('err', 'Could not save: the "data" folder is not writable.');
            go('legal');

        case 'texts_save':
            $in = (array) ($_POST['t'] ?? []);
            $new = ['es' => [], 'en' => []];
            foreach (text_groups($def) as $keys) {
                foreach ($keys as $k) {
                    foreach (['es', 'en'] as $l) {
                        $v = ms_block(((array) ($in[$l] ?? []))[$k] ?? '', 3000);
                        if ($v !== '' && $v !== ($def[$l][$k] ?? '')) {
                            $new[$l][$k] = $v;
                        }
                    }
                }
            }
            $ok = ms_update('settings', function ($st) use ($new) {
                $st['texts'] = $new;
                return $st;
            });
            $ok ? flash('ok', 'Page texts saved. Fields left empty use the original wording.') : flash('err', 'Could not save: the "data" folder is not writable.');
            go('texts');

        case 'password':
            $cur = (string) ($_POST['cur'] ?? '');
            $p1 = (string) ($_POST['p1'] ?? '');
            $p2 = (string) ($_POST['p2'] ?? '');
            $newuser = ms_line($_POST['user'] ?? '', 60);
            if (!password_verify($cur, (string) $admin['hash'])) {
                flash('err', 'Your current password is not correct.');
                go('account');
            }
            if ($newuser !== '' && mb_strlen($newuser) < 3) {
                flash('err', 'The username needs at least 3 characters.');
                go('account');
            }
            if ($p1 !== '' && mb_strlen($p1) < 10) {
                flash('err', 'The new password needs at least 10 characters.');
                go('account');
            }
            if ($p1 !== $p2) {
                flash('err', 'The two new passwords do not match.');
                go('account');
            }
            if ($p1 !== '') {
                $admin['hash'] = password_hash($p1, PASSWORD_DEFAULT);
            }
            if ($newuser !== '') {
                $admin['user'] = $newuser;
            }
            ms_write('admin', $admin);
            session_regenerate_id(true);
            flash('ok', 'Account updated.');
            go('account');

        case 'msg_read':
        case 'msg_unread':
        case 'msg_delete':
        case 'msg_readall':
            $id = (string) ($_POST['id'] ?? '');
            ms_update('messages', function ($list) use ($action, $id) {
                $out = [];
                foreach ($list as $m) {
                    if ($action === 'msg_readall') {
                        $m['read'] = true;
                    } elseif (($m['id'] ?? '') === $id) {
                        if ($action === 'msg_delete') {
                            continue;
                        }
                        $m['read'] = ($action === 'msg_read');
                    }
                    $out[] = $m;
                }
                return $out;
            }, []);
            go('messages');

        case 'test_mail':
            $S = ms_settings(true);
            $to = ms_notify_email($S);
            $sent = ms_mail($to, '[' . ms_brand($S) . '] Test email from your dashboard', "This is a test.\n\nIf you can read this, the contact form can email you at $to.\n");
            if ($sent) {
                flash('ok', "Test email handed to the mail server for $to. Check that inbox and its spam folder in a minute or two. If it never arrives, see the README (\"Email not arriving\"). Messages are always saved in the Messages tab anyway.");
            } else {
                flash('err', 'The server refused to send the test email. Messages will still be saved in the Messages tab. See the README ("Email not arriving").');
            }
            go('site');
    }
    go();
}

/* ------------------------------------------------------------------ views */

function layout_open(string $title, bool $nav = false, string $tab = '', int $unread = 0): void
{
    global $base, $self, $nonce;
    $S = ms_settings();
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#2C4238">
<title><?= h($title) ?> — Dashboard</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Crect width='40' height='40' rx='8' fill='%233D5A4C'/%3E%3Cpath d='M20 7l13 7H7z' fill='%23C29650'/%3E%3Crect x='8' y='16' width='24' height='2.5' fill='%23C29650'/%3E%3Crect x='10.5' y='20' width='4' height='10' fill='%23EFEAE0'/%3E%3Crect x='18' y='20' width='4' height='10' fill='%23EFEAE0'/%3E%3Crect x='25.5' y='20' width='4' height='10' fill='%23EFEAE0'/%3E%3Crect x='8' y='31' width='24' height='2.5' fill='%23C29650'/%3E%3C/svg%3E">
<style>
:root{--paper:#EFEAE0;--raised:#E6E0D3;--card:#F7F4EC;--ink:#23282B;--soft:#3A4045;--muted:#666A6D;--forest:#3D5A4C;--dark:#2C4238;--brass:#A9803F;--brass2:#C29650;--line:#D8D1C0;--red:#9B3B2E;--green:#2F6B45}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:var(--paper);color:var(--ink);font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;-webkit-text-size-adjust:100%;padding-bottom:env(safe-area-inset-bottom)}
a{color:var(--forest)}
h1,h2,h3{font-family:Georgia,"Times New Roman",serif;font-weight:500;line-height:1.25}
h2{font-size:22px;margin-bottom:6px}h3{font-size:17px;margin-bottom:10px}
.wrap{max-width:980px;margin:0 auto;padding:0 max(16px,env(safe-area-inset-left)) 0 max(16px,env(safe-area-inset-right))}
.top{background:var(--dark);color:var(--paper);padding-top:env(safe-area-inset-top)}
.top .wrap{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:56px}
.top b{font-family:Georgia,serif;font-size:18px;font-weight:600}
.top a,.top button.link{color:var(--paper);font-size:13.5px;text-decoration:none;opacity:.9}
.top .r{display:flex;align-items:center;gap:16px}
button.link{background:none;border:none;cursor:pointer;font:inherit;padding:8px 0}
.tabs{background:var(--raised);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:5}
.tabs .wrap{display:flex;gap:2px;overflow-x:auto;-webkit-overflow-scrolling:touch}
.tabs a{white-space:nowrap;text-decoration:none;color:var(--soft);padding:14px 14px;font-weight:600;font-size:14px;border-bottom:3px solid transparent;display:inline-flex;gap:8px;align-items:center}
.tabs a.on{color:var(--dark);border-bottom-color:var(--brass)}
.badge{background:var(--brass);color:#1f1606;border-radius:10px;font-size:12px;padding:0 7px;line-height:18px}
main{padding:26px 0 60px}
.lead{color:var(--muted);margin-bottom:20px;max-width:680px}
.card{background:var(--card);border:1px solid var(--line);border-radius:8px;padding:20px;margin-bottom:18px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:700px){.grid{grid-template-columns:1fr}}
label{display:block;font-size:13px;font-weight:600;color:var(--soft);margin-bottom:5px}
.hint{font-size:12.5px;color:var(--muted);font-weight:400;margin-top:4px}
input[type=text],input[type=email],input[type=password],input[type=url],input[type=number],textarea{width:100%;padding:11px 12px;min-height:44px;border:1px solid #CFC7B4;border-radius:5px;background:#fff;font:inherit;color:var(--ink)}
textarea{resize:vertical;min-height:70px}
input:focus,textarea:focus,button:focus-visible,summary:focus-visible,a:focus-visible{outline:2px solid var(--brass);outline-offset:2px}
.field{margin-bottom:14px}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 22px;border-radius:5px;border:1px solid transparent;font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
.btn.pri{background:var(--forest);color:var(--paper)}.btn.pri:hover{background:var(--dark)}
.btn.sec{background:transparent;border-color:var(--forest);color:var(--forest)}
.btn.dng{background:transparent;border-color:var(--red);color:var(--red)}
.btn.sm{min-height:36px;padding:0 14px;font-size:13.5px}
.bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:6px}
.flash{padding:12px 14px;border-radius:6px;margin-bottom:16px;font-size:14px;border:1px solid}
.flash.ok{background:#E4F0E7;border-color:#B5D4BE;color:#1F4D31}
.flash.err{background:#F6E3DF;border-color:#E2B4AC;color:#7E2C21}
details.msg{background:var(--card);border:1px solid var(--line);border-radius:8px;margin-bottom:10px}
details.msg>summary{list-style:none;cursor:pointer;padding:14px 16px;display:grid;grid-template-columns:14px 1fr auto;gap:10px;align-items:center}
details.msg>summary::-webkit-details-marker{display:none}
.dot{width:10px;height:10px;border-radius:50%;background:transparent;border:1px solid var(--line)}
.msg.unread .dot{background:var(--brass);border-color:var(--brass)}
.msg.unread .who{font-weight:700}
.who small{display:block;color:var(--muted);font-weight:400;font-size:13px}
.when{font-size:12.5px;color:var(--muted);text-align:right}
.tag{display:inline-block;font-size:11.5px;border-radius:4px;padding:1px 7px;margin-left:6px;background:#F6E3DF;color:#7E2C21;font-weight:600}
.msg .body{padding:2px 16px 16px;border-top:1px solid var(--line)}
.msg .body p{white-space:pre-wrap;margin:14px 0;overflow-wrap:anywhere}
.meta{font-size:13px;color:var(--muted)}
.person{display:grid;grid-template-columns:110px 1fr;gap:18px}
@media(max-width:700px){.person{grid-template-columns:1fr}}
.ph{width:110px;aspect-ratio:3/4;background:var(--forest);border-radius:4px;overflow:hidden;display:flex;align-items:center;justify-content:center;color:rgba(239,234,224,.5);font:600 34px Georgia,serif}
.ph img{width:100%;height:100%;object-fit:cover;display:block}
.chk{display:flex;align-items:center;gap:8px;font-size:13.5px;font-weight:400;margin:6px 0}
.chk input{width:18px;height:18px;accent-color:var(--forest)}
details.grp{background:var(--card);border:1px solid var(--line);border-radius:8px;margin-bottom:10px}
details.grp>summary{cursor:pointer;padding:14px 16px;font-weight:600}
details.grp .in{padding:4px 16px 14px;border-top:1px solid var(--line)}
.trow{margin-top:14px}
.trow .lab{font-size:13px;font-weight:700;margin-bottom:6px;color:var(--soft)}
.sticky{position:sticky;bottom:0;background:linear-gradient(to top,var(--paper) 70%,transparent);padding:14px 0 calc(14px + env(safe-area-inset-bottom));margin-top:10px}
.center{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.center .card{width:100%;max-width:420px;padding:28px}
.empty{color:var(--muted);padding:30px 0;text-align:center}
code{background:#fff;border:1px solid var(--line);border-radius:4px;padding:1px 6px;font-size:13px}
</style>
</head>
<body>
<?php if ($nav) : ?>
<div class="top"><div class="wrap">
  <b><?= h(ms_brand($S)) ?> — Dashboard</b>
  <div class="r">
    <a href="<?= h(dirname(rtrim($base, '/')) === '/' || dirname(rtrim($base, '/')) === '\\' ? '/' : dirname(rtrim($base, '/')) . '/') ?>" target="_blank" rel="noopener">View website</a>
    <form method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="link" type="submit">Log out</button></form>
  </div>
</div></div>
<nav class="tabs"><div class="wrap">
  <?php
    $tabs = ['messages' => 'Messages', 'site' => 'Site info', 'team' => 'Team', 'texts' => 'Page texts', 'legal' => 'Legal pages', 'account' => 'Account'];
    foreach ($tabs as $k => $label) {
        echo '<a href="' . h($self) . '?tab=' . $k . '"' . ($k === $tab ? ' class="on" aria-current="page"' : '') . '>' . h($label);
        if ($k === 'messages' && $unread > 0) {
            echo ' <span class="badge">' . (int) $unread . '</span>';
        }
        echo '</a>';
    }
  ?>
</div></nav>
<?php endif;
}

function layout_close(): void
{
    global $nonce; ?>
<script nonce="<?= h($nonce) ?>">
document.addEventListener('submit', function (e) {
  var m = e.target.getAttribute && e.target.getAttribute('data-confirm');
  if (m && !confirm(m)) { e.preventDefault(); }
});
</script>
</body>
</html>
<?php
}

function show_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $f) {
            echo '<div class="flash ' . ($f[0] === 'ok' ? 'ok' : 'err') . '" role="status">' . h($f[1]) . '</div>';
        }
        unset($_SESSION['flash']);
    }
}

/* ---------- logged out: setup or login ---------- */

if (!$logged) {
    layout_open($has_admin ? 'Log in' : 'First-time setup');
    echo '<div class="center"><div class="card">';
    echo '<h2>' . ($has_admin ? 'Log in' : 'First-time setup') . '</h2>';
    show_flash();
    if ($has_admin) {
        ?>
        <p class="lead" style="margin-bottom:16px">Sign in to manage the website.</p>
        <form method="post" action="<?= h($self) ?>" autocomplete="on">
          <?= csrf_field() ?><input type="hidden" name="action" value="login">
          <div class="field"><label for="u">Username</label><input id="u" name="user" type="text" autocomplete="username" required autofocus></div>
          <div class="field"><label for="p">Password</label><input id="p" name="pass" type="password" autocomplete="current-password" required></div>
          <button class="btn pri" type="submit" style="width:100%">Log in</button>
        </form>
        <?php
    } elseif (SETUP_CODE === '') {
        echo '<p class="lead">Setup is not available: the file <code>inc/setup.php</code> is missing its setup code. Re-upload it from the original package.</p>';
    } else {
        ?>
        <p class="lead" style="margin-bottom:16px">Create the administrator account. You need the <b>setup code</b> from the README (it is also inside <code>inc/setup.php</code> on your hosting).</p>
        <form method="post" action="<?= h($self) ?>" autocomplete="off">
          <?= csrf_field() ?><input type="hidden" name="action" value="setup">
          <div class="field"><label for="c">Setup code</label><input id="c" name="code" type="text" required autofocus></div>
          <div class="field"><label for="u">Choose a username</label><input id="u" name="user" type="text" autocomplete="username" required></div>
          <div class="field"><label for="p1">Choose a password <span class="hint">(at least 10 characters)</span></label><input id="p1" name="p1" type="password" autocomplete="new-password" minlength="10" required></div>
          <div class="field"><label for="p2">Repeat the password</label><input id="p2" name="p2" type="password" autocomplete="new-password" minlength="10" required></div>
          <button class="btn pri" type="submit" style="width:100%">Create account</button>
        </form>
        <?php
    }
    echo '</div></div>';
    layout_close();
    exit;
}

/* ---------- logged in ---------- */

$S = ms_settings(true);
$messages = ms_read('messages', []);
$unread = 0;
foreach ($messages as $m) {
    if (empty($m['read'])) {
        $unread++;
    }
}
$tab = (string) ($_GET['tab'] ?? 'messages');
if (!in_array($tab, ['messages', 'site', 'team', 'texts', 'legal', 'account'], true)) {
    $tab = 'messages';
}
$titles = ['messages' => 'Messages', 'site' => 'Site info', 'team' => 'Team', 'texts' => 'Page texts', 'legal' => 'Legal pages', 'account' => 'Account'];
layout_open($titles[$tab], true, $tab, $unread);
echo '<main><div class="wrap">';
show_flash();

function act_form(string $action, array $extra, string $label, string $cls = 'sec sm', string $confirm = ''): string
{
    global $self;
    $s = '<form method="post" action="' . h($self) . '"' . ($confirm !== '' ? ' data-confirm="' . h($confirm) . '"' : '') . ' style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="' . h($action) . '">';
    foreach ($extra as $k => $v) {
        $s .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    }
    return $s . '<button class="btn ' . h($cls) . '" type="submit">' . h($label) . '</button></form>';
}

/* ---- Messages ---- */
if ($tab === 'messages') {
    echo '<h2>Messages</h2><p class="lead">Everything sent through the contact form on your website. Times are Costa Rica time. Messages are also emailed to you.</p>';
    if (!$messages) {
        echo '<div class="card empty">No messages yet. When someone uses the contact form, it will appear here.</div>';
    } else {
        echo '<div class="bar" style="margin:0 0 14px"><span class="meta">' . count($messages) . ' message' . (count($messages) === 1 ? '' : 's') . ' · ' . (int) $unread . ' unread</span>';
        if ($unread) {
            echo act_form('msg_readall', [], 'Mark all as read');
        }
        echo '</div>';
        foreach ($messages as $m) {
            $isu = empty($m['read']);
            $id = (string) ($m['id'] ?? '');
            echo '<details class="msg' . ($isu ? ' unread' : '') . '"><summary><span class="dot"></span><span class="who">' . h($m['name'] ?? '') . '<small>' . h($m['topic'] ?? '') . '</small></span><span class="when">' . h(date('j M Y, H:i', (int) ($m['t'] ?? 0))) . (empty($m['mailed']) ? '<br><span class="tag">email not sent</span>' : '') . '</span></summary>';
            echo '<div class="body"><div class="meta"><a href="mailto:' . h($m['email'] ?? '') . '">' . h($m['email'] ?? '') . '</a> · wrote in ' . (($m['lang'] ?? 'es') === 'en' ? 'English' : 'Spanish') . '</div>';
            echo '<p>' . h($m['message'] ?? '') . '</p><div class="bar">';
            echo '<a class="btn pri sm" href="mailto:' . h($m['email'] ?? '') . '?subject=' . h(rawurlencode('Re: ' . ($m['topic'] ?? ''))) . '">Reply by email</a>';
            echo act_form($isu ? 'msg_read' : 'msg_unread', ['id' => $id], $isu ? 'Mark as read' : 'Mark as unread');
            echo act_form('msg_delete', ['id' => $id], 'Delete', 'dng sm', 'Delete this message permanently?');
            echo '</div></div></details>';
        }
    }
}

/* ---- Site info ---- */
if ($tab === 'site') {
    $c = $S['contact'];
    ?>
    <h2>Site info</h2>
    <p class="lead">The firm's name and contact details. Changes go live on the website as soon as you save.</p>
    <form method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="site_save">
      <div class="card"><h3>Firm name</h3>
        <div class="grid">
          <div class="field"><label for="ba">First name in the logo</label><input id="ba" name="brand_a" type="text" value="<?= h($S['brand']['a']) ?>" maxlength="60" required></div>
          <div class="field"><label for="bb">Second name <span class="hint">(optional)</span></label><input id="bb" name="brand_b" type="text" value="<?= h($S['brand']['b']) ?>" maxlength="60"></div>
        </div>
        <p class="hint">Shown in the logo as "First &amp; Second" and wherever the firm's name appears in the page texts.</p>
      </div>
      <div class="card"><h3>Office</h3>
        <div class="field"><label for="a1">Address, line 1</label><input id="a1" name="address1" type="text" value="<?= h($c['address1']) ?>" maxlength="120"></div>
        <div class="field"><label for="a2">Address, line 2</label><input id="a2" name="address2" type="text" value="<?= h($c['address2']) ?>" maxlength="120"></div>
        <div class="grid">
          <div class="field"><label for="ph">Phone</label><input id="ph" name="phone" type="text" value="<?= h($c['phone']) ?>" maxlength="40" inputmode="tel"><div class="hint">Tapping it on a phone starts a call.</div></div>
          <div class="field"><label for="em">Public email</label><input id="em" name="email" type="email" value="<?= h($c['email']) ?>" maxlength="160" required><div class="hint">Shown on the website.</div></div>
        </div>
        <div class="grid">
          <div class="field"><label for="hs">Office hours — Spanish</label><input id="hs" name="hours_es" type="text" value="<?= h($c['hours_es']) ?>" maxlength="140"></div>
          <div class="field"><label for="he">Office hours — English</label><input id="he" name="hours_en" type="text" value="<?= h($c['hours_en']) ?>" maxlength="140"></div>
        </div>
      </div>
      <div class="card"><h3>Contact form email</h3>
        <div class="field"><label for="ne">Send contact messages to</label><input id="ne" name="notify_email" type="email" value="<?= h($c['notify_email']) ?>" maxlength="160" placeholder="<?= h('info@' . ms_host()) ?>">
          <div class="hint">Leave empty to use <b><?= h('info@' . ms_host()) ?></b> (that mailbox must exist in cPanel). Or type any address, for example a Gmail one.</div></div>
      </div>
      <div class="card"><h3>Social links</h3>
        <div class="grid">
          <div class="field"><label for="li">LinkedIn page</label><input id="li" name="linkedin" type="text" value="<?= h($S['social']['linkedin']) ?>" placeholder="https://www.linkedin.com/company/..."></div>
          <div class="field"><label for="tg">Telegram</label><input id="tg" name="telegram" type="text" value="<?= h($S['social']['telegram']) ?>" placeholder="https://t.me/yourname or @yourname"></div>
        </div>
        <p class="hint">A button only appears on the website when its link is filled in.</p>
      </div>
      <div class="sticky"><div class="bar"><button class="btn pri" type="submit">Save changes</button></div></div>
    </form>
    <div class="card"><h3>Check that email works</h3>
      <p class="hint" style="margin-bottom:12px">Sends a test email to <b><?= h(ms_notify_email($S)) ?></b>. Check the inbox <i>and</i> the spam folder.</p>
      <?= act_form('test_mail', [], 'Send a test email', 'sec') ?>
    </div>
    <?php
}

/* ---- Team ---- */
if ($tab === 'team') {
    $team = $S['team'];
    $rows = $team;
    if (count($rows) < 8) {
        $rows[] = ['name' => '', 'role_es' => '', 'role_en' => '', 'photo' => '', '_new' => true];
    }
    ?>
    <h2>Team</h2>
    <p class="lead">The people shown on the website, in the order of the "Position" numbers. Photos may be JPG, PNG or WebP (max 6 MB each); they are resized automatically. To remove someone, tick "Remove this person". Up to 8 people.</p>
    <form method="post" action="<?= h($self) ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="team_save">
    <?php foreach ($rows as $i => $m) :
        $isnew = !empty($m['_new']);
        $has_photo = $m['photo'] !== '' && photo_ok($m['photo']) && is_file(MS_PHOTOS . '/' . $m['photo']); ?>
      <div class="card"><div class="person">
        <div>
          <div class="ph"><?php if ($has_photo) : ?><img src="<?= h(dirname(rtrim($base, '/')) === '/' ? '' : dirname(rtrim($base, '/'))) ?>/uploads/team/<?= h($m['photo']) ?>" alt=""><?php else : ?><?= h(mb_strtoupper(mb_substr($m['name'] !== '' ? $m['name'] : '+', 0, 1, 'UTF-8'), 'UTF-8')) ?><?php endif; ?></div>
        </div>
        <div>
          <?php if ($isnew) : ?><h3>Add a new person</h3><?php endif; ?>
          <input type="hidden" name="photo_old[<?= (int) $i ?>]" value="<?= h($has_photo ? $m['photo'] : '') ?>">
          <div class="grid">
            <div class="field"><label for="n<?= (int) $i ?>">Full name</label><input id="n<?= (int) $i ?>" name="name[<?= (int) $i ?>]" type="text" value="<?= h($m['name']) ?>" maxlength="80"></div>
            <div class="field"><label for="o<?= (int) $i ?>">Position in the list</label><input id="o<?= (int) $i ?>" name="order[<?= (int) $i ?>]" type="number" min="1" max="99" value="<?= (int) $i + 1 ?>"></div>
          </div>
          <div class="grid">
            <div class="field"><label for="rs<?= (int) $i ?>">Title — Spanish</label><input id="rs<?= (int) $i ?>" name="role_es[<?= (int) $i ?>]" type="text" value="<?= h($m['role_es']) ?>" maxlength="80" placeholder="Socio Fundador"></div>
            <div class="field"><label for="re<?= (int) $i ?>">Title — English</label><input id="re<?= (int) $i ?>" name="role_en[<?= (int) $i ?>]" type="text" value="<?= h($m['role_en']) ?>" maxlength="80" placeholder="Founding Partner"></div>
          </div>
          <div class="field"><label for="f<?= (int) $i ?>">Photo <?= $has_photo ? '(choose a file to replace it)' : '(optional)' ?></label><input id="f<?= (int) $i ?>" name="photo[<?= (int) $i ?>]" type="file" accept="image/jpeg,image/png,image/webp"></div>
          <?php if ($has_photo) : ?><label class="chk"><input type="checkbox" name="remove_photo[<?= (int) $i ?>]" value="1"> Remove the photo</label><?php endif; ?>
          <?php if (!$isnew) : ?><label class="chk" style="color:var(--red)"><input type="checkbox" name="remove[<?= (int) $i ?>]" value="1"> Remove this person from the website</label><?php endif; ?>
        </div>
      </div></div>
    <?php endforeach; ?>
      <div class="sticky"><div class="bar"><button class="btn pri" type="submit">Save team</button></div></div>
    </form>
    <?php
}

/* ---- Page texts ---- */
if ($tab === 'texts') {
    $eff = ms_texts_for_site($S);
    ?>
    <h2>Page texts</h2>
    <p class="lead">Every piece of wording on the website, in Spanish and English. Open a section, change what you want, and save. The firm's name, address, phone, email, hours and team are edited in their own tabs. Write <code>{brand}</code> to insert the firm name automatically. Clear a box to go back to the original wording.</p>
    <form method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="texts_save">
    <?php foreach (text_groups($def) as $gname => $keys) :
        $over = 0;
        foreach ($keys as $k) {
            foreach (['es', 'en'] as $l) {
                if (isset($S['texts'][$l][$k]) && trim($S['texts'][$l][$k]) !== '') {
                    $over++;
                }
            }
        } ?>
      <details class="grp"><summary><?= h($gname) ?><?= $over ? ' <span class="badge">' . (int) $over . ' edited</span>' : '' ?></summary><div class="in">
      <?php foreach ($keys as $k) : ?>
        <div class="trow"><div class="lab"><?= h(key_label($k)) ?></div>
          <div class="grid">
          <?php foreach (['es' => 'Spanish', 'en' => 'English'] as $l => $ln) :
              $val = isset($S['texts'][$l][$k]) && trim($S['texts'][$l][$k]) !== '' ? $S['texts'][$l][$k] : ($def[$l][$k] ?? '');
              $rows = max(2, min(9, (int) ceil(mb_strlen($val) / 55))); ?>
            <div><label for="t_<?= h($l . '_' . $k) ?>" class="hint" style="margin:0 0 4px"><?= h($ln) ?></label>
              <textarea id="t_<?= h($l . '_' . $k) ?>" name="t[<?= h($l) ?>][<?= h($k) ?>]" rows="<?= (int) $rows ?>" maxlength="3000"><?= h($val) ?></textarea></div>
          <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div></details>
    <?php endforeach; ?>
      <div class="sticky"><div class="bar"><button class="btn pri" type="submit">Save page texts</button></div></div>
    </form>
    <?php
}

/* ---- Legal pages ---- */
if ($tab === 'legal') {
    $eff = ms_legal_effective($S);
    $defl = ms_legal_defaults();
    $siteroot = (ms_is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    ?>
    <h2>Legal pages</h2>
    <p class="lead">Privacy Policy, Terms &amp; Conditions and Legal Notice, in Spanish and English. Each page appears in the website footer and is shown in the visitor's language. Untick "Show this page" to hide a page and its footer link.</p>
    <div class="card" style="border-color:#C29650"><b>Starter drafts.</b> The texts below are generic drafts, not legal advice. Have a Costa Rican attorney review and complete them (look for the parts in [square brackets]) before relying on them.</div>
    <div class="card"><h3>Format</h3>
      <p class="hint" style="margin:0">Start a line with <code>## </code> for a heading and with <code>- </code> for a bullet point. Leave a blank line between paragraphs. You can write <code>{brand}</code>, <code>{email}</code>, <code>{address}</code>, <code>{phone}</code> and <code>{site}</code>: they are filled in from Site info automatically. Clear a box to go back to the starter draft.</p></div>
    <form method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="legal_save">
    <?php foreach (ms_legal_pages() as $k => $pg) :
        $edited = ($S['legal'][$k]['es'] ?? '') !== '' || ($S['legal'][$k]['en'] ?? '') !== ''; ?>
      <details class="grp"<?= $edited ? '' : '' ?>><summary><?= h($pg['label']) ?> <span class="hint" style="font-weight:400">— <?= h($siteroot) ?>/<?= h($pg['slug']) ?></span><?= $edited ? ' <span class="badge">edited</span>' : '' ?><?= $eff[$k]['show'] ? '' : ' <span class="badge">hidden</span>' ?></summary><div class="in">
        <div class="trow"><label style="display:flex;gap:10px;align-items:center;font-weight:600"><input type="checkbox" name="l[<?= h($k) ?>][show]" value="1"<?= $eff[$k]['show'] ? ' checked' : '' ?> style="width:20px;height:20px"> Show this page on the website</label></div>
        <?php foreach (['es' => 'Spanish', 'en' => 'English'] as $l => $ln) : ?>
          <div class="trow"><label class="lab" for="l_<?= h($k . '_' . $l) ?>"><?= h($ln) ?></label>
            <textarea id="l_<?= h($k . '_' . $l) ?>" name="l[<?= h($k) ?>][<?= h($l) ?>]" rows="16" maxlength="40000" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;line-height:1.5"><?= h($eff[$k][$l]) ?></textarea></div>
        <?php endforeach; ?>
      </div></details>
    <?php endforeach; ?>
      <div class="sticky"><div class="bar"><button class="btn pri" type="submit">Save legal pages</button></div></div>
    </form>
    <?php
}

/* ---- Account ---- */
if ($tab === 'account') {
    ?>
    <h2>Account</h2>
    <p class="lead">Signed in as <b><?= h($admin['user']) ?></b>. You can change the username and password here. Everything is saved in the <code>data</code> folder on your hosting: keep it safe, and never delete it.</p>
    <form method="post" action="<?= h($self) ?>" class="card" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <h3>Change username or password</h3>
      <div class="field"><label for="cu">Current password <span class="hint">(required)</span></label><input id="cu" name="cur" type="password" autocomplete="current-password" required></div>
      <div class="field"><label for="nu">New username <span class="hint">(leave empty to keep it)</span></label><input id="nu" name="user" type="text" autocomplete="username"></div>
      <div class="grid">
        <div class="field"><label for="n1">New password <span class="hint">(at least 10 characters; leave empty to keep it)</span></label><input id="n1" name="p1" type="password" autocomplete="new-password"></div>
        <div class="field"><label for="n2">Repeat the new password</label><input id="n2" name="p2" type="password" autocomplete="new-password"></div>
      </div>
      <button class="btn pri" type="submit">Save account</button>
    </form>
    <div class="card"><h3>Forgot your password?</h3>
      <p class="hint">In cPanel > File Manager, open the <code>data</code> folder and delete <code>admin.php</code>. Then visit this page again and create a new account with the setup code from <code>inc/setup.php</code>. Your site info, team and messages are not touched.</p>
    </div>
    <?php
}

echo '</div></main>';
layout_close();
