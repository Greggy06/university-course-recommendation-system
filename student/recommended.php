<?php
/**
 * Student Recommended Courses Page (FR-70 to FR-77, FR-80 to FR-83, SEC-06, SEC-07)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_student();

$pdo = get_db();
$userId = (int)$_SESSION['user_id'];
$pageTitle = 'Recommended Courses';

// Fetch recommendations (checks profile completeness, compares rules in PHP server)
$recommendations = get_student_recommendations($pdo, $userId);

// Fetch saved interests for this student to show toggled state
$stmtInt = $pdo->prepare("SELECT course_id FROM student_interests WHERE user_id = ?");
$stmtInt->execute([$userId]);
$savedCourseIds = array_map('intval', $stmtInt->fetchAll(PDO::FETCH_COLUMN));

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Recommended Courses</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Degrees and diplomas tailored to your academic qualifications.
    </p>
</div>

<?php if ($recommendations['status'] === 'incomplete'): ?>
    <!-- FR-80: Profile Incomplete Blocking -->
    <div class="card" style="text-align: center; padding: 3rem 1.5rem; background: #fffaf0; border-color: #feebc8;">
        <div style="font-size: 3rem; margin-bottom: 1rem; color: #dd6b20;">&#9888;</div>
        <h2 style="margin-top: 0; color: #9c4221;">Complete Your Profile to Unlock Recommendations</h2>
        <p style="max-width: 600px; margin: 0 auto 1.5rem; color: #7b341e; font-size: 1.05rem;">
            Before we can determine course eligibility, you must finish providing your academic qualifications:
        </p>
        <div style="max-width: 480px; margin: 0 auto 2rem; text-align: left; background: white; padding: 1.25rem; border-radius: 8px; border: 1px solid #fbd38d;">
            <ul style="margin: 0; padding-left: 1.25rem; color: #9c4221;">
                <?php foreach ($recommendations['missing'] as $miss): ?>
                    <li><strong><?= e($miss) ?></strong></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <a href="<?= e(app_url('student/profile.php')) ?>" class="btn btn-warning btn-lg">Complete My Profile Now</a>
    </div>

<?php elseif ($recommendations['status'] === 'no_matches'): ?>
    <!-- FR-81: No Matches Message -->
    <div class="card" style="text-align: center; padding: 3rem 1.5rem; background: #f8fafc;">
        <div style="font-size: 3rem; margin-bottom: 1rem; color: #64748b;">&#128269;</div>
        <h2 style="margin-top: 0; color: #1e293b;">No courses match your details yet</h2>
        <p style="max-width: 600px; margin: 0 auto 1.5rem; color: var(--secondary); font-size: 1.05rem; line-height: 1.6;">
            Based on the subjects or degree program you entered, you do not currently meet all entry requirements for any active courses.
        </p>
        <div style="max-width: 500px; margin: 0 auto 2rem; background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; text-align: left;">
            <strong style="color: var(--primary);">Helpful Tips:</strong>
            <ul style="margin: 0.5rem 0 0; padding-left: 1.25rem; color: var(--secondary);">
                <li>Check your subjects on your profile to make sure none were missed.</li>
                <li>Browse all courses in the catalogue to view prerequisites and plan future studies.</li>
                <li>Reach out to academic counseling for alternative study tracks.</li>
            </ul>
        </div>
        <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
            <a href="<?= e(app_url('student/profile.php')) ?>" class="btn btn-outline btn-lg">Edit My Subjects</a>
            <a href="<?= e(app_url('student/courses.php')) ?>" class="btn btn-primary btn-lg">Browse Full Catalogue</a>
        </div>
    </div>

<?php else: ?>
    <!-- FR-73, FR-74, FR-75: Render Recommendations -->
    <?php foreach ($recommendations['grouped_courses'] as $categoryName => $courses): ?>
        <div style="margin-bottom: 2.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.4rem; color: var(--primary); margin: 0;"><?= e($categoryName) ?></h2>
                <span class="badge badge-secondary"><?= count($courses) ?> match<?= count($courses) > 1 ? 'es' : '' ?></span>
            </div>

            <div class="grid-2">
                <?php foreach ($courses as $course): ?>
                    <?php $isSaved = in_array((int)$course['id'], $savedCourseIds, true); ?>
                    <div class="course-card">
                        <div class="course-card-top">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.25rem;">
                                <span class="course-code"><?= e($course['code']) ?></span>
                                <span class="badge badge-success">&#10003; Recommended</span>
                            </div>
                            <h3 class="course-title"><?= e($course['name']) ?></h3>
                            <div class="course-meta">
                                <span><strong>Level:</strong> <?= e($course['level']) ?></span>
                                <span>&bull;</span>
                                <span><strong>Duration:</strong> <?= e($course['duration']) ?></span>
                            </div>
                            <p class="course-desc"><?= e($course['description']) ?></p>

                            <!-- FR-74: Explanation of match -->
                            <div class="match-reason-box">
                                <strong>Why this matches:</strong> <?= e($course['match_reason']) ?>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: auto;">
                            <!-- FR-76: Click to see units and topics -->
                            <a href="<?= e(app_url('student/course.php?id=' . $course['id'])) ?>" class="btn btn-primary btn-sm">
                                Explore Units & Topics &rarr;
                            </a>

                            <!-- FR-77: Save to My Interests -->
                            <form method="POST" action="<?= e(app_url('student/interests.php')) ?>" style="margin: 0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
                                <input type="hidden" name="action" value="toggle">
                                <button type="submit" class="btn <?= $isSaved ? 'btn-success' : 'btn-outline' ?> btn-sm">
                                    <?= $isSaved ? '&#9733; Saved' : '&#9734; Save to Interests' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
