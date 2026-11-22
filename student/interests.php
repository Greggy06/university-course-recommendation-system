<?php
/**
 * Student Saved Interests List (FR-77, SEC-04, SEC-06, SEC-07)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_student();

$pdo = get_db();
$userId = (int)$_SESSION['user_id'];
$pageTitle = 'My Interests';

// Handle Toggle / Remove action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security verification failed.');
    } else {
        $courseId = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
        $action = $_POST['action'] ?? '';

        if ($courseId > 0) {
            if ($action === 'toggle' || $action === 'remove') {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM student_interests WHERE user_id = ? AND course_id = ?");
                $stmtCheck->execute([$userId, $courseId]);
                $exists = (int)$stmtCheck->fetchColumn() > 0;

                if ($exists) {
                    $stmtDel = $pdo->prepare("DELETE FROM student_interests WHERE user_id = ? AND course_id = ?");
                    $stmtDel->execute([$userId, $courseId]);
                    set_flash('info', 'Course removed from your saved interests.');
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO student_interests (user_id, course_id) VALUES (?, ?)");
                    $stmtIns->execute([$userId, $courseId]);
                    set_flash('success', 'Course saved to your interests.');
                }
            }
        }
    }
    // Redirect back to referring page or interests page
    $redirect = $_SERVER['HTTP_REFERER'] ?? app_url('student/interests.php');
    header('Location: ' . $redirect);
    exit;
}

// Student eligibility context for display labels
$completeness = check_profile_completeness($pdo, $userId);
$isProfileComplete = $completeness['is_complete'];
$educationLevel = $completeness['education_level'];
$studentItems = $isProfileComplete ? get_student_qualification_items($pdo, $userId, $educationLevel) : [];

// Fetch saved courses
$stmt = $pdo->prepare("
    SELECT c.*, cat.name AS category_name, si.created_at AS saved_at
    FROM student_interests si
    JOIN courses c ON si.course_id = c.id
    JOIN categories cat ON c.category_id = cat.id
    WHERE si.user_id = ? AND c.is_active = 1
    ORDER BY si.created_at DESC
");
$stmt->execute([$userId]);
$savedCourses = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">My Saved Interests</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Courses and degree programs you have bookmarked for review and planning.
    </p>
</div>

<?php if (empty($savedCourses)): ?>
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
        <div style="font-size: 2.5rem; margin-bottom: 0.5rem; color: #94a3b8;">&#9734;</div>
        <h3 style="margin-top: 0; color: #1e293b;">No saved courses yet</h3>
        <p style="color: var(--secondary); max-width: 480px; margin: 0 auto 1.5rem;">
            You can bookmark any course from your recommendations or the course catalogue by clicking the "Save" button.
        </p>
        <div style="display: flex; justify-content: center; gap: 1rem;">
            <a href="<?= e(app_url('student/recommended.php')) ?>" class="btn btn-primary">View Recommendations</a>
            <a href="<?= e(app_url('student/courses.php')) ?>" class="btn btn-outline">Browse Catalogue</a>
        </div>
    </div>
<?php else: ?>
    <div class="grid-2">
        <?php foreach ($savedCourses as $course): ?>
            <?php
            $isEligible = false;
            $missingDesc = '';
            if ($isProfileComplete) {
                $eval = evaluate_course_for_student($pdo, $course, $userId, $educationLevel, $studentItems);
                $isEligible = $eval['is_eligible'];
                $missingDesc = $eval['missing_description'];
            }
            ?>
            <div class="course-card">
                <div class="course-card-top">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.35rem;">
                        <span class="course-code"><?= e($course['code']) ?></span>
                        <?php if ($isEligible): ?>
                            <span class="badge badge-success">&#10003; Eligible</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">&#9888; Requirements not met</span>
                        <?php endif; ?>
                    </div>

                    <h3 class="course-title"><?= e($course['name']) ?></h3>
                    <div class="course-meta">
                        <span><strong>Category:</strong> <?= e($course['category_name']) ?></span>
                        <span>&bull;</span>
                        <span><strong>Level:</strong> <?= e($course['level']) ?></span>
                    </div>

                    <p class="course-desc"><?= e($course['description']) ?></p>

                    <?php if (!$isEligible && !empty($missingDesc)): ?>
                        <div class="missing-rules-box">
                            <strong>Prerequisite:</strong> <?= e($missingDesc) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: auto;">
                    <a href="<?= e(app_url('student/course.php?id=' . $course['id'])) ?>" class="btn btn-primary btn-sm">
                        View Course &rarr;
                    </a>

                    <form method="POST" action="<?= e(app_url('student/interests.php')) ?>" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
                        <input type="hidden" name="action" value="remove">
                        <button type="submit" class="btn btn-danger btn-sm">
                            Remove
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
