<?php
/**
 * Student Profile Management (FR-40 to FR-46, SEC-04, SEC-07, T-17)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_student();

$pdo = get_db();
$userId = (int)$_SESSION['user_id'];
$pageTitle = 'My Profile';

// Fetch user account
$stmtUser = $pdo->prepare("SELECT id, full_name, email, education_level FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch();

// Fetch student profile record
$stmtProfile = $pdo->prepare("SELECT stream, program_id, program_status FROM student_profiles WHERE user_id = ?");
$stmtProfile->execute([$userId]);
$profile = $stmtProfile->fetch() ?: ['stream' => null, 'program_id' => null, 'program_status' => null];

// Fetch all available subjects and programs
$allSubjects = $pdo->query("SELECT id, name FROM subjects WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$allPrograms = $pdo->query("SELECT id, name FROM programs WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Fetch currently chosen subjects
$stmtSubs = $pdo->prepare("SELECT subject_id FROM student_subjects WHERE user_id = ?");
$stmtSubs->execute([$userId]);
$chosenSubjectIds = array_map('intval', $stmtSubs->fetchAll(PDO::FETCH_COLUMN));

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Security check failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? 'update_academic';

        if ($action === 'update_academic') {
            // FR-44, FR-46: Update academic qualifications
            $educationLevel = trim($_POST['education_level'] ?? '');
            $stream = trim($_POST['stream'] ?? '');
            $submittedSubjects = isset($_POST['subjects']) && is_array($_POST['subjects']) ? array_map('intval', $_POST['subjects']) : [];
            $programId = trim($_POST['program_id'] ?? '');
            $programStatus = trim($_POST['program_status'] ?? '');

            if (!in_array($educationLevel, ['SECONDARY', 'TERTIARY'], true)) {
                $errors['education_level'] = 'Please select a valid education level.';
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();

                    // Update user education level
                    $stmtUpdateUser = $pdo->prepare("UPDATE users SET education_level = ? WHERE id = ?");
                    $stmtUpdateUser->execute([$educationLevel, $userId]);
                    $_SESSION['education_level'] = $educationLevel;

                    // Update profile
                    $validStream = in_array($stream, ['SCIENCE', 'BUSINESS', 'ARTS', 'OTHER']) ? $stream : null;
                    $validProgId = ($educationLevel === 'TERTIARY' && !empty($programId)) ? (int)$programId : null;
                    $validProgStatus = ($educationLevel === 'TERTIARY' && in_array($programStatus, ['CURRENT', 'COMPLETED'])) ? $programStatus : null;

                    $stmtUpsert = $pdo->prepare("
                        INSERT INTO student_profiles (user_id, stream, program_id, program_status)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            stream = VALUES(stream),
                            program_id = VALUES(program_id),
                            program_status = VALUES(program_status)
                    ");
                    $stmtUpsert->execute([$userId, $validStream, $validProgId, $validProgStatus]);

                    // Sync subjects (FR-44)
                    $stmtDelSubs = $pdo->prepare("DELETE FROM student_subjects WHERE user_id = ?");
                    $stmtDelSubs->execute([$userId]);

                    if ($educationLevel === 'SECONDARY' && !empty($submittedSubjects)) {
                        $stmtInsSub = $pdo->prepare("INSERT INTO student_subjects (user_id, subject_id) VALUES (?, ?)");
                        foreach ($submittedSubjects as $sId) {
                            $stmtInsSub->execute([$userId, $sId]);
                        }
                    }

                    $pdo->commit();
                    set_flash('success', 'Academic profile updated successfully! Recommendations have been updated.');
                    header('Location: ' . app_url('student/profile.php'));
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Profile update error: " . $e->getMessage());
                    $errors['general'] = 'Failed to update academic profile. Please try again.';
                }
            }
        } elseif ($action === 'update_account') {
            // FR-45: Let student change their name and password
            $fullName = trim($_POST['full_name'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (strlen(preg_replace('/[^a-zA-Z]/', '', $fullName)) < 2) {
                $errors['full_name'] = 'Full name must contain at least 2 letters.';
            }

            if (!empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    $errors['new_password'] = 'New password must be at least 6 characters.';
                } elseif ($newPassword !== $confirmPassword) {
                    $errors['confirm_password'] = 'Passwords do not match.';
                }
            }

            if (empty($errors)) {
                try {
                    if (!empty($newPassword)) {
                        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                        $stmtUpdate = $pdo->prepare("UPDATE users SET full_name = ?, password_hash = ? WHERE id = ?");
                        $stmtUpdate->execute([$fullName, $hash, $userId]);
                    } else {
                        $stmtUpdate = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
                        $stmtUpdate->execute([$fullName, $userId]);
                    }

                    $_SESSION['user_name'] = $fullName;
                    set_flash('success', 'Account details updated successfully.');
                    header('Location: ' . app_url('student/profile.php'));
                    exit;

                } catch (Exception $e) {
                    error_log("Account update error: " . $e->getMessage());
                    $errors['general'] = 'Failed to update account details.';
                }
            }
        }
    }
}

// Re-check profile completeness (FR-41)
$completeness = check_profile_completeness($pdo, $userId);
$isComplete = $completeness['is_complete'];
$missingItems = $completeness['missing_items'];

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h1 style="font-size: 1.85rem; margin: 0; color: #1e3a8a;">Academic Profile & Account</h1>
            <p style="color: var(--text-muted); margin: 0.25rem 0 0;">Update your qualifications to recalibrate your recommendations.</p>
        </div>
        <div>
            <?php if ($isComplete): ?>
                <span class="badge badge-success" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;">Profile Complete</span>
            <?php else: ?>
                <span class="badge badge-danger" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;">Profile Incomplete</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$isComplete): ?>
        <div class="alert alert-warning">
            <div>
                <strong>Profile Incomplete:</strong>
                <ul style="margin: 0.25rem 0 0; padding-left: 1.25rem;">
                    <?php foreach ($missingItems as $m): ?>
                        <li><?= e($m) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <!-- Card 1: Academic Qualifications (FR-40 - FR-44, FR-46) -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Academic Qualifications</h2>
        </div>

        <form method="POST" action="<?= e(app_url('student/profile.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_academic">

            <!-- Education Level Toggle (FR-46) -->
            <div class="form-group">
                <label class="form-label">Education Level <span style="color: var(--danger);">*</span></label>
                <div style="display: flex; gap: 1.5rem; margin-top: 0.25rem;">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                        <input type="radio" name="education_level" value="SECONDARY" <?= $user['education_level'] === 'SECONDARY' ? 'checked' : '' ?> required>
                        <strong>Secondary School (High School)</strong>
                    </label>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                        <input type="radio" name="education_level" value="TERTIARY" <?= $user['education_level'] === 'TERTIARY' ? 'checked' : '' ?> required>
                        <strong>Tertiary (University / College)</strong>
                    </label>
                </div>
            </div>

            <!-- Secondary Fields (FR-15, FR-21, FR-42) -->
            <div id="secondary-fields" style="display: <?= $user['education_level'] === 'SECONDARY' ? 'block' : 'none' ?>; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                <h3 style="margin-top: 0; font-size: 1.1rem; color: #1e3a8a;">Secondary School Details</h3>
                
                <div class="form-group">
                    <label for="stream" class="form-label">Study Stream (Optional)</label>
                    <select id="stream" name="stream" class="form-control">
                        <option value="">-- Select Stream (Optional) --</option>
                        <option value="SCIENCE" <?= ($profile['stream'] ?? '') === 'SCIENCE' ? 'selected' : '' ?>>Science</option>
                        <option value="BUSINESS" <?= ($profile['stream'] ?? '') === 'BUSINESS' ? 'selected' : '' ?>>Business</option>
                        <option value="ARTS" <?= ($profile['stream'] ?? '') === 'ARTS' ? 'selected' : '' ?>>Arts</option>
                        <option value="OTHER" <?= ($profile['stream'] ?? '') === 'OTHER' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Subjects Completed <span class="form-hint">(Select at least 3 subjects)</span></label>
                    <div class="checkbox-grid">
                        <?php foreach ($allSubjects as $sub): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="subjects[]" value="<?= (int)$sub['id'] ?>" <?= in_array((int)$sub['id'], $chosenSubjectIds, true) ? 'checked' : '' ?>>
                                <span><?= e($sub['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tertiary Fields (FR-16, FR-43) -->
            <div id="tertiary-fields" style="display: <?= $user['education_level'] === 'TERTIARY' ? 'block' : 'none' ?>; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                <h3 style="margin-top: 0; font-size: 1.1rem; color: #1e3a8a;">Tertiary Program Details</h3>
                
                <div class="form-group">
                    <label for="program_id" class="form-label">Program of Study</label>
                    <select id="program_id" name="program_id" class="form-control">
                        <option value="">-- Select Program --</option>
                        <?php foreach ($allPrograms as $prog): ?>
                            <option value="<?= (int)$prog['id'] ?>" <?= (int)($profile['program_id'] ?? 0) === (int)$prog['id'] ? 'selected' : '' ?>>
                                <?= e($prog['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Program Status</label>
                    <div style="display: flex; gap: 1.5rem; margin-top: 0.25rem;">
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                            <input type="radio" name="program_status" value="CURRENT" <?= ($profile['program_status'] ?? '') === 'CURRENT' ? 'checked' : '' ?>>
                            <span>Current Student</span>
                        </label>
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                            <input type="radio" name="program_status" value="COMPLETED" <?= ($profile['program_status'] ?? '') === 'COMPLETED' ? 'checked' : '' ?>>
                            <span>Graduated / Completed</span>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Academic Details</button>
        </form>
    </div>

    <!-- Card 2: Account Details & Password (FR-45) -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Account Details & Password</h2>
        </div>

        <form method="POST" action="<?= e(app_url('student/profile.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_account">

            <div class="form-group">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" value="<?= e($user['full_name']) ?>" required>
                <?php if (isset($errors['full_name'])): ?>
                    <span class="form-error"><?= e($errors['full_name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled style="background-color: #f1f5f9; cursor: not-allowed;">
                <span class="form-hint">Email address cannot be changed.</span>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password <span class="form-hint">(leave blank to keep current)</span></label>
                    <input type="password" id="new_password" name="new_password" class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>" placeholder="Min 6 characters">
                    <?php if (isset($errors['new_password'])): ?>
                        <span class="form-error"><?= e($errors['new_password']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" placeholder="Re-enter new password">
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="form-error"><?= e($errors['confirm_password']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Update Account</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
