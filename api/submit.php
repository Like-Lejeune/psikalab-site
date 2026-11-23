<?php
/**
 * PsikaLab — réception des formulaires (contact, candidature, newsletter).
 *
 * - Reçoit du JSON en POST depuis le site.
 * - Valide les champs, bloque les robots (champ piège) et limite le débit par IP.
 * - Enregistre chaque envoi dans un fichier .jsonl HORS du dossier public.
 * - Envoie un email de notification à l'équipe.
 *
 * Configuration : créez le fichier psikalab-config.php À CÔTÉ de public_html
 * (pas dedans), voir README.md. Sans ce fichier, les valeurs par défaut ci-dessous
 * sont utilisées.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------
$outside = dirname(__DIR__, 2); // dossier parent de public_html
$config = [
    'to'              => 'contact@psikalab.space',      // qui reçoit les notifications
    'from'            => 'no-reply@psikalab.space',     // expéditeur (doit être sur votre domaine)
    'allowed_hosts'   => ['psikalab.space', 'www.psikalab.space', 'localhost', '127.0.0.1'],
    'storage_dir'     => $outside . '/psikalab-data',   // hors du web
    'rate_limit'      => 5,                             // envois max…
    'rate_window'     => 600,                           // …par fenêtre de 10 minutes et par IP
    'send_mail'       => true,
];
$custom = $outside . '/psikalab-config.php';
if (is_file($custom)) {
    $loaded = require $custom;
    if (is_array($loaded)) {
        $config = array_merge($config, $loaded);
    }
}

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function clean(mixed $v, int $max): string
{
    $s = is_scalar($v) ? (string) $v : '';
    $s = trim(str_replace("\0", '', $s));
    return mb_substr($s, 0, $max);
}

function one_line(string $s): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', $s) ?? '');
}

// ---------------------------------------------------------------------------
// Contrôles de base
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $host = parse_url($origin, PHP_URL_HOST) ?: '';
    if (!in_array($host, $config['allowed_hosts'], true)) {
        respond(403, ['ok' => false, 'error' => 'forbidden_origin']);
    }
}

$raw = file_get_contents('php://input', false, null, 0, 20000) ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST; // secours : envoi classique de formulaire
}

// Champ piège : un humain ne le remplit jamais. On répond "ok" sans rien faire.
if (clean($data['website'] ?? '', 200) !== '') {
    respond(200, ['ok' => true]);
}

$form = clean($data['form'] ?? '', 20);
$lang = clean($data['lang'] ?? 'fr', 2) === 'en' ? 'en' : 'fr';
if (!in_array($form, ['contact', 'join', 'newsletter'], true)) {
    respond(422, ['ok' => false, 'error' => 'unknown_form']);
}

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------
$errors = [];
$email = clean($data['email'] ?? '', 160);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'email';
}

$record = ['form' => $form, 'lang' => $lang, 'email' => $email];

if ($form === 'contact' || $form === 'join') {
    $name = one_line(clean($data['name'] ?? '', 120));
    $message = clean($data['message'] ?? '', 5000);
    if (mb_strlen($name) < 2) $errors[] = 'name';
    if (mb_strlen($message) < 10) $errors[] = 'message';
    $record['name'] = $name;
    $record['message'] = $message;
}
if ($form === 'contact') {
    $subject = clean($data['subject'] ?? 'other', 20);
    $record['subject'] = in_array($subject, ['project', 'partner', 'event', 'press', 'other'], true) ? $subject : 'other';
}
if ($form === 'join') {
    $profile = clean($data['profile'] ?? 'other', 20);
    $record['profile'] = in_array($profile, ['dev', 'ai', 'design', 'partner', 'other'], true) ? $profile : 'other';
    $link = clean($data['link'] ?? '', 300);
    if ($link !== '' && (!filter_var($link, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $link))) {
        $errors[] = 'link';
    }
    $record['link'] = $link;
}

if ($errors) {
    respond(422, ['ok' => false, 'error' => 'invalid', 'fields' => $errors]);
}

// ---------------------------------------------------------------------------
// Stockage + limitation de débit
// ---------------------------------------------------------------------------
$dir = $config['storage_dir'];
if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
    error_log('[psikalab] cannot create storage dir ' . $dir);
    respond(500, ['ok' => false, 'error' => 'storage']);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipHash = substr(hash('sha256', $ip . '|' . __FILE__), 0, 16); // IP non stockée en clair

$rateFile = $dir . '/ratelimit.json';
$fh = fopen($rateFile, 'c+');
if ($fh && flock($fh, LOCK_EX)) {
    $now = time();
    $hits = json_decode(stream_get_contents($fh) ?: '{}', true) ?: [];
    foreach ($hits as $k => $times) {
        $hits[$k] = array_values(array_filter($times, fn($t) => $t > $now - $config['rate_window']));
        if (!$hits[$k]) unset($hits[$k]);
    }
    if (count($hits[$ipHash] ?? []) >= $config['rate_limit']) {
        flock($fh, LOCK_UN);
        fclose($fh);
        respond(429, ['ok' => false, 'error' => 'too_many_requests']);
    }
    $hits[$ipHash][] = $now;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($hits));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

$record = ['date' => date('c'), 'ip' => $ipHash] + $record;
$line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
if (file_put_contents($dir . '/' . $form . '.jsonl', $line, FILE_APPEND | LOCK_EX) === false) {
    error_log('[psikalab] cannot write submission');
    respond(500, ['ok' => false, 'error' => 'storage']);
}

// ---------------------------------------------------------------------------
// Notification email (un échec d'email n'empêche pas l'enregistrement)
// ---------------------------------------------------------------------------
if ($config['send_mail']) {
    $titles = [
        'contact'    => 'Nouveau message de contact',
        'join'       => 'Nouvelle candidature',
        'newsletter' => 'Nouvelle inscription newsletter',
    ];
    $subjectLine = '[PsikaLab] ' . $titles[$form] . (isset($record['name']) ? ' — ' . one_line($record['name']) : '');
    $body = '';
    foreach ($record as $k => $v) {
        if ($k === 'ip') continue;
        $body .= ucfirst($k) . ' : ' . $v . "\n";
    }
    $headers = [
        'From: PsikaLab <' . one_line($config['from']) . '>',
        'Reply-To: ' . one_line($email),
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PsikaLab',
    ];
    $sent = @mail(
        one_line($config['to']),
        '=?UTF-8?B?' . base64_encode($subjectLine) . '?=',
        $body,
        implode("\r\n", $headers)
    );
    if (!$sent) {
        error_log('[psikalab] mail() failed for ' . $form);
    }
}

respond(200, ['ok' => true]);
