<?php
/**
 * University Course Recommendation System (UCRS)
 * Configuration & Core Helpers
 */

// Strict error reporting for development, but friendly for end users (SEC-11)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Base Paths
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Database Credentials (SEC-08)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'ucrs');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// System Constants
define('APP_NAME', 'University Course Recommendation System');
define('APP_SHORT_NAME', 'UCRS');
define('APP_VERSION', '1.0');
define('SESSION_LIFETIME', 1800); // 30 minutes in seconds (FR-33)
define('MAX_LOGIN_ATTEMPTS', 5); // 5 attempts (FR-37)
define('LOCKOUT_DURATION_MINUTES', 15); // 15 minutes lockout (FR-37)

// Ensure session is started safely
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

/**
 * Generate application URL relative to the server document root
 */
function app_url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
        $appRoot = str_replace('\\', '/', realpath(APP_ROOT));
        if ($docRoot && str_starts_with($appRoot, $docRoot)) {
            $base = substr($appRoot, strlen($docRoot));
        } else {
            // Fallback detection from SCRIPT_NAME
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $parts = explode('/', trim($script, '/'));
            $base = !empty($parts) && $parts[0] !== 'index.php' ? '/' . $parts[0] : '';
        }
        $base = rtrim($base, '/');
    }
    $path = ltrim($path, '/');
    return ($base ? $base : '') . ($path !== '' ? '/' . $path : '');
}

/**
 * Escape HTML output for XSS prevention (SEC-03, T-24)
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token (SEC-04)
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Return hidden input with CSRF token (SEC-04)
 */
function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verify submitted CSRF token (SEC-04)
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash message helper
 */
function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$type][] = $message;
}

/**
 * Retrieve and clear flash messages
 */
function get_flashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Render flash messages as HTML alerts
 */
function render_flash_messages(): string {
    $flashes = get_flashes();
    if (empty($flashes)) {
        return '';
    }
    $html = '<div class="flash-messages-container" style="margin-bottom: 1.5rem;">';
    foreach ($flashes as $type => $messages) {
        $alertClass = ($type === 'success') ? 'alert-success' : (($type === 'danger' || $type === 'error') ? 'alert-danger' : 'alert-info');
        foreach ($messages as $msg) {
            $html .= '<div class="alert ' . e($alertClass) . '">';
            $html .= '<span class="alert-text">' . e($msg) . '</span>';
            $html .= '</div>';
        }
    }
    $html .= '</div>';
    return $html;
}
