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

namespace report_courseanalytics;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use stdClass;
use context_course;
use context_module;
use completion_info;
use assign;
use mod_quiz\quiz_settings;

/**
 * Service helper providing accurate student learning metrics and data models.
 *
 * @package    report_courseanalytics
 * @copyright  2026 Abishek A S (Praskla Technology)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_dashboard_helper {

    /**
     * Get active enrolled courses for the student.
     *
     * @param int $userid Target student user ID.
     * @return array Array of course objects with category, completion, and enrolment details.
     */
    public static function get_enrolled_courses(int $userid): array {
        global $DB;

        // Retrieve only active, visible courses where the student has an active enrolment.
        $courses = enrol_get_users_courses($userid, true, 'id, fullname, shortname, category, enablecompletion, visible');
        $result = [];

        foreach ($courses as $c) {
            if ($c->id == SITEID) {
                continue;
            }

            // Retrieve category name.
            $cat = $DB->get_record('course_categories', ['id' => $c->category], 'name');
            $c->categoryname = $cat ? $cat->name : get_string('defaultcategoryname');

            // Check course completion status (mdl_course_completions).
            $c->course_completed = false;
            $ccompletion = $DB->get_record('course_completions', ['course' => $c->id, 'userid' => $userid]);
            if ($ccompletion && !empty($ccompletion->timecompleted)) {
                $c->course_completed = true;
                $c->timecompleted = $ccompletion->timecompleted;
            }

            // Calculate student-specific activity completion percentage.
            $comp_stats = self::get_course_activity_completion_stats($c, $userid);
            $c->completion_stats = $comp_stats;

            $result[$c->id] = $c;
        }

        return $result;
    }

    /**
     * Calculate student-specific activity completion percentage and counts for a course.
     *
     * @param stdClass $course Course record.
     * @param int $userid User ID.
     * @return array Stats array with total_tracked, completed, percentage, and warning flag.
     */
    public static function get_course_activity_completion_stats(stdClass $course, int $userid): array {
        global $DB;

        if (empty($course->enablecompletion)) {
            return [
                'enabled'      => false,
                'total_tracked'=> 0,
                'completed'    => 0,
                'percentage'   => null,
                'below_50'     => false,
            ];
        }

        $completioninfo = new completion_info($course);
        if (!$completioninfo->is_enabled()) {
            return [
                'enabled'      => false,
                'total_tracked'=> 0,
                'completed'    => 0,
                'percentage'   => null,
                'below_50'     => false,
            ];
        }

        $modinfo = get_fast_modinfo($course, $userid);
        $total_tracked = 0;
        $completed = 0;

        foreach ($modinfo->cms as $cm) {
            if ($cm->deletioninprogress || !$cm->uservisible) {
                continue;
            }
            if ($cm->completion == COMPLETION_TRACKING_NONE) {
                continue;
            }

            $total_tracked++;
            $data = $completioninfo->get_data($cm, false, $userid);

            if (in_array((int)$data->completionstate, [
                COMPLETION_COMPLETE,
                COMPLETION_COMPLETE_PASS,
                COMPLETION_COMPLETE_FAIL,
            ], true)) {
                $completed++;
            }
        }

        if ($total_tracked === 0) {
            return [
                'enabled'      => true,
                'total_tracked'=> 0,
                'completed'    => 0,
                'percentage'   => null,
                'below_50'     => false,
            ];
        }

        $pct = round(($completed / $total_tracked) * 100.0, 1);
        $pct = min(100.0, max(0.0, $pct));

        return [
            'enabled'      => true,
            'total_tracked'=> $total_tracked,
            'completed'    => $completed,
            'percentage'   => $pct,
            'below_50'     => ($pct < 50.0),
        ];
    }

    /**
     * Retrieve all assignments across the student's enrolled courses with exact lifecycle states.
     *
     * @param array $enrolled_courses Array of enrolled course objects.
     * @param int $userid User ID.
     * @return array Evaluated assignments grouped and list.
     */
    public static function get_assignments_data(array $enrolled_courses, int $userid): array {
        global $DB;
        $assignments = [];
        $now = time();

        if (empty($enrolled_courses)) {
            return $assignments;
        }

        $assign_module = $DB->get_record('modules', ['name' => 'assign']);
        if (!$assign_module) {
            return $assignments;
        }

        foreach ($enrolled_courses as $course) {
            $cms = $DB->get_records('course_modules', [
                'course' => $course->id,
                'module' => $assign_module->id,
                'deletioninprogress' => 0,
            ]);

            foreach ($cms as $cm_record) {
                $context = context_module::instance($cm_record->id);
                $assign = new assign($context, $cm_record, $course);

                // Exclude tasks that do not require student submissions.
                if (!$assign->is_any_submission_plugin_enabled() || !empty($assign->get_instance()->nosubmissions)) {
                    continue;
                }

                $assign_instance = $assign->get_instance($userid);
                $assign->update_effective_access($userid);
                $userflags = $assign->get_user_flags($userid, false);

                $effective_due = !empty($userflags->extensionduedate) ? (int)$userflags->extensionduedate : (int)$assign_instance->duedate;
                $submissions_open = $assign->submissions_open($userid);
                $submission = $assign->get_user_submission($userid, false);

                // Check grading status.
                $usergrade = $assign->get_user_grade($userid, false);
                $is_graded = false;

                if ($assign_instance->markingworkflow) {
                    $workflowstate = $assign->get_grading_status($userid);
                    $is_graded = ($workflowstate === ASSIGN_MARKING_WORKFLOW_STATE_RELEASED);
                } else {
                    $is_graded = ($usergrade && $usergrade->grade !== null && (float)$usergrade->grade >= 0);
                }

                // Determine the 6 mutually exclusive states:
                $state = '';
                $is_pending = false;

                if ($is_graded) {
                    $state = 'graded';
                } else if ($submission && $submission->status === 'submitted') {
                    $state = 'submitted';
                } else if ($submission && $submission->status === 'draft') {
                    $state = $submissions_open ? 'draft_submittable' : 'draft_closed';
                    if ($submissions_open) {
                        $is_pending = true;
                    }
                } else if ($submissions_open) {
                    if ($effective_due > 0 && $now > $effective_due) {
                        $state = 'overdue';
                        $is_pending = true;
                    } else {
                        $state = 'pending';
                        $is_pending = true;
                    }
                } else {
                    $state = 'closed';
                }

                $assignments[] = [
                    'id'              => $assign_instance->id,
                    'cmid'            => $cm_record->id,
                    'name'            => $assign_instance->name,
                    'courseid'        => $course->id,
                    'coursename'      => $course->fullname,
                    'effective_due'   => $effective_due,
                    'submissions_open'=> $submissions_open,
                    'state'           => $state,
                    'is_pending'      => $is_pending,
                    'grade'           => $usergrade ? $usergrade->grade : null,
                ];
            }
        }

        return $assignments;
    }

    /**
     * Retrieve all quizzes across enrolled courses with access manager verification.
     *
     * @param array $enrolled_courses Array of enrolled course objects.
     * @param int $userid User ID.
     * @return array Evaluated quizzes.
     */
    public static function get_quizzes_data(array $enrolled_courses, int $userid): array {
        global $DB;
        $quizzes = [];
        $now = time();

        if (empty($enrolled_courses)) {
            return $quizzes;
        }

        $quiz_module = $DB->get_record('modules', ['name' => 'quiz']);
        if (!$quiz_module) {
            return $quizzes;
        }

        foreach ($enrolled_courses as $course) {
            $cms = $DB->get_records('course_modules', [
                'course' => $course->id,
                'module' => $quiz_module->id,
                'deletioninprogress' => 0,
            ]);

            foreach ($cms as $cm_record) {
                if (!$cm_record->visible) {
                    continue;
                }

                $quiz = $DB->get_record('quiz', ['id' => $cm_record->instance]);
                if (!$quiz) {
                    continue;
                }

                $quizobj = quiz_settings::create($quiz->id, $userid);
                $accessmanager = $quizobj->get_access_manager($now);

                // Check attempts.
                $all_attempts = quiz_get_user_attempts($quiz->id, $userid, 'all');
                $unfinished_attempts = quiz_get_user_attempts($quiz->id, $userid, 'unfinished');
                $finished_attempts = quiz_get_user_attempts($quiz->id, $userid, 'finished');

                $attempts_count = count($all_attempts);
                $has_in_progress = !empty($unfinished_attempts);

                $attempts_exhausted = ($quiz->attempts > 0 && $attempts_count >= $quiz->attempts && !$has_in_progress);
                $is_closed = ($quiz->timeclose > 0 && $now > $quiz->timeclose);

                $prevent_access_msgs = $accessmanager->prevent_access();
                $is_future_open = ($quiz->timeopen > 0 && $now < $quiz->timeopen);

                $status = '';
                $is_upcoming = false;

                if ($has_in_progress) {
                    $status = 'in_progress';
                } else if ($attempts_exhausted) {
                    $status = 'attempts_exhausted';
                } else if ($is_closed) {
                    $status = 'closed';
                } else if ($is_future_open) {
                    $status = 'upcoming_future';
                    $is_upcoming = true;
                } else if (!empty($prevent_access_msgs)) {
                    $status = 'restricted';
                } else {
                    $status = 'available_now';
                    $is_upcoming = true;
                }

                $quizzes[] = [
                    'id'               => $quiz->id,
                    'cmid'             => $cm_record->id,
                    'name'             => $quiz->name,
                    'courseid'         => $course->id,
                    'coursename'       => $course->fullname,
                    'timeopen'         => (int)$quiz->timeopen,
                    'timeclose'        => (int)$quiz->timeclose,
                    'attempts_count'   => $attempts_count,
                    'max_attempts'     => (int)$quiz->attempts,
                    'status'           => $status,
                    'is_upcoming'      => $is_upcoming,
                ];
            }
        }

        return $quizzes;
    }

    /**
     * Retrieve recorded login events from Moodle standard logstore.
     *
     * @param int $userid User ID.
     * @return array Status array with count, available boolean, and descriptive note.
     */
    public static function get_login_activity(int $userid): array {
        global $DB, $USER;

        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers('\core\log\sql_reader');
        $has_reader = !empty($readers['logstore_standard']);

        $user_record = $DB->get_record('user', ['id' => $userid], 'lastlogin, currentlogin');
        $lastlogin_timestamp = $user_record ? (int)$user_record->lastlogin : 0;

        if (!$has_reader) {
            return [
                'count'     => null,
                'available' => false,
                'message'   => get_string('login_count_unavailable', 'report_courseanalytics'),
                'lastlogin' => $lastlogin_timestamp,
            ];
        }

        $count = $DB->count_records('logstore_standard_log', [
            'eventname' => '\core\event\user_loggedin',
            'userid'    => $userid,
        ]);

        return [
            'count'     => $count,
            'available' => true,
            'message'   => $count . ' ' . get_string('recorded_sessions', 'report_courseanalytics'),
            'lastlogin' => $lastlogin_timestamp,
        ];
    }

    /**
     * Calculate the arithmetic average of activity completion percentages across eligible courses.
     *
     * @param array $enrolled_courses Array of enrolled course objects with completion_stats.
     * @return float|null Arithmetic average or null if no completion-tracked courses exist.
     */
    public static function calculate_average_completion(array $enrolled_courses): ?float {
        $total_pct = 0.0;
        $eligible_course_count = 0;

        foreach ($enrolled_courses as $c) {
            if (!empty($c->completion_stats['enabled']) && $c->completion_stats['percentage'] !== null) {
                $total_pct += (float)$c->completion_stats['percentage'];
                $eligible_course_count++;
            }
        }

        if ($eligible_course_count === 0) {
            return null;
        }

        return round($total_pct / $eligible_course_count, 1);
    }
}
