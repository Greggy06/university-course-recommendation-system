<?php
/**
 * Secondary School Subjects Management (FR-104, FR-111, SEC-04, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db();
$pageTitle = 'Manage Secondary Subjects';

$editSubject = null;
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security check failed. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['name'] ?? '');
            $subjectId = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;

            if (empty($name)) {
                $errors['name'] = 'Subject name cannot be empty.';
            } else {
                $stmtCheck = $pdo->prepare("SELECT id FROM subjects WHERE name = ? AND id != ?");
                $stmtCheck->execute([$name, $subjectId]);
                if ($stmtCheck->fetch()) {
                    $errors['name'] = 'A subject with this name already exists.';
                }
            }

            if (empty($errors)) {
                if ($action === 'add') {
                    $stmtAdd = $pdo->prepare("INSERT INTO subjects (name, is_active) VALUES (?, 1)");
                    $stmtAdd->execute([$name]);
                    log_admin_action('SUBJECT_CREATE', "Added secondary school subject '{$name}'.");
                    set_flash('success', "Subject '{$name}' created.");
                } else {
                    $stmtUpdate = $pdo->prepare("UPDATE subjects SET name = ? WHERE id = ?");
                    $stmtUpdate->execute([$name, $subjectId]);
                    log_admin_action('SUBJECT_UPDATE', "Updated subject ID {$subjectId} ('{$name}').");
                    set_flash('success', "Subject '{$name}' updated.");
                }
                header('Location: ' . app_url('admin/subjects.php'));
                exit;
            }
        } elseif ($action === 'toggle_active') {
            $subjectId = (int)($_POST['subject_id'] ?? 0);
            $stmtGet = $pdo->prepare("SELECT name, is_active FROM subjects WHERE id = ?");
            $stmtGet->execute([$subjectId]);
            $sub = $stmtGet->fetch();

            if ($sub) {
                $newStatus = $sub['is_active'] ? 0 : 1;
                $stmtToggle = $pdo->prepare("UPDATE subjects SET is_active = ? WHERE id = ?");
                $stmtToggle->execute([$newStatus, $subjectId]);
                $statusLabel = $newStatus ? 'activated' : 'deactivated';
                log_admin_action('SUBJECT_TOGGLE', "Changed subject '{$sub['name']}' status to {$statusLabel}.");
                set_flash('info', "Subject '{$sub['name']}' is now {$statusLabel}.");
            }
            header('Location: ' . app_url('admin/subjects.php'));
            exit;
        }
    }
}

// Check edit parameter
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtEdit = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmtEdit->execute([$editId]);
    $editSubject = $stmtEdit->fetch();
}

// Fetch all subjects with rule usage counts
$stmt = $pdo->query("
    SELECT s.*,
        (SELECT COUNT(*) FROM student_subjects ss WHERE ss.subject_id = s.id) AS student_count,
        (SELECT COUNT(*) FROM course_requirements cr WHERE cr.subject_id = s.id) AS rule_count
    FROM subjects s
    ORDER BY s.name ASC
");
$subjects = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Secondary School Subjects</h1>
            <p style="color: var(--text-muted); margin: 0;">
                Configure high school subjects used in student signup checklists and course prerequisite groups.
            </p>
        </div>
        <div>
            <a href="<?= e(app_url('admin/programs.php')) ?>" class="btn btn-outline">Manage Tertiary Programs &rarr;</a>
        </div>
    </div>
</div>

<div class="grid-3" style="align-items: flex-start;">
    <!-- Add / Edit Form Column -->
    <div class="card" style="grid-column: span 1;">
        <div class="card-header">
            <h2 class="card-title"><?= $editSubject ? 'Edit Subject' : 'Add New Subject' ?></h2>
            <?php if ($editSubject): ?>
                <a href="<?= e(app_url('admin/subjects.php')) ?>" class="btn btn-outline btn-sm">Cancel</a>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= e(app_url('admin/subjects.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editSubject ? 'edit' : 'add' ?>">
            <?php if ($editSubject): ?>
                <input type="hidden" name="subject_id" value="<?= (int)$editSubject['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="name" class="form-label">Subject Name <span style="color: var(--danger);">*</span></label>
                <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($editSubject['name'] ?? '') ?>" placeholder="e.g. Accounting or Computer Studies" required>
                <?php if (isset($errors['name'])): ?>
                    <span class="form-error"><?= e($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <?= $editSubject ? 'Update Subject' : 'Create Subject' ?>
            </button>
        </form>
    </div>

    <!-- Table Column -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title">All Subjects</h2>
            <span class="badge badge-secondary"><?= count($subjects) ?> Subjects</span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Students Chosen</th>
                        <th>Course Rules</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $s): ?>
                        <tr>
                            <td><strong><?= e($s['name']) ?></strong></td>
                            <td><?= (int)$s['student_count'] ?> students</td>
                            <td>
                                <span class="badge badge-info"><?= (int)$s['rule_count'] ?> rules</span>
                            </td>
                            <td>
                                <?php if ($s['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                    <a href="<?= e(app_url('admin/subjects.php?edit=' . $s['id'])) ?>" class="btn btn-outline btn-sm">Edit</a>
                                    <form method="POST" action="<?= e(app_url('admin/subjects.php')) ?>" style="margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="subject_id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" class="btn <?= $s['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-sm">
                                            <?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
