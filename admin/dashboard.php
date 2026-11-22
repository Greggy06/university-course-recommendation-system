<?php
/**
 * Admin Dashboard (FR-100, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_admin();

$pdo = get_db();
$pageTitle = 'Admin Dashboard';

// Totals (FR-100)
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'STUDENT'")->fetchColumn();
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalUnits = (int)$pdo->query("SELECT COUNT(*) FROM units")->fetchColumn();

// Calculate students with incomplete profiles (FR-100)
$stmtAllStudents = $pdo->query("SELECT id FROM users WHERE role = 'STUDENT'");
$allStudentIds = $stmtAllStudents->fetchAll(PDO::FETCH_COLUMN);
$incompleteCount = 0;
foreach ($allStudentIds as $sId) {
    $comp = check_profile_completeness($pdo, (int)$sId);
    if (!$comp['is_complete']) {
        $incompleteCount++;
    }
}

// 5 Newest Signups (FR-100)
$stmtNewest = $pdo->query("
    SELECT id, full_name, email, education_level, is_active, created_at
    FROM users
    WHERE role = 'STUDENT'
    ORDER BY created_at DESC, id DESC
    LIMIT 5
");
$newestStudents = $stmtNewest->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">System Administration Dashboard</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Platform overview, student statistics, and catalogue management tools.
    </p>
</div>

<!-- Stat Cards (FR-100) -->
<div class="grid-4" style="margin-bottom: 2.5rem;">
    <div class="stat-card">
        <span class="stat-label">Total Students</span>
        <div class="stat-value" style="color: var(--primary);"><?= $totalStudents ?></div>
    </div>

    <div class="stat-card">
        <span class="stat-label">Total Courses</span>
        <div class="stat-value" style="color: #0284c7;"><?= $totalCourses ?></div>
    </div>

    <div class="stat-card">
        <span class="stat-label">Categories / Units</span>
        <div class="stat-value" style="color: #7c3aed; font-size: 1.6rem; margin-top: 0.6rem;">
            <?= $totalCategories ?> <span style="font-size: 1rem; color: var(--text-muted); font-weight: normal;">cats</span> / <?= $totalUnits ?> <span style="font-size: 1rem; color: var(--text-muted); font-weight: normal;">units</span>
        </div>
    </div>

    <div class="stat-card" style="border-left: 4px solid <?= $incompleteCount > 0 ? 'var(--warning)' : 'var(--success)' ?>;">
        <span class="stat-label">Incomplete Profiles</span>
        <div class="stat-value" style="color: <?= $incompleteCount > 0 ? 'var(--warning)' : 'var(--success)' ?>;"><?= $incompleteCount ?></div>
    </div>
</div>

<!-- 5 Newest Signups Table (FR-100) -->
<div class="card" style="margin-bottom: 2.5rem;">
    <div class="card-header">
        <h2 class="card-title">5 Newest Student Signups</h2>
        <a href="<?= e(app_url('admin/students.php')) ?>" class="btn btn-outline btn-sm">Manage All Students &rarr;</a>
    </div>

    <?php if (empty($newestStudents)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No student accounts registered yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Email</th>
                        <th>Level</th>
                        <th>Profile Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($newestStudents as $s): ?>
                        <?php
                        $sComp = check_profile_completeness($pdo, (int)$s['id']);
                        ?>
                        <tr>
                            <td><strong><?= e($s['full_name']) ?></strong></td>
                            <td><?= e($s['email']) ?></td>
                            <td><span class="badge badge-info"><?= e($s['education_level'] ?: 'Not set') ?></span></td>
                            <td>
                                <?php if ($sComp['is_complete']): ?>
                                    <span class="badge badge-success">Complete</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Incomplete</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y H:i', strtotime($s['created_at'])) ?></td>
                            <td>
                                <a href="<?= e(app_url('admin/student_view.php?id=' . $s['id'])) ?>" class="btn btn-outline btn-sm">
                                    View Profile &amp; Rules
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Administration Modules Grid -->
<div class="grid-3">
    <div class="card">
        <h3 style="margin-top: 0; color: var(--primary);">Course Catalogue</h3>
        <p style="color: var(--secondary); font-size: 0.95rem;">Manage courses, degrees, and study categories. Set descriptions and duration.</p>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="<?= e(app_url('admin/courses.php')) ?>" class="btn btn-primary btn-sm">Courses</a>
            <a href="<?= e(app_url('admin/categories.php')) ?>" class="btn btn-outline btn-sm">Categories</a>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top: 0; color: var(--primary);">Curriculum Structure</h3>
        <p style="color: var(--secondary); font-size: 0.95rem;">Manage units and learning topics. Reorder units and write lesson content.</p>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="<?= e(app_url('admin/units.php')) ?>" class="btn btn-primary btn-sm">Units &amp; Topics</a>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top: 0; color: var(--primary);">Prerequisite Criteria</h3>
        <p style="color: var(--secondary); font-size: 0.95rem;">Configure secondary school subjects and tertiary degree program criteria.</p>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="<?= e(app_url('admin/subjects.php')) ?>" class="btn btn-primary btn-sm">Subjects</a>
            <a href="<?= e(app_url('admin/programs.php')) ?>" class="btn btn-outline btn-sm">Programs</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
