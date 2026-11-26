<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function secureSessionStart(array $app): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name($app['session']['name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps || $app['force_https'],
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_start();

    if (!isset($_SESSION['_created_at'])) {
        $_SESSION['_created_at'] = time();
    }

    $lifetime = (int) $app['session']['lifetime'];
    if (isset($_SESSION['_last_activity']) && (time() - (int) $_SESSION['_last_activity']) > $lifetime) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['_last_activity'] = time();
}

function csrfToken(array $app): string
{
    $ttl = (int) $app['csrf_ttl'];
    if (empty($_SESSION['_csrf_token']) || empty($_SESSION['_csrf_expires']) || time() > (int) $_SESSION['_csrf_expires']) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['_csrf_expires'] = time() + $ttl;
    }
    return $_SESSION['_csrf_token'];
}

function csrfField(array $app): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken($app)) . '">';
}

function verifyCsrf(array $app): bool
{
    $token = $_POST['_csrf'] ?? '';
    $sessionToken = $_SESSION['_csrf_token'] ?? '';
    $expires = (int) ($_SESSION['_csrf_expires'] ?? 0);
    if (!is_string($token) || !is_string($sessionToken) || $token === '' || $sessionToken === '') {
        return false;
    }
    if ($expires < time()) {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

function enforceHttps(array $app): void
{
    if (!$app['force_https']) {
        return;
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($isHttps) {
        return;
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function sendSecurityHeaders(array $app): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    if ($app['force_https']) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    header_remove('X-Powered-By');
}

function enforceCors(array $app): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = $app['allowed_origins'];
    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function clientIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function auditLog(?int $userId, string $action, string $severity = 'info', ?string $entityType = null, ?string $entityId = null, array $details = []): void
{
    try {
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent, severity) VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip, :ua, :severity)');
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip' => clientIp(),
            'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
            'severity' => $severity,
        ]);
    } catch (Throwable $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

function rateLimitExceeded(string $identifier, int $maxAttempts, int $windowSeconds): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = :id AND created_at > NOW() - (:window * INTERVAL \'1 second\')');
    $stmt->bindValue(':id', $identifier);
    $stmt->bindValue(':window', $windowSeconds, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $stmt->fetchColumn() >= $maxAttempts;
}

function recordLoginAttempt(string $identifier, bool $successful): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (identifier, ip_address, user_agent, was_successful) VALUES (:id, :ip, :ua, :ok)');
    $stmt->bindValue(':id', $identifier);
    $stmt->bindValue(':ip', clientIp());
    $stmt->bindValue(':ua', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000));
    $stmt->bindValue(':ok', $successful, PDO::PARAM_BOOL);
    $stmt->execute();
}

function sanitizeText(?string $value, int $maxLength = 255): string
{
    $value = trim((string) $value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}
