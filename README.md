# University Course Recommendation System (UCRS)

**Software Requirements Specification (SRS) v1.0 Implementation**  
Built with **HTML5, CSS3, JavaScript, PHP 8, and MySQL**.

---

## 1. Project Overview

The **University Course Recommendation System (UCRS)** is a web-based academic advisory platform designed to help secondary school leavers and tertiary students find undergraduate degree and diploma courses that match their completed qualifications.

The platform uses a transparent, deterministic **rule-based recommendation engine** managed directly by administrators in the database (no black-box AI). If a student's profile is incomplete or prerequisites are not satisfied, recommendations are strictly blocked on the server.

---

## 2. Default Accounts & Credentials

### Administrator Account (FR-18, Section 9.5)
* **Email:** `admin@ucrs.local`
* **Password:** `Admin@123`
* **Role:** `ADMIN`
* **Access:** Admin Dashboard (`/admin/dashboard.php`), Student Management, Catalogue, Curriculum Units & Topics, Prerequisite Rules Engine, Reports, Activity Logs.

### Starter Student Accounts (Section 4.4, Section 11)
All starter student accounts use password: `password123`

| Name | Email | Education Level | Qualifications | Recommendation Result |
| :--- | :--- | :--- | :--- | :--- |
| **Amy Adams** | `amy@test.local` | Secondary | Biology, Chemistry, Physics, English | **MBBS Medicine & BSc Biological Sciences** (Meets all 3 groups) |
| **Ben Baker** | `ben@test.local` | Secondary | Biology, Chemistry, Business Studies | **BSc Biological Sciences** (Fails Medicine: missing Physics / Adv Maths) |
| **Cara Clark** | `cara@test.local` | Secondary | Geography, History, English | **Bachelor of Laws (LLB)** (History + English) |
| **David Davies** | `david@test.local` | Tertiary | Engineering (Current) | **BSc Computer Science** (Tertiary Engineering rule satisfied) |

---

## 3. System Architecture & Folder Layout

```text
university_course_recommendation/
├── index.php                 # Public home landing page (FR-01, FR-02, FR-03)
├── about.php                 # Explains how rules work, target users (FR-04)
├── login.php                 # Role-based authentication & lockout (FR-30 to FR-38)
├── signup.php                # Student registration with dynamic fields (FR-10 to FR-21)
├── logout.php                # Complete session destruction (FR-34)
├── test_spec.php             # Automated test suite (T-01 to T-25)
│
├── includes/
│   ├── config.php            # Database parameters, URL router, CSRF & XSS helpers
│   ├── db.php                # PDO database singleton with connection error page (NFR-05)
│   ├── auth.php              # Session checks, role authorization, lockout & activity logger
│   ├── eligibility.php       # Prerequisite evaluation & profile completeness engine
│   ├── header.php            # Role-aware navigation header & flash notifications
│   └── footer.php            # Common footer & JavaScript loader
│
├── student/
│   ├── dashboard.php         # Student welcome, profile status & recommendations (FR-90 to FR-94)
│   ├── profile.php           # Edit subjects/program, stream, and credentials (FR-40 to FR-46)
│   ├── recommended.php       # Grouped recommendations with match explanations (FR-70 to FR-76)
│   ├── courses.php           # Browse & search catalogue with prerequisite hints (FR-54, FR-55, FR-58, FR-84)
│   ├── course.php            # Course details, curriculum outline & lesson gate (FR-56, FR-57)
│   ├── topic.php             # Lesson reader with resource links for eligible students (FR-53, FR-57)
│   └── interests.php         # Bookmarked course interests (FR-77)
│
├── admin/
│   ├── dashboard.php         # Admin metrics, incomplete profile count, 5 new signups (FR-100)
│   ├── categories.php        # Manage categories, active/hide toggles (FR-101, FR-112)
│   ├── courses.php           # Manage courses, level rules warning (FR-102, FR-64)
│   ├── units.php             # Manage curriculum units, sequence ordering (FR-103)
│   ├── topics.php            # Manage lesson topics, content & resource links (FR-103)
│   ├── subjects.php          # Manage secondary school subjects list (FR-104)
│   ├── programs.php          # Manage tertiary degree programs list (FR-104)
│   ├── requirements.php      # Grouped prerequisite rules builder (FR-60 to FR-65, FR-105)
│   ├── students.php          # Search, filter, deactivate & reset student password (FR-106 to FR-109)
│   ├── student_view.php      # View student profile & live system recommendations (FR-107)
│   ├── reports.php           # Analytics: demographics & recommendation rates (FR-110)
│   ├── log.php               # Administrator audit activity log (FR-111)
│   ├── settings.php          # Dynamic minimum subjects threshold (FR-42)
│   └── admins.php            # Create administrative accounts (Section 12 Q6)
│
├── assets/
│   ├── css/
│   │   └── style.css         # Responsive mobile-first stylesheet (down to 360px)
│   └── js/
│       └── forms.js          # Dynamic form toggles, mobile menu & client validations
│
└── database/
    └── ucrs.sql              # MySQL DDL schema and complete starter data
```

---

## 4. How the Recommendation Logic Works

### The Master Rule (SRS Section 4.3):
$$\text{Eligible} \iff \forall \text{ Group } g, \quad (\text{Student Items} \cap \text{Group } g \text{ Items}) \neq \emptyset$$

1. **Groups are connected by AND:** The student must satisfy **every group**.
2. **Items inside a group are connected by OR:** Having **at least one** item in a group satisfies that group.
3. **No rules = Never recommended (FR-64):** An active course without rules for a level is never recommended to students at that level.
4. **Calculated dynamically:** Profile completeness and course eligibility are calculated dynamically in PHP on the server; never stored as static columns to avoid stale cache bugs.
5. **Why it matches (FR-74):** Each recommended course displays which subjects or program matched (e.g. *"You studied Biology and Chemistry and Physics"*).
6. **Lesson protection (FR-57, T-21):** Only students eligible for a course can open and view its lesson topic content.

---

## 5. Security & Non-Functional Compliance

* **Prepared Statements (SEC-02):** 100% of queries use PDO prepared statements with parameters.
* **XSS Prevention (SEC-03):** All output rendered on pages is passed through `htmlspecialchars()`.
* **Password Hashing (SEC-01):** Passwords stored using PHP `password_hash($pass, PASSWORD_BCRYPT)`.
* **CSRF Protection (SEC-04):** Cryptographic CSRF tokens embedded in all data modification forms.
* **Role Enforcement (SEC-06):** Server-side session verification at the top of every student and admin page.
* **Brute-Force Lockout (FR-37, SEC-09):** Accounts are locked for 15 minutes after 5 consecutive failed login attempts.
* **Inactivity Timeout (FR-33):** Sessions expire after 30 minutes of no user activity.
* **Responsive Layout (NFR-03, T-25):** Fluid layout supporting viewports from 360px up to large desktop monitors.

---

## 6. Running the Automated Tests

From the command line:
```bash
php test_spec.php
```

All 25 test cases (`T-01` through `T-25`) from Section 11 of the SRS will execute and report passing status.
