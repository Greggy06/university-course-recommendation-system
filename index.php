<?php
/**
 * Public Landing Page (FR-01, FR-02, FR-03)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';

// Fetch active categories to display
$pdo = get_db();
$stmt = $pdo->query("
    SELECT c.*, COUNT(co.id) AS course_count 
    FROM categories c 
    LEFT JOIN courses co ON c.id = co.category_id AND co.is_active = 1 
    WHERE c.is_active = 1 
    GROUP BY c.id 
    ORDER BY c.name ASC
");
$categories = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Top Section: Campus Image as Background with Welcome Message & Inspiring Phrase Over It -->
<div class="welcome-hero-card">
    <div class="welcome-hero-content">
        <span class="welcome-tag">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                <path d="M6 12v5c3 3 9 3 12 0v-5"/>
            </svg>
            Welcome to UCRS
        </span>
        <h1 class="welcome-title">Welcome to Your Future in Higher Education</h1>
        <p class="welcome-phrase">
            &ldquo;Education is the bridge between ambition and achievement. Discover where your academic strengths can take you, and step confidently onto your future university campus.&rdquo;
        </p>
        <?php if (is_logged_in()): ?>
            <div class="welcome-actions">
                <a href="<?= e(app_url($_SESSION['role'] === 'ADMIN' ? 'admin/dashboard.php' : 'student/dashboard.php')) ?>" class="btn btn-primary btn-lg" style="background: #ffffff; color: var(--primary); font-weight: 700; border: none;">Go to My Dashboard &rarr;</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 2. How It Works Section -->
<section id="how-it-works" style="margin-bottom: 3rem;">
    <div style="text-align: center; max-width: 700px; margin: 0 auto 2rem;">
        <h2 style="font-size: 2rem; margin-bottom: 0.5rem;">How It Works</h2>
        <p style="color: var(--text-muted);">A transparent, rule-driven system that checks your actual academic credentials against real university prerequisites.</p>
    </div>

    <div class="grid-3">
        <div class="card" style="text-align: center; padding: 2rem 1.5rem;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: #dbeafe; color: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-weight: 700; font-size: 1.25rem;">1</div>
            <h3 style="margin-top: 0; font-size: 1.2rem;">Tell Us Your Background</h3>
            <p style="color: var(--secondary); margin-bottom: 0;">Secondary students select their completed subjects (e.g., Biology, Chemistry, Maths). Tertiary students select their field of study.</p>
        </div>

        <div class="card" style="text-align: center; padding: 2rem 1.5rem;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: #dcfce7; color: var(--success); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-weight: 700; font-size: 1.25rem;">2</div>
            <h3 style="margin-top: 0; font-size: 1.2rem;">Deterministic Matching</h3>
            <p style="color: var(--secondary); margin-bottom: 0;">Our engine evaluates every active course requirement group. No guesswork or probabilistic AI—only verified prerequisites.</p>
        </div>

        <div class="card" style="text-align: center; padding: 2rem 1.5rem;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: #fef3c7; color: var(--warning); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-weight: 700; font-size: 1.25rem;">3</div>
            <h3 style="margin-top: 0; font-size: 1.2rem;">Instant Recommendations</h3>
            <p style="color: var(--secondary); margin-bottom: 0;">View all courses you qualify for, see exact explanations of why you matched, and browse through course units and lessons.</p>
        </div>
    </div>
</section>

<!-- 3. Explore Study Areas Section -->
<section style="margin-bottom: 3rem;">
    <div style="text-align: center; max-width: 700px; margin: 0 auto 2rem;">
        <h2 style="font-size: 1.75rem; margin-bottom: 0.5rem;">Explore Study Areas</h2>
        <p style="color: var(--text-muted); margin: 0;">Curated courses across six core disciplines.</p>
    </div>

    <div class="grid-3">
        <?php foreach ($categories as $cat): ?>
            <div class="card">
                <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
                    <h3 class="card-title" style="color: var(--primary); font-size: 1.2rem;"><?= e($cat['name']) ?></h3>
                    <span class="badge badge-secondary"><?= (int)$cat['course_count'] ?> courses</span>
                </div>
                <p style="color: var(--secondary); font-size: 0.95rem; margin: 0.75rem 0 1.25rem;"><?= e($cat['description']) ?></p>
                <div>
                    <a href="<?= e(app_url(is_logged_in() ? 'student/courses.php?category=' . $cat['id'] : 'login.php')) ?>" class="btn btn-outline btn-sm">
                        View Courses &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- 4. Bottom Section: Find Your University Path With Clarity (FR-03) -->
<div class="hero-box" style="margin-top: 3.5rem; margin-bottom: 1rem;">
    <h2 style="font-size: 2.25rem; margin: 0 0 1rem; font-weight: 800; color: white;">Find Your University Path With Clarity</h2>
    <p>Discover degree and diploma programs tailored strictly to your secondary school subjects or tertiary academic background using verified entry rules.</p>
    <div class="hero-actions">
        <?php if (!is_logged_in()): ?>
            <a href="<?= e(app_url('signup.php')) ?>" class="btn btn-primary btn-lg" style="background: white; color: var(--primary);">Get Started - Free Signup</a>
            <a href="<?= e(app_url('login.php')) ?>" class="btn btn-outline btn-lg" style="border-color: rgba(255,255,255,0.7); color: white;">Sign In</a>
        <?php else: ?>
            <a href="<?= e(app_url($_SESSION['role'] === 'ADMIN' ? 'admin/dashboard.php' : 'student/dashboard.php')) ?>" class="btn btn-primary btn-lg" style="background: white; color: var(--primary);">Go to My Dashboard</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
