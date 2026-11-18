# University Course Recommendation System

A system for recording students' academic results and high-school subject
combinations, managing the university course catalog, and recommending courses
that students are eligible to follow.

## Requirements

1. **Student registration and results**
   - Register student details and account results.
   - Allow students to optionally provide their high-school or secondary-school
     subject combination.
   - Support streams such as Humanities, Business Studies, and Science
     (including Biology, Physics, Chemistry, Engineering, or STEM).
   - Record the applicable mathematics level: Advanced Maths or General Maths.
2. **Course catalog**
   - Store and maintain the courses offered by the university.
3. **Eligibility**
   - Identify and display courses a student is eligible to follow, based on the
     student's recorded results and subject combination and each course's
     requirements.
4. **Prerequisites**
   - Record prerequisite relationships between courses.
   - Prevent a student from being considered eligible to follow a course when
     they have not met its prerequisites.

## Branch assignments

Use the following feature branches and assignments:

| Branch | Assigned to | Scope |
|---|---|---|
| `feature/student-registration-gillion` | Gillion | Student registration, account results, and optional school stream/subject combination |
| `feature/course-catalog-boma` | Boma | University course catalog and course data |
| `feature/course-eligibility-shehuell` | Shehuell | Eligibility evaluation and course recommendations |
| `feature/prerequisite-enforcement-gregory` | Gregory | Prerequisite relationships and blocking courses with unmet prerequisites |

## Core eligibility rule

A course may be recommended only when the student satisfies the course's
eligibility requirements **and** has met every prerequisite. An unmet
prerequisite must block the course from being recommended as eligible.
