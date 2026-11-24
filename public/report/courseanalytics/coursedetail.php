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
 * Course detailed statistics view for the active student.
 *
 * @package    report_courseanalytics
 * @copyright  2026 Abishek A S (Praskla Technology)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use report_courseanalytics\student_dashboard_helper;

// 1. Strict Security: Require authentication and enforce active enrolment.
require_login();
$userid = (int)$USER->id; // Strictly bound to current student.

$courseid = required_param('course', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);

// Enforce active enrolment strictly ($onlyactive = true).
if (!is_enrolled($coursecontext, $userid, '', true)) {
    throw new \moodle_exception('nopermissions', 'error', '', get_string('notenrolled', 'report_courseanalytics'));
}

$PAGE->set_context($coursecontext);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/report/courseanalytics/coursedetail.php', ['course' => $course->id]));
$PAGE->set_title(format_string($course->fullname) . ' - ' . get_string('coursestatistics', 'report_courseanalytics'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');

// Navigation breadcrumbs.
$PAGE->navbar->add(get_string('studentdashboard', 'report_courseanalytics'), new moodle_url('/report/courseanalytics/student.php'));
$PAGE->navbar->add(get_string('coursestatistics', 'report_courseanalytics'));

echo $OUTPUT->header();

// 2. Data Retrieval.
$cstats = student_dashboard_helper::get_course_activity_completion_stats($course, $userid);
$assignments = student_dashboard_helper::get_assignments_data([$course->id => $course], $userid);
$quizzes = student_dashboard_helper::get_quizzes_data([$course->id => $course], $userid);

// Check overall course completion.
$ccompletion = $DB->get_record('course_completions', ['course' => $course->id, 'userid' => $userid]);
$is_course_complete = ($ccompletion && !empty($ccompletion->timecompleted));

// 3. Top Banner & Progress Overview.
echo html_writer::start_div('card mb-4 shadow-sm');
echo html_writer::start_div('card-body');

$cat = $DB->get_record('course_categories', ['id' => $course->category], 'name');
$catname = $cat ? $cat->name : get_string('defaultcategoryname');
echo html_writer::tag('span', $catname, ['class' => 'badge bg-light text-dark border mb-2']);
echo html_writer::tag('h3', format_string($course->fullname), ['class' => 'card-title mb-2']);

// Progress summary.
$pct = $cstats['percentage'];
if ($pct !== null) {
    $bar_class = $pct >= 50.0 ? 'bg-success' : 'bg-warning text-dark';
    echo html_writer::start_div('row align-items-center mt-3');
    echo html_writer::start_div('col-md-8');
    echo html_writer::tag('div', "<strong>{$cstats['completed']}</strong> of <strong>{$cstats['total_tracked']}</strong> activities completed (<strong>{$pct}%</strong>)", ['class' => 'small mb-1']);
    echo html_writer::start_div('progress', ['style' => 'height: 16px;']);
    echo html_writer::div('', 'progress-bar ' . $bar_class, [
        'role' => 'progressbar',
        'style' => 'width: ' . $pct . '%;',
        'aria-valuenow' => $pct,
        'aria-valuemin' => 0,
        'aria-valuemax' => 100,
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::start_div('col-md-4 text-md-end mt-2 mt-md-0');
    if ($is_course_complete) {
        echo html_writer::span('<i class="fa fa-check-circle me-1"></i> Course Complete', 'badge bg-success p-2');
    } else {
        echo html_writer::span('Course In Progress', 'badge bg-secondary p-2');
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
} else {
    echo html_writer::div(get_string('completion_not_tracked', 'report_courseanalytics'), 'badge bg-secondary mt-2');
}

// Low Completion Alert (< 50%).
if ($cstats['below_50']) {
    $warn_msg = get_string('low_completion_warning', 'report_courseanalytics', $pct);
    echo html_writer::div('<i class="fa fa-exclamation-triangle me-2"></i><strong>Warning:</strong> ' . $warn_msg, 'alert alert-danger mt-3 mb-0');
}

echo html_writer::end_div();
echo html_writer::end_div();

// 4. Assignments Breakdown Table.
echo html_writer::tag('h4', '<i class="fa fa-tasks me-2"></i> Assignments', ['class' => 'mt-4 mb-3 border-bottom pb-2']);
if (empty($assignments)) {
    echo html_writer::div('No assignments configured in this course.', 'alert alert-light border text-muted');
} else {
    echo html_writer::start_tag('table', ['class' => 'generaltable table table-striped table-hover shadow-sm']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Assignment Name');
    echo html_writer::tag('th', 'Due Date');
    echo html_writer::tag('th', 'Status');
    echo html_writer::tag('th', 'Grade');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    foreach ($assignments as $a) {
        $link = html_writer::link(new moodle_url('/mod/assign/view.php', ['id' => $a['cmid']]), format_string($a['name']));
        $due_display = $a['effective_due'] > 0 ? userdate($a['effective_due']) : 'No due date';
        $state_label = get_string('state_' . $a['state'], 'report_courseanalytics');
        $badge_class = 'bg-secondary';
        if ($a['state'] === 'graded') {
            $badge_class = 'bg-success';
        } else if ($a['state'] === 'submitted') {
            $badge_class = 'bg-info text-dark';
        } else if ($a['state'] === 'overdue') {
            $badge_class = 'bg-danger';
        } else if ($a['is_pending']) {
            $badge_class = 'bg-warning text-dark';
        }

        $grade_display = $a['grade'] !== null ? round($a['grade'], 2) : '-';

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $link);
        echo html_writer::tag('td', $due_display);
        echo html_writer::tag('td', html_writer::span($state_label, 'badge ' . $badge_class . ' p-2'));
        echo html_writer::tag('td', $grade_display);
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

// 5. Quizzes Breakdown Table.
echo html_writer::tag('h4', '<i class="fa fa-question-circle me-2"></i> Quizzes', ['class' => 'mt-4 mb-3 border-bottom pb-2']);
if (empty($quizzes)) {
    echo html_writer::div('No quizzes configured in this course.', 'alert alert-light border text-muted');
} else {
    echo html_writer::start_tag('table', ['class' => 'generaltable table table-striped table-hover shadow-sm']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Quiz Name');
    echo html_writer::tag('th', 'Window');
    echo html_writer::tag('th', 'Attempts Taken');
    echo html_writer::tag('th', 'Availability');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    foreach ($quizzes as $q) {
        $link = html_writer::link(new moodle_url('/mod/quiz/view.php', ['id' => $q['cmid']]), format_string($q['name']));
        $window = [];
        if ($q['timeopen'] > 0) {
            $window[] = 'Opens: ' . userdate($q['timeopen']);
        }
        if ($q['timeclose'] > 0) {
            $window[] = 'Closes: ' . userdate($q['timeclose']);
        }
        $window_display = !empty($window) ? implode('<br>', $window) : 'Always open';

        $attempts_display = $q['max_attempts'] > 0 ? "{$q['attempts_count']} / {$q['max_attempts']}" : "{$q['attempts_count']} (Unlimited)";
        $status_label = get_string('quiz_' . $q['status'], 'report_courseanalytics');
        $badge_class = $q['status'] === 'available_now' ? 'bg-success' : 'bg-info text-dark';

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $link);
        echo html_writer::tag('td', $window_display);
        echo html_writer::tag('td', $attempts_display);
        echo html_writer::tag('td', html_writer::span($status_label, 'badge ' . $badge_class . ' p-2'));
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

// Back to Dashboard button.
echo html_writer::div(
    html_writer::link(new moodle_url('/report/courseanalytics/student.php'), '&larr; Back to Student Activity Dashboard', ['class' => 'btn btn-secondary mt-3']),
    'mb-4'
);

echo $OUTPUT->footer();

