# Course Analytics Report & Student Activity Dashboard

A custom Moodle reporting plugin (`report_courseanalytics`) developed for Moodle 6.0dev that centralizes course-level performance analytics for educators and administrators, and delivers a personalized learning dashboard with proactive completion warnings for students.

---

## 1. Project Overview & Repository Information

- **Repository:** [https://github.com/Abishek07-tech/moodle.git](https://github.com/Abishek07-tech/moodle.git)
- **Branch:** `internship/course-analytics-dashboard`
- **Platform:** Moodle 6.0dev (Build: 20261124)
- **Plugin Type:** Local Report Plugin (`public/report/courseanalytics/`)
- **PHP Version:** 8.5.13
- **Database:** MariaDB 11.8.8

---

## 2. Details of Implemented Work

### A. Modification: Course Analytics Report (Phase 4)
*Target Users: Administrators, Managers, and Editing Teachers*
*Access Path: Site administration → Reports → Course Analytics (`/report/courseanalytics/index.php`)*

- **Multi-Table SQL Aggregation:** Aggregates real-time metrics across core Moodle tables without modifying core files.
- **Report Columns:**
  - Course Name (linked directly to course)
  - Category Name
  - Total Enrolled Students (active, non-suspended enrolments)
  - Assignments Count
  - Quizzes Count
  - Completed Activities Count
  - Last Access Timestamp (formatted via `userdate()`)
  - Overall Completion % (rendered with Bootstrap color-coded progress bars)
- **Search:** Case-insensitive search on Course Fullname and Shortname via `$DB->sql_like()`.
- **Sorting & Pagination:** Built on Moodle's `table_sql` supporting multi-column sorting and server-side pagination with configurable page limits.
- **Multi-Criteria Filtering:**
  - *Category Filter:* Filters by specific course categories.
  - *Completion Status:* Not Started (0%), In Progress (1–99%), Completed (100%).
  - *Last Access Activity:* Today, Last 7 Days, Last 30 Days, Never Accessed.

### B. New Feature: Student Activity Dashboard & Course Detail (Phase 5)
*Target Users: Authenticated Students*
*Access Path: User Menu → Student Activity Dashboard (`/report/courseanalytics/student.php`)*

- **Top KPI Metric Cards:**
  - **Enrolled Courses:** Total active courses for the logged-in student.
  - **Completed Courses:** Courses marked complete in `mdl_course_completions`.
  - **Pending Assignments:** Assignments with upcoming deadlines not yet submitted by the student.
  - **Actionable Quizzes:** Currently open quizzes with available attempts remaining.
  - **Login Metrics:** Lifetime login count and previous login timestamp.
  - **Average Completion %:** Average completion percentage across all enrolled courses.
- **Enrolled Course Grid:** Visual course cards showing individual completion progress bars.
- **Course Detail View (`coursedetail.php`):**
  - Detailed activity breakdown for assignments and quizzes with direct module links, due dates, submission statuses, and grades.
  - **Dynamic `< 50%` Low Completion Warning Alert:** Automatically displays a prominent warning alert (`alert-danger`) if the student's completion progress is strictly below 50%, encouraging timely intervention.

---

## 3. Database Structure & Understanding

The plugin uses Moodle's Data Manipulation Layer (`$DB`) with portable ANSI SQL and `{table}` prefix abstraction.

### Entity Relationship & Key Tables:
```text
                  +--------------------------+
                  |  mdl_course_categories   |
                  +--------------------------+
                               | (id = category)
                               v
+------------------+     +-------------------+
|     mdl_user     |     |    mdl_course     |
+------------------+     +-------------------+
   |            |          |        |       |
   | (id)       | (id)     | (id)   | (id)  | (course)
   |            v          |        |       v
   |    +---------------+  |        |  +--------------------+
   |    | mdl_user_last |  |        |  |     mdl_enrol      |
   |    |    access     |  |        |  +--------------------+
   |    +---------------+  |        |            | (id)
   |            ^          |        |            v
   |            | (courseid)        |  +--------------------+
   |            +----------+        |  | mdl_user_enrolments|
   |                                |  +--------------------+
   | (userid)                       |            | (userid)
   +--------------------------------+------------+
   |
   | (userid)
   |-----------------------------+-----------------------------+
   |                             |                             |
   v                             v                             v
+---------------------+   +---------------------+   +---------------------+
| mdl_course_modules_ |   |   mdl_assign_       |   |   mdl_quiz_         |
|     completion      |   |   submission        |   |     attempts        |
+---------------------+   +---------------------+   +---------------------+
           ^                             ^                             ^
           | (coursemoduleid)            | (assignment)                | (quiz)
+---------------------+   +---------------------+   +---------------------+
| mdl_course_modules  |   |     mdl_assign      |   |      mdl_quiz       |
+---------------------+   +---------------------+   +---------------------+
           | (course)                    | (course)                    | (course)
           +-----------------------------+-----------------------------+
                                         |
                                         v
                                  (mdl_course.id)
```

### Table Roles:
1. `mdl_course`: Master record of courses. Excludes site frontpage (`c.id != 1`).
2. `mdl_enrol` & `mdl_user_enrolments`: Two-table normalized enrolment architecture. Links students to active course enrolment instances.
3. `mdl_user_lastaccess`: Stores individual user course-level access timestamps (`MAX(ula.timeaccess)`).
4. `mdl_course_modules` & `mdl_course_modules_completion`: Tracks activity completion states (`1` = Complete, `2` = Complete Pass, `3` = Complete Fail).
5. `mdl_assign` & `mdl_assign_submission`: Identifies pending assignments where status is not `'submitted'` and due date has not passed.
6. `mdl_quiz` & `mdl_quiz_attempts`: Tracks actionable quizzes based on open time windows and remaining attempts.

### Mathematical Formulas:
- **Course Completion %:**
  $$\text{Total Possible} = \text{Enrolled Students} \times \text{Tracked Activities}$$
  $$\text{Completion \%} = \frac{\text{Completed Activities}}{\text{Total Possible}} \times 100$$
  *(Safely defaults to `0.0%` if enrolled students or tracked activities are zero).*

---

## 4. Project Setup & Local Installation

### Prerequisites
- PHP 8.2+ (PHP 8.5 tested)
- MariaDB 10.6+ / 11.8+
- Composer 2.x

### Installation Steps
1. Clone the repository and checkout the assignment branch:
   ```bash
   git clone https://github.com/Abishek07-tech/moodle.git
   cd moodle
   git checkout internship/course-analytics-dashboard
   ```
2. Verify plugin placement:
   Ensure the plugin exists at `public/report/courseanalytics/`.
3. Configure `config.php`:
   ```php
   $CFG->dbtype    = 'mariadb';
   $CFG->dbhost    = 'localhost';
   $CFG->dbname    = 'moodle';
   $CFG->dbuser    = 'moodleuser';
   $CFG->dbpass    = 'moodle@123';
   $CFG->prefix    = 'mdl_';
   $CFG->wwwroot   = 'http://localhost:8000';
   $CFG->dataroot  = '/home/abishek/Projects/Internship/moodledata';
   ```
4. Run Moodle database upgrade (if installing for the first time):
   ```bash
   php public/admin/cli/upgrade.php --non-interactive
   ```
5. Start the local development web server:
   ```bash
   cd public
   php -S 0.0.0.0:8000
   ```
6. Access Moodle at: `http://localhost:8000`

### Demonstration Credentials
| Role | Username | Password | Target Page |
|---|---|---|---|
| **Administrator** | `admin` | `Admin@12345` | `/report/courseanalytics/index.php` |
| **Student** | `student01` | `Student@123` | `/report/courseanalytics/student.php` |

---

## 5. Challenges Faced & Approaches Taken

### Challenge 1: Accidental Markdown Corruption in Coding Standard Config
- **Issue:** During project work, documentation markdown text was accidentally appended to `phpcs.xml.dist`, corrupting the XML parser and causing PHPCS code-style scans to fail.
- **Approach:** Analyzed Git diff history against upstream Moodle core, isolated the syntax corruption, cleanly removed the appended lines, and restored `phpcs.xml.dist` to its valid XML schema without modifying core rules.

### Challenge 2: Zero-Division and Edge-Case Handling in SQL Aggregation
- **Issue:** Courses with zero enrolled students, zero activities, or activities without completion tracking enabled caused division-by-zero exceptions (`Fatal error: Division by zero`) or misleading `NULL` percentages.
- **Approach:** Implemented defensive subqueries with `COALESCE` and added explicit boundary guard clauses in `analytics_helper::calculate_completion_percentage()`. If `enrolled <= 0` or `tracked <= 0`, the method immediately returns `0.0%`.

### Challenge 3: Insecure Direct Object Reference (IDOR) & Student Isolation
- **Issue:** Allowing students to specify a `?userid=X` parameter on `student.php` would enable malicious users to view other students' private assignment statuses and academic progress.
- **Approach:** Strictly bound the dashboard to the authenticated session user (`$userid = (int)$USER->id`). In `coursedetail.php`, enforced `is_enrolled($coursecontext, $userid, '', true)`, immediately throwing a `moodle_exception('nopermissions')` if a student attempts to view a course in which they are not actively enrolled.

### Challenge 4: Headless Terminal Credential Prompts & Dataroot Permissions
- **Issue:** Fedora's default Git credential helper attempted to spawn a GUI askpass window (`ksshaskpass`) which hung headless terminal sessions during git operations. Additionally, Moodle's session writer threw a 500 error when pointing to a read-only root dataroot.
- **Approach:** Reconfigured the local environment to use a dedicated writable dataroot directory (`Projects/Internship/moodledata`), bypassed interactive askpass using GitHub Personal Access Tokens (PAT), and configured upstream tracking cleanly.

---

## 6. Testing & Quality Assurance

The plugin includes automated PHPUnit test suites covering database aggregation, completion formulas, pending assignment logic, and warning thresholds.

```bash
vendor/bin/phpunit public/report/courseanalytics/tests/
```

### Test Results
| Test File | Tests | Assertions | Result |
|---|---:|---:|:---:|
| `analytics_test.php` | 2 | 11 | **PASS** |
| `student_dashboard_test.php` | 8 | 42 | **PASS** |
| **Total** | **10** | **53** | **100% PASS** |

- **Security Verification:** Parameterized DML queries prevent SQL injection; `html_writer` and `format_string()` prevent Cross-Site Scripting (XSS).
- **Standards:** All 12 PHP plugin files pass PHP 8.5 syntax linting with 0 errors.
- **Core Integrity:** Zero Moodle core files modified; completely modular architecture.

---

## 7. License
This plugin is distributed under the GNU General Public License version 3 (GPL-3.0) in accordance with Moodle's open-source licensing standards.
