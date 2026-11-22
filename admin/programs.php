<?php
/**
 * Tertiary Programs Management (FR-104, FR-111, SEC-04, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db();
$pageTitle = 'Manage Tertiary Programs';

$editProgram = null;
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security verification failed. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['name'] ?? '');
            $programId = isset($_POST['program_id']) ? (int)$_POST['program_id'] : 0;

            if (empty($name)) {
                $errors['name'] = 'Program name cannot be empty.';
            } else {
                $stmtCheck = $pdo->prepare("SELECT id FROM programs WHERE name = ? AND id != ?");
                $stmtCheck->execute([$name, $programId]);
                if ($stmtCheck->fetch()) {
                    $errors['name'] = 'A program with this name already exists.';
                }
            }

            if (empty($errors)) {
                if ($action === 'add') {
                    $stmtAdd = $pdo->prepare("INSERT INTO programs (name, is_active) VALUES (?, 1)");
                    $stmtAdd->execute([$name]);
                    log_admin_action('PROGRAM_CREATE', "Added tertiary program '{$name}'.");
                    set_flash('success', "Program '{$name}' created.");
                } else {
                    $stmtUpdate = $pdo->prepare("UPDATE programs SET name = ? WHERE id = ?");
                    $stmtUpdate->execute([$name, $programId]);
                    log_admin_action('PROGRAM_UPDATE', "Updated tertiary program ID {$programId} ('{$name}').");
                    set_flash('success', "Program '{$name}' updated.");
                }
                header('Location: ' . app_url('admin/programs.php'));
                exit;
            }
        } elseif ($action === 'toggle_active') {
            $programId = (int)($_POST['program_id'] ?? 0);
            $stmtGet = $pdo->prepare("SELECT name, is_active FROM programs WHERE id = ?");
            $stmtGet->execute([$programId]);
            $prog = $stmtGet->fetch();

            if ($prog) {
                $newStatus = $prog['is_active'] ? 0 : 1;
                $stmtToggle = $pdo->prepare("UPDATE programs SET is_active = ? WHERE id = ?");
                $stmtToggle->execute([$newStatus, $programId]);
                $statusLabel = $newStatus ? 'activated' : 'deactivated';
                log_admin_action('PROGRAM_TOGGLE', "Changed program '{$prog['name']}' status to {$statusLabel}.");
                set_flash('info', "Program '{$prog['name']}' is now {$statusLabel}.");
            }
            header('Location: ' . app_url('admin/programs.php'));
            exit;
        }
    }
}

// Check edit parameter
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtEdit = $pdo->prepare("SELECT * FROM programs WHERE id = ?");
    $stmtEdit->execute([$editId]);
    $editProgram = $stmtEdit->fetch();
}

// Fetch all programs with usage counts
$stmt = $pdo->query("
    SELECT p.*,
        (SELECT COUNT(*) FROM student_profiles sp WHERE sp.program_id = p.id) AS student_count,
        (SELECT COUNT(*) FROM course_requirements cr WHERE cr.program_id = p.id) AS rule_count
    FROM programs p
    ORDER BY p.name ASC
");
$programs = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Tertiary Study Programs</h1>
            <p style="color: var(--text-muted); margin: 0;">
                Configure fields of study recognized for tertiary student backgrounds and university entry rules.
            </p>
        </div>
        <div>
            <a href="<?= e(app_url('admin/subjects.php')) ?>" class="btn btn-outline">&larr; Manage Secondary Subjects</a>
        </div>
    </div>
</div>

<div class="grid-3" style="align-items: flex-start;">
    <!-- Add / Edit Form Column -->
    <div class="card" style="grid-column: span 1;">
        <div class="card-header">
            <h2 class="card-title"><?= $editProgram ? 'Edit Program' : 'Add New Program' ?></h2>
            <?php if ($editProgram): ?>
                <a href="<?= e(app_url('admin/programs.php')) ?>" class="btn btn-outline btn-sm">Cancel</a>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= e(app_url('admin/programs.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editProgram ? 'edit' : 'add' ?>">
            <?php if ($editProgram): ?>
                <input type="hidden" name="program_id" value="<?= (int)$editProgram['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="name" class="form-label">Program Name <span style="color: var(--danger);">*</span></label>
                <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($editProgram['name'] ?? '') ?>" placeholder="e.g. Data Science / Analytics" required>
                <?php if (isset($errors['name'])): ?>
                    <span class="form-error"><?= e($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <?= $editProgram ? 'Update Program' : 'Create Program' ?>
            </button>
        </form>
    </div>

    <!-- Table Column -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title">All Tertiary Programs</h2>
            <span class="badge badge-secondary"><?= count($programs) ?> Programs</span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Program Name</th>
                        <th>Enrolled Students</th>
                        <th>Course Rules</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($programs as $p): ?>
                        <tr>
                            <td><strong><?= e($p['name']) ?></strong></td>
                            <td><?= (int)$p['student_count'] ?> students</td>
                            <td>
                                <span class="badge badge-info"><?= (int)$p['rule_count'] ?> rules</span>
                            </td>
                            <td>
                                <?php if ($p['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                    <a href="<?= e(app_url('admin/programs.php?edit=' . $p['id'])) ?>" class="btn btn-outline btn-sm">Edit</a>
                                    <form method="POST" action="<?= e(app_url('admin/programs.php')) ?>" style="margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="program_id" value="<?= (int)$p['id'] ?>">
                                        <button type="submit" class="btn <?= $p['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-sm">
                                            <?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>
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
