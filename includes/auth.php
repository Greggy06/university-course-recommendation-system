<?php
/**
 * Authentication & Authorization System (FR-30 to FR-38, SEC-01 to SEC-07, FR-111)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Check 30-minute session timeout (FR-33)
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        header('Location: ' . app_url('login.php?timeout=1'));
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Check if current visitor is authenticated
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Get currently logged-in user record
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    static $currentUser = null;
    if ($currentUser === null) {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT id, full_name, email, role, education_level, is_active FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch() ?: null;
    }
    return $currentUser;
}

/**
 * Require user to be logged in (FR-36, T-10)
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to access this page.');
        header('Location: ' . app_url('login.php'));
        exit;
    }
}

/**
 * Require user to have ADMIN role (FR-35, SEC-06, T-09)
 */
function require_admin(): void {
    require_login();
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
        http_response_code(403);
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Denied - UCRS</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
                .card { background: white; border: 1px solid #fee2e2; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); max-width: 480px; width: 100%; padding: 2.5rem; text-align: center; }
                h1 { color: #dc2626; font-size: 1.75rem; margin-top: 0; }
                p { color: #64748b; line-height: 1.6; margin-bottom: 2rem; }
                .btn { display: inline-block; background: #2563eb; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; }
                .btn:hover { background: #1d4ed8; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>Access Denied</h1>
                <p>You do not have administrative privileges to access this page. This area is restricted to administrators only.</p>
                <a href="' . htmlspecialchars(app_url('student/dashboard.php'), ENT_QUOTES, 'UTF-8') . '" class="btn">Return to Student Dashboard</a>
            </div>
        </body>
        </html>';
        exit;
    }
}

/**
 * Require user to have STUDENT role (FR-35, SEC-06)
 */
function require_student(): void {
    require_login();
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'STUDENT') {
        // If an admin navigates to student page, redirect them to admin dashboard
        header('Location: ' . app_url('admin/dashboard.php'));
        exit;
    }
}

/**
 * Attempt user authentication (FR-30, FR-31, FR-32, FR-37, FR-38, SEC-01, SEC-05, SEC-09)
 */
function attempt_login(string $email, string $password): array {
    $pdo = get_db();
    $email = trim($email);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Prevent user enumeration: dummy verification time
        password_verify($password, '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEF1234567890');
        return [
            'success' => false,
            'message' => 'Email or password is incorrect.' // FR-32
        ];
    }

    // Check account lockout (FR-37, SEC-09)
    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
        return [
            'success' => false,
            'message' => "Too many failed attempts. Account sign-in is locked. Please try again in {$remaining} minute(s)."
        ];
    }

    // Check account active status (FR-38, T-20)
    if ((int)$user['is_active'] !== 1) {
        return [
            'success' => false,
            'message' => 'Your account has been deactivated. Please contact the administrator.'
        ];
    }

    // Verify password hash (SEC-01)
    if (!password_verify($password, $user['password_hash'])) {
        $failedAttempts = (int)$user['failed_logins'] + 1;
        $lockSql = "";
        $params = [$failedAttempts, $user['id']];

        if ($failedAttempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + (LOCKOUT_DURATION_MINUTES * 60));
            $stmtLock = $pdo->prepare("UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?");
            $stmtLock->execute([$failedAttempts, $lockUntil, $user['id']]);
            return [
                'success' => false,
                'message' => "Too many failed attempts. Sign-in has been locked for " . LOCKOUT_DURATION_MINUTES . " minutes."
            ];
        } else {
            $stmtFail = $pdo->prepare("UPDATE users SET failed_logins = ? WHERE id = ?");
            $stmtFail->execute([$failedAttempts, $user['id']]);
            return [
                'success' => false,
                'message' => 'Email or password is incorrect.' // FR-32
            ];
        }
    }

    // Login successful: reset failed attempts and unlock
    $stmtReset = $pdo->prepare("UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?");
    $stmtReset->execute([$user['id']]);

    // Regenerate session ID for session fixation protection (SEC-05)
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['education_level'] = $user['education_level'];
    $_SESSION['last_activity'] = time();

    return [
        'success' => true,
        'user' => $user
    ];
}

/**
 * Log out user and destroy session completely (FR-34, SEC-05)
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Log admin action into activity_log table (FR-111)
 */
function log_admin_action(string $action, string $details): void {
    if (empty($_SESSION['user_id'])) {
        return;
    }
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("INSERT INTO activity_log (admin_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([(int)$_SESSION['user_id'], $action, $details]);
    } catch (Exception $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
    }
}
