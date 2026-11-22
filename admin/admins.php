<?php
/**
 * Administrator Accounts Management (FR-18, Section 12 Q6, SEC-04, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db();
$pageTitle = 'Manage Admin Accounts';

$errors = [];

// Handle POST add new admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security verification failed.');
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen(preg_replace('/[^a-zA-Z]/', '', $fullName)) < 2) {
            $errors['full_name'] = 'Full name must contain at least 2 letters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email address is required.';
        } else {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $errors['email'] = 'An account with this email already exists.';
            }
        }

        if (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmtAdd = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role, is_active)
                VALUES (?, ?, ?, 'ADMIN', 1)
            ");
            $stmtAdd->execute([$fullName, $email, $hash]);

            log_admin_action('ADMIN_CREATE', "Created new administrator account '{$fullName}' ({$email}).");
            set_flash('success', "New administrator account '{$fullName}' created successfully.");
            header('Location: ' . app_url('admin/admins.php'));
            exit;
        }
    }
}

// Fetch all existing admins
$admins = $pdo->query("SELECT id, full_name, email, is_active, created_at FROM users WHERE role = 'ADMIN' ORDER BY created_at ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Administrator Team Management</h1>
            <p style="color: var(--text-muted); margin: 0;">
                Authorized administrators who manage platform courses, students, and prerequisite rules.
            </p>
        </div>
        <div>
            <a href="<?= e(app_url('admin/settings.php')) ?>" class="btn btn-outline">&larr; Back to Settings</a>
        </div>
    </div>
</div>

<div class="grid-3" style="align-items: flex-start;">
    <!-- Add Admin Form Column -->
    <div class="card" style="grid-column: span 1;">
        <div class="card-header">
            <h2 class="card-title">Add New Administrator</h2>
        </div>

        <form method="POST" action="<?= e(app_url('admin/admins.php')) ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="full_name" class="form-label">Full Name <span style="color: var(--danger);">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" value="<?= e($_POST['full_name'] ?? '') ?>" placeholder="e.g. Dr. Alex Morgan" required>
                <?php if (isset($errors['full_name'])): ?>
                    <span class="form-error"><?= e($errors['full_name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email Address <span style="color: var(--danger);">*</span></label>
                <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= e($_POST['email'] ?? '') ?>" placeholder="admin@ucrs.edu" required>
                <?php if (isset($errors['email'])): ?>
                    <span class="form-error"><?= e($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password (min 6 chars) <span style="color: var(--danger);">*</span></label>
                <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder="At least 6 characters" required>
                <?php if (isset($errors['password'])): ?>
                    <span class="form-error"><?= e($errors['password']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password <span style="color: var(--danger);">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" placeholder="Re-enter password" required>
                <?php if (isset($errors['confirm_password'])): ?>
                    <span class="form-error"><?= e($errors['confirm_password']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Admin Account</button>
        </form>
    </div>

    <!-- Admins Table Column -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title">Existing Administrators</h2>
            <span class="badge badge-secondary"><?= count($admins) ?> Admins</span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Administrator Name</th>
                        <th>Email</th>
                        <th>Created</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $adm): ?>
                        <tr>
                            <td>
                                <strong><?= e($adm['full_name']) ?></strong>
                                <?php if ((int)$adm['id'] === (int)$_SESSION['user_id']): ?>
                                    <span class="badge badge-info" style="margin-left: 0.35rem;">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($adm['email']) ?></td>
                            <td><?= date('M d, Y', strtotime($adm['created_at'])) ?></td>
                            <td>
                                <span class="badge badge-success">Active</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
