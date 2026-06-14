<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function generate_csrf_token() {
    if (empty($_SESSION['csrf_tokens'])) $_SESSION['csrf_tokens'] = [];
    $token = bin2hex(random_bytes(16));
    // keep small cache of tokens
    $_SESSION['csrf_tokens'][$token] = time();
    // prune old
    foreach ($_SESSION['csrf_tokens'] as $t => $ts) {
        if ($ts + 3600 < time()) unset($_SESSION['csrf_tokens'][$t]);
    }
    return $token;
}

function csrf_input_field() {
    $t = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t) . '">';
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_tokens'])) return false;
    if (empty($token)) return false;
    if (!isset($_SESSION['csrf_tokens'][$token])) return false;
    // keep token valid until expiry (not single-use) to support AJAX uploads
    return true;
}

?>
