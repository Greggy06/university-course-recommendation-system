<?php
/**
 * Student Accounts Management (FR-106, FR-108, FR-109, T-20, SEC-04, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_admin();

$pdo = get_db();
$pageTitle = 'Manage Students';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'toggle_active') {
            // FR-108 & T-20: Deactivate or reactivate student account
            $studentId = (int)($_POST['student_id'] ?? 0);
            $stmtUser = $pdo->prepare("SELECT full_name, is_active FROM users WHERE id = ? AND role = 'STUDENT'");
            $stmtUser->execute([$studentId]);
            $stu = $stmtUser->fetch();

            if ($stu) {
                $newStatus = $stu['is_active'] ? 0 : 1;
                $stmtToggle = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $stmtToggle->execute([$newStatus, $studentId]);

                $statusLabel = $newStatus ? 'reactivated' : 'deactivated';
                log_admin_action('STUDENT_STATUS', "{$statusLabel} student account '{$stu['full_name']}' (ID: {$studentId}).");
                set_flash('info', "Student '{$stu['full_name']}' account has been {$statusLabel}.");
            }
            header('Location: ' . app_url('admin/students.php'));
            exit;

        } elseif ($action === 'reset_password') {
            // FR-109: Reset student password (admin never sees old password)
            $studentId = (int)($_POST['student_id'] ?? 0);
            $newPassword = $_POST['new_password'] ?? '';

            if (strlen($newPassword) < 6) {
                set_flash('error', 'New password must be at least 6 characters.');
            } else {
                $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'STUDENT'");
                $stmtUser->execute([$studentId]);
                $stu = $stmtUser->fetch();

                if ($stu) {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmtReset = $pdo->prepare("UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE id = ?");
                    $stmtReset->execute([$newHash, $studentId]);

                    log_admin_action('PASSWORD_RESET', "Reset password for student '{$stu['full_name']}' (ID: {$studentId}).");
                    set_flash('success', "Password successfully reset for student '{$stu['full_name']}'.");
                }
            }
            header('Location: ' . app_url('admin/students.php'));
            exit;
        }
    }
}

// Search and Filters (FR-106)
$search = trim($_GET['search'] ?? '');
$filterLevel = trim($_GET['level'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

// Build Query
$query = "
    SELECT u.id, u.full_name, u.email, u.education_level, u.is_active, u.created_at,
           sp.stream, sp.program_id, sp.program_status, p.name AS program_name,
           (SELECT COUNT(*) FROM student_subjects ss WHERE ss.user_id = u.id) AS subject_count
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN programs p ON sp.program_id = p.id
    WHERE u.role = 'STUDENT'
";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if (!empty($filterLevel) && in_array($filterLevel, ['SECONDARY', 'TERTIARY'])) {
    $query .= " AND u.education_level = ?";
    $params[] = $filterLevel;
}

$query .= " ORDER BY u.created_at DESC, u.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rawStudents = $stmt->fetchAll();

// Evaluate profile completeness dynamically for filtering (FR-106)
$students = [];
foreach ($rawStudents as $s) {
    $comp = check_profile_completeness($pdo, (int)$s['id']);
    $s['is_complete'] = $comp['is_complete'];
    $s['missing_items'] = $comp['missing_items'];

    if (!empty($filterStatus)) {
        if ($filterStatus === 'COMPLETE' && !$s['is_complete']) continue;
        if ($filterStatus === 'INCOMPLETE' && $s['is_complete']) continue;
    }

    $students[] = $s;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Student Accounts Directory</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Search and filter registered students, inspect qualification profiles, manage account access, and reset credentials.
    </p>
</div>

<!-- Search & Filters (FR-106) -->
<div class="card" style="padding: 1.25rem; margin-bottom: 2rem;">
    <form method="GET" action="<?= e(app_url('admin/students.php')) ?>" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 2; min-width: 220px;">
            <label for="search" class="form-label">Search Student</label>
            <input type="text" id="search" name="search" class="form-control" value="<?= e($search) ?>" placeholder="Search by name or email address...">
        </div>

        <div style="flex: 1; min-width: 170px;">
            <label for="level" class="form-label">Education Level</label>
            <select id="level" name="level" class="form-control">
                <option value="">All Levels</option>
                <option value="SECONDARY" <?= $filterLevel === 'SECONDARY' ? 'selected' : '' ?>>Secondary</option>
                <option value="TERTIARY" <?= $filterLevel === 'TERTIARY' ? 'selected' : '' ?>>Tertiary</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 170px;">
            <label for="status" class="form-label">Profile Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="COMPLETE" <?= $filterStatus === 'COMPLETE' ? 'selected' : '' ?>>Complete Only</option>
                <option value="INCOMPLETE" <?= $filterStatus === 'INCOMPLETE' ? 'selected' : '' ?>>Incomplete Only</option>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if (!empty($search) || !empty($filterLevel) || !empty($filterStatus)): ?>
                <a href="<?= e(app_url('admin/students.php')) ?>" class="btn btn-outline" style="margin-left: 0.5rem;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Students Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Registered Students (<?= count($students) ?>)</h2>
    </div>

    <?php if (empty($students)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No student accounts match the specified criteria.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student Name &amp; Email</th>
                        <th>Level &amp; Details</th>
                        <th>Profile Status</th>
                        <th>Account Access</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $stu): ?>
                        <tr>
                            <td>
                                <strong><?= e($stu['full_name']) ?></strong>
                                <div style="font-size: 0.85rem; color: var(--text-muted);"><?= e($stu['email']) ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;">Joined: <?= date('M d, Y', strtotime($stu['created_at'])) ?></div>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= e($stu['education_level'] ?: 'Not set') ?></span>
                                <div style="font-size: 0.85rem; color: var(--secondary); margin-top: 0.25rem;">
                                    <?php if ($stu['education_level'] === 'SECONDARY'): ?>
                                        <?= (int)$stu['subject_count'] ?> subjects chosen
                                        <?= !empty($stu['stream']) ? ' &bull; ' . e($stu['stream']) : '' ?>
                                    <?php elseif ($stu['education_level'] === 'TERTIARY'): ?>
                                        <?= e($stu['program_name'] ?? 'No program') ?> (<?= e($stu['program_status'] ?? '') ?>)
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($stu['is_complete']): ?>
                                    <span class="badge badge-success">&#10003; Complete</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">&#9888; Incomplete</span>
                                    <div style="font-size: 0.75rem; color: #991b1b; margin-top: 0.25rem;">
                                        <?= e($stu['missing_items'][0] ?? '') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($stu['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Deactivated</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.35rem; flex-wrap: wrap; align-items: center;">
                                    <!-- FR-107: Open student profile & recommendations -->
                                    <a href="<?= e(app_url('admin/student_view.php?id=' . $stu['id'])) ?>" class="btn btn-secondary btn-sm" title="View Profile & Recommended Courses">
                                        View
                                    </a>

                                    <!-- FR-108, T-20: Deactivate / Reactivate -->
                                    <form method="POST" action="<?= e(app_url('admin/students.php')) ?>" style="margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="student_id" value="<?= (int)$stu['id'] ?>">
                                        <button type="submit" class="btn <?= $stu['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-sm">
                                            <?= $stu['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                                        </button>
                                    </form>

                                    <!-- FR-109: Reset Password Button (Prompt) -->
                                    <button type="button" class="btn btn-outline btn-sm" onclick="resetPassword(<?= (int)$stu['id'] ?>, '<?= e(addslashes($stu['full_name'])) ?>')">
                                        Reset PW
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Password Reset Hidden Form Script (FR-109) -->
<form id="reset-password-form" method="POST" action="<?= e(app_url('admin/students.php')) ?>" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" id="reset-student-id" name="student_id" value="">
    <input type="hidden" id="reset-new-password" name="new_password" value="">
</form>

<script>
function resetPassword(studentId, studentName) {
    const newPass = prompt('Enter a new password for student ' + studentName + ' (min 6 characters):');
    if (newPass === null) return;
    if (newPass.length < 6) {
        alert('Password must be at least 6 characters long.');
        return;
    }
    document.getElementById('reset-student-id').value = studentId;
    document.getElementById('reset-new-password').value = newPass;
    document.getElementById('reset-password-form').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
