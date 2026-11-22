<?php
/**
 * Common Site Header (FR-02, FR-05, FR-06)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$isLoggedIn = is_logged_in();
$userRole = $_SESSION['role'] ?? null;
$userName = $_SESSION['user_name'] ?? null;
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' . APP_SHORT_NAME : APP_NAME ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="header-container">
        <div class="logo-area">
            <a href="<?= e(app_url($isLoggedIn ? ($userRole === 'ADMIN' ? 'admin/dashboard.php' : 'student/dashboard.php') : 'index.php')) ?>" class="logo-link">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary);">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                </svg>
                <span>UCRS</span>
            </a>
            <?php if ($isLoggedIn): ?>
                <span class="badge-role <?= $userRole === 'ADMIN' ? 'admin' : 'student' ?>">
                    <?= e($userRole) ?>
                </span>
            <?php endif; ?>
        </div>

        <button class="mobile-nav-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
            &#9776;
        </button>

        <nav>
            <ul class="nav-menu">
                <?php if (!$isLoggedIn): ?>
                    <!-- Public Navigation (FR-02) -->
                    <li><a href="<?= e(app_url('index.php')) ?>" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a></li>
                    <li><a href="<?= e(app_url('about.php')) ?>" class="nav-link <?= $currentPage === 'about.php' ? 'active' : '' ?>">About</a></li>
                    <li><a href="<?= e(app_url('login.php')) ?>" class="nav-link <?= $currentPage === 'login.php' ? 'active' : '' ?>">Login</a></li>
                    <li><a href="<?= e(app_url('signup.php')) ?>" class="nav-link nav-btn-highlight <?= $currentPage === 'signup.php' ? 'active' : '' ?>">Signup</a></li>
                <?php elseif ($userRole === 'STUDENT'): ?>
                    <!-- Student Navigation (FR-05, FR-93) -->
                    <li><a href="<?= e(app_url('student/dashboard.php')) ?>" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
                    <li><a href="<?= e(app_url('student/recommended.php')) ?>" class="nav-link <?= $currentPage === 'recommended.php' ? 'active' : '' ?>">Recommended Courses</a></li>
                    <li><a href="<?= e(app_url('student/courses.php')) ?>" class="nav-link <?= $currentPage === 'courses.php' ? 'active' : '' ?>">Browse All Courses</a></li>
                    <li><a href="<?= e(app_url('student/interests.php')) ?>" class="nav-link <?= $currentPage === 'interests.php' ? 'active' : '' ?>">My Interests</a></li>
                    <li><a href="<?= e(app_url('student/profile.php')) ?>" class="nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">My Profile</a></li>
                    <li><a href="<?= e(app_url('logout.php')) ?>" class="nav-link">Logout</a></li>
                <?php elseif ($userRole === 'ADMIN'): ?>
                    <!-- Admin Navigation (FR-100 to FR-112) -->
                    <li><a href="<?= e(app_url('admin/dashboard.php')) ?>" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
                    <li><a href="<?= e(app_url('admin/students.php')) ?>" class="nav-link <?= $currentPage === 'students.php' ? 'active' : '' ?>">Students</a></li>
                    <li><a href="<?= e(app_url('admin/categories.php')) ?>" class="nav-link <?= $currentPage === 'categories.php' ? 'active' : '' ?>">Categories</a></li>
                    <li><a href="<?= e(app_url('admin/courses.php')) ?>" class="nav-link <?= $currentPage === 'courses.php' ? 'active' : '' ?>">Courses</a></li>
                    <li><a href="<?= e(app_url('admin/units.php')) ?>" class="nav-link <?= $currentPage === 'units.php' || $currentPage === 'topics.php' ? 'active' : '' ?>">Curriculum</a></li>
                    <li><a href="<?= e(app_url('admin/subjects.php')) ?>" class="nav-link <?= $currentPage === 'subjects.php' || $currentPage === 'programs.php' ? 'active' : '' ?>">Criteria</a></li>
                    <li><a href="<?= e(app_url('admin/reports.php')) ?>" class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>">Reports</a></li>
                    <li><a href="<?= e(app_url('admin/log.php')) ?>" class="nav-link <?= $currentPage === 'log.php' ? 'active' : '' ?>">Activity Log</a></li>
                    <li><a href="<?= e(app_url('admin/settings.php')) ?>" class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">Settings</a></li>
                    <li><a href="<?= e(app_url('logout.php')) ?>" class="nav-link">Logout</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main class="main-wrapper">
    <?= render_flash_messages() ?>
