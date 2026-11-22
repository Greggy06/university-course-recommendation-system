<?php
/**
 * Browse Course Catalogue (FR-54, FR-55, FR-58, FR-84, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_student();

$pdo = get_db();
$userId = (int)$_SESSION['user_id'];
$pageTitle = 'Browse All Courses';

// Student eligibility context
$completeness = check_profile_completeness($pdo, $userId);
$isProfileComplete = $completeness['is_complete'];
$educationLevel = $completeness['education_level'];
$studentItems = $isProfileComplete ? get_student_qualification_items($pdo, $userId, $educationLevel) : [];

// Filters (FR-55)
$search = trim($_GET['search'] ?? '');
$categoryId = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : 0;

// Fetch categories for filter dropdown
$allCategories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Query courses with filters
$query = "
    SELECT c.*, cat.name AS category_name
    FROM courses c
    JOIN categories cat ON c.category_id = cat.id
    WHERE c.is_active = 1 AND cat.is_active = 1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (c.name LIKE ? OR c.code LIKE ? OR c.description LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($categoryId > 0) {
    $query .= " AND c.category_id = ?";
    $params[] = $categoryId;
}

$query .= " ORDER BY cat.name ASC, c.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Evaluate each course for current student (FR-58, FR-84)
$evaluatedCourses = [];
foreach ($courses as $c) {
    if ($isProfileComplete) {
        $eval = evaluate_course_for_student($pdo, $c, $userId, $educationLevel, $studentItems);
        $c['is_eligible'] = $eval['is_eligible'];
        $c['match_reason'] = $eval['reason'];
        $c['missing_description'] = $eval['missing_description'];
    } else {
        $c['is_eligible'] = false;
        $c['match_reason'] = '';
        $c['missing_description'] = 'Complete your profile to check eligibility.';
    }
    $evaluatedCourses[] = $c;
}

// Group courses by category
$groupedCourses = [];
foreach ($evaluatedCourses as $c) {
    $cat = $c['category_name'];
    if (!isset($groupedCourses[$cat])) {
        $groupedCourses[$cat] = [];
    }
    $groupedCourses[$cat][] = $c;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Course Catalogue</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Browse all active degree and diploma programs, inspect prerequisites, and check your admission status.
    </p>
</div>

<!-- Search and Filter Bar (FR-55) -->
<div class="card" style="padding: 1.25rem; margin-bottom: 2rem;">
    <form method="GET" action="<?= e(app_url('student/courses.php')) ?>" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 240px;">
            <label for="search" class="form-label">Search Courses</label>
            <input type="text" id="search" name="search" class="form-control" value="<?= e($search) ?>" placeholder="Search by name, code or keyword...">
        </div>

        <div style="flex: 1; min-width: 200px;">
            <label for="category" class="form-label">Category Filter</label>
            <select id="category" name="category" class="form-control">
                <option value="0">All Categories</option>
                <?php foreach ($allCategories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">Filter</button>
            <?php if (!empty($search) || $categoryId > 0): ?>
                <a href="<?= e(app_url('student/courses.php')) ?>" class="btn btn-outline" style="margin-left: 0.5rem;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (empty($groupedCourses)): ?>
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
        <h3 style="color: var(--secondary); margin-top: 0;">No courses found</h3>
        <p style="color: var(--text-muted);">Try adjusting your search criteria or removing filters.</p>
        <a href="<?= e(app_url('student/courses.php')) ?>" class="btn btn-outline">Show All Courses</a>
    </div>
<?php else: ?>
    <?php foreach ($groupedCourses as $catName => $cList): ?>
        <div style="margin-bottom: 2.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.35rem; color: var(--primary); margin: 0;"><?= e($catName) ?></h2>
                <span class="badge badge-secondary"><?= count($cList) ?> course<?= count($cList) > 1 ? 's' : '' ?></span>
            </div>

            <div class="grid-2">
                <?php foreach ($cList as $c): ?>
                    <div class="course-card">
                        <div class="course-card-top">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.35rem;">
                                <span class="course-code"><?= e($c['code']) ?></span>
                                <!-- FR-58: Information-only Eligibility label -->
                                <?php if ($c['is_eligible']): ?>
                                    <span class="badge badge-success">&#10003; Eligible</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">&#9888; Requirements not met</span>
                                <?php endif; ?>
                            </div>

                            <h3 class="course-title"><?= e($c['name']) ?></h3>
                            <div class="course-meta">
                                <span><strong>Level:</strong> <?= e($c['level']) ?></span>
                                <span>&bull;</span>
                                <span><strong>Duration:</strong> <?= e($c['duration']) ?></span>
                            </div>

                            <p class="course-desc"><?= e($c['description']) ?></p>

                            <!-- FR-84: Show which requirement is missing -->
                            <?php if (!$c['is_eligible'] && !empty($c['missing_description'])): ?>
                                <div class="missing-rules-box">
                                    <strong>Prerequisite Guidance:</strong> <?= e($c['missing_description']) ?>
                                </div>
                            <?php elseif ($c['is_eligible']): ?>
                                <div class="match-reason-box">
                                    <strong>Prerequisite Met:</strong> <?= e($c['match_reason']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: auto;">
                            <a href="<?= e(app_url('student/course.php?id=' . $c['id'])) ?>" class="btn btn-primary btn-sm">
                                View Course & Outline &rarr;
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
