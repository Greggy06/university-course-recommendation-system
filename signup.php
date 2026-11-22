<?php
/**
 * Student Registration Page (FR-10 to FR-21, SEC-01 to SEC-04, SEC-10)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ' . app_url($_SESSION['role'] === 'ADMIN' ? 'admin/dashboard.php' : 'student/dashboard.php'));
    exit;
}

$pageTitle = 'Student Signup';
$pdo = get_db();

// Load active subjects and programs from database (FR-15, FR-16)
$stmtSubjects = $pdo->query("SELECT id, name FROM subjects WHERE is_active = 1 ORDER BY name ASC");
$allSubjects = $stmtSubjects->fetchAll();

$stmtPrograms = $pdo->query("SELECT id, name FROM programs WHERE is_active = 1 ORDER BY name ASC");
$allPrograms = $stmtPrograms->fetchAll();

// Form state
$fullName = '';
$email = '';
$educationLevel = '';
$stream = '';
$selectedSubjects = [];
$programId = '';
$programStatus = '';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check (SEC-04)
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Security verification failed. Please try submitting the form again.';
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $educationLevel = trim($_POST['education_level'] ?? '');
    $stream = trim($_POST['stream'] ?? '');
    $selectedSubjects = isset($_POST['subjects']) && is_array($_POST['subjects']) ? array_map('intval', $_POST['subjects']) : [];
    $programId = trim($_POST['program_id'] ?? '');
    $programStatus = trim($_POST['program_status'] ?? '');

    // Server-side validations (SEC-10)
    // FR-11: Full name at least 2 letters
    if (strlen(preg_replace('/[^a-zA-Z]/', '', $fullName)) < 2) {
        $errors['full_name'] = 'Full name must contain at least 2 letters.';
    }

    // FR-12: Valid email and uniqueness check
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } else {
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            $errors['email'] = 'This email is already registered. Please sign in or use another email.';
        }
    }

    // FR-13: Password min 6 chars
    if (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    // FR-14: Confirm password check
    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Education level check
    if (!in_array($educationLevel, ['SECONDARY', 'TERTIARY'], true)) {
        $errors['education_level'] = 'Please select your education level.';
    }

    // If no errors, process registration
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // FR-17: Hash password (never plain text)
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // FR-18: Always create new signups with role Student
            $stmtUser = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role, education_level, is_active)
                VALUES (?, ?, ?, 'STUDENT', ?, 1)
            ");
            $stmtUser->execute([$fullName, $email, $passwordHash, $educationLevel]);
            $userId = (int)$pdo->lastInsertId();

            // Create student profile
            $validStream = in_array($stream, ['SCIENCE', 'BUSINESS', 'ARTS', 'OTHER']) ? $stream : null;
            $validProgId = ($educationLevel === 'TERTIARY' && !empty($programId)) ? (int)$programId : null;
            $validProgStatus = ($educationLevel === 'TERTIARY' && in_array($programStatus, ['CURRENT', 'COMPLETED'])) ? $programStatus : null;

            $stmtProfile = $pdo->prepare("
                INSERT INTO student_profiles (user_id, stream, program_id, program_status)
                VALUES (?, ?, ?, ?)
            ");
            $stmtProfile->execute([$userId, $validStream, $validProgId, $validProgStatus]);

            // If secondary, save chosen subjects
            if ($educationLevel === 'SECONDARY' && !empty($selectedSubjects)) {
                $stmtSub = $pdo->prepare("INSERT INTO student_subjects (user_id, subject_id) VALUES (?, ?)");
                foreach ($selectedSubjects as $subId) {
                    $stmtSub->execute([$userId, $subId]);
                }
            }

            $pdo->commit();

            // FR-19: Show success message and redirect to login
            set_flash('success', 'Registration successful! You may now sign in with your email and password.');
            header('Location: ' . app_url('login.php?signup=success'));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Signup error: " . $e->getMessage());
            $errors['general'] = 'An unexpected error occurred while creating your account. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 650px; margin: 0 auto;">
    <div class="card" style="padding: 2.25rem 2rem;">
        <h1 style="font-size: 1.85rem; margin-top: 0; color: #1e3a8a; text-align: center;">Create Student Account</h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem;">
            Join UCRS to discover academic programs matching your qualifications.
        </p>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger">
                <?= e($errors['general']) ?>
            </div>
        <?php endif; ?>

        <form id="signup-form" method="POST" action="<?= e(app_url('signup.php')) ?>" novalidate>
            <?= csrf_field() ?>

            <!-- Full Name (FR-10, FR-11) -->
            <div class="form-group">
                <label for="full_name" class="form-label">Full Name <span style="color: var(--danger);">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" value="<?= e($fullName) ?>" placeholder="e.g. Jane Doe" required>
                <span id="full_name_error" class="form-error"><?= e($errors['full_name'] ?? '') ?></span>
            </div>

            <!-- Email (FR-10, FR-12) -->
            <div class="form-group">
                <label for="email" class="form-label">Email Address <span style="color: var(--danger);">*</span></label>
                <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= e($email) ?>" placeholder="you@example.com" required>
                <span id="email_error" class="form-error"><?= e($errors['email'] ?? '') ?></span>
            </div>

            <!-- Password (FR-10, FR-13) -->
            <div class="form-group">
                <label for="password" class="form-label">Password (min 6 characters) <span style="color: var(--danger);">*</span></label>
                <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder="At least 6 characters" required>
                <span id="password_error" class="form-error"><?= e($errors['password'] ?? '') ?></span>
            </div>

            <!-- Confirm Password (FR-14) -->
            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password <span style="color: var(--danger);">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" placeholder="Re-enter your password" required>
                <span id="confirm_password_error" class="form-error"><?= e($errors['confirm_password'] ?? '') ?></span>
            </div>

            <!-- Education Level (FR-10) -->
            <div class="form-group">
                <label class="form-label">Education Level <span style="color: var(--danger);">*</span></label>
                <div style="display: flex; gap: 1.5rem; margin-top: 0.25rem;">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                        <input type="radio" name="education_level" value="SECONDARY" <?= $educationLevel === 'SECONDARY' ? 'checked' : '' ?> required>
                        <strong>Secondary School (High School)</strong>
                    </label>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                        <input type="radio" name="education_level" value="TERTIARY" <?= $educationLevel === 'TERTIARY' ? 'checked' : '' ?> required>
                        <strong>Tertiary (University / College)</strong>
                    </label>
                </div>
                <span id="education_level_error" class="form-error"><?= e($errors['education_level'] ?? '') ?></span>
            </div>

            <!-- Secondary Fields (FR-15, FR-21) -->
            <div id="secondary-fields" style="display: <?= $educationLevel === 'SECONDARY' ? 'block' : 'none' ?>; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                <h3 style="margin-top: 0; font-size: 1.1rem; color: #1e3a8a;">Secondary School Details</h3>
                
                <!-- Optional Stream (FR-21) -->
                <div class="form-group">
                    <label for="stream" class="form-label">Study Stream (Optional)</label>
                    <select id="stream" name="stream" class="form-control">
                        <option value="">-- Select Stream (Optional) --</option>
                        <option value="SCIENCE" <?= $stream === 'SCIENCE' ? 'selected' : '' ?>>Science</option>
                        <option value="BUSINESS" <?= $stream === 'BUSINESS' ? 'selected' : '' ?>>Business</option>
                        <option value="ARTS" <?= $stream === 'ARTS' ? 'selected' : '' ?>>Arts</option>
                        <option value="OTHER" <?= $stream === 'OTHER' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <!-- Subjects Checklist (FR-15) -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Subjects Studied <span class="form-hint">(Select all that you completed)</span></label>
                    <div class="checkbox-grid">
                        <?php foreach ($allSubjects as $sub): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="subjects[]" value="<?= (int)$sub['id'] ?>" <?= in_array((int)$sub['id'], $selectedSubjects, true) ? 'checked' : '' ?>>
                                <span><?= e($sub['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tertiary Fields (FR-16) -->
            <div id="tertiary-fields" style="display: <?= $educationLevel === 'TERTIARY' ? 'block' : 'none' ?>; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                <h3 style="margin-top: 0; font-size: 1.1rem; color: #1e3a8a;">Tertiary Program Details</h3>
                
                <!-- Program list from database (FR-16) -->
                <div class="form-group">
                    <label for="program_id" class="form-label">Program (Field of Study)</label>
                    <select id="program_id" name="program_id" class="form-control">
                        <option value="">-- Select Program --</option>
                        <?php foreach ($allPrograms as $prog): ?>
                            <option value="<?= (int)$prog['id'] ?>" <?= (string)$prog['id'] === (string)$programId ? 'selected' : '' ?>>
                                <?= e($prog['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Current or Completed (FR-16) -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Program Status</label>
                    <div style="display: flex; gap: 1.5rem; margin-top: 0.25rem;">
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                            <input type="radio" name="program_status" value="CURRENT" <?= $programStatus === 'CURRENT' ? 'checked' : '' ?>>
                            <span>Current Student</span>
                        </label>
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                            <input type="radio" name="program_status" value="COMPLETED" <?= $programStatus === 'COMPLETED' ? 'checked' : '' ?>>
                            <span>Graduated / Completed</span>
                        </label>
                    </div>
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Create Account</button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; font-size: 0.95rem; color: var(--text-muted);">
            Already have an account? <a href="<?= e(app_url('login.php')) ?>" style="font-weight: 600;">Log in here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
