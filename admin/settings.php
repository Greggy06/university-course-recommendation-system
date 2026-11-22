<?php
/**
 * System Settings Management (FR-42, FR-111, SEC-04, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db();
$pageTitle = 'System Settings';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security verification failed.');
    } else {
        $minSubjects = (int)($_POST['min_subjects'] ?? 3);

        if ($minSubjects < 1 || $minSubjects > 15) {
            set_flash('error', 'Minimum subjects must be between 1 and 15.');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES ('min_subjects', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([(string)$minSubjects]);

            log_admin_action('SETTINGS_UPDATE', "Updated 'min_subjects' threshold to {$minSubjects}.");
            set_flash('success', "Settings updated: Secondary students now require at least {$minSubjects} subjects for profile completeness.");
            header('Location: ' . app_url('admin/settings.php'));
            exit;
        }
    }
}

// Fetch current setting value
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'min_subjects'");
$currentMinSubjects = (int)($stmt->fetchColumn() ?: 3);

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">System Settings &amp; Policies</h1>
            <p style="color: var(--text-muted); margin: 0;">
                Configure dynamic system thresholds, profile validation rules, and administrative credentials.
            </p>
        </div>
        <div>
            <a href="<?= e(app_url('admin/admins.php')) ?>" class="btn btn-primary">Manage Admin Accounts &rarr;</a>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Setting: Minimum Subjects for Secondary Completion (FR-42) -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Secondary Profile Completeness Rule</h2>
        </div>

        <form method="POST" action="<?= e(app_url('admin/settings.php')) ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="min_subjects" class="form-label">
                    Minimum Subjects Required (FR-42)
                </label>
                <input type="number" id="min_subjects" name="min_subjects" class="form-control" value="<?= $currentMinSubjects ?>" min="1" max="15" required>
                <div class="form-hint" style="margin-top: 0.5rem; line-height: 1.5;">
                    Per SRS Section 3.4 (FR-42): A secondary student's profile is treated as <strong>Complete</strong> only when they have selected at least this number of subjects. Changing this number immediately affects recommendation calculations for all secondary students across the platform.
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>

    <!-- Security & Environment Info Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Environment &amp; Security Specs</h2>
        </div>

        <ul style="list-style: none; margin: 0; padding: 0; font-size: 0.95rem; line-height: 2;">
            <li><strong>PHP Version:</strong> <?= phpversion() ?></li>
            <li><strong>Session Lifetime:</strong> <?= SESSION_LIFETIME / 60 ?> minutes of inactivity (FR-33)</li>
            <li><strong>Failed Login Lockout:</strong> <?= MAX_LOGIN_ATTEMPTS ?> attempts / <?= LOCKOUT_DURATION_MINUTES ?> min lock (FR-37)</li>
            <li><strong>Database Driver:</strong> PDO MySQL (utf8mb4) (SEC-02)</li>
            <li><strong>Password Hashing:</strong> PHP BCRYPT / password_hash() (SEC-01)</li>
            <li><strong>CSRF Protection:</strong> Active on all data modification forms (SEC-04)</li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
