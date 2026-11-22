<?php
/**
 * System Reports & Analytics (FR-110, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_admin();

$pdo = get_db();
$pageTitle = 'System Reports';

// 1. Students per Education Level (FR-110)
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'STUDENT'")->fetchColumn();

$stmtLevels = $pdo->query("
    SELECT COALESCE(education_level, 'NOT_SET') AS level_name, COUNT(*) AS count
    FROM users
    WHERE role = 'STUDENT'
    GROUP BY education_level
    ORDER BY count DESC
");
$studentsByLevel = $stmtLevels->fetchAll();

// 2. Students per Program (FR-110)
$stmtPrograms = $pdo->query("
    SELECT p.name AS program_name, COUNT(sp.user_id) AS student_count
    FROM programs p
    LEFT JOIN student_profiles sp ON p.id = sp.program_id
    GROUP BY p.id
    ORDER BY student_count DESC, p.name ASC
");
$studentsByProgram = $stmtPrograms->fetchAll();

// 3. How often each course is recommended (FR-110)
// Fetch all active courses
$courses = $pdo->query("
    SELECT c.id, c.code, c.name, cat.name AS category_name
    FROM courses c
    JOIN categories cat ON c.category_id = cat.id
    WHERE c.is_active = 1
    ORDER BY cat.name ASC, c.name ASC
")->fetchAll();

// Fetch all students with complete profiles
$stmtAllStudents = $pdo->query("SELECT id FROM users WHERE role = 'STUDENT' AND is_active = 1");
$allStudentIds = $stmtAllStudents->fetchAll(PDO::FETCH_COLUMN);

$courseRecommendationCounts = [];
foreach ($courses as $c) {
    $courseRecommendationCounts[$c['id']] = 0;
}

$completeStudentsCount = 0;
foreach ($allStudentIds as $sId) {
    $rec = get_student_recommendations($pdo, (int)$sId);
    if ($rec['status'] === 'success') {
        $completeStudentsCount++;
        foreach ($rec['grouped_courses'] as $cat => $cList) {
            foreach ($cList as $rc) {
                if (isset($courseRecommendationCounts[$rc['id']])) {
                    $courseRecommendationCounts[$rc['id']]++;
                }
            }
        }
    }
}

// Attach recommendation count to courses and sort descending
foreach ($courses as &$c) {
    $c['rec_count'] = $courseRecommendationCounts[$c['id']] ?? 0;
    $c['rec_percentage'] = $completeStudentsCount > 0 ? round(($c['rec_count'] / $completeStudentsCount) * 100) : 0;
}
unset($c);

usort($courses, function($a, $b) {
    return $b['rec_count'] <=> $a['rec_count'];
});

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Academic Analytics &amp; Reports</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Demographics, program enrollment distributions, and course recommendation match metrics.
    </p>
</div>

<!-- Grid for Demographics -->
<div class="grid-2" style="margin-bottom: 2.5rem;">
    <!-- Report 1: Students per Education Level (FR-110) -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">1. Students by Education Level</h2>
            <span class="badge badge-info"><?= $totalStudents ?> Total</span>
        </div>

        <?php if (empty($studentsByLevel)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 1.5rem 0;">No student data available.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <?php foreach ($studentsByLevel as $lvl): ?>
                    <?php
                    $pct = $totalStudents > 0 ? round(((int)$lvl['count'] / $totalStudents) * 100) : 0;
                    $displayName = ($lvl['level_name'] === 'SECONDARY') ? 'Secondary School (High School)' : (($lvl['level_name'] === 'TERTIARY') ? 'Tertiary (University / College)' : 'Not Specified');
                    ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.95rem; margin-bottom: 0.35rem;">
                            <strong><?= e($displayName) ?></strong>
                            <span><?= (int)$lvl['count'] ?> students (<?= $pct ?>%)</span>
                        </div>
                        <div style="background: #e2e8f0; height: 10px; border-radius: 9999px; overflow: hidden;">
                            <div style="background: var(--primary); height: 100%; width: <?= $pct ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Report 2: Students per Program (FR-110) -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">2. Students by Tertiary Program</h2>
        </div>

        <?php if (empty($studentsByProgram)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 1.5rem 0;">No tertiary program data available.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <?php foreach ($studentsByProgram as $prog): ?>
                    <?php
                    $progCount = (int)$prog['student_count'];
                    $progPct = $totalStudents > 0 ? round(($progCount / $totalStudents) * 100) : 0;
                    ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.95rem; margin-bottom: 0.35rem;">
                            <strong><?= e($prog['program_name']) ?></strong>
                            <span><?= $progCount ?> students (<?= $progPct ?>%)</span>
                        </div>
                        <div style="background: #e2e8f0; height: 10px; border-radius: 9999px; overflow: hidden;">
                            <div style="background: #0284c7; height: 100%; width: <?= $progPct ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Report 3: Course Recommendation Frequency (FR-110) -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">3. Course Recommendation Frequency</h2>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                Evaluated against <?= $completeStudentsCount ?> student(s) currently having complete profiles.
            </div>
        </div>
    </div>

    <?php if (empty($courses)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No active courses found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Course Code &amp; Title</th>
                        <th>Category</th>
                        <th>Recommended To</th>
                        <th>Student Qualification Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $c): ?>
                        <tr>
                            <td>
                                <strong><?= e($c['code']) ?></strong> - <?= e($c['name']) ?>
                            </td>
                            <td><?= e($c['category_name']) ?></td>
                            <td>
                                <strong><?= (int)$c['rec_count'] ?></strong> / <?= $completeStudentsCount ?> student(s)
                            </td>
                            <td style="min-width: 200px;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="flex: 1; background: #e2e8f0; height: 10px; border-radius: 9999px; overflow: hidden;">
                                        <div style="background: var(--success); height: 100%; width: <?= (int)$c['rec_percentage'] ?>%;"></div>
                                    </div>
                                    <span style="font-size: 0.85rem; font-weight: 600; min-width: 38px;">
                                        <?= (int)$c['rec_percentage'] ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
