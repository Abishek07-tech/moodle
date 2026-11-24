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
 * Student Activity Dashboard main controller & view.
 *
 * @package    report_courseanalytics
 * @copyright  2026 Abishek A S (Praskla Technology)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use report_courseanalytics\student_dashboard_helper;

// 1. Strict Security: Require authentication and enforce self-access.
require_login();
$userid = (int)$USER->id; // Strictly bound to logged-in user.

$context = context_user::instance($userid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/courseanalytics/student.php'));
$PAGE->set_title(get_string('studentdashboard', 'report_courseanalytics'));
$PAGE->set_heading(get_string('studentdashboard_title', 'report_courseanalytics'));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

// 2. Data Retrieval via Helper Service.
$enrolled_courses = student_dashboard_helper::get_enrolled_courses($userid);
$assignments = student_dashboard_helper::get_assignments_data($enrolled_courses, $userid);
$quizzes = student_dashboard_helper::get_quizzes_data($enrolled_courses, $userid);
$login_info = student_dashboard_helper::get_login_activity($userid);
$avg_completion = student_dashboard_helper::calculate_average_completion($enrolled_courses);

// Metrics calculation.
$total_enrolled = count($enrolled_courses);
$completed_courses = count(array_filter($enrolled_courses, fn($c) => !empty($c->course_completed)));
$pending_assignments = count(array_filter($assignments, fn($a) => !empty($a['is_pending'])));
$upcoming_quizzes = count(array_filter($quizzes, fn($q) => !empty($q['is_upcoming'])));

// 3. User Banner.
echo html_writer::start_div('p-3 mb-4 bg-light rounded-3 border d-flex align-items-center justify-content-between');
echo html_writer::start_div('d-flex align-items-center');
echo $OUTPUT->user_picture($USER, ['size' => 60, 'class' => 'me-3 rounded-circle']);
echo html_writer::start_div();
echo html_writer::tag('h3', fullname($USER), ['class' => 'mb-0']);
echo html_writer::tag('span', $USER->email, ['class' => 'text-muted small']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::start_div('text-end');
echo html_writer::tag('small', get_string('kpi_lastlogin', 'report_courseanalytics'), ['class' => 'text-muted text-uppercase fw-bold d-block']);
$lastlogin_display = $login_info['lastlogin'] > 0 ? userdate($login_info['lastlogin']) : get_string('never_logged_in', 'report_courseanalytics');
echo html_writer::tag('span', $lastlogin_display, ['class' => 'badge bg-secondary p-2']);
echo html_writer::end_div();
echo html_writer::end_div();

// 4. Summary KPI Cards (6 Grid).
echo html_writer::start_div('row g-3 mb-4');

// Card 1: Enrolled Courses
echo html_writer::start_div('col-md-4 col-lg-2');
echo html_writer::start_div('card h-100 shadow-sm border-primary text-center p-3');
echo html_writer::tag('div', $total_enrolled, ['class' => 'display-6 fw-bold text-primary']);
echo html_writer::tag('div', get_string('kpi_enrolledcourses', 'report_courseanalytics'), ['class' => 'text-muted small fw-bold']);
echo html_writer::end_div();
echo html_writer::end_div();

// Card 2: Completed Courses
echo html_writer::start_div('col-md-4 col-lg-2');
echo html_writer::start_div('card h-100 shadow-sm border-success text-center p-3');
echo html_writer::tag('div', $completed_courses, ['class' => 'display-6 fw-bold text-success']);
echo html_writer::tag('div', get_string('kpi_completedcourses', 'report_courseanalytics'), ['class' => 'text-muted small fw-bold']);
echo html_writer::end_div();
echo html_writer::end_div();

// Card 3: Pending Assignments
$pending_color = $pending_assignments > 0 ? 'text-warning' : 'text-secondary';
echo html_writer::start_div('col-md-4 col-lg-2');
echo html_writer::start_div('card h-100 shadow-sm border-warning text-center p-3');
echo html_writer::tag('div', $pending_assignments, ['class' => 'display-6 fw-bold ' . $pending_color]);
echo html_writer::tag('div', get_string('kpi_pendingassignments', 'report_courseanalytics'), ['class' => 'text-muted small fw-bold']);
echo html_writer::end_div();
echo html_writer::end_div();

// Card 4: Upcoming Quizzes
echo html_writer::start_div('col-md-4 col-lg-2');
echo html_writer::start_div('card h-100 shadow-sm border-info text-center p-3');
echo html_writer::tag('div', $upcoming_quizzes, ['class' => 'display-6 fw-bold text-info']);
echo html_writer::tag('div', get_string('kpi_upcomingquizzes', 'report_courseanalytics'), ['class' => 'text-muted small fw-bold']);
echo html_writer::end_div();
echo html_writer::end_div();

// Card 5: Login Count
echo html_writer::start_div('col-md-4 col-lg-2');
echo html_writer::start_div('card h-100 shadow-sm border-secondary text-center p-3');
$login_display = $login_info['available'] ? $login_info['count'] : 'N/A';
echo html_writer::tag('div', $login_display, ['class' => 'display-6 fw-bold text-dark']);
echo html_writer::tag('div', get_string('kpi_logincount', 'report_courseanalytics'), ['class' => 'text-muted small fw-bold']);
echo html_writer::tag('small', $login_info['message'], ['class' => 'text-muted d-block mt-1', 'style' => 'font-size: 0.7rem;']);
echo html_writer::end_div();
echo html_writer::end_div();

// Card 6: Average Completion %
echo html_writer::start_div('col-md-4 col-lg-2');
echo html_writer::start_div('card h-100 shadow-sm border-dark text-center p-3');
$avg_display = $avg_completion !== null ? $avg_completion . '%' : get_string('na_completion', 'report_courseanalytics');
echo html_writer::tag('div', $avg_display, ['class' => 'display-6 fw-bold text-dark']);
echo html_writer::tag('div', get_string('kpi_avgcompletion', 'report_courseanalytics'), ['class' => 'text-muted small fw-bold']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End KPI row

// 5. Section: Enrolled Courses List
echo html_writer::tag('h4', get_string('mycourses', 'report_courseanalytics'), ['class' => 'mb-3 mt-4 border-bottom pb-2']);

if (empty($enrolled_courses)) {
    echo html_writer::div(get_string('no_enrolled_courses', 'report_courseanalytics'), 'alert alert-info');
} else {
    echo html_writer::start_div('row g-3 mb-4');
    foreach ($enrolled_courses as $c) {
        $cstats = $c->completion_stats;
        $pct = $cstats['percentage'];
        $has_warning = $cstats['below_50'];

        echo html_writer::start_div('col-md-6 col-lg-4');
        echo html_writer::start_div('card h-100 shadow-sm border');
        echo html_writer::start_div('card-body d-flex flex-column');

        // Course Name & Category
        echo html_writer::tag('span', $c->categoryname, ['class' => 'badge bg-light text-dark border mb-2 align-self-start']);
        echo html_writer::tag('h5', html_writer::link(new moodle_url('/course/view.php', ['id' => $c->id]), format_string($c->fullname)), ['class' => 'card-title mb-2']);

        // Progress Bar
        if ($pct !== null) {
            $bar_class = $pct >= 50.0 ? 'bg-success' : 'bg-warning text-dark';
            echo html_writer::tag('div', get_string('completionpercentage', 'report_courseanalytics') . ": <strong>{$pct}%</strong>", ['class' => 'small mb-1']);
            echo html_writer::start_div('progress mb-3', ['style' => 'height: 12px;']);
            echo html_writer::div('', 'progress-bar ' . $bar_class, [
                'role' => 'progressbar',
                'style' => 'width: ' . $pct . '%;',
                'aria-valuenow' => $pct,
                'aria-valuemin' => 0,
                'aria-valuemax' => 100,
            ]);
            echo html_writer::end_div();
        } else {
            echo html_writer::div(get_string('completion_not_tracked', 'report_courseanalytics'), 'badge bg-secondary mb-3 align-self-start');
        }

        // Low Completion Warning (< 50%)
        if ($has_warning) {
            $warn_msg = get_string('low_completion_warning', 'report_courseanalytics', $pct);
            echo html_writer::div('<i class="fa fa-exclamation-triangle me-1"></i> ' . $warn_msg, 'alert alert-danger py-2 small mb-3');
        }

        // Action button to View Detailed Statistics
        $detailurl = new moodle_url('/report/courseanalytics/coursedetail.php', ['course' => $c->id]);
        echo html_writer::start_div('mt-auto pt-2');
        echo html_writer::link($detailurl, get_string('viewstatistics', 'report_courseanalytics'), ['class' => 'btn btn-outline-primary btn-sm w-100']);
        echo html_writer::end_div();

        echo html_writer::end_div(); // card-body
        echo html_writer::end_div(); // card
        echo html_writer::end_div(); // col
    }
    echo html_writer::end_div(); // row
}

// 6. Section: Two-Column Action Items (Pending Assignments & Upcoming Quizzes)
echo html_writer::start_div('row g-4 mt-2');

// Left Column: Pending Assignments
echo html_writer::start_div('col-lg-6');
echo html_writer::tag('h5', '<i class="fa fa-tasks me-2"></i> ' . get_string('kpi_pendingassignments', 'report_courseanalytics'), ['class' => 'border-bottom pb-2 mb-3']);
$pending_items = array_filter($assignments, fn($a) => !empty($a['is_pending']));

if (empty($pending_items)) {
    echo html_writer::div(get_string('no_pending_assignments', 'report_courseanalytics'), 'alert alert-light border text-muted');
} else {
    echo html_writer::start_div('list-group shadow-sm');
    foreach ($pending_items as $a) {
        $link = new moodle_url('/mod/assign/view.php', ['id' => $a['cmid']]);
        $state_label = get_string('state_' . $a['state'], 'report_courseanalytics');
        $badge_class = $a['state'] === 'overdue' ? 'bg-danger' : 'bg-warning text-dark';

        echo html_writer::start_div('list-group-item d-flex justify-content-between align-items-center');
        echo html_writer::start_div();
        echo html_writer::tag('h6', html_writer::link($link, format_string($a['name'])), ['class' => 'mb-1']);
        echo html_writer::tag('small', $a['coursename'], ['class' => 'text-muted d-block']);
        if ($a['effective_due'] > 0) {
            echo html_writer::tag('small', 'Due: ' . userdate($a['effective_due']), ['class' => 'text-secondary']);
        }
        echo html_writer::end_div();
        echo html_writer::span($state_label, 'badge ' . $badge_class . ' p-2');
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();

// Right Column: Upcoming Quizzes
echo html_writer::start_div('col-lg-6');
echo html_writer::tag('h5', '<i class="fa fa-question-circle me-2"></i> ' . get_string('kpi_upcomingquizzes', 'report_courseanalytics'), ['class' => 'border-bottom pb-2 mb-3']);
$upcoming_items = array_filter($quizzes, fn($q) => !empty($q['is_upcoming']));

if (empty($upcoming_items)) {
    echo html_writer::div(get_string('no_upcoming_quizzes', 'report_courseanalytics'), 'alert alert-light border text-muted');
} else {
    echo html_writer::start_div('list-group shadow-sm');
    foreach ($upcoming_items as $q) {
        $link = new moodle_url('/mod/quiz/view.php', ['id' => $q['cmid']]);
        $status_label = get_string('quiz_' . $q['status'], 'report_courseanalytics');
        $badge_class = $q['status'] === 'available_now' ? 'bg-success' : 'bg-info text-dark';

        echo html_writer::start_div('list-group-item d-flex justify-content-between align-items-center');
        echo html_writer::start_div();
        echo html_writer::tag('h6', html_writer::link($link, format_string($q['name'])), ['class' => 'mb-1']);
        echo html_writer::tag('small', $q['coursename'], ['class' => 'text-muted d-block']);
        if ($q['timeclose'] > 0) {
            echo html_writer::tag('small', 'Closes: ' . userdate($q['timeclose']), ['class' => 'text-secondary']);
        }
        echo html_writer::end_div();
        echo html_writer::span($status_label, 'badge ' . $badge_class . ' p-2');
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::end_div(); // End row

echo $OUTPUT->footer();

