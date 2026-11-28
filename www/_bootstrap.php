<?php
if (session_status() === PHP_SESSION_NONE) {
    // Must be set before session_start(). The remember_token cookie already
    // carries these flags; PHPSESSID is the more valuable of the two, so it
    // gets them too: no JS access, HTTPS only when the request came over TLS,
    // and not sent on cross-site requests.
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header("Location: " . $path);
    exit;
}

function csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

function csrf_verify() {
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Issues a new CSRF token. Called wherever the session's identity changes
// (login, remember-me login, logout) alongside session_regenerate_id(), so a
// token handed out before login can't be replayed against the session after it.
function csrf_rotate() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// The end user's IP, for per-IP rate limiting.
//
// Behind the Caddy reverse proxy REMOTE_ADDR is the proxy's own container
// address, so every visitor would share one bucket. Caddy appends the real peer
// to X-Forwarded-For, so its LAST entry is the client. We only read that header
// when REMOTE_ADDR is a private address (i.e. the request really did come from
// a local proxy) — otherwise a client could spoof the header to get a fresh
// bucket per request.
function client_ip() {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $viaLocalProxy = $remote !== '' && !filter_var(
        $remote,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );

    if ($viaLocalProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $last  = end($parts);
        if (filter_var($last, FILTER_VALIDATE_IP)) return $last;
    }

    return $remote !== '' ? $remote : '0.0.0.0';
}

// ---------------------------------------------------------------------------
// Canonical site address
// ---------------------------------------------------------------------------
// EDIT THIS when the site moves to a new domain: the real production host goes
// first, then any extra host the site also answers on (www, dev, localhost).
//
// Every absolute URL we hand out — the password-reset link we email, the QR
// codes we print — is built from this list and NEVER from the Host header the
// client sent. A request carrying "Host: evil.com" would otherwise make us mail
// the victim a genuine reset link pointing at the attacker's site, handing them
// the token and the account.
const APP_ALLOWED_HOSTS = [
    'lapikad.com',
    'www.lapikad.com',
    'localhost:8080',
    'localhost',
    '127.0.0.1:8080',
    '127.0.0.1',
];

// The request's host when we recognise it, otherwise the canonical one.
function app_host() {
    $host = strtolower(trim($_SERVER['HTTP_HOST'] ?? ''));
    return in_array($host, APP_ALLOWED_HOSTS, true) ? $host : APP_ALLOWED_HOSTS[0];
}

function app_scheme() {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return 'https';
    if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return 'https';
    return 'http';
}

// "scheme://host", no trailing slash.
function app_base_url() {
    return app_scheme() . '://' . app_host();
}
?>
