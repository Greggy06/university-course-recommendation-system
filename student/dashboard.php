<?php
/**
 * Student Dashboard (FR-90 to FR-94, FR-80, FR-81, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_student();

$pdo = get_db();
$userId = (int)$_SESSION['user_id'];
$pageTitle = 'Student Dashboard';

// Check profile completeness (FR-41, FR-91)
$completeness = check_profile_completeness($pdo, $userId);
$isComplete = $completeness['is_complete'];
$missingItems = $completeness['missing_items'];
$educationLevel = $completeness['education_level'];

// Fetch student academic details for summary
$studentProfile = null;
$chosenSubjects = [];
if ($educationLevel === 'SECONDARY') {
    $stmtSubs = $pdo->prepare("
        SELECT s.name 
        FROM student_subjects ss 
        JOIN subjects s ON ss.subject_id = s.id 
        WHERE ss.user_id = ? 
        ORDER BY s.name ASC
    ");
    $stmtSubs->execute([$userId]);
    $chosenSubjects = $stmtSubs->fetchAll(PDO::FETCH_COLUMN);

    $stmtProf = $pdo->prepare("SELECT stream FROM student_profiles WHERE user_id = ?");
    $stmtProf->execute([$userId]);
    $studentProfile = $stmtProf->fetch();
} elseif ($educationLevel === 'TERTIARY') {
    $stmtProf = $pdo->prepare("
        SELECT sp.stream, sp.program_status, p.name AS program_name 
        FROM student_profiles sp 
        LEFT JOIN programs p ON sp.program_id = p.id 
        WHERE sp.user_id = ?
    ");
    $stmtProf->execute([$userId]);
    $studentProfile = $stmtProf->fetch();
}

// Fetch recommendations (FR-92, FR-80, FR-81)
$recommendations = get_student_recommendations($pdo, $userId);

// Fetch recently viewed courses (FR-94)
$stmtRecent = $pdo->prepare("
    SELECT c.id, c.code, c.name, cat.name AS category_name, rv.viewed_at
    FROM recently_viewed rv
    JOIN courses c ON rv.course_id = c.id
    JOIN categories cat ON c.category_id = cat.id
    WHERE rv.user_id = ? AND c.is_active = 1
    ORDER BY rv.viewed_at DESC
    LIMIT 4
");
$stmtRecent->execute([$userId]);
$recentCourses = $stmtRecent->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner (FR-90) -->
<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">
        Welcome back, <?= e($_SESSION['user_name']) ?>!
    </h1>
    <p style="color: var(--text-muted); margin: 0;">
        Education Level: <strong><?= e($educationLevel ?: 'Not set') ?></strong>
        <?php if ($educationLevel === 'SECONDARY' && !empty($studentProfile['stream'])): ?>
            &bull; Stream: <strong><?= e($studentProfile['stream']) ?></strong>
        <?php endif; ?>
    </p>
</div>

<!-- Profile Status Banner (FR-91, FR-80) -->
<div class="card" style="margin-bottom: 2rem; border-left: 6px solid <?= $isComplete ? 'var(--success)' : 'var(--danger)' ?>;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
                <h2 style="font-size: 1.25rem; margin: 0;">Academic Profile Status:</h2>
                <?php if ($isComplete): ?>
                    <span class="badge badge-success" style="font-size: 0.85rem; padding: 0.35rem 0.75rem;">Complete</span>
                <?php else: ?>
                    <span class="badge badge-danger" style="font-size: 0.85rem; padding: 0.35rem 0.75rem;">Incomplete</span>
                <?php endif; ?>
            </div>
            
            <?php if ($isComplete): ?>
                <p style="color: var(--secondary); margin: 0; font-size: 0.95rem;">
                    <?php if ($educationLevel === 'SECONDARY'): ?>
                        You have selected <strong><?= count($chosenSubjects) ?> subjects</strong>: <?= e(implode(', ', $chosenSubjects)) ?>.
                    <?php elseif ($educationLevel === 'TERTIARY'): ?>
                        Enrolled Program: <strong><?= e($studentProfile['program_name'] ?? 'N/A') ?></strong> (<?= e($studentProfile['program_status'] ?? 'N/A') ?>).
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <div style="color: #991b1b; font-size: 0.95rem; margin-top: 0.5rem;">
                    <p style="margin: 0 0 0.25rem; font-weight: 600;">Recommendations are currently locked because your profile is incomplete:</p>
                    <ul style="margin: 0; padding-left: 1.25rem;">
                        <?php foreach ($missingItems as $item): ?>
                            <li><?= e($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <a href="<?= e(app_url('student/profile.php')) ?>" class="btn <?= $isComplete ? 'btn-outline' : 'btn-primary' ?>">
                <?= $isComplete ? 'Edit Profile' : 'Complete Your Profile &rarr;' ?>
            </a>
        </div>
    </div>
</div>

<!-- Recommended Courses Section (FR-92, FR-80, FR-81) -->
<section style="margin-bottom: 2.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h2 style="font-size: 1.6rem; margin: 0; color: #1e3a8a;">Your Recommended Courses</h2>
            <p style="color: var(--text-muted); margin: 0.25rem 0 0;">Degrees and diplomas you meet all prerequisites for.</p>
        </div>
        <?php if ($isComplete && $recommendations['status'] === 'success'): ?>
            <a href="<?= e(app_url('student/recommended.php')) ?>" class="btn btn-outline btn-sm">View Full List &rarr;</a>
        <?php endif; ?>
    </div>

    <?php if (!$isComplete): ?>
        <!-- FR-80: Incomplete profile blocks recommendations -->
        <div class="card" style="text-align: center; padding: 2.5rem 1.5rem; background: #fffaf0; border-color: #feebc8;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem; color: #dd6b20;">&#9888;</div>
            <h3 style="margin-top: 0; color: #9c4221;">Recommendations Blocked</h3>
            <p style="max-width: 550px; margin: 0 auto 1.5rem; color: #7b341e;">
                To view which university courses you qualify for, please complete your academic profile by selecting your subjects or study program.
            </p>
            <a href="<?= e(app_url('student/profile.php')) ?>" class="btn btn-warning">Complete Profile Now</a>
        </div>

    <?php elseif ($recommendations['status'] === 'no_matches'): ?>
        <!-- FR-81: Complete profile but no course matches -->
        <div class="card" style="text-align: center; padding: 2.5rem 1.5rem; background: #f8fafc;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem; color: #64748b;">&#128269;</div>
            <h3 style="margin-top: 0; color: #1e293b;">No courses match your details yet</h3>
            <p style="max-width: 550px; margin: 0 auto 1.5rem; color: var(--secondary);">
                None of the active degree programs match your current combination of qualifications. You can check your subjects on your profile, or browse all courses in the catalogue to see specific requirements.
            </p>
            <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                <a href="<?= e(app_url('student/profile.php')) ?>" class="btn btn-outline">Check Your Subjects</a>
                <a href="<?= e(app_url('student/courses.php')) ?>" class="btn btn-primary">Browse Course Catalogue</a>
            </div>
        </div>

    <?php else: ?>
        <!-- Render Recommended Courses Grouped by Category (FR-73, FR-74) -->
        <?php foreach ($recommendations['grouped_courses'] as $categoryName => $courses): ?>
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.25rem; color: var(--primary); border-bottom: 2px solid #e2e8f0; padding-bottom: 0.35rem; margin-bottom: 1rem;">
                    <?= e($categoryName) ?>
                </h3>

                <div class="grid-2">
                    <?php foreach ($courses as $c): ?>
                        <div class="course-card">
                            <div class="course-card-top">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <span class="course-code"><?= e($c['code']) ?></span>
                                    <span class="badge badge-success">&#10003; Recommended</span>
                                </div>
                                <h4 class="course-title"><?= e($c['name']) ?></h4>
                                <div class="course-meta">
                                    <span><strong>Level:</strong> <?= e($c['level']) ?></span>
                                    <span>&bull;</span>
                                    <span><strong>Duration:</strong> <?= e($c['duration']) ?></span>
                                </div>
                                <p class="course-desc"><?= e($c['description']) ?></p>

                                <!-- FR-74: Why it matches -->
                                <div class="match-reason-box">
                                    <strong>Why this matches:</strong> <?= e($c['match_reason']) ?>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: auto;">
                                <a href="<?= e(app_url('student/course.php?id=' . $c['id'])) ?>" class="btn btn-primary btn-sm">
                                    View Course & Lessons &rarr;
                                </a>
                                <form method="POST" action="<?= e(app_url('student/interests.php')) ?>" style="margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button type="submit" class="btn btn-outline btn-sm" title="Save to My Interests">
                                        &#9734; Save
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- Recently Viewed Courses (FR-94) -->
<?php if (!empty($recentCourses)): ?>
    <section style="margin-top: 3rem;">
        <h3 style="font-size: 1.25rem; color: #1e3a8a; margin-bottom: 1rem;">Recently Viewed Courses</h3>
        <div class="grid-4">
            <?php foreach ($recentCourses as $rc): ?>
                <div class="card" style="margin-bottom: 0; padding: 1rem;">
                    <span class="course-code" style="font-size: 0.75rem;"><?= e($rc['code']) ?></span>
                    <h4 style="margin: 0.25rem 0 0.5rem; font-size: 1rem; font-weight: 700;">
                        <a href="<?= e(app_url('student/course.php?id=' . $rc['id'])) ?>"><?= e($rc['name']) ?></a>
                    </h4>
                    <span style="font-size: 0.8rem; color: var(--text-muted);"><?= e($rc['category_name']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
