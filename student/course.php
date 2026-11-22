<?php
/**
 * Course Details & Curriculum Page (FR-56, FR-57, FR-94, T-21, SEC-06, SEC-07)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_student();

$pdo = get_db();
$userId = (int)$_SESSION['user_id'];
$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($courseId <= 0) {
    header('Location: ' . app_url('student/courses.php'));
    exit;
}

// Fetch course information
$stmt = $pdo->prepare("
    SELECT c.*, cat.name AS category_name
    FROM courses c
    JOIN categories cat ON c.category_id = cat.id
    WHERE c.id = ? AND c.is_active = 1 AND cat.is_active = 1
");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    set_flash('error', 'The requested course does not exist or is currently inactive.');
    header('Location: ' . app_url('student/courses.php'));
    exit;
}

// Record recently viewed course (FR-94)
record_recent_course_view($pdo, $userId, $courseId);

// Check current student eligibility (FR-57)
$completeness = check_profile_completeness($pdo, $userId);
$isProfileComplete = $completeness['is_complete'];
$educationLevel = $completeness['education_level'];

$isEligible = false;
$evalResult = null;
if ($isProfileComplete) {
    $studentItems = get_student_qualification_items($pdo, $userId, $educationLevel);
    $evalResult = evaluate_course_for_student($pdo, $course, $userId, $educationLevel, $studentItems);
    $isEligible = $evalResult['is_eligible'];
}

// Fetch secondary and tertiary prerequisite rules for display
$secRules = get_course_rules_by_group($pdo, $courseId, 'SECONDARY');
$tertRules = get_course_rules_by_group($pdo, $courseId, 'TERTIARY');

// Fetch Units and Topics in order (FR-56)
$stmtUnits = $pdo->prepare("
    SELECT * FROM units 
    WHERE course_id = ? 
    ORDER BY sort_order ASC, id ASC
");
$stmtUnits->execute([$courseId]);
$units = $stmtUnits->fetchAll();

$unitsWithTopics = [];
foreach ($units as $unit) {
    $stmtTopics = $pdo->prepare("
        SELECT id, unit_id, title, sort_order, resource_url 
        FROM topics 
        WHERE unit_id = ? 
        ORDER BY sort_order ASC, id ASC
    ");
    $stmtTopics->execute([$unit['id']]);
    $unit['topics'] = $stmtTopics->fetchAll();
    $unitsWithTopics[] = $unit;
}

$pageTitle = $course['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="<?= e(app_url('student/courses.php')) ?>" class="btn btn-outline btn-sm">&larr; Back to Catalogue</a>
</div>

<!-- Course Header Card -->
<div class="card" style="padding: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem;">
        <div>
            <span class="course-code" style="font-size: 0.9rem;"><?= e($course['code']) ?></span>
            <h1 style="font-size: 2.2rem; margin: 0.25rem 0 0.5rem; color: #1e3a8a;"><?= e($course['name']) ?></h1>
            <div class="course-meta" style="font-size: 0.95rem;">
                <span><strong>Category:</strong> <?= e($course['category_name']) ?></span>
                <span>&bull;</span>
                <span><strong>Level:</strong> <?= e($course['level']) ?></span>
                <span>&bull;</span>
                <span><strong>Duration:</strong> <?= e($course['duration']) ?></span>
            </div>
        </div>

        <div>
            <!-- FR-58 & FR-57 Status Indicator -->
            <?php if ($isEligible): ?>
                <span class="badge badge-success" style="font-size: 1rem; padding: 0.5rem 1rem;">&#10003; Eligible for Admission</span>
            <?php else: ?>
                <span class="badge badge-secondary" style="font-size: 1rem; padding: 0.5rem 1rem;">&#128274; Requirements Not Met</span>
            <?php endif; ?>
        </div>
    </div>

    <p style="font-size: 1.05rem; line-height: 1.6; color: var(--secondary); margin-top: 1rem; margin-bottom: 1.5rem;">
        <?= e($course['description']) ?>
    </p>

    <!-- Student Status Notice -->
    <?php if ($isEligible): ?>
        <div class="match-reason-box" style="font-size: 0.95rem; padding: 1rem;">
            <strong>Your Status:</strong> You satisfy all entry requirements for this course. (<em><?= e($evalResult['reason']) ?></em>). You have full access to study all curriculum units and topics below!
        </div>
    <?php else: ?>
        <div class="missing-rules-box" style="font-size: 0.95rem; padding: 1rem;">
            <strong>Your Status:</strong> <?= $isProfileComplete ? e($evalResult['missing_description']) : 'Please complete your academic profile to determine admission eligibility.' ?>
            <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #7c2d12;">
                &#128274; <em>Per university guidelines (FR-57), the full text of curriculum lessons is reserved for eligible students. You can view the curriculum outline and requirements below.</em>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Admission Prerequisites Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Admission Requirements</h2>
    </div>

    <div class="grid-2">
        <!-- Secondary Entry Rules -->
        <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem;">
            <h3 style="margin-top: 0; font-size: 1.1rem; color: var(--primary);">Secondary School Entry</h3>
            <?php if (empty($secRules)): ?>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0;">No secondary admission rules configured.</p>
            <?php else: ?>
                <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.95rem; line-height: 1.6;">
                    <?php foreach ($secRules as $gNo => $gData): ?>
                        <li><strong>Group <?= $gNo ?>:</strong> <?= e(implode(' OR ', $gData['names'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Tertiary Entry Rules -->
        <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem;">
            <h3 style="margin-top: 0; font-size: 1.1rem; color: var(--primary);">Tertiary / Higher Entry</h3>
            <?php if (empty($tertRules)): ?>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0;">No tertiary admission rules configured.</p>
            <?php else: ?>
                <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.95rem; line-height: 1.6;">
                    <?php foreach ($tertRules as $gNo => $gData): ?>
                        <li><strong>Group <?= $gNo ?>:</strong> <?= e(implode(' OR ', $gData['names'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Course Units and Topics Curriculum (FR-56, FR-57, T-21) -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Curriculum & Course Units</h2>
        <span class="badge badge-info"><?= count($unitsWithTopics) ?> Units</span>
    </div>

    <?php if (empty($unitsWithTopics)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No units have been added to this course syllabus yet.</p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <?php foreach ($unitsWithTopics as $unitIndex => $unit): ?>
                <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
                    <div style="background: #f8fafc; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                            <h3 style="margin: 0; font-size: 1.15rem; color: var(--text-main);">
                                Unit <?= $unitIndex + 1 ?>: <?= e($unit['title']) ?>
                            </h3>
                            <span style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= count($unit['topics']) ?> Topic<?= count($unit['topics']) !== 1 ? 's' : '' ?>
                            </span>
                        </div>
                        <?php if (!empty($unit['description'])): ?>
                            <p style="color: var(--secondary); margin: 0.35rem 0 0; font-size: 0.9rem;">
                                <?= e($unit['description']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Topics Listing -->
                    <div style="padding: 0.5rem 1.25rem;">
                        <?php if (empty($unit['topics'])): ?>
                            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.75rem 0;">No lessons currently listed under this unit.</p>
                        <?php else: ?>
                            <ul style="list-style: none; margin: 0; padding: 0;">
                                <?php foreach ($unit['topics'] as $topicIndex => $topic): ?>
                                    <li style="padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">
                                                <?= ($unitIndex + 1) ?>.<?= ($topicIndex + 1) ?>
                                            </span>
                                            
                                            <!-- FR-57 & T-21: Access control for topic links -->
                                            <?php if ($isEligible): ?>
                                                <a href="<?= e(app_url('student/topic.php?id=' . $topic['id'])) ?>" style="font-weight: 600; font-size: 0.95rem;">
                                                    <?= e($topic['title']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--secondary); font-size: 0.95rem;">
                                                    <?= e($topic['title']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div>
                                            <?php if ($isEligible): ?>
                                                <a href="<?= e(app_url('student/topic.php?id=' . $topic['id'])) ?>" class="btn btn-outline btn-sm">
                                                    Open Lesson &rarr;
                                                </a>
                                            <?php else: ?>
                                                <span class="badge badge-secondary" title="Eligible students only" style="cursor: not-allowed;">
                                                    &#128274; Locked
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
