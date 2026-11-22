-- University Course Recommendation System (UCRS)
-- Database Schema & Starter Data

CREATE DATABASE IF NOT EXISTS ucrs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ucrs;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('STUDENT', 'ADMIN') NOT NULL DEFAULT 'STUDENT',
    education_level ENUM('SECONDARY', 'TERTIARY') NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_logins INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Subjects Table (Secondary Education)
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Programs Table (Tertiary Education Fields)
CREATE TABLE IF NOT EXISTS programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Student Profiles Table
CREATE TABLE IF NOT EXISTS student_profiles (
    user_id INT PRIMARY KEY,
    stream ENUM('SCIENCE', 'BUSINESS', 'ARTS', 'OTHER') NULL,
    program_id INT NULL,
    program_status ENUM('CURRENT', 'COMPLETED') NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Student Subjects Mapping Table
CREATE TABLE IF NOT EXISTS student_subjects (
    user_id INT NOT NULL,
    subject_id INT NOT NULL,
    PRIMARY KEY (user_id, subject_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Courses Table
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    level VARCHAR(50),
    duration VARCHAR(50),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Units Table
CREATE TABLE IF NOT EXISTS units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    sort_order INT NOT NULL DEFAULT 1,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Topics Table
CREATE TABLE IF NOT EXISTS topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    content TEXT,
    resource_url VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 1,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Course Requirements Table (Prerequisite Rules)
CREATE TABLE IF NOT EXISTS course_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    applies_to ENUM('SECONDARY', 'TERTIARY') NOT NULL,
    group_no INT NOT NULL DEFAULT 1,
    subject_id INT NULL,
    program_id INT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Settings Table
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Activity Log Table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Student Interests Table (FR-77: Save to My Interests)
CREATE TABLE IF NOT EXISTS student_interests (
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, course_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Recently Viewed Courses Table (FR-94: Recently viewed courses)
CREATE TABLE IF NOT EXISTS recently_viewed (
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, course_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- STARTER DATA INSERTION
-- =========================================================================

-- Initial Settings
INSERT INTO settings (setting_key, setting_value) VALUES ('min_subjects', '3')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- 9.1 Categories
INSERT INTO categories (id, name, description, is_active) VALUES
(1, 'Science', 'Scientific exploration, biological studies, chemical principles, and ecological systems.', 1),
(2, 'Computer Science', 'Foundations of computing, software systems, algorithms, and computational modeling.', 1),
(3, 'Information Technology', 'Applied IT infrastructure, networking, cloud systems, and modern web application development.', 1),
(4, 'Business', 'Commerce, corporate administration, accounting, strategic finance, and marketing leadership.', 1),
(5, 'Laws & Ethics', 'Legal systems, jurisprudence, constitutional law, contracts, and professional ethics.', 1),
(6, 'Medicine', 'Medical sciences, clinical care, healthcare delivery, physiology, and pharmacology.', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), is_active = VALUES(is_active);

-- 9.2 Subjects
INSERT INTO subjects (id, name, is_active) VALUES
(1, 'Biology', 1),
(2, 'Chemistry', 1),
(3, 'Physics', 1),
(4, 'English', 1),
(5, 'Advanced Maths', 1),
(6, 'General Maths', 1),
(7, 'Business Studies', 1),
(8, 'Humanities', 1),
(9, 'History', 1),
(10, 'Geography', 1),
(11, 'Economics', 1),
(12, 'Legal Studies', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = VALUES(is_active);

-- 9.3 Programs
INSERT INTO programs (id, name, is_active) VALUES
(1, 'Computer Science / IT', 1),
(2, 'Business', 1),
(3, 'Engineering', 1),
(4, 'Medicine', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = VALUES(is_active);

-- 9.4 Courses
INSERT INTO courses (id, category_id, code, name, description, level, duration, is_active) VALUES
(1, 1, 'BSC-BIO', 'BSc Biological Sciences', 'Study living organisms, cellular mechanisms, genetics, biodiversity, and ecosystem dynamics.', 'Undergraduate Degree', '3 Years', 1),
(2, 2, 'BSC-CS', 'BSc Computer Science', 'Comprehensive curriculum in programming, algorithm design, computer systems, and software engineering.', 'Undergraduate Degree', '4 Years', 1),
(3, 3, 'DIP-IT', 'Diploma in IT', 'Hands-on training in computing fundamentals, web development, network architecture, and technical support.', 'Diploma', '2 Years', 1),
(4, 4, 'BCOM-BM', 'BCom Business Management', 'Develop essential skills in strategic management, corporate accounting, economics, and marketing operations.', 'Undergraduate Degree', '3 Years', 1),
(5, 5, 'LLB-LAW', 'Bachelor of Laws (LLB)', 'In-depth legal education encompassing constitutional law, legal reasoning, contracts, and ethical practice.', 'Professional Undergraduate', '4 Years', 1),
(6, 6, 'MBBS-MED', 'MBBS Medicine', 'Rigorous clinical and pre-clinical medical education preparing students for careers in medical practice.', 'Medical Degree', '5 Years', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), level = VALUES(level), duration = VALUES(duration), is_active = VALUES(is_active);

-- Units
INSERT INTO units (id, course_id, title, description, sort_order) VALUES
-- BSc Biological Sciences
(1, 1, 'Cell Biology', 'Exploration of cell structure, organelles, membranes, and cellular energy cycles.', 1),
(2, 1, 'Genetics', 'Principles of heredity, chromosome structure, DNA replication, and gene expression.', 2),
(3, 1, 'Ecology', 'Study of ecosystems, population interactions, food webs, and environmental conservation.', 3),
-- BSc Computer Science
(4, 2, 'Programming Basics', 'Core procedural and object-oriented programming concepts using modern syntax.', 1),
(5, 2, 'Data Structures', 'Organization and manipulation of linear and non-linear memory data structures.', 2),
(6, 2, 'Databases', 'Relational database architecture, relational algebra, SQL querying, and normalization.', 3),
-- Diploma in IT
(7, 3, 'Computer Basics', 'Computer hardware components, operating system internals, and diagnostics.', 1),
(8, 3, 'Networks', 'Network topologies, OSI model, IP addressing, routing, and switching fundamentals.', 2),
(9, 3, 'Web Development', 'Semantic HTML5, CSS layout techniques, responsive design, and JavaScript fundamentals.', 3),
-- BCom Business Management
(10, 4, 'Principles of Management', 'Theoretical foundations of organizational management, planning, and leadership.', 1),
(11, 4, 'Accounting', 'Financial accounting fundamentals, balance sheets, ledger entries, and financial statements.', 2),
(12, 4, 'Marketing', 'Principles of product positioning, consumer behavior, market research, and promotion.', 3),
-- Bachelor of Laws (LLB)
(13, 5, 'Legal Systems', 'Evolution of common law, statutory interpretation, court hierarchy, and legal methods.', 1),
(14, 5, 'Contract Law', 'Formation of legally binding agreements, consideration, terms, breach, and remedies.', 2),
(15, 5, 'Professional Ethics', 'Moral philosophies and regulatory codes governing legal practitioners.', 3),
-- MBBS Medicine
(16, 6, 'Anatomy', 'Detailed study of human gross anatomy, osteology, muscular systems, and regional dissection.', 1),
(17, 6, 'Physiology', 'Mechanisms of body function, homeostasis, cardiovascular and respiratory systems.', 2),
(18, 6, 'Biochemistry', 'Metabolic pathways, enzymology, biomolecules, and clinical laboratory chemistry.', 3)
ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), sort_order = VALUES(sort_order);

-- Topics
INSERT INTO topics (id, unit_id, title, content, resource_url, sort_order) VALUES
-- Cell Biology Topics
(1, 1, 'Cell Structure and Organelles', 'All living organisms are made up of cells. The prokaryotic cell lacks a membrane-bound nucleus, whereas eukaryotic cells have defined nuclei, mitochondria, endoplasmic reticulum, and Golgi apparatus. In this lesson, we study the functions of each major organelle and how they cooperate to maintain life.', 'https://en.wikipedia.org/wiki/Organelle', 1),
(2, 1, 'Membrane Transport & Permeability', 'The plasma membrane is a phospholipid bilayer with embedded transport proteins. Passive transport occurs along concentration gradients via simple or facilitated diffusion and osmosis. Active transport requires ATP energy to transport ions against steep gradients via pumps.', 'https://en.wikipedia.org/wiki/Membrane_transport', 2),
-- Genetics Topics
(3, 2, 'Mendelian Genetics & Inheritance', 'Gregor Mendel established the fundamental laws of inheritance: the Law of Segregation and the Law of Independent Assortment. Punnett squares demonstrate how dominant and recessive alleles segregate during gamete formation and combine upon fertilization.', 'https://en.wikipedia.org/wiki/Mendelian_inheritance', 1),
(4, 2, 'DNA Replication & Protein Synthesis', 'DNA replication proceeds in a semi-conservative manner guided by DNA polymerase. Transcription creates messenger RNA in the nucleus, which travels to ribosomes where translation synthesizes polypeptides based on codons.', 'https://en.wikipedia.org/wiki/Molecular_biology', 2),
-- Ecology Topics
(5, 3, 'Ecosystem Dynamics & Trophic Levels', 'Energy enters biological ecosystems via primary producers (autotrophs) using solar photosynthesis. It moves through primary, secondary, and tertiary consumers, with approximately 90% dissipated as heat at each trophic transition.', 'https://en.wikipedia.org/wiki/Ecosystem', 1),
-- Programming Basics Topics
(6, 4, 'Variables, Types, and Control Flow', 'Programs process information stored in memory variables. Primitive types include integers, floating-point numbers, characters, and booleans. Execution flow is governed by conditional branches (if/else, switch) and loops (for, while), enabling automated logic repetition.', 'https://en.wikipedia.org/wiki/Control_flow', 1),
(7, 4, 'Functions and Modular Decomposition', 'Functions bundle self-contained logic into reusable blocks with explicit parameters and return values. Modular decomposition enhances readability, testability, and reduces duplication across large software applications.', 'https://en.wikipedia.org/wiki/Subroutine', 2),
-- Data Structures Topics
(8, 5, 'Arrays, Linked Lists, and Stacks', 'Arrays provide O(1) random memory access with contiguous blocks, while linked lists offer flexible dynamic node allocation. Stacks implement Last-In First-Out (LIFO) discipline, essential for runtime call stacks and expression parsing.', 'https://en.wikipedia.org/wiki/Data_structure', 1),
(9, 5, 'Trees, Graphs, and Hash Tables', 'Binary search trees maintain sorted keys for fast O(log n) lookup. Graphs model interconnected networks of vertices and edges. Hash tables provide near constant O(1) average lookup using hash functions and collision resolution strategies.', 'https://en.wikipedia.org/wiki/Binary_search_tree', 2),
-- Databases Topics
(10, 6, 'Relational Modeling & Normalization', 'Relational databases represent entities as tables with rows and columns. Normalization rules (1NF, 2NF, 3NF, BCNF) eliminate redundant information and prevent insertion, update, and deletion anomalies through primary and foreign keys.', 'https://en.wikipedia.org/wiki/Database_normalization', 1),
(11, 6, 'SQL Queries, Joins, and Transactions', 'Structured Query Language (SQL) enables robust data manipulation. INNER, LEFT, and RIGHT joins synthesize related tables. Database transactions uphold ACID properties: Atomicity, Consistency, Isolation, and Durability.', 'https://en.wikipedia.org/wiki/SQL', 2),
-- Diploma in IT Topics
(12, 7, 'Computer Architecture & Storage', 'Modern computers operate on the von Neumann architecture comprising the Central Processing Unit (ALU and Control Unit), memory, and I/O devices. Solid state drives and volatile RAM govern access latency and throughput.', 'https://en.wikipedia.org/wiki/Von_Neumann_architecture', 1),
(13, 8, 'IP Subnetting & Network Layers', 'The OSI 7-layer reference model delineates network responsibilities from Physical up to Application layer. IPv4 addresses are divided into network and host bits using subnet masks, enabling efficient packet routing across local and wide-area networks.', 'https://en.wikipedia.org/wiki/OSI_model', 1),
(14, 9, 'HTML5 Semantics & Responsive Layout', 'Modern web documents utilize semantic HTML tags such as header, nav, main, article, and section to enhance accessibility and SEO. CSS Flexbox and CSS Grid adapt user interfaces dynamically to diverse viewport resolutions.', 'https://en.wikipedia.org/wiki/Responsive_web_design', 1),
-- BCom Topics
(15, 10, 'Organizational Structures & Planning', 'Modern enterprises employ functional, divisional, or matrix structures depending on operational scope. Strategic management involves SWOT analysis, goal setting, operational coordination, and key performance metric monitoring.', 'https://en.wikipedia.org/wiki/Strategic_management', 1),
(16, 11, 'Double-Entry Bookkeeping & Statements', 'The fundamental accounting equation states: Assets = Liabilities + Equity. Every commercial transaction affects at least two accounts in double-entry bookkeeping, culminating in the Balance Sheet and Income Statement.', 'https://en.wikipedia.org/wiki/Double-entry_bookkeeping', 1),
(17, 12, 'The Marketing Mix and Brand Strategy', 'The 4 Ps of marketing—Product, Price, Place, and Promotion—form the marketing mix. Effective brand campaigns perform market segmentation, target customer archetypes, and communicate distinct value propositions.', 'https://en.wikipedia.org/wiki/Marketing_mix', 1),
-- LLB Topics
(18, 13, 'Sources of Law and Precedent', 'The legal system relies on constitutional mandates, legislative statutes, and judicial precedent (stare decisis). Higher court rulings bind subordinate courts, ensuring consistency and predictability in legal dispute adjudication.', 'https://en.wikipedia.org/wiki/Precedent', 1),
(19, 14, 'Contract Formation and Remedies', 'A legally enforceable contract requires offer, unequivocal acceptance, mutual intention to create legal relations, and valuable consideration. Remedies for breach include compensatory damages, restitution, and specific performance.', 'https://en.wikipedia.org/wiki/Contract', 1),
(20, 15, 'Fiduciary Duties and Legal Ethics', 'Attorneys owe clients strict duties of loyalty, competence, and confidentiality. They also hold an overarching paramount duty to the court and the administration of justice that supersedes private client preferences.', 'https://en.wikipedia.org/wiki/Legal_ethics', 1),
-- MBBS Topics
(21, 16, 'Human Gross Anatomy Overview', 'Human anatomy explores the structural architecture of the body by regions: head and neck, thorax, abdomen, pelvis, and extremities. Dissection reveals relationships between bones, nerves, vascular arches, and visceral organs.', 'https://en.wikipedia.org/wiki/Gross_anatomy', 1),
(22, 17, 'Cardiovascular & Hemodynamic Physiology', 'The cardiac conduction system generates rhythmic electrical impulses originating in the SA node. The heart pumps oxygenated blood through systemic circulation, tightly regulated by baroreceptors and sympathetic/parasympathetic reflexes.', 'https://en.wikipedia.org/wiki/Circulatory_system', 1),
(23, 18, 'Cellular Metabolism and Bioenergetics', 'Carbohydrates, lipids, and proteins undergo catabolic breakdown to yield adenosine triphosphate (ATP). Glycolysis feeds into the citric acid cycle (Krebs cycle) and oxidative phosphorylation across mitochondrial cristae.', 'https://en.wikipedia.org/wiki/Metabolism', 1)
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), resource_url = VALUES(resource_url), sort_order = VALUES(sort_order);

-- Prerequisite Rules (Section 9.4 & Section 4.4)
-- Clear existing to ensure accurate seeding
DELETE FROM course_requirements;

-- 1. BSc Biological Sciences (Course 1)
-- Secondary: G1: Biology (1) · G2: Chemistry (2)
INSERT INTO course_requirements (course_id, applies_to, group_no, subject_id, program_id) VALUES
(1, 'SECONDARY', 1, 1, NULL),
(1, 'SECONDARY', 2, 2, NULL),
-- Tertiary: Medicine (4)
(1, 'TERTIARY', 1, NULL, 4);

-- 2. BSc Computer Science (Course 2)
-- Secondary: G1: Advanced Maths (5) OR General Maths (6) · G2: English (4)
INSERT INTO course_requirements (course_id, applies_to, group_no, subject_id, program_id) VALUES
(2, 'SECONDARY', 1, 5, NULL),
(2, 'SECONDARY', 1, 6, NULL),
(2, 'SECONDARY', 2, 4, NULL),
-- Tertiary: Computer Science / IT (1) OR Engineering (3)
(2, 'TERTIARY', 1, NULL, 1),
(2, 'TERTIARY', 1, NULL, 3);

-- 3. Diploma in IT (Course 3)
-- Secondary: G1: General Maths (6) OR Advanced Maths (5) · G2: English (4)
INSERT INTO course_requirements (course_id, applies_to, group_no, subject_id, program_id) VALUES
(3, 'SECONDARY', 1, 6, NULL),
(3, 'SECONDARY', 1, 5, NULL),
(3, 'SECONDARY', 2, 4, NULL),
-- Tertiary: Computer Science / IT (1)
(3, 'TERTIARY', 1, NULL, 1);

-- 4. BCom Business Management (Course 4)
-- Secondary: G1: Business Studies (7) OR Economics (11) · G2: English (4)
INSERT INTO course_requirements (course_id, applies_to, group_no, subject_id, program_id) VALUES
(4, 'SECONDARY', 1, 7, NULL),
(4, 'SECONDARY', 1, 11, NULL),
(4, 'SECONDARY', 2, 4, NULL),
-- Tertiary: Business (2)
(4, 'TERTIARY', 1, NULL, 2);

-- 5. Bachelor of Laws (LLB) (Course 5)
-- Secondary: G1: Legal Studies (12) OR History (9) OR Humanities (8) · G2: English (4)
INSERT INTO course_requirements (course_id, applies_to, group_no, subject_id, program_id) VALUES
(5, 'SECONDARY', 1, 12, NULL),
(5, 'SECONDARY', 1, 9, NULL),
(5, 'SECONDARY', 1, 8, NULL),
(5, 'SECONDARY', 2, 4, NULL),
-- Tertiary: Business (2)
(5, 'TERTIARY', 1, NULL, 2);

-- 6. MBBS Medicine (Course 6)
-- Secondary: G1: Biology (1) · G2: Chemistry (2) · G3: Physics (3) OR Advanced Maths (5)
INSERT INTO course_requirements (course_id, applies_to, group_no, subject_id, program_id) VALUES
(6, 'SECONDARY', 1, 1, NULL),
(6, 'SECONDARY', 2, 2, NULL),
(6, 'SECONDARY', 3, 3, NULL),
(6, 'SECONDARY', 3, 5, NULL),
-- Tertiary: Medicine (4)
(6, 'TERTIARY', 1, NULL, 4);

-- Starter Accounts
-- Admin: admin@ucrs.local / Admin@123
INSERT INTO users (id, full_name, email, password_hash, role, education_level, is_active) VALUES
(1, 'System Administrator', 'admin@ucrs.local', '$2y$10$qVPG9W/TP6MLz.LsqKdNFuUf47kWB7nqTWP.v2fQfC9qj8L2WNEqu', 'ADMIN', NULL, 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), password_hash = VALUES(password_hash), role = VALUES(role), is_active = VALUES(is_active);

-- Test Students (Amy, Ben, Cara, David) with password 'password123'
INSERT INTO users (id, full_name, email, password_hash, role, education_level, is_active) VALUES
(2, 'Amy Adams', 'amy@test.local', '$2y$10$ySk18gCEa.lUDWQANwAY3.CaTXv2eolPvSsEo8yyQvCMkqJKZ1p1u', 'STUDENT', 'SECONDARY', 1),
(3, 'Ben Baker', 'ben@test.local', '$2y$10$ySk18gCEa.lUDWQANwAY3.CaTXv2eolPvSsEo8yyQvCMkqJKZ1p1u', 'STUDENT', 'SECONDARY', 1),
(4, 'Cara Clark', 'cara@test.local', '$2y$10$ySk18gCEa.lUDWQANwAY3.CaTXv2eolPvSsEo8yyQvCMkqJKZ1p1u', 'STUDENT', 'SECONDARY', 1),
(5, 'David Davies', 'david@test.local', '$2y$10$ySk18gCEa.lUDWQANwAY3.CaTXv2eolPvSsEo8yyQvCMkqJKZ1p1u', 'STUDENT', 'TERTIARY', 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), password_hash = VALUES(password_hash), role = VALUES(role), education_level = VALUES(education_level), is_active = VALUES(is_active);

-- Student Profiles
INSERT INTO student_profiles (user_id, stream, program_id, program_status) VALUES
(2, 'SCIENCE', NULL, NULL),
(3, 'SCIENCE', NULL, NULL),
(4, 'ARTS', NULL, NULL),
(5, NULL, 3, 'CURRENT')
ON DUPLICATE KEY UPDATE stream = VALUES(stream), program_id = VALUES(program_id), program_status = VALUES(program_status);

-- Student Subjects:
-- Amy: Biology (1), Chemistry (2), Physics (3), English (4) -> Meets MBBS Medicine & Biological Sciences
-- Ben: Biology (1), Chemistry (2), Business Studies (7) -> Fails MBBS Medicine (needs Physics or Adv Maths), Meets Biological Sciences
-- Cara: Geography (10), History (9), English (4) -> Fails Medicine, Meets LLB Law (History + English)
DELETE FROM student_subjects WHERE user_id IN (2, 3, 4, 5);
INSERT INTO student_subjects (user_id, subject_id) VALUES
-- Amy
(2, 1), (2, 2), (2, 3), (2, 4),
-- Ben
(3, 1), (3, 2), (3, 7),
-- Cara
(4, 10), (4, 9), (4, 4);

-- Initial Activity Log Entry
INSERT INTO activity_log (admin_id, action, details) VALUES
(1, 'DATABASE_INITIALIZATION', 'Starter database schema and seed data loaded successfully.');
