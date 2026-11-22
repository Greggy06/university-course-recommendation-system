<?php
/**
 * Categories Management (FR-101, FR-112, FR-111, SEC-04, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db();
$pageTitle = 'Manage Categories';

$editCategory = null;
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security check failed. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

            if (empty($name)) {
                $errors['name'] = 'Category name cannot be empty.';
            } else {
                // Check name uniqueness
                $stmtCheck = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                $stmtCheck->execute([$name, $categoryId]);
                if ($stmtCheck->fetch()) {
                    $errors['name'] = 'A category with this name already exists.';
                }
            }

            if (empty($errors)) {
                if ($action === 'add') {
                    $stmtAdd = $pdo->prepare("INSERT INTO categories (name, description, is_active) VALUES (?, ?, 1)");
                    $stmtAdd->execute([$name, $description]);
                    log_admin_action('CATEGORY_CREATE', "Added new category '{$name}'.");
                    set_flash('success', "Category '{$name}' created successfully.");
                } else {
                    $stmtUpdate = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                    $stmtUpdate->execute([$name, $description, $categoryId]);
                    log_admin_action('CATEGORY_UPDATE', "Updated category ID {$categoryId} ('{$name}').");
                    set_flash('success', "Category '{$name}' updated successfully.");
                }
                header('Location: ' . app_url('admin/categories.php'));
                exit;
            }
        } elseif ($action === 'toggle_active') {
            // FR-112: Hide (inactive) instead of permanent delete
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $stmtGet = $pdo->prepare("SELECT name, is_active FROM categories WHERE id = ?");
            $stmtGet->execute([$categoryId]);
            $cat = $stmtGet->fetch();

            if ($cat) {
                $newStatus = $cat['is_active'] ? 0 : 1;
                $stmtToggle = $pdo->prepare("UPDATE categories SET is_active = ? WHERE id = ?");
                $stmtToggle->execute([$newStatus, $categoryId]);
                $statusLabel = $newStatus ? 'shown (active)' : 'hidden (inactive)';
                log_admin_action('CATEGORY_TOGGLE', "Changed category '{$cat['name']}' status to {$statusLabel}.");
                set_flash('info', "Category '{$cat['name']}' is now {$statusLabel}.");
            }
            header('Location: ' . app_url('admin/categories.php'));
            exit;
        }
    }
}

// Check if editing a category
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtEdit = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmtEdit->execute([$editId]);
    $editCategory = $stmtEdit->fetch();
}

// Fetch all categories with course count
$stmt = $pdo->query("
    SELECT c.*, COUNT(co.id) AS course_count
    FROM categories c
    LEFT JOIN courses co ON c.id = co.category_id
    GROUP BY c.id
    ORDER BY c.id ASC
");
$categories = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Course Categories</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Manage the primary academic disciplines under which degree and diploma programs are catalogued.
    </p>
</div>

<div class="grid-3" style="align-items: flex-start;">
    <!-- Add / Edit Form Column -->
    <div class="card" style="grid-column: span 1;">
        <div class="card-header">
            <h2 class="card-title"><?= $editCategory ? 'Edit Category' : 'Add New Category' ?></h2>
            <?php if ($editCategory): ?>
                <a href="<?= e(app_url('admin/categories.php')) ?>" class="btn btn-outline btn-sm">Cancel</a>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= e(app_url('admin/categories.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editCategory ? 'edit' : 'add' ?>">
            <?php if ($editCategory): ?>
                <input type="hidden" name="category_id" value="<?= (int)$editCategory['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="name" class="form-label">Category Name <span style="color: var(--danger);">*</span></label>
                <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($editCategory['name'] ?? '') ?>" placeholder="e.g. Health Sciences" required>
                <?php if (isset($errors['name'])): ?>
                    <span class="form-error"><?= e($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4" placeholder="Brief summary of study topics covered in this area..."><?= e($editCategory['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <?= $editCategory ? 'Update Category' : 'Create Category' ?>
            </button>
        </form>
    </div>

    <!-- Table Column (FR-101, FR-112) -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title">All Categories</h2>
            <span class="badge badge-secondary"><?= count($categories) ?> Total</span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Courses</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= (int)$cat['id'] ?></td>
                            <td>
                                <strong><?= e($cat['name']) ?></strong>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                                    <?= e($cat['description']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= (int)$cat['course_count'] ?></span>
                            </td>
                            <td>
                                <?php if ($cat['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <a href="<?= e(app_url('admin/categories.php?edit=' . $cat['id'])) ?>" class="btn btn-outline btn-sm">Edit</a>
                                    
                                    <!-- FR-112: Hide/Show Toggle button -->
                                    <form method="POST" action="<?= e(app_url('admin/categories.php')) ?>" style="margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                                        <button type="submit" class="btn <?= $cat['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-sm">
                                            <?= $cat['is_active'] ? 'Hide' : 'Show' ?>
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
