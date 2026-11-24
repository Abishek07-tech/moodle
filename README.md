# Moodle — Course Analytics & Student Activity Dashboard

A robust, enterprise-grade Moodle extension providing comprehensive course-level analytics and student activity insights through two custom reporting interfaces.

---

## 1. Project Overview

This repository branch contains the complete implementation of the Course Analytics and Student Activity Dashboard features for Moodle:

1. **Course Analytics Report (Admin & Teacher View)**
   Provides an administrative overview of course metrics, student enrolments, assignments, quizzes, completion numbers, and last-access timestamps with real-time search, sorting, pagination, and multi-criteria filtering.
   * **URL:** `http://localhost/report/courseanalytics/index.php`

2. **Student Activity Dashboard (Student Portal)**
   Provides authenticated students with a personalized dashboard summarizing their enrolled courses, course progress, pending assignments, actionable quizzes, login metrics, and last-login information.
   * **URL:** `http://localhost/report/courseanalytics/student.php`

3. **Course Detail Statistics View**
   Provides a detailed breakdown of individual course activity progress, listing assignments, quizzes, and a dynamic warning banner for courses where activity completion is strictly below 50.0%.
   * **URL:** `http://localhost/report/courseanalytics/coursedetail.php?course={id}`

Both features are implemented within Moodle's native plugin architecture under `public/report/courseanalytics/`.

---

## 2. Features and Functionality

### 2.1 Course Analytics Report (`index.php`)
- **Structured Data Columns:**
  - Course Name (hyperlinked with `format_string()` sanitization)
  - Category (joined from `mdl_course_categories`)
  - Enrolled Students (distinct active enrolments)
  - Assignments (count of visible assignment activities)
  - Quizzes (count of visible quiz activities)
  - Completed Activities (aggregated completion states 1, 2, 3)
  - Last Access (formatted timestamp via `userdate()`, or "Never")
  - Completion Percentage (color-coded status badge)
- **Live Search:** Database-independent, case-insensitive search matching `fullname` or `shortname` with whitespace trimming.
- **Sorting:** Full ASC and DESC sorting on all metric columns.
- **Pagination:** Clean page navigation preserving search and active filter states.
- **Multi-Criteria Filters:**
  - Category Filter (validated by category ID)
  - Completion Status Filter (`notstarted`, `inprogress`, `completed`)
  - Last-Access Filter (`today`, `last7days`, `last30days`, `never` respecting user timezones)

### 2.2 Student Activity Dashboard (`student.php`)
- **Personalized Header:** Authenticated student profile image and full name.
- **6 Key Performance Indicator (KPI) Cards:**
  - Enrolled Courses count
  - Completed Courses count
  - Pending Assignments count
  - Actionable Quizzes count
  - Recorded Logins count (integrated with standard logstore)
  - Average Course Completion percentage
- **Enrolled Courses Grid:** Visual cards for each active course displaying category, progress bar, completion percentage, and direct links to the course detail view.
- **Pending Assignments List:** Direct activity links with formatted due dates.
- **Actionable Quizzes List:** Direct links to quizzes with opening/closing availability states.

### 2.3 Course Detail Page (`coursedetail.php`)
- **Visual Progress Bar:** Renders student activity completion percentage.
- **Low-Completion Warning Banner:** Displays a prominent alert banner strictly when course activity completion is below 50.0%:
  `Warning: Your course completion is X%, which is below the 50% threshold.`
  (Automatically suppressed when progress is $\ge 50.0\%$ or when completion tracking is disabled).
- **Assignments Breakdown Table:** Details assignment name, effective due date, 6-state lifecycle status, and grade.
- **Quizzes Breakdown Table:** Details quiz name, opening/closing window, attempts taken, and availability status.

---

## 3. Technology Stack & Prerequisites

| Component | Specification |
|---|---|
| **Platform** | Moodle 6.0dev (Build: 20261124) |
| **Language** | PHP 8.5.13 |
| **Database** | MariaDB 11.8.8 / MySQL compatible |
| **Web Server** | Apache 2.4.71 with PHP-FPM |
| **Test Runner** | PHPUnit 11.5.56 |
| **Dependency Manager** | Composer 2.8.5 |
| **Operating System** | Fedora Linux 44 (x86_64) |

---

## 4. Architecture & Directory Structure

All custom plugin components are self-contained within `public/report/courseanalytics/`:

```text
public/report/courseanalytics/
├── classes/
│   ├── analytics_helper.php         # Pre-aggregated SQL subqueries for multi-table analytics
│   ├── table_courseanalytics.php    # Extended table_sql for sorting, filtering, and pagination
│   └── student_dashboard_helper.php # Core business logic for student metrics and activity APIs
├── coursedetail.php                 # Controller & view for Course Details breakdown
├── db/
│   └── access.php                   # Capability definitions ('report/courseanalytics:view')
├── index.php                        # Controller & view for Course Analytics Report
├── lang/en/
│   └── report_courseanalytics.php   # Centralized localization and language strings
├── settings.php                     # Site administration navigation integration
├── student.php                      # Controller & view for Student Activity Dashboard
├── tests/
│   ├── analytics_test.php           # PHPUnit test suite for Phase 4
│   └── student_dashboard_test.php   # PHPUnit test suite for Phase 5
└── version.php                      # Plugin component version and Moodle requirements
```

---

## 5. Security & Access Control

1. **Authentication Enforcement:** `require_login()` is enforced on all endpoints; unauthenticated guests are redirected to `/login/index.php`.
2. **Capability-Based Access:** The Course Analytics report strictly requires the `report/courseanalytics:view` capability at the system context.
3. **Student Identity Enclosure:** The student dashboard strictly binds to `$USER->id`. Parameter tampering (`?id=...`) is ignored.
4. **Active Enrolment Guard:** `coursedetail.php` validates enrolment using `is_enrolled($coursecontext, $USER->id, '', true)`. Unauthorized course access attempts are blocked with permission exceptions (HTTP 404).
5. **SQL Injection Prevention:** 100% of custom database queries use Moodle DML APIs with named parameter placeholders (`:paramname`).
6. **XSS Prevention:** All output rendering utilizes `format_string()`, `s()`, and `html_writer`.

---

## 6. Installation & Setup

1. **Deploy Plugin Files:**
   Ensure the plugin directory is located at `report/courseanalytics` within your Moodle installation (`public/report/courseanalytics`).

2. **Run Database Upgrade:**
   Run the Moodle CLI upgrade utility to register the new plugin and capabilities:
   ```bash
   php public/admin/cli/upgrade.php --non-interactive
   ```

3. **Verify Settings & Capabilities:**
   Navigate to **Site Administration > Reports > Course Analytics** to configure or view the report.

---

## 7. Automated Testing & Verification

### Running Automated PHPUnit Tests
The plugin includes comprehensive unit and integration test suites utilizing Moodle's isolated test environment:

```bash
# Run Course Analytics Report tests
vendor/bin/phpunit public/report/courseanalytics/tests/analytics_test.php

# Run Student Activity Dashboard tests
vendor/bin/phpunit public/report/courseanalytics/tests/student_dashboard_test.php
```

### Verified Test Results
- **Course Analytics Report (`analytics_test.php`):** 2 tests, 11 assertions (PASS).
- **Student Activity Dashboard (`student_dashboard_test.php`):** 8 tests, 42 assertions (PASS).
- **Combined Test Total:** **10 tests, 53 assertions, 0 failures, 0 errors (100% PASS)**.

### Running PHP Syntax Checks
```bash
for f in $(find public/report/courseanalytics -name "*.php"); do
    php -l "$f" || exit 1
done
```
*Result: 12 of 12 files passed with 0 syntax errors.*

---

## 8. Demonstration Credentials

For manual testing and verification on local development environments:

| Role | Username | Password | Target Page |
|---|---|---|---|
| **Administrator** | `admin` | `Admin@12345` | `/report/courseanalytics/index.php` |
| **Student** | `student01` | `Student@123` | `/report/courseanalytics/student.php` |

---

**Project:** Moodle Course Analytics & Student Activity Dashboard
**Platform:** Moodle 6.0dev (Build: 20261124)
**Branch:** `internship/course-analytics-dashboard`
