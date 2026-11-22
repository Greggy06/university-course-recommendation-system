<?php
/**
 * Course Units Management (FR-103, FR-52, FR-111, SEC-04, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db();
$pageTitle = 'Manage Units';

// Fetch all courses for selector
$courses = $pdo->query("SELECT id, code, name FROM courses ORDER BY code ASC")->fetchAll();

$selectedCourseId = isset($_GET['course']) ? (int)$_GET['course'] : ($courses[0]['id'] ?? 0);
$editUnit = null;
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $unitId = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0;
            $courseId = (int)($_POST['course_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sortOrder = (int)($_POST['sort_order'] ?? 1);

            if (empty($title)) {
                $errors['title'] = 'Unit title is required.';
            }

            if ($courseId <= 0) {
                $errors['course_id'] = 'Valid course must be selected.';
            }

            if (empty($errors)) {
                if ($action === 'add') {
                    $stmtAdd = $pdo->prepare("
                        INSERT INTO units (course_id, title, description, sort_order)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmtAdd->execute([$courseId, $title, $description, $sortOrder]);
                    log_admin_action('UNIT_CREATE', "Added unit '{$title}' to course ID {$courseId}.");
                    set_flash('success', "Unit '{$title}' added successfully.");
                } else {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE units 
                        SET course_id = ?, title = ?, description = ?, sort_order = ?
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([$courseId, $title, $description, $sortOrder, $unitId]);
                    log_admin_action('UNIT_UPDATE', "Updated unit ID {$unitId} ('{$title}').");
                    set_flash('success', "Unit '{$title}' updated successfully.");
                }
                header('Location: ' . app_url('admin/units.php?course=' . $courseId));
                exit;
            }
        } elseif ($action === 'delete') {
            $unitId = (int)($_POST['unit_id'] ?? 0);
            $courseId = (int)($_POST['course_id'] ?? 0);

            $stmtGet = $pdo->prepare("SELECT title FROM units WHERE id = ?");
            $stmtGet->execute([$unitId]);
            $uTitle = $stmtGet->fetchColumn();

            $stmtDel = $pdo->prepare("DELETE FROM units WHERE id = ?");
            $stmtDel->execute([$unitId]);
            log_admin_action('UNIT_DELETE', "Removed unit '{$uTitle}' (ID: {$unitId}).");
            set_flash('info', "Unit '{$uTitle}' deleted.");
            header('Location: ' . app_url('admin/units.php?course=' . $courseId));
            exit;
        }
    }
}

// Edit unit query
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtEdit = $pdo->prepare("SELECT * FROM units WHERE id = ?");
    $stmtEdit->execute([$editId]);
    $editUnit = $stmtEdit->fetch();
    if ($editUnit) {
        $selectedCourseId = (int)$editUnit['course_id'];
    }
}

// Fetch units for selected course with topic counts
$stmtUnits = $pdo->prepare("
    SELECT u.*, (SELECT COUNT(*) FROM topics t WHERE t.unit_id = u.id) AS topic_count
    FROM units u
    WHERE u.course_id = ?
    ORDER BY u.sort_order ASC, u.id ASC
");
$stmtUnits->execute([$selectedCourseId]);
$units = $stmtUnits->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Curriculum Units Management</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Organize learning units within courses and manage their sequence.
    </p>
</div>

<!-- Course Selector Bar -->
<div class="card" style="padding: 1.25rem; margin-bottom: 2rem;">
    <form method="GET" action="<?= e(app_url('admin/units.php')) ?>" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 280px;">
            <label for="course_select" class="form-label">Select Course</label>
            <select id="course_select" name="course" class="form-control" onchange="this.form.submit()">
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selectedCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                        [<?= e($c['code']) ?>] <?= e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-outline">Switch Course</button>
            <a href="<?= e(app_url('admin/requirements.php?course=' . $selectedCourseId)) ?>" class="btn btn-secondary">
                Prerequisite Rules &rarr;
            </a>
        </div>
    </form>
</div>

<div class="grid-3" style="align-items: flex-start;">
    <!-- Add / Edit Form Column -->
    <div class="card" style="grid-column: span 1;">
        <div class="card-header">
            <h2 class="card-title"><?= $editUnit ? 'Edit Unit' : 'Add New Unit' ?></h2>
            <?php if ($editUnit): ?>
                <a href="<?= e(app_url('admin/units.php?course=' . $selectedCourseId)) ?>" class="btn btn-outline btn-sm">Cancel</a>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= e(app_url('admin/units.php?course=' . $selectedCourseId)) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editUnit ? 'edit' : 'add' ?>">
            <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">
            <?php if ($editUnit): ?>
                <input type="hidden" name="unit_id" value="<?= (int)$editUnit['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="title" class="form-label">Unit Title <span style="color: var(--danger);">*</span></label>
                <input type="text" id="title" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" value="<?= e($editUnit['title'] ?? '') ?>" placeholder="e.g. Data Structures" required>
                <?php if (isset($errors['title'])): ?>
                    <span class="form-error"><?= e($errors['title']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="sort_order" class="form-label">Sequence / Sort Order</label>
                <input type="number" id="sort_order" name="sort_order" class="form-control" value="<?= (int)($editUnit['sort_order'] ?? (count($units) + 1)) ?>" min="1" required>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3" placeholder="Brief outline of unit objectives..."><?= e($editUnit['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <?= $editUnit ? 'Update Unit' : 'Add Unit to Course' ?>
            </button>
        </form>
    </div>

    <!-- Units Table Column -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title">Units for Selected Course (<?= count($units) ?>)</h2>
        </div>

        <?php if (empty($units)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No units defined for this course yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Unit Title &amp; Description</th>
                            <th>Topics</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($units as $u): ?>
                            <tr>
                                <td><strong>#<?= (int)$u['sort_order'] ?></strong></td>
                                <td>
                                    <strong><?= e($u['title']) ?></strong>
                                    <?php if (!empty($u['description'])): ?>
                                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                                            <?= e($u['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= e(app_url('admin/topics.php?unit=' . $u['id'])) ?>" class="badge badge-info" style="text-decoration: none;">
                                        <?= (int)$u['topic_count'] ?> Topics &rarr;
                                    </a>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                        <a href="<?= e(app_url('admin/topics.php?unit=' . $u['id'])) ?>" class="btn btn-secondary btn-sm" title="Add / View Topics">
                                            Topics
                                        </a>
                                        <a href="<?= e(app_url('admin/units.php?course=' . $selectedCourseId . '&edit=' . $u['id'])) ?>" class="btn btn-outline btn-sm">
                                            Edit
                                        </a>
                                        <form method="POST" action="<?= e(app_url('admin/units.php?course=' . $selectedCourseId)) ?>" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this unit and all its topics?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="unit_id" value="<?= (int)$u['id'] ?>">
                                            <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
