<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for report_courseanalytics.
 *
 * @package    report_courseanalytics
 * @copyright  2026 Abishek A S (Praskla Technology)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Course Analytics Report';
$string['courseanalytics:view'] = 'View course analytics report';

$string['coursename'] = 'Course Name';
$string['category'] = 'Category';
$string['enrolledstudents'] = 'Enrolled Students';
$string['assignments'] = 'Assignments';
$string['quizzes'] = 'Quizzes';
$string['completedactivities'] = 'Completed Activities';
$string['lastaccess'] = 'Last Access';
$string['completionpercentage'] = 'Completion Percentage';

$string['searchcourses'] = 'Search by course name...';
$string['allcategories'] = 'All Categories';
$string['filter_category'] = 'Category';
$string['filter_completion'] = 'Completion Status';
$string['filter_lastaccess'] = 'Last Access';

$string['status_all'] = 'All Statuses';
$string['status_notstarted'] = 'Not Started (0%)';
$string['status_inprogress'] = 'In Progress (> 0% and < 100%)';
$string['status_completed'] = 'Completed (100%)';

$string['access_all'] = 'Any Time';
$string['access_today'] = 'Today';
$string['access_last7days'] = 'Last 7 Days';
$string['access_last30days'] = 'Last 30 Days';
$string['access_never'] = 'Never Accessed';

$string['never'] = 'Never';
$string['applyfilters'] = 'Apply Filters';
$string['resetfilters'] = 'Reset';
$string['nodatafound'] = 'No courses match the specified criteria.';

// --- Student Activity Dashboard Strings ---
$string['studentdashboard'] = 'Student Activity Dashboard';
$string['studentdashboard_title'] = 'My Learning Activity & Progress';
$string['kpi_enrolledcourses'] = 'Enrolled Courses';
$string['kpi_completedcourses'] = 'Completed Courses';
$string['kpi_pendingassignments'] = 'Pending Assignments';
$string['kpi_upcomingquizzes'] = 'Actionable Quizzes';
$string['kpi_logincount'] = 'Login Count';
$string['kpi_lastlogin'] = 'Last Login';
$string['kpi_avgcompletion'] = 'Average Completion';

$string['login_count_unavailable'] = 'Login count unavailable (logstore not enabled)';
$string['login_history_note'] = 'Count reflects available recorded login events in system logstore';
$string['recorded_sessions'] = 'recorded session(s)';
$string['never_logged_in'] = 'Never logged in';

$string['mycourses'] = 'My Enrolled Courses';
$string['coursestatistics'] = 'Course Statistics';
$string['viewstatistics'] = 'View Detailed Statistics';
$string['completion_not_tracked'] = 'Completion tracking disabled';
$string['low_completion_warning'] = 'Your course completion is below 50% ({$a}%). You may need to complete more learning activities.';
$string['good_progress'] = 'Good progress! Above 50%';

$string['state_pending'] = 'Pending (Submittable)';
$string['state_overdue'] = 'Overdue (Still Submittable)';
$string['state_closed'] = 'Submission Window Closed';
$string['state_draft_submittable'] = 'Draft Awaiting Submission';
$string['state_draft_closed'] = 'Draft (Window Closed)';
$string['state_submitted'] = 'Submitted (Awaiting Grading)';
$string['state_graded'] = 'Graded';

$string['quiz_available_now'] = 'Available Now';
$string['quiz_upcoming_future'] = 'Upcoming (Future Open Date)';
$string['quiz_in_progress'] = 'Attempt in Progress';
$string['quiz_restricted'] = 'Restricted';
$string['quiz_closed'] = 'Closed';
$string['quiz_attempts_exhausted'] = 'Attempts Exhausted';

$string['notenrolled'] = 'You do not have active enrolment in this course.';
$string['no_pending_assignments'] = 'No pending assignments at this time.';
$string['no_upcoming_quizzes'] = 'No actionable quizzes at this time.';
$string['no_enrolled_courses'] = 'You are not currently enrolled in any active courses.';
$string['na_completion'] = 'N/A (No tracked activities)';
