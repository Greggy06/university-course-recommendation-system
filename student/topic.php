<?php
/**
 * Lesson Topic Viewer (FR-53, FR-57, T-21, SEC-06, SEC-07)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_student();

$pdo = get_db();
$userId = (int)$_SESSION['user_id'];
$topicId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($topicId <= 0) {
    header('Location: ' . app_url('student/courses.php'));
    exit;
}

// Fetch topic along with unit and course details
$stmt = $pdo->prepare("
    SELECT t.*, u.title AS unit_title, u.id AS unit_id, c.id AS course_id, c.name AS course_name, c.code AS course_code, c.is_active AS course_is_active
    FROM topics t
    JOIN units u ON t.unit_id = u.id
    JOIN courses c ON u.course_id = c.id
    WHERE t.id = ?
");
$stmt->execute([$topicId]);
$topic = $stmt->fetch();

if (!$topic || (int)$topic['course_is_active'] !== 1) {
    set_flash('error', 'Lesson not found or course is currently inactive.');
    header('Location: ' . app_url('student/courses.php'));
    exit;
}

$courseId = (int)$topic['course_id'];

// FR-57, T-21, SEC-07: Strict server-side verification that student is eligible
$canView = can_student_view_lessons($pdo, $userId, $courseId);

if (!$canView) {
    // Student is not eligible for this course! Stop and display friendly blocked message
    $pageTitle = 'Lesson Access Restricted';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div style="max-width: 600px; margin: 2rem auto;">
        <div class="card" style="text-align: center; padding: 2.5rem 1.5rem; border-color: #fecaca; background: #fffaf0;">
            <div style="font-size: 3rem; margin-bottom: 1rem; color: #dc2626;">&#128274;</div>
            <h1 style="font-size: 1.6rem; color: #991b1b; margin-top: 0;">Lesson Content Locked</h1>
            <p style="color: #7f1d1d; line-height: 1.6; margin-bottom: 1.5rem;">
                Under university course guidelines (FR-57), the detailed study content for <strong><?= e($topic['course_name']) ?></strong> is accessible only to students who fulfill all entrance prerequisites.
            </p>
            <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                <a href="<?= e(app_url('student/course.php?id=' . $courseId)) ?>" class="btn btn-primary">
                    View Course Prerequisites
                </a>
                <a href="<?= e(app_url('student/recommended.php')) ?>" class="btn btn-outline">
                    View My Eligible Courses
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Student is eligible! Record view (FR-94)
record_recent_course_view($pdo, $userId, $courseId);

// Find Previous and Next topics in the same unit or course
$stmtAllTopics = $pdo->prepare("
    SELECT t.id, t.title 
    FROM topics t 
    JOIN units u ON t.unit_id = u.id 
    WHERE u.course_id = ? 
    ORDER BY u.sort_order ASC, t.sort_order ASC, t.id ASC
");
$stmtAllTopics->execute([$courseId]);
$courseTopics = $stmtAllTopics->fetchAll();

$prevTopic = null;
$nextTopic = null;
$total = count($courseTopics);
for ($i = 0; $i < $total; $i++) {
    if ((int)$courseTopics[$i]['id'] === $topicId) {
        if ($i > 0) {
            $prevTopic = $courseTopics[$i - 1];
        }
        if ($i < $total - 1) {
            $nextTopic = $courseTopics[$i + 1];
        }
        break;
    }
}

$pageTitle = $topic['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumbs -->
<div style="margin-bottom: 1.5rem; font-size: 0.9rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
    <a href="<?= e(app_url('student/courses.php')) ?>">Courses</a>
    <span>&rsaquo;</span>
    <a href="<?= e(app_url('student/course.php?id=' . $courseId)) ?>"><?= e($topic['course_name']) ?></a>
    <span>&rsaquo;</span>
    <span><?= e($topic['unit_title']) ?></span>
    <span>&rsaquo;</span>
    <span style="color: var(--text-main); font-weight: 600;"><?= e($topic['title']) ?></span>
</div>

<div class="card" style="padding: 2.5rem 2rem;">
    <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 1.25rem; margin-bottom: 1.75rem;">
        <span class="badge badge-info" style="margin-bottom: 0.5rem;"><?= e($topic['unit_title']) ?></span>
        <h1 style="font-size: 2rem; margin: 0.25rem 0 0.5rem; color: #1e3a8a;"><?= e($topic['title']) ?></h1>
        <div style="font-size: 0.9rem; color: var(--text-muted);">
            Course: <strong><?= e($topic['course_code']) ?> &bull; <?= e($topic['course_name']) ?></strong>
        </div>
    </div>

    <!-- Topic Learning Content (FR-53) -->
    <div style="font-size: 1.05rem; line-height: 1.8; color: var(--text-main); margin-bottom: 2rem;">
        <?= nl2br(e($topic['content'])) ?>
    </div>

    <!-- Optional Resource Link (FR-53) -->
    <?php if (!empty($topic['resource_url'])): ?>
        <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; margin-bottom: 2.5rem;">
            <h4 style="margin: 0 0 0.5rem; font-size: 1rem; color: var(--primary);">External Reference & Resource</h4>
            <p style="margin: 0 0 0.75rem; font-size: 0.9rem; color: var(--secondary);">
                Supplementary reference material is available for this lesson:
            </p>
            <a href="<?= e($topic['resource_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm">
                Open External Resource &nearr;
            </a>
        </div>
    <?php endif; ?>

    <!-- Navigation between lessons -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <?php if ($prevTopic): ?>
                <a href="<?= e(app_url('student/topic.php?id=' . $prevTopic['id'])) ?>" class="btn btn-outline">
                    &larr; <?= e($prevTopic['title']) ?>
                </a>
            <?php endif; ?>
        </div>

        <div>
            <a href="<?= e(app_url('student/course.php?id=' . $courseId)) ?>" class="btn btn-secondary btn-sm">
                Curriculum Overview
            </a>
        </div>

        <div>
            <?php if ($nextTopic): ?>
                <a href="<?= e(app_url('student/topic.php?id=' . $nextTopic['id'])) ?>" class="btn btn-primary">
                    <?= e($nextTopic['title']) ?> &rarr;
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
