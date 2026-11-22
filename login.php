<?php
/**
 * Role-Based Login Page (FR-30 to FR-38, SEC-01 to SEC-05, SEC-09)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to respective dashboard
if (is_logged_in()) {
    header('Location: ' . app_url($_SESSION['role'] === 'ADMIN' ? 'admin/dashboard.php' : 'student/dashboard.php'));
    exit;
}

$pageTitle = 'Sign In';
$email = '';
$errorMessage = '';

// Handle status query parameters
if (isset($_GET['timeout'])) {
    set_flash('info', 'Your session timed out after 30 minutes of inactivity. Please log in again.');
} elseif (isset($_GET['logged_out'])) {
    set_flash('success', 'You have been successfully logged out.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check (SEC-04)
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Security verification failed. Please try submitting again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errorMessage = 'Email or password is incorrect.'; // FR-32
        } else {
            $result = attempt_login($email, $password);
            if ($result['success']) {
                $role = $result['user']['role'];
                // FR-31: Role-based redirect
                if ($role === 'ADMIN') {
                    header('Location: ' . app_url('admin/dashboard.php'));
                } else {
                    header('Location: ' . app_url('student/dashboard.php'));
                }
                exit;
            } else {
                $errorMessage = $result['message'];
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 450px; margin: 1.5rem auto;">
    <div class="card" style="padding: 2.25rem 2rem;">
        <h1 style="font-size: 1.85rem; margin-top: 0; color: #1e3a8a; text-align: center;">Sign In</h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem;">
            Access your course recommendations or administrative tools.
        </p>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                <span class="alert-text"><?= e($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= e(app_url('login.php')) ?>" novalidate>
            <?= csrf_field() ?>

            <!-- Email (FR-30) -->
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" placeholder="you@example.com" required autofocus>
            </div>

            <!-- Password (FR-30) -->
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                    <label for="password" class="form-label" style="margin-bottom: 0;">Password</label>
                </div>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <div style="margin-top: 1.75rem;">
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Sign In</button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; font-size: 0.95rem; color: var(--text-muted);">
            Don't have an account yet? <a href="<?= e(app_url('signup.php')) ?>" style="font-weight: 600;">Sign up free</a>
        </div>

        <div style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); font-size: 0.85rem; color: var(--text-muted); text-align: center; line-height: 1.5;">
            <strong>Test Sign-in Credentials:</strong><br>
            Student: <code>amy@test.local</code> / <code>password123</code><br>
            Admin: <code>admin@ucrs.local</code> / <code>Admin@123</code>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
