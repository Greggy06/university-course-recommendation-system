<?php
/**
 * Administrator Activity Audit Log (FR-111, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db();
$pageTitle = 'Activity Log';

$search = trim($_GET['search'] ?? '');

$query = "
    SELECT al.*, u.full_name AS admin_name, u.email AS admin_email
    FROM activity_log al
    JOIN users u ON al.admin_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (al.action LIKE ? OR al.details LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term];
}

$query .= " ORDER BY al.created_at DESC, al.id DESC LIMIT 100";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Administrator Activity Audit Log</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Immutable audit trail recording administrative configuration changes, status updates, and criteria edits (FR-111).
    </p>
</div>

<!-- Search Bar -->
<div class="card" style="padding: 1.25rem; margin-bottom: 2rem;">
    <form method="GET" action="<?= e(app_url('admin/log.php')) ?>" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 250px;">
            <label for="search" class="form-label">Search Activity Logs</label>
            <input type="text" id="search" name="search" class="form-control" value="<?= e($search) ?>" placeholder="Search by action, keyword, or administrator name...">
        </div>
        <div>
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if (!empty($search)): ?>
                <a href="<?= e(app_url('admin/log.php')) ?>" class="btn btn-outline" style="margin-left: 0.5rem;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Audit Events (<?= count($logs) ?>)</h2>
    </div>

    <?php if (empty($logs)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No audit events recorded yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Administrator</th>
                        <th>Action Type</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 0.85rem; color: var(--text-muted);">
                                <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td>
                                <strong><?= e($log['admin_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($log['admin_email']) ?></div>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= e($log['action']) ?></span>
                            </td>
                            <td style="font-size: 0.9rem; color: var(--text-main);">
                                <?= e($log['details']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
