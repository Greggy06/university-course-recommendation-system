<?php
/**
 * Recommendation & Eligibility Engine (FR-40 to FR-84, SEC-07)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Check if a student's profile is complete (FR-41, FR-42, FR-43)
 * Calculated dynamically; not stored as a column in the database.
 */
function check_profile_completeness(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT id, full_name, email, education_level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return [
            'is_complete' => false,
            'education_level' => null,
            'missing_items' => ['User account not found.']
        ];
    }

    $educationLevel = $user['education_level'];
    $missing = [];

    if (empty($educationLevel)) {
        $missing[] = 'Please select your education level (Secondary or Tertiary).';
        return [
            'is_complete' => false,
            'education_level' => null,
            'missing_items' => $missing
        ];
    }

    if ($educationLevel === 'SECONDARY') {
        // Fetch minimum subjects required from settings (FR-42)
        $stmtSettings = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'min_subjects'");
        $minSubjects = (int)($stmtSettings->fetchColumn() ?: 3);

        $stmtSubs = $pdo->prepare("SELECT COUNT(*) FROM student_subjects WHERE user_id = ?");
        $stmtSubs->execute([$userId]);
        $subjectCount = (int)$stmtSubs->fetchColumn();

        if ($subjectCount < $minSubjects) {
            $missing[] = "You must choose at least {$minSubjects} secondary subjects (currently selected: {$subjectCount}).";
        }
    } elseif ($educationLevel === 'TERTIARY') {
        $stmtProfile = $pdo->prepare("SELECT program_id, program_status FROM student_profiles WHERE user_id = ?");
        $stmtProfile->execute([$userId]);
        $profile = $stmtProfile->fetch();

        if (empty($profile['program_id'])) {
            $missing[] = 'Please select your tertiary program (field of study).';
        }
        if (empty($profile['program_status']) || !in_array($profile['program_status'], ['CURRENT', 'COMPLETED'])) {
            $missing[] = 'Please specify your program status (Current or Completed).';
        }
    }

    return [
        'is_complete' => empty($missing),
        'education_level' => $educationLevel,
        'missing_items' => $missing
    ];
}

/**
 * Get student qualification items (Subject IDs or Program ID)
 */
function get_student_qualification_items(PDO $pdo, int $userId, string $educationLevel): array {
    if ($educationLevel === 'SECONDARY') {
        $stmt = $pdo->prepare("SELECT subject_id FROM student_subjects WHERE user_id = ?");
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } elseif ($educationLevel === 'TERTIARY') {
        $stmt = $pdo->prepare("SELECT program_id FROM student_profiles WHERE user_id = ? AND program_status IN ('CURRENT', 'COMPLETED')");
        $stmt->execute([$userId]);
        $progId = $stmt->fetchColumn();
        return $progId ? [(int)$progId] : [];
    }
    return [];
}

/**
 * Fetch prerequisite rules for a course organized by group_no (FR-61, FR-62)
 */
function get_course_rules_by_group(PDO $pdo, int $courseId, string $educationLevel): array {
    if ($educationLevel === 'SECONDARY') {
        $stmt = $pdo->prepare("
            SELECT cr.group_no, cr.subject_id AS item_id, s.name AS item_name
            FROM course_requirements cr
            JOIN subjects s ON cr.subject_id = s.id
            WHERE cr.course_id = ? AND cr.applies_to = 'SECONDARY' AND s.is_active = 1
            ORDER BY cr.group_no ASC, s.name ASC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT cr.group_no, cr.program_id AS item_id, p.name AS item_name
            FROM course_requirements cr
            JOIN programs p ON cr.program_id = p.id
            WHERE cr.course_id = ? AND cr.applies_to = 'TERTIARY' AND p.is_active = 1
            ORDER BY cr.group_no ASC, p.name ASC
        ");
    }
    $stmt->execute([$courseId]);
    $rows = $stmt->fetchAll();

    $groups = [];
    foreach ($rows as $row) {
        $gNo = (int)$row['group_no'];
        if (!isset($groups[$gNo])) {
            $groups[$gNo] = [
                'ids' => [],
                'names' => []
            ];
        }
        $groups[$gNo]['ids'][] = (int)$row['item_id'];
        $groups[$gNo]['names'][] = $row['item_name'];
    }

    return $groups;
}

/**
 * Mathematical eligibility function matching Section 5.4 of SRS
 * All groups must pass. A group passes if student has at least one item.
 */
function isEligible(array $studentItems, array $groups): bool {
    // No rules for that level = never recommended (FR-64)
    if (count($groups) === 0) {
        return false;
    }

    foreach ($groups as $group) {
        $groupIds = is_array($group) && isset($group['ids']) ? $group['ids'] : $group;
        if (count(array_intersect($studentItems, $groupIds)) === 0) {
            return false; // one group failed
        }
    }

    return true; // every group passed
}

/**
 * Detailed evaluation of a course for a student
 * Returns eligibility, reasons (FR-74), match count for ranking (FR-75), and missing rules (FR-84)
 */
function evaluate_course_for_student(PDO $pdo, array $course, int $userId, string $educationLevel, array $studentItems): array {
    $courseId = (int)$course['id'];
    $groups = get_course_rules_by_group($pdo, $courseId, $educationLevel);

    // If no rules for this education level, never eligible / recommended (FR-64)
    if (empty($groups)) {
        return [
            'is_eligible' => false,
            'reason' => '',
            'matched_count' => 0,
            'has_rules' => false,
            'missing_description' => 'No admission rules defined for ' . strtolower($educationLevel) . ' entry.'
        ];
    }

    $allPassed = true;
    $matchedItems = [];
    $unmetGroups = [];

    foreach ($groups as $groupNo => $gData) {
        $intersect = array_intersect($studentItems, $gData['ids']);
        if (count($intersect) > 0) {
            // Find names of matched items in this group
            foreach ($intersect as $matchedId) {
                $idx = array_search($matchedId, $gData['ids']);
                if ($idx !== false && isset($gData['names'][$idx])) {
                    $matchedItems[] = $gData['names'][$idx];
                }
            }
        } else {
            $allPassed = false;
            $unmetGroups[] = implode(' or ', $gData['names']);
        }
    }

    $matchedItems = array_values(array_unique($matchedItems));
    $matchedCount = count($matchedItems);

    if ($allPassed) {
        $reason = '';
        if ($educationLevel === 'SECONDARY') {
            $reason = 'You studied ' . implode(' and ', $matchedItems);
        } else {
            $reason = 'Your program is ' . implode(' / ', $matchedItems);
        }

        return [
            'is_eligible' => true,
            'reason' => $reason,
            'matched_count' => $matchedCount,
            'has_rules' => true,
            'missing_description' => ''
        ];
    } else {
        // Missing requirements for FR-84
        $missingDesc = 'Needs ' . implode('; and ', $unmetGroups);
        return [
            'is_eligible' => false,
            'reason' => '',
            'matched_count' => $matchedCount,
            'has_rules' => true,
            'missing_description' => $missingDesc
        ];
    }
}

/**
 * Get all recommended courses for student, grouped by category and sorted by matched count (FR-70 - FR-75, FR-80, FR-81)
 */
function get_student_recommendations(PDO $pdo, int $userId): array {
    $completeness = check_profile_completeness($pdo, $userId);

    // FR-80: If profile is incomplete, return no courses, return missing items
    if (!$completeness['is_complete']) {
        return [
            'status' => 'incomplete',
            'missing' => $completeness['missing_items'],
            'grouped_courses' => []
        ];
    }

    $educationLevel = $completeness['education_level'];
    $studentItems = get_student_qualification_items($pdo, $userId, $educationLevel);

    // Fetch all active courses with category information
    $stmt = $pdo->query("
        SELECT c.*, cat.name AS category_name, cat.id AS category_id
        FROM courses c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.is_active = 1 AND cat.is_active = 1
        ORDER BY cat.name ASC, c.name ASC
    ");
    $courses = $stmt->fetchAll();

    $eligibleCourses = [];
    foreach ($courses as $course) {
        $eval = evaluate_course_for_student($pdo, $course, $userId, $educationLevel, $studentItems);
        if ($eval['is_eligible']) {
            $course['match_reason'] = $eval['reason'];
            $course['matched_count'] = $eval['matched_count'];
            $eligibleCourses[] = $course;
        }
    }

    if (empty($eligibleCourses)) {
        return [
            'status' => 'no_matches',
            'missing' => [],
            'grouped_courses' => []
        ];
    }

    // Group by category (FR-73) and sort with most matched items first (FR-75)
    $grouped = [];
    foreach ($eligibleCourses as $c) {
        $cat = $c['category_name'];
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][] = $c;
    }

    foreach ($grouped as $cat => &$cList) {
        // FR-75: Put the courses with the most matched items first inside each category
        usort($cList, function($a, $b) {
            if ($b['matched_count'] !== $a['matched_count']) {
                return $b['matched_count'] <=> $a['matched_count'];
            }
            return strcmp($a['name'], $b['name']);
        });
    }
    unset($cList);

    return [
        'status' => 'success',
        'missing' => [],
        'grouped_courses' => $grouped
    ];
}

/**
 * Check if a student is allowed to open lesson content (FR-57, T-21)
 * Must be active course, and student must satisfy prerequisites.
 */
function can_student_view_lessons(PDO $pdo, int $userId, int $courseId): bool {
    $completeness = check_profile_completeness($pdo, $userId);
    if (!$completeness['is_complete']) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND is_active = 1");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
    if (!$course) {
        return false;
    }

    $educationLevel = $completeness['education_level'];
    $studentItems = get_student_qualification_items($pdo, $userId, $educationLevel);
    $eval = evaluate_course_for_student($pdo, $course, $userId, $educationLevel, $studentItems);

    return $eval['is_eligible'];
}

/**
 * Record a course view for student recently viewed list (FR-94)
 */
function record_recent_course_view(PDO $pdo, int $userId, int $courseId): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO recently_viewed (user_id, course_id, viewed_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE viewed_at = NOW()
        ");
        $stmt->execute([$userId, $courseId]);
    } catch (Exception $e) {
        error_log("Failed to record recently viewed course: " . $e->getMessage());
    }
}
