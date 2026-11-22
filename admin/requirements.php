<?php
/**
 * Course Prerequisite Rules Management (FR-60 to FR-65, FR-105, T-18, T-19, SEC-04, SEC-06)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';

require_admin();

$pdo = get_db();
$pageTitle = 'Manage Prerequisite Rules';

// Fetch all courses for selector
$courses = $pdo->query("SELECT id, code, name, is_active FROM courses ORDER BY code ASC")->fetchAll();

$courseId = isset($_GET['course']) ? (int)$_GET['course'] : ($courses[0]['id'] ?? 0);

// Fetch current course
$stmtCourse = $pdo->prepare("
    SELECT c.*, cat.name AS category_name 
    FROM courses c 
    JOIN categories cat ON c.category_id = cat.id 
    WHERE c.id = ?
");
$stmtCourse->execute([$courseId]);
$currentCourse = $stmtCourse->fetch();

if (!$currentCourse && !empty($courses)) {
    $courseId = (int)$courses[0]['id'];
    $stmtCourse->execute([$courseId]);
    $currentCourse = $stmtCourse->fetch();
}

// Fetch all active subjects and programs for add rule dropdowns
$allSubjects = $pdo->query("SELECT id, name FROM subjects WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$allPrograms = $pdo->query("SELECT id, name FROM programs WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security check failed. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_rule') {
            $appliesTo = trim($_POST['applies_to'] ?? '');
            $groupNo = (int)($_POST['group_no'] ?? 1);
            $subjectId = ($appliesTo === 'SECONDARY') ? (int)($_POST['subject_id'] ?? 0) : null;
            $programId = ($appliesTo === 'TERTIARY') ? (int)($_POST['program_id'] ?? 0) : null;

            if (!in_array($appliesTo, ['SECONDARY', 'TERTIARY'], true)) {
                set_flash('error', 'Invalid entry level specified.');
            } elseif ($groupNo < 1) {
                set_flash('error', 'Group number must be 1 or greater.');
            } elseif ($appliesTo === 'SECONDARY' && $subjectId <= 0) {
                set_flash('error', 'Please select a valid subject.');
            } elseif ($appliesTo === 'TERTIARY' && $programId <= 0) {
                set_flash('error', 'Please select a valid program.');
            } else {
                // Check if already in this group
                $stmtCheck = $pdo->prepare("
                    SELECT id FROM course_requirements 
                    WHERE course_id = ? AND applies_to = ? AND group_no = ? 
                    AND (subject_id = ? OR (subject_id IS NULL AND ? IS NULL))
                    AND (program_id = ? OR (program_id IS NULL AND ? IS NULL))
                ");
                $stmtCheck->execute([$courseId, $appliesTo, $groupNo, $subjectId, $subjectId, $programId, $programId]);

                if ($stmtCheck->fetch()) {
                    set_flash('warning', 'This item already exists in Group ' . $groupNo . '.');
                } else {
                    $stmtAdd = $pdo->prepare("
                        INSERT INTO course_requirements (course_id, applies_to, group_no, subject_id, program_id)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmtAdd->execute([$courseId, $appliesTo, $groupNo, $subjectId, $programId]);

                    $itemName = '';
                    if ($subjectId) {
                        $sStmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
                        $sStmt->execute([$subjectId]);
                        $itemName = $sStmt->fetchColumn();
                    } elseif ($programId) {
                        $pStmt = $pdo->prepare("SELECT name FROM programs WHERE id = ?");
                        $pStmt->execute([$programId]);
                        $itemName = $pStmt->fetchColumn();
                    }

                    log_admin_action('RULE_ADD', "Added '{$itemName}' to Group {$groupNo} ({$appliesTo}) for course '{$currentCourse['name']}'.");
                    set_flash('success', "Rule added to Group {$groupNo}.");
                }
            }
            header('Location: ' . app_url('admin/requirements.php?course=' . $courseId));
            exit;

        } elseif ($action === 'delete_rule') {
            // FR-63: Remove rule
            $ruleId = (int)($_POST['rule_id'] ?? 0);
            $stmtDel = $pdo->prepare("DELETE FROM course_requirements WHERE id = ? AND course_id = ?");
            $stmtDel->execute([$ruleId, $courseId]);

            log_admin_action('RULE_DELETE', "Removed prerequisite rule ID {$ruleId} from course '{$currentCourse['name']}'.");
            set_flash('info', 'Rule removed.');
            header('Location: ' . app_url('admin/requirements.php?course=' . $courseId));
            exit;

        } elseif ($action === 'delete_group') {
            // Delete entire group
            $groupNo = (int)($_POST['group_no'] ?? 0);
            $appliesTo = trim($_POST['applies_to'] ?? '');
            if ($groupNo > 0 && in_array($appliesTo, ['SECONDARY', 'TERTIARY'])) {
                $stmtDel = $pdo->prepare("DELETE FROM course_requirements WHERE course_id = ? AND applies_to = ? AND group_no = ?");
                $stmtDel->execute([$courseId, $appliesTo, $groupNo]);

                log_admin_action('GROUP_DELETE', "Deleted entire Group {$groupNo} ({$appliesTo}) for course '{$currentCourse['name']}'.");
                set_flash('info', "Group {$groupNo} deleted.");
            }
            header('Location: ' . app_url('admin/requirements.php?course=' . $courseId));
            exit;
        }
    }
}

// Fetch detailed rules for current course
$stmtSec = $pdo->prepare("
    SELECT cr.id AS rule_id, cr.group_no, s.id AS subject_id, s.name AS subject_name
    FROM course_requirements cr
    JOIN subjects s ON cr.subject_id = s.id
    WHERE cr.course_id = ? AND cr.applies_to = 'SECONDARY'
    ORDER BY cr.group_no ASC, s.name ASC
");
$stmtSec->execute([$courseId]);
$secRows = $stmtSec->fetchAll();

$secGrouped = [];
foreach ($secRows as $r) {
    $g = (int)$r['group_no'];
    if (!isset($secGrouped[$g])) $secGrouped[$g] = [];
    $secGrouped[$g][] = $r;
}

$stmtTert = $pdo->prepare("
    SELECT cr.id AS rule_id, cr.group_no, p.id AS program_id, p.name AS program_name
    FROM course_requirements cr
    JOIN programs p ON cr.program_id = p.id
    WHERE cr.course_id = ? AND cr.applies_to = 'TERTIARY'
    ORDER BY cr.group_no ASC, p.name ASC
");
$stmtTert->execute([$courseId]);
$tertRows = $stmtTert->fetchAll();

$tertGrouped = [];
foreach ($tertRows as $r) {
    $g = (int)$r['group_no'];
    if (!isset($tertGrouped[$g])) $tertGrouped[$g] = [];
    $tertGrouped[$g][] = $r;
}

// Check FR-64 Warning
$hasNoSecRules = empty($secGrouped);
$hasNoTertRules = empty($tertGrouped);
$courseIsActive = (int)$currentCourse['is_active'] === 1;

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin: 0 0 0.5rem; color: #1e3a8a;">Prerequisite Rules Engine</h1>
    <p style="color: var(--text-muted); margin: 0;">
        Configure entrance prerequisite groups for secondary and tertiary admission pathways.
    </p>
</div>

<!-- Course Selector Bar -->
<div class="card" style="padding: 1.25rem; margin-bottom: 2rem;">
    <form method="GET" action="<?= e(app_url('admin/requirements.php')) ?>" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 280px;">
            <label for="course_select" class="form-label">Select Course to Configure</label>
            <select id="course_select" name="course" class="form-control" onchange="this.form.submit()">
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>>
                        [<?= e($c['code']) ?>] <?= e($c['name']) ?> <?= $c['is_active'] ? '' : '(Hidden)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-outline">Switch Course</button>
            <a href="<?= e(app_url('admin/courses.php?edit=' . $courseId)) ?>" class="btn btn-secondary">
                Edit Course Details &rarr;
            </a>
        </div>
    </form>
</div>

<!-- FR-64 Rule Warning Banner -->
<?php if ($courseIsActive && ($hasNoSecRules || $hasNoTertRules)): ?>
    <div class="alert alert-warning" style="margin-bottom: 2rem;">
        <div>
            <strong>Rule Warning (FR-64):</strong>
            <?php
            $missingLevels = [];
            if ($hasNoSecRules) $missingLevels[] = 'Secondary Students';
            if ($hasNoTertRules) $missingLevels[] = 'Tertiary Students';
            ?>
            This course is currently <strong>Active</strong> in the catalogue but has no prerequisite rules defined for <strong><?= e(implode(' or ', $missingLevels)) ?></strong>.
            A course with no rules for a level is <em>never recommended</em> to that level.
        </div>
    </div>
<?php endif; ?>

<!-- Course Summary Header -->
<div class="card" style="margin-bottom: 2rem; background: #f8fafc;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <span class="course-code"><?= e($currentCourse['code']) ?></span>
            <h2 style="margin: 0.25rem 0 0.5rem; font-size: 1.5rem; color: #1e3a8a;"><?= e($currentCourse['name']) ?></h2>
            <div class="course-meta" style="margin-bottom: 0;">
                <span><strong>Category:</strong> <?= e($currentCourse['category_name']) ?></span>
                <span>&bull;</span>
                <span><strong>Award:</strong> <?= e($currentCourse['level']) ?></span>
                <span>&bull;</span>
                <span><strong>Status:</strong> <?= $currentCourse['is_active'] ? 'Active' : 'Inactive' ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Explanation of Logic Box -->
<div class="card" style="background: #f0fdf4; border-color: #bbf7d0; margin-bottom: 2rem; padding: 1.25rem;">
    <h3 style="margin: 0 0 0.35rem; color: #166534; font-size: 1.05rem;">Prerequisite Evaluation Formula (SRS Section 4.3):</h3>
    <p style="margin: 0; color: #14532d; font-size: 0.95rem;">
        <strong>All groups must pass. A group passes if the student has ANY ONE of its listed items.</strong><br>
        (e.g., Group 1 AND Group 2 AND Group 3. Inside Group 1: Item A OR Item B).
    </p>
</div>

<!-- SECTION 1: SECONDARY RULES (FR-61) -->
<div class="card" style="margin-bottom: 2.5rem;">
    <div class="card-header">
        <div>
            <h2 class="card-title">1. Secondary Entry Prerequisite Rules</h2>
            <span class="form-hint">Rule criteria based on high school subjects studied by the student.</span>
        </div>
        <span class="badge badge-info"><?= count($secGrouped) ?> Group<?= count($secGrouped) !== 1 ? 's' : '' ?></span>
    </div>

    <!-- Add Subject Rule Form -->
    <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
        <h3 style="margin-top: 0; font-size: 1.05rem; color: var(--primary);">Add Subject to Prerequisite Group</h3>
        <form method="POST" action="<?= e(app_url('admin/requirements.php?course=' . $courseId)) ?>" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_rule">
            <input type="hidden" name="applies_to" value="SECONDARY">

            <div style="min-width: 140px;">
                <label for="sec_group_no" class="form-label">Group Number</label>
                <input type="number" id="sec_group_no" name="group_no" class="form-control" value="<?= empty($secGrouped) ? 1 : max(array_keys($secGrouped)) ?>" min="1" required>
            </div>

            <div style="flex: 1; min-width: 240px;">
                <label for="subject_id" class="form-label">Select Subject</label>
                <select id="subject_id" name="subject_id" class="form-control" required>
                    <option value="">-- Choose Subject --</option>
                    <?php foreach ($allSubjects as $sub): ?>
                        <option value="<?= (int)$sub['id'] ?>"><?= e($sub['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Add to Group</button>
            </div>
        </form>
    </div>

    <!-- Groups Display -->
    <?php if (empty($secGrouped)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 1.5rem 0;">No secondary prerequisite groups configured yet.</p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($secGrouped as $gNo => $rules): ?>
                <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem 1.25rem; background: white;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="badge badge-info" style="font-size: 0.85rem;">Group <?= $gNo ?></span>
                            <span style="font-size: 0.85rem; color: var(--text-muted);">(Student needs at least ONE of these subjects)</span>
                        </div>
                        <form method="POST" action="<?= e(app_url('admin/requirements.php?course=' . $courseId)) ?>" style="margin: 0;" onsubmit="return confirm('Delete all rules in Group <?= $gNo ?>?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_group">
                            <input type="hidden" name="applies_to" value="SECONDARY">
                            <input type="hidden" name="group_no" value="<?= $gNo ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: var(--danger-border);">
                                Delete Entire Group
                            </button>
                        </form>
                    </div>

                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                        <?php foreach ($rules as $idx => $r): ?>
                            <?php if ($idx > 0): ?>
                                <span style="color: #64748b; font-weight: bold; font-size: 0.85rem;">OR</span>
                            <?php endif; ?>
                            <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.35rem 0.65rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <strong><?= e($r['subject_name']) ?></strong>
                                <form method="POST" action="<?= e(app_url('admin/requirements.php?course=' . $courseId)) ?>" style="margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_rule">
                                    <input type="hidden" name="rule_id" value="<?= (int)$r['rule_id'] ?>">
                                    <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 1rem; padding: 0;" title="Remove this subject">&times;</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION 2: TERTIARY RULES (FR-61) -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">2. Tertiary Entry Prerequisite Rules</h2>
            <span class="form-hint">Rule criteria based on tertiary student's current or completed field of study.</span>
        </div>
        <span class="badge badge-info"><?= count($tertGrouped) ?> Group<?= count($tertGrouped) !== 1 ? 's' : '' ?></span>
    </div>

    <!-- Add Program Rule Form -->
    <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
        <h3 style="margin-top: 0; font-size: 1.05rem; color: var(--primary);">Add Program to Prerequisite Group</h3>
        <form method="POST" action="<?= e(app_url('admin/requirements.php?course=' . $courseId)) ?>" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_rule">
            <input type="hidden" name="applies_to" value="TERTIARY">

            <div style="min-width: 140px;">
                <label for="tert_group_no" class="form-label">Group Number</label>
                <input type="number" id="tert_group_no" name="group_no" class="form-control" value="<?= empty($tertGrouped) ? 1 : max(array_keys($tertGrouped)) ?>" min="1" required>
            </div>

            <div style="flex: 1; min-width: 240px;">
                <label for="program_id" class="form-label">Select Program</label>
                <select id="program_id" name="program_id" class="form-control" required>
                    <option value="">-- Choose Program --</option>
                    <?php foreach ($allPrograms as $prog): ?>
                        <option value="<?= (int)$prog['id'] ?>"><?= e($prog['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Add to Group</button>
            </div>
        </form>
    </div>

    <!-- Groups Display -->
    <?php if (empty($tertGrouped)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 1.5rem 0;">No tertiary prerequisite groups configured yet.</p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($tertGrouped as $gNo => $rules): ?>
                <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem 1.25rem; background: white;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="badge badge-info" style="font-size: 0.85rem;">Group <?= $gNo ?></span>
                            <span style="font-size: 0.85rem; color: var(--text-muted);">(Student needs at least ONE of these programs)</span>
                        </div>
                        <form method="POST" action="<?= e(app_url('admin/requirements.php?course=' . $courseId)) ?>" style="margin: 0;" onsubmit="return confirm('Delete all rules in Group <?= $gNo ?>?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_group">
                            <input type="hidden" name="applies_to" value="TERTIARY">
                            <input type="hidden" name="group_no" value="<?= $gNo ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: var(--danger-border);">
                                Delete Entire Group
                            </button>
                        </form>
                    </div>

                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                        <?php foreach ($rules as $idx => $r): ?>
                            <?php if ($idx > 0): ?>
                                <span style="color: #64748b; font-weight: bold; font-size: 0.85rem;">OR</span>
                            <?php endif; ?>
                            <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.35rem 0.65rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <strong><?= e($r['program_name']) ?></strong>
                                <form method="POST" action="<?= e(app_url('admin/requirements.php?course=' . $courseId)) ?>" style="margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_rule">
                                    <input type="hidden" name="rule_id" value="<?= (int)$r['rule_id'] ?>">
                                    <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 1rem; padding: 0;" title="Remove this program">&times;</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
