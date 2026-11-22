<?php
/**
 * Course Catalogue Management (FR-102, FR-64, FR-112, FR-111, T-19, SEC-04, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_admin();

$pdo = get_db();
$pageTitle = 'Manage Courses';

$editCourse = null;
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token invalid. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $courseId = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $level = trim($_POST['level'] ?? '');
            $duration = trim($_POST['duration'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (empty($code)) {
                $errors['code'] = 'Course code is required.';
            } else {
                // Check code uniqueness
                $stmtCheck = $pdo->prepare("SELECT id FROM courses WHERE code = ? AND id != ?");
                $stmtCheck->execute([$code, $courseId]);
                if ($stmtCheck->fetch()) {
                    $errors['code'] = 'A course with this code already exists.';
                }
            }

            if (empty($name)) {
                $errors['name'] = 'Course title is required.';
            }

            if ($categoryId <= 0) {
                $errors['category_id'] = 'Please select a valid category.';
            }

            if (empty($errors)) {
                if ($action === 'add') {
                    $stmtAdd = $pdo->prepare("
                        INSERT INTO courses (category_id, code, name, description, level, duration, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtAdd->execute([$categoryId, $code, $name, $description, $level, $duration, $isActive]);
                    $courseId = (int)$pdo->lastInsertId();
                    log_admin_action('COURSE_CREATE', "Created course '{$name}' ({$code}).");
                    set_flash('success', "Course '{$name}' created successfully.");
                } else {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE courses 
                        SET category_id = ?, code = ?, name = ?, description = ?, level = ?, duration = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([$categoryId, $code, $name, $description, $level, $duration, $isActive, $courseId]);
                    log_admin_action('COURSE_UPDATE', "Updated course ID {$courseId} ('{$name}').");
                    set_flash('success', "Course '{$name}' updated successfully.");
                }

                // FR-64 & T-19: Warn admin when saving an active course that has no rules for a level
                if ($isActive === 1) {
                    $secCount = (int)$pdo->query("SELECT COUNT(*) FROM course_requirements WHERE course_id = {$courseId} AND applies_to = 'SECONDARY'")->fetchColumn();
                    $tertCount = (int)$pdo->query("SELECT COUNT(*) FROM course_requirements WHERE course_id = {$courseId} AND applies_to = 'TERTIARY'")->fetchColumn();

                    $warningLevels = [];
                    if ($secCount === 0) $warningLevels[] = 'Secondary';
                    if ($tertCount === 0) $warningLevels[] = 'Tertiary';

                    if (!empty($warningLevels)) {
                        $warnMsg = "Warning: Course '{$name}' is Active but has no prerequisite rules configured for " . implode(' and ', $warningLevels) . " students. A course with no rules for a level is NEVER recommended to that level (FR-64). Please configure prerequisite rules.";
                        set_flash('warning', $warnMsg);
                    }
                }

                header('Location: ' . app_url('admin/courses.php'));
                exit;
            }
        } elseif ($action === 'toggle_active') {
            // FR-112: Hide (inactive) instead of permanent delete
            $courseId = (int)($_POST['course_id'] ?? 0);
            $stmtGet = $pdo->prepare("SELECT name, is_active FROM courses WHERE id = ?");
            $stmtGet->execute([$courseId]);
            $c = $stmtGet->fetch();

            if ($c) {
                $newStatus = $c['is_active'] ? 0 : 1;
                $stmtToggle = $pdo->prepare("UPDATE courses SET is_active = ? WHERE id = ?");
                $stmtToggle->execute([$newStatus, $courseId]);
                $statusLabel = $newStatus ? 'active' : 'hidden (inactive)';
                log_admin_action('COURSE_TOGGLE', "Changed course '{$c['name']}' status to {$statusLabel}.");
                set_flash('info', "Course '{$c['name']}' is now {$statusLabel}.");

                // Check rule warning on reactivation (FR-64)
                if ($newStatus === 1) {
                    $secCount = (int)$pdo->query("SELECT COUNT(*) FROM course_requirements WHERE course_id = {$courseId} AND applies_to = 'SECONDARY'")->fetchColumn();
                    $tertCount = (int)$pdo->query("SELECT COUNT(*) FROM course_requirements WHERE course_id = {$courseId} AND applies_to = 'TERTIARY'")->fetchColumn();
                    $warningLevels = [];
                    if ($secCount === 0) $warningLevels[] = 'Secondary';
                    if ($tertCount === 0) $warningLevels[] = 'Tertiary';
                    if (!empty($warningLevels)) {
                        set_flash('warning', "Notice: Activated course '{$c['name']}' currently has no rules for " . implode(' and ', $warningLevels) . " level. It will not be recommended until rules are configured.");
                    }
                }
            }
            header('Location: ' . app_url('admin/courses.php'));
            exit;
        }
    }
}

// Check if editing a course
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtEdit = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmtEdit->execute([$editId]);
    $editCourse = $stmtEdit->fetch();
}

// Fetch categories for select dropdown
$allCategories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Fetch courses list with unit counts and rule counts
$stmt = $pdo->query("
    SELECT c.*, cat.name AS category_name,
        (SELECT COUNT(*) FROM units u WHERE u.course_id = c.id) AS unit_count,
        (SELECT COUNT(*) FROM course_requirements cr WHERE cr.course_id = c.id AND cr.applies_to = 'SECONDARY') AS sec_rule_count,
        (SELECT COUNT(*) FROM course_requirements cr WHERE cr.course_id = c.id AND cr.applies_to = 'TERTIARY') AS tert_rule_count
    FROM courses c
    JOIN categories cat ON c.category_id = cat.id
    ORDER BY cat.name ASC, c.name ASC
");
$courses = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Course Management</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Create and maintain courses, configure admission prerequisite rules, and structure curriculum units.
    </p>
</div>

<!-- Add / Edit Form Card -->
<div class="card" style="margin-bottom: 2.5rem;">
    <div class="card-header">
        <h2 class="card-title"><?= $editCourse ? 'Edit Course: ' . e($editCourse['name']) : 'Add New Course' ?></h2>
        <?php if ($editCourse): ?>
            <a href="<?= e(app_url('admin/courses.php')) ?>" class="btn btn-outline btn-sm">Cancel Edit</a>
        <?php endif; ?>
    </div>

    <form method="POST" action="<?= e(app_url('admin/courses.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editCourse ? 'edit' : 'add' ?>">
        <?php if ($editCourse): ?>
            <input type="hidden" name="course_id" value="<?= (int)$editCourse['id'] ?>">
        <?php endif; ?>

        <div class="grid-3">
            <div class="form-group">
                <label for="code" class="form-label">Course Code <span style="color: var(--danger);">*</span></label>
                <input type="text" id="code" name="code" class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" value="<?= e($editCourse['code'] ?? '') ?>" placeholder="e.g. BSC-CS" required>
                <?php if (isset($errors['code'])): ?>
                    <span class="form-error"><?= e($errors['code']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group" style="grid-column: span 2;">
                <label for="name" class="form-label">Course Title <span style="color: var(--danger);">*</span></label>
                <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($editCourse['name'] ?? '') ?>" placeholder="e.g. Bachelor of Science in Computer Science" required>
                <?php if (isset($errors['name'])): ?>
                    <span class="form-error"><?= e($errors['name']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid-3">
            <div class="form-group">
                <label for="category_id" class="form-label">Category <span style="color: var(--danger);">*</span></label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= (int)($editCourse['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="level" class="form-label">Award Level</label>
                <input type="text" id="level" name="level" class="form-control" value="<?= e($editCourse['level'] ?? 'Undergraduate Degree') ?>" placeholder="e.g. Undergraduate Degree, Diploma">
            </div>

            <div class="form-group">
                <label for="duration" class="form-label">Duration</label>
                <input type="text" id="duration" name="duration" class="form-control" value="<?= e($editCourse['duration'] ?? '3 Years') ?>" placeholder="e.g. 3 Years, 4 Years">
            </div>
        </div>

        <div class="form-group">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" class="form-control" rows="3" placeholder="Overview of the course program and learning goals..."><?= e($editCourse['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label style="cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="is_active" value="1" <?= (!isset($editCourse) || !empty($editCourse['is_active'])) ? 'checked' : '' ?> style="width: 1.15rem; height: 1.15rem;">
                <strong>Active in Catalogue</strong> <span class="form-hint">(When checked, course is visible and evaluable for recommendation)</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary">
            <?= $editCourse ? 'Update Course' : 'Create Course' ?>
        </button>
    </form>
</div>

<!-- Courses List Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">All Courses (<?= count($courses) ?>)</h2>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Course Title &amp; Category</th>
                    <th>Units</th>
                    <th>Rules (Sec / Tert)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): ?>
                    <?php
                    $hasMissingRules = ((int)$c['is_active'] === 1 && ((int)$c['sec_rule_count'] === 0 || (int)$c['tert_rule_count'] === 0));
                    ?>
                    <tr>
                        <td><strong><?= e($c['code']) ?></strong></td>
                        <td>
                            <strong><?= e($c['name']) ?></strong>
                            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                                <?= e($c['category_name']) ?> &bull; <?= e($c['level']) ?> &bull; <?= e($c['duration']) ?>
                            </div>
                        </td>
                        <td>
                            <a href="<?= e(app_url('admin/units.php?course=' . $c['id'])) ?>" class="badge badge-info" style="text-decoration: none;">
                                <?= (int)$c['unit_count'] ?> Units
                            </a>
                        </td>
                        <td>
                            <a href="<?= e(app_url('admin/requirements.php?course=' . $c['id'])) ?>" style="text-decoration: none;">
                                <span class="badge <?= (int)$c['sec_rule_count'] > 0 ? 'badge-success' : 'badge-warning' ?>" title="Secondary rules">
                                    Sec: <?= (int)$c['sec_rule_count'] ?>
                                </span>
                                <span class="badge <?= (int)$c['tert_rule_count'] > 0 ? 'badge-success' : 'badge-warning' ?>" title="Tertiary rules">
                                    Tert: <?= (int)$c['tert_rule_count'] ?>
                                </span>
                            </a>
                            <?php if ($hasMissingRules): ?>
                                <div style="font-size: 0.75rem; color: #b45309; margin-top: 0.25rem;">
                                    &#9888; Missing level rule
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Hidden</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                <a href="<?= e(app_url('admin/courses.php?edit=' . $c['id'])) ?>" class="btn btn-outline btn-sm">Edit</a>
                                <a href="<?= e(app_url('admin/requirements.php?course=' . $c['id'])) ?>" class="btn btn-secondary btn-sm" title="Edit prerequisite rules">Rules</a>
                                <a href="<?= e(app_url('admin/units.php?course=' . $c['id'])) ?>" class="btn btn-secondary btn-sm" title="Manage curriculum units">Units</a>

                                <!-- FR-112: Toggle Hide/Show -->
                                <form method="POST" action="<?= e(app_url('admin/courses.php')) ?>" style="margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn <?= $c['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-sm">
                                        <?= $c['is_active'] ? 'Hide' : 'Show' ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
