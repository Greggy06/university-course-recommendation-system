# University Course Recommendation System

A system for recording students' academic results and high-school subject
combinations, managing the university course catalog, and recommending courses
that students are eligible to follow.

## Requirements

1. **Student registration and results**
   - Register students and record their important details and academic results.
   - Let students provide their high-school or secondary-school subject
     combination, including Humanities, Business Studies, or Science.
   - Record Science subjects where applicable (Biology, Physics, Chemistry,
     Engineering, or STEM) and the mathematics level (Advanced Maths or
     General Maths).
   - A student may complete registration without a subject combination, but
     must provide it before the system can recommend courses.
2. **Course catalog**
   - Store the courses offered by the university:

   **Humanities**
   - Bachelor of Arts (BA) in History or Political Science
   - Bachelor of Arts in Anthropology, Sociology, and Archaeology
   - Bachelor of Arts in English Communication, Literature, or Journalism

   **Science**
   - Bachelor of Science (BSc) in Biological Sciences, Chemistry, or Physics
   - Bachelor of Science in Applied Mathematics, Statistics, or Computer Science
   - Bachelor of Medicine and Bachelor of Surgery (MBBS)

   **Business Studies**
   - Bachelor of Business in Accountancy
   - Bachelor of Business in Applied Economics
   - Bachelor of Business in Management (or Human Resource Management)
3. **Eligibility and recommendations**
   - After registration is complete, categorize students into Humanities,
     Science, or Business Studies based on their recorded school subject
     combination or stream.
   - Recommend courses from the matching stream, taking the student's recorded
     academic results and course requirements into account.
4. **Recommendation prerequisites**
   - Require all important student details needed for recommendations,
     especially the high-school or secondary-school subject combination.
   - If required details or prerequisites are missing or unmet, do not
     recommend courses to the student.

## Branch assignments

Use the following feature branches and assignments:

| Branch | Assigned to | Scope |
|---|---|---|
| `feature/student-registration` | Gillion | Student registration, account results, and optional school stream/subject combination |
| `feature/course-catalog-boma` | Boma | University course catalog and course data |
| `feature/course-eligibility-shehuell` | Shehuell | Eligibility evaluation and course recommendations |
| `feature/prerequisite-enforcement-gregory` | Gregory | Prerequisite relationships and blocking courses with unmet prerequisites |

## Core eligibility rule

A course may be recommended only after registration is complete, the student
has provided the required details (especially their school subject combination),
and they satisfy the course's eligibility requirements. Missing or unmet
prerequisites must block course recommendations.

## Student registration feature

Gillion's PHP/MySQL registration feature is in [`student-registration/`](./student-registration/).
It records a student's name, email, password hash, school subject results, and
optional school stream and mathematics level. Students can register without a
stream, but the recommendation process should wait until the required school
subject combination has been provided.

### Setup

1. Create a MySQL database named `university_course_recommendation` and import
   [`student-registration/database/schema.sql`](./student-registration/database/schema.sql).
2. Copy `student-registration/config/config.example.php` to
   `student-registration/config/config.php` and set the MySQL username and
   password for your environment. The local configuration file is ignored by
   Git.
3. Configure the web server's document root to
   `student-registration/public/` (or use that folder as the web root) and open
   `register.php`.

The registration form requires at least one subject/result pair. It validates
input, hashes passwords, protects form submissions with a CSRF token, and uses
prepared SQL statements. Course recommendations are not part of this feature.
