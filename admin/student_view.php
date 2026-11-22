<?php
/**
 * Student Profile & Recommendation Inspector (FR-107, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_admin();

$pdo = get_db();
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($studentId <= 0) {
    header('Location: ' . app_url('admin/students.php'));
    exit;
}

// Fetch student account
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'STUDENT'");
$stmtUser->execute([$studentId]);
$student = $stmtUser->fetch();

if (!$student) {
    set_flash('error', 'Student account not found.');
    header('Location: ' . app_url('admin/students.php'));
    exit;
}

$pageTitle = 'Student Profile: ' . $student['full_name'];

// Check completeness (FR-41, FR-42, FR-43)
$completeness = check_profile_completeness($pdo, $studentId);
$isComplete = $completeness['is_complete'];
$missingItems = $completeness['missing_items'];
$educationLevel = $student['education_level'];

// Fetch academic profile
$stmtProfile = $pdo->prepare("
    SELECT sp.*, p.name AS program_name 
    FROM student_profiles sp 
    LEFT JOIN programs p ON sp.program_id = p.id 
    WHERE sp.user_id = ?
");
$stmtProfile->execute([$studentId]);
$profile = $stmtProfile->fetch();

// Fetch subjects
$stmtSubjects = $pdo->prepare("
    SELECT s.name 
    FROM student_subjects ss 
    JOIN subjects s ON ss.subject_id = s.id 
    WHERE ss.user_id = ? 
    ORDER BY s.name ASC
");
$stmtSubjects->execute([$studentId]);
$subjects = $stmtSubjects->fetchAll(PDO::FETCH_COLUMN);

// Compute recommendations as seen by the system (FR-107)
$recommendations = get_student_recommendations($pdo, $studentId);

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="<?= e(app_url('admin/students.php')) ?>" class="btn btn-outline btn-sm">&larr; Back to Students Directory</a>
</div>

<!-- Header Card -->
<div class="card" style="padding: 2rem; margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 2rem; margin: 0 0 0.25rem; color: #1e3a8a;"><?= e($student['full_name']) ?></h1>
            <div style="font-size: 0.95rem; color: var(--text-muted);">
                Email: <strong><?= e($student['email']) ?></strong> &bull; Registered: <?= date('M d, Y H:i', strtotime($student['created_at'])) ?>
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <?php if ($student['is_active']): ?>
                <span class="badge badge-success" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Active Account</span>
            <?php else: ?>
                <span class="badge badge-danger" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Deactivated</span>
            <?php endif; ?>

            <?php if ($isComplete): ?>
                <span class="badge badge-success" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Profile Complete</span>
            <?php else: ?>
                <span class="badge badge-warning" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Profile Incomplete</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Academic Profile Details (FR-107) -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h2 class="card-title">Academic Qualifications Profile</h2>
    </div>

    <div class="grid-2">
        <div>
            <div style="margin-bottom: 1rem;">
                <strong style="color: var(--secondary); font-size: 0.9rem;">Education Level:</strong>
                <div style="font-size: 1.1rem; font-weight: 600; color: var(--text-main); margin-top: 0.2rem;">
                    <?= e($educationLevel ?: 'Not Selected') ?>
                </div>
            </div>

            <?php if ($educationLevel === 'SECONDARY'): ?>
                <div style="margin-bottom: 1rem;">
                    <strong style="color: var(--secondary); font-size: 0.9rem;">School Stream:</strong>
                    <div style="font-size: 1rem; color: var(--text-main); margin-top: 0.2rem;">
                        <?= e($profile['stream'] ?? 'Not specified') ?>
                    </div>
                </div>

                <div>
                    <strong style="color: var(--secondary); font-size: 0.9rem;">Subjects Completed (<?= count($subjects) ?>):</strong>
                    <?php if (empty($subjects)): ?>
                        <div style="color: #dc2626; font-size: 0.95rem; margin-top: 0.25rem;">No subjects selected.</div>
                    <?php else: ?>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem;">
                            <?php foreach ($subjects as $sName): ?>
                                <span class="badge badge-info" style="font-size: 0.85rem; padding: 0.35rem 0.65rem;">
                                    <?= e($sName) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($educationLevel === 'TERTIARY'): ?>
                <div style="margin-bottom: 1rem;">
                    <strong style="color: var(--secondary); font-size: 0.9rem;">Field of Study (Program):</strong>
                    <div style="font-size: 1.1rem; font-weight: 600; color: var(--text-main); margin-top: 0.2rem;">
                        <?= e($profile['program_name'] ?? 'None selected') ?>
                    </div>
                </div>

                <div>
                    <strong style="color: var(--secondary); font-size: 0.9rem;">Enrollment Status:</strong>
                    <div style="font-size: 1rem; color: var(--text-main); margin-top: 0.2rem;">
                        <?= e($profile['program_status'] ?? 'Not selected') ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem;">
                <h3 style="margin-top: 0; font-size: 1.05rem; color: var(--primary);">System Completeness Audit</h3>
                <?php if ($isComplete): ?>
                    <p style="color: #166534; font-size: 0.95rem; margin: 0;">
                        &#10003; Student profile satisfies all validation requirements for admission recommendation checking.
                    </p>
                <?php else: ?>
                    <div style="color: #991b1b; font-size: 0.95rem;">
                        <p style="margin: 0 0 0.5rem; font-weight: 600;">Recommendations are currently blocked:</p>
                        <ul style="margin: 0; padding-left: 1.25rem;">
                            <?php foreach ($missingItems as $m): ?>
                                <li><?= e($m) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recommendations System Simulation (FR-107) -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Eligible Courses (System Recommendations for this Student)</h2>
    </div>

    <?php if ($recommendations['status'] === 'incomplete'): ?>
        <div class="alert alert-warning" style="margin: 1rem 0;">
            Recommendations cannot be calculated because the student's profile is incomplete.
        </div>
    <?php elseif ($recommendations['status'] === 'no_matches'): ?>
        <div class="alert alert-info" style="margin: 1rem 0;">
            No courses currently match this student's qualifications.
        </div>
    <?php else: ?>
        <?php foreach ($recommendations['grouped_courses'] as $catName => $cList): ?>
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.2rem; color: var(--primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.35rem; margin-bottom: 0.75rem;">
                    <?= e($catName) ?>
                </h3>

                <div class="grid-2">
                    <?php foreach ($cList as $c): ?>
                        <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.25rem;">
                                <span class="course-code"><?= e($c['code']) ?></span>
                                <span class="badge badge-success">&#10003; Matches Rules</span>
                            </div>
                            <h4 style="margin: 0.25rem 0 0.5rem; font-size: 1.1rem; font-weight: 700;"><?= e($c['name']) ?></h4>
                            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                                <?= e($c['level']) ?> &bull; <?= e($c['duration']) ?>
                            </div>
                            <div class="match-reason-box" style="margin-bottom: 0;">
                                <strong>Match Reason:</strong> <?= e($c['match_reason']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
