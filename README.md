# Course Analytics & Student Activity Dashboard

A custom Moodle reporting plugin that helps administrators and educators understand course performance, monitor student activity, and identify students who may need additional support.

## Overview

The Course Analytics & Student Activity Dashboard extends Moodle's reporting capabilities with two reporting features:

1. **Course Analytics Report** – Provides an overview of course enrolments, completion status, and course activity.
2. **Student Activity Dashboard** – Provides student-level activity insights, summary statistics, and individual course details.

The plugin brings relevant course and student information together to make academic monitoring easier.

## Features

### 1. Course Analytics Report

Provides a consolidated view of course information and student progress.

- Course information and category details.
- Enrolled student statistics.
- Course completion information.
- Assignment and quiz activity insights.
- Filter courses by category.
- Filter by completion status.
- Filter by last-access activity.
- Search courses by name.
- Sort report columns.
- Paginated report results.

### 2. Student Activity Dashboard

Provides a summary of student activity and academic progress.

- Total enrolled courses.
- Completed courses.
- Pending assignments.
- Actionable quizzes.
- Login activity information.
- Average course completion percentage.
- Individual course details.

### 3. Course Detail View

Allows users to explore individual course information.

- Course-level student progress.
- Completion percentage.
- Assignment and quiz activity.
- Identification of courses with low completion rates.
- Warnings for courses with less than 50% completion.

## Technical Implementation

The plugin is developed using Moodle's reporting architecture and PHP.

| Component | Technology |
|---|---|
| Platform | Moodle |
| Backend | PHP |
| Database | Moodle Database API |
| Frontend | Moodle rendering and reporting interfaces |
| Reporting | Moodle Flexible Table API |
| Testing | PHPUnit |
| Coding Standards | Moodle Coding Style / PHPCS |

### Main Components

```text
courseanalytics/
├── classes/
│   ├── analytics_helper.php
│   ├── student_dashboard_helper.php
│   └── table_courseanalytics.php
├── coursedetail.php
├── db/
│   └── access.php
├── lang/
│   └── en/
│       └── report_courseanalytics.php
├── tests/
│   ├── analytics_test.php
│   └── student_dashboard_test.php
├── index.php
├── settings.php
├── student.php
├── version.php
└── README.md
```

## Installation

1. Ensure you have a working Moodle installation.
2. Navigate to your Moodle installation's `public/report/` directory.
3. Place the `courseanalytics` plugin directory inside it.
4. Log in as a Moodle administrator.
5. Open **Site administration → Notifications**.
6. Follow the plugin installation or upgrade process.
7. Configure the appropriate reporting permissions.

## Accessing the Reports

After installation, access the reporting pages using the appropriate Moodle navigation and permissions.

The plugin provides:

- Course Analytics Report.
- Student Activity Dashboard.
- Individual Course Detail View.

Access depends on the capabilities configured for the respective Moodle roles.

## Testing and Validation

The plugin includes automated PHPUnit tests covering its reporting and student dashboard functionality.

| Test File | Tests | Assertions |
|---|---:|---:|
| analytics_test.php | 2 | 11 |
| student_dashboard_test.php | 8 | 42 |
| **Total** | **10** | **53** |

All 10 tests passed in the reported validation run.

PHP syntax validation was also completed for all 12 plugin PHP files.

## Project Structure

This is a custom Moodle report plugin developed as part of an internship assignment. It extends Moodle's existing reporting functionality without requiring modifications to Moodle core files.

## License

Moodle is open-source software distributed under the GNU General Public License version 3 (GPL-3.0).

This plugin is developed for use within a Moodle installation and should follow the applicable Moodle licensing and development guidelines.

For more information:

- [Moodle](https://moodle.org/)
- [Moodle Developer Documentation](https://moodledev.io/)
- [Moodle User Documentation](https://docs.moodle.org/)
