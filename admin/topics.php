<?php
/**
 * Lesson Topics Management (FR-103, FR-53, FR-111, SEC-04, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db();
$pageTitle = 'Manage Topics';

// Fetch all units for selector
$units = $pdo->query("
    SELECT u.id, u.title, c.code AS course_code, c.name AS course_name, c.id AS course_id
    FROM units u
    JOIN courses c ON u.course_id = c.id
    ORDER BY c.code ASC, u.sort_order ASC
")->fetchAll();

$selectedUnitId = isset($_GET['unit']) ? (int)$_GET['unit'] : ($units[0]['id'] ?? 0);
$editTopic = null;
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $topicId = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
            $unitId = (int)($_POST['unit_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $resourceUrl = trim($_POST['resource_url'] ?? '');
            $sortOrder = (int)($_POST['sort_order'] ?? 1);

            if (empty($title)) {
                $errors['title'] = 'Topic title is required.';
            }

            if ($unitId <= 0) {
                $errors['unit_id'] = 'Valid unit must be selected.';
            }

            if (empty($errors)) {
                $validResource = !empty($resourceUrl) ? $resourceUrl : null;

                if ($action === 'add') {
                    $stmtAdd = $pdo->prepare("
                        INSERT INTO topics (unit_id, title, content, resource_url, sort_order)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmtAdd->execute([$unitId, $title, $content, $validResource, $sortOrder]);
                    log_admin_action('TOPIC_CREATE', "Added topic '{$title}' to unit ID {$unitId}.");
                    set_flash('success', "Topic '{$title}' created successfully.");
                } else {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE topics 
                        SET unit_id = ?, title = ?, content = ?, resource_url = ?, sort_order = ?
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([$unitId, $title, $content, $validResource, $sortOrder, $topicId]);
                    log_admin_action('TOPIC_UPDATE', "Updated topic ID {$topicId} ('{$title}').");
                    set_flash('success', "Topic '{$title}' updated successfully.");
                }
                header('Location: ' . app_url('admin/topics.php?unit=' . $unitId));
                exit;
            }
        } elseif ($action === 'delete') {
            $topicId = (int)($_POST['topic_id'] ?? 0);
            $unitId = (int)($_POST['unit_id'] ?? 0);

            $stmtGet = $pdo->prepare("SELECT title FROM topics WHERE id = ?");
            $stmtGet->execute([$topicId]);
            $tTitle = $stmtGet->fetchColumn();

            $stmtDel = $pdo->prepare("DELETE FROM topics WHERE id = ?");
            $stmtDel->execute([$topicId]);
            log_admin_action('TOPIC_DELETE', "Deleted topic '{$tTitle}' (ID: {$topicId}).");
            set_flash('info', "Topic '{$tTitle}' deleted.");
            header('Location: ' . app_url('admin/topics.php?unit=' . $unitId));
            exit;
        }
    }
}

// Edit query
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtEdit = $pdo->prepare("SELECT * FROM topics WHERE id = ?");
    $stmtEdit->execute([$editId]);
    $editTopic = $stmtEdit->fetch();
    if ($editTopic) {
        $selectedUnitId = (int)$editTopic['unit_id'];
    }
}

// Fetch selected unit details
$stmtUnitInfo = $pdo->prepare("
    SELECT u.*, c.name AS course_name, c.code AS course_code, c.id AS course_id
    FROM units u
    JOIN courses c ON u.course_id = c.id
    WHERE u.id = ?
");
$stmtUnitInfo->execute([$selectedUnitId]);
$currentUnit = $stmtUnitInfo->fetch();

// Fetch topics for selected unit
$stmtTopics = $pdo->prepare("
    SELECT * FROM topics 
    WHERE unit_id = ? 
    ORDER BY sort_order ASC, id ASC
");
$stmtTopics->execute([$selectedUnitId]);
$topics = $stmtTopics->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Lesson Topics Management</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Write, edit, and sequence lesson topics, text explanations, and multimedia links.
    </p>
</div>

<!-- Unit Selector Bar -->
<div class="card" style="padding: 1.25rem; margin-bottom: 2rem;">
    <form method="GET" action="<?= e(app_url('admin/topics.php')) ?>" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 280px;">
            <label for="unit_select" class="form-label">Select Unit</label>
            <select id="unit_select" name="unit" class="form-control" onchange="this.form.submit()">
                <?php foreach ($units as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= $selectedUnitId === (int)$u['id'] ? 'selected' : '' ?>>
                        [<?= e($u['course_code']) ?>] <?= e($u['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-outline">Switch Unit</button>
            <?php if ($currentUnit): ?>
                <a href="<?= e(app_url('admin/units.php?course=' . $currentUnit['course_id'])) ?>" class="btn btn-secondary">
                    &larr; Units List
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="grid-3" style="align-items: flex-start;">
    <!-- Add / Edit Form Column -->
    <div class="card" style="grid-column: span 1;">
        <div class="card-header">
            <h2 class="card-title"><?= $editTopic ? 'Edit Topic' : 'Add New Topic' ?></h2>
            <?php if ($editTopic): ?>
                <a href="<?= e(app_url('admin/topics.php?unit=' . $selectedUnitId)) ?>" class="btn btn-outline btn-sm">Cancel</a>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= e(app_url('admin/topics.php?unit=' . $selectedUnitId)) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editTopic ? 'edit' : 'add' ?>">
            <input type="hidden" name="unit_id" value="<?= $selectedUnitId ?>">
            <?php if ($editTopic): ?>
                <input type="hidden" name="topic_id" value="<?= (int)$editTopic['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="title" class="form-label">Topic Title <span style="color: var(--danger);">*</span></label>
                <input type="text" id="title" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" value="<?= e($editTopic['title'] ?? '') ?>" placeholder="e.g. Memory Layout & Pointers" required>
                <?php if (isset($errors['title'])): ?>
                    <span class="form-error"><?= e($errors['title']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="sort_order" class="form-label">Sequence / Sort Order</label>
                <input type="number" id="sort_order" name="sort_order" class="form-control" value="<?= (int)($editTopic['sort_order'] ?? (count($topics) + 1)) ?>" min="1" required>
            </div>

            <div class="form-group">
                <label for="resource_url" class="form-label">Resource Link (Video or File URL)</label>
                <input type="url" id="resource_url" name="resource_url" class="form-control" value="<?= e($editTopic['resource_url'] ?? '') ?>" placeholder="https://example.com/lecture-video">
                <span class="form-hint">Optional external URL.</span>
            </div>

            <div class="form-group">
                <label for="content" class="form-label">Lesson Learning Content</label>
                <textarea id="content" name="content" class="form-control" rows="8" placeholder="Enter lesson text, explanations, formulas, or guidelines..."><?= e($editTopic['content'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <?= $editTopic ? 'Update Topic' : 'Add Topic to Unit' ?>
            </button>
        </form>
    </div>

    <!-- Topics Table Column -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Topics in <?= e($currentUnit['title'] ?? 'Selected Unit') ?></h2>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                    Course: <?= e($currentUnit['course_code'] ?? '') ?> &bull; <?= e($currentUnit['course_name'] ?? '') ?>
                </div>
            </div>
            <span class="badge badge-secondary"><?= count($topics) ?> Topics</span>
        </div>

        <?php if (empty($topics)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No topics added to this unit yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Title &amp; Resource</th>
                            <th>Preview</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topics as $t): ?>
                            <tr>
                                <td><strong>#<?= (int)$t['sort_order'] ?></strong></td>
                                <td>
                                    <strong><?= e($t['title']) ?></strong>
                                    <?php if (!empty($t['resource_url'])): ?>
                                        <div style="margin-top: 0.25rem;">
                                            <a href="<?= e($t['resource_url']) ?>" target="_blank" class="badge badge-info" style="font-size: 0.75rem; text-decoration: none;">
                                                &nearr; External Link
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 260px; font-size: 0.85rem; color: var(--secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= e($t['content']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                        <a href="<?= e(app_url('admin/topics.php?unit=' . $selectedUnitId . '&edit=' . $t['id'])) ?>" class="btn btn-outline btn-sm">
                                            Edit
                                        </a>
                                        <form method="POST" action="<?= e(app_url('admin/topics.php?unit=' . $selectedUnitId)) ?>" style="margin: 0;" onsubmit="return confirm('Delete this lesson topic?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="topic_id" value="<?= (int)$t['id'] ?>">
                                            <input type="hidden" name="unit_id" value="<?= $selectedUnitId ?>">
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
