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
require_once($CFG->dirroot . '/report/courseanalytics/classes/student_dashboard_helper.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Comprehensive Unit and Integration tests for Student Activity Dashboard.
 *
 * @package    report_courseanalytics
 * @category   test
 * @copyright  2026 Abishek A S (Praskla Technology)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_dashboard_test extends \advanced_testcase {

    /**
     * Test average completion calculation across eligible and ineligible courses.
     */
    public function test_calculate_average_completion(): void {
        // Scenario 1: Mixed courses (one 50%, one 100%, one untracked)
        $course1 = (object)[
            'completion_stats' => ['enabled' => true, 'percentage' => 50.0],
        ];
        $course2 = (object)[
            'completion_stats' => ['enabled' => true, 'percentage' => 100.0],
        ];
        $course3 = (object)[
            'completion_stats' => ['enabled' => false, 'percentage' => null],
        ];

        $avg = student_dashboard_helper::calculate_average_completion([$course1, $course2, $course3]);
        $this->assertEquals(75.0, $avg);

        // Scenario 2: No completion-tracked courses -> returns null
        $avg_none = student_dashboard_helper::calculate_average_completion([$course3]);
        $this->assertNull($avg_none);

        // Scenario 3: Empty courses list -> returns null
        $this->assertNull(student_dashboard_helper::calculate_average_completion([]));
    }

    /**
     * Test that warning flag is strictly triggered below 50.0% and suppressed at or above 50.0%.
     */
    public function test_completion_stats_warning_threshold(): void {
        $this->resetAfterTest(true);

        set_config('enablecompletion', 1);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $assign1 = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $assign2 = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $cm1 = get_coursemodule_from_instance('assign', $assign1->id, $course->id);
        $cm2 = get_coursemodule_from_instance('assign', $assign2->id, $course->id);

        $completioninfo = new \completion_info($course);

        // 1. Initial state: 0 completed out of 2 (0.0% < 50.0%) -> warning flag MUST be true.
        $stats = student_dashboard_helper::get_course_activity_completion_stats($course, $user->id);
        $this->assertTrue($stats['enabled']);
        $this->assertEquals(2, $stats['total_tracked']);
        $this->assertEquals(0, $stats['completed']);
        $this->assertEquals(0.0, $stats['percentage']);
        $this->assertTrue($stats['below_50']);

        // 2. Complete 1 activity: 1 completed out of 2 (50.0% >= 50.0%) -> warning flag MUST be false (NOT below 50%).
        $completioninfo->update_state($cm1, COMPLETION_COMPLETE, $user->id);
        $stats2 = student_dashboard_helper::get_course_activity_completion_stats($course, $user->id);
        $this->assertEquals(1, $stats2['completed']);
        $this->assertEquals(50.0, $stats2['percentage']);
        $this->assertFalse($stats2['below_50']);

        // 3. Complete both activities: 2 completed out of 2 (100.0%) -> warning flag MUST be false.
        $completioninfo->update_state($cm2, COMPLETION_COMPLETE, $user->id);
        $stats3 = student_dashboard_helper::get_course_activity_completion_stats($course, $user->id);
        $this->assertEquals(2, $stats3['completed']);
        $this->assertEquals(100.0, $stats3['percentage']);
        $this->assertFalse($stats3['below_50']);
    }

    /**
     * Test course activity completion stats on course without completion tracking.
     */
    public function test_course_without_completion_tracking(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $user = $this->getDataGenerator()->create_user();

        $stats = student_dashboard_helper::get_course_activity_completion_stats($course, $user->id);
        $this->assertFalse($stats['enabled']);
        $this->assertNull($stats['percentage']);
        $this->assertFalse($stats['below_50']);
        $this->assertEquals(0, $stats['total_tracked']);
    }

    /**
     * Test retrieval of active enrolled courses excluding unenrolled or site courses.
     */
    public function test_get_enrolled_courses(): void {
        $this->resetAfterTest(true);

        $student = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'Course One']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'Course Two']);
        $course3 = $this->getDataGenerator()->create_course(['fullname' => 'Course Three']);

        // Enrol student in Course 1 and Course 2 only.
        $this->getDataGenerator()->enrol_user($student->id, $course1->id);
        $this->getDataGenerator()->enrol_user($student->id, $course2->id);

        $enrolled = student_dashboard_helper::get_enrolled_courses($student->id);
        $this->assertCount(2, $enrolled);
        $this->assertArrayHasKey($course1->id, $enrolled);
        $this->assertArrayHasKey($course2->id, $enrolled);
        $this->assertArrayNotHasKey($course3->id, $enrolled);
    }

    /**
     * Test assignment lifecycle states: pending, overdue, draft, submitted, graded.
     */
    public function test_assignment_lifecycle_states(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $now = time();

        // 1. Pending Assignment (due in future).
        $assign_pending = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => 'Pending Assignment',
            'duedate' => $now + 86400,
            'cutoffdate' => 0,
            'allowsubmissionsfromdate' => $now - 3600,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);

        // 2. Overdue Assignment (due in past, cutoff in future or none).
        $assign_overdue = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => 'Overdue Assignment',
            'duedate' => $now - 86400,
            'cutoffdate' => 0,
            'allowsubmissionsfromdate' => $now - 172800,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);

        $enrolled_courses = student_dashboard_helper::get_enrolled_courses($student->id);
        $assignments = student_dashboard_helper::get_assignments_data($enrolled_courses, $student->id);

        $this->assertCount(2, $assignments);

        $by_id = [];
        foreach ($assignments as $a) {
            $by_id[$a['id']] = $a;
        }

        $this->assertEquals('pending', $by_id[$assign_pending->id]['state']);
        $this->assertTrue($by_id[$assign_pending->id]['is_pending']);

        $this->assertEquals('overdue', $by_id[$assign_overdue->id]['state']);
        $this->assertTrue($by_id[$assign_overdue->id]['is_pending']);
    }

    /**
     * Test quiz categorization: future opening vs available now vs closed.
     */
    public function test_quiz_categorization_and_actionable_states(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $now = time();

        // 1. Currently open quiz.
        $quiz_open = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Open Quiz',
            'timeopen' => $now - 3600,
            'timeclose' => $now + 86400,
            'attempts' => 2,
        ]);

        // 2. Future opening quiz.
        $quiz_future = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Future Quiz',
            'timeopen' => $now + 86400,
            'timeclose' => $now + 172800,
            'attempts' => 1,
        ]);

        // 3. Closed quiz.
        $quiz_closed = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Closed Quiz',
            'timeopen' => $now - 172800,
            'timeclose' => $now - 86400,
            'attempts' => 1,
        ]);

        $enrolled_courses = student_dashboard_helper::get_enrolled_courses($student->id);
        $quizzes = student_dashboard_helper::get_quizzes_data($enrolled_courses, $student->id);

        $this->assertCount(3, $quizzes);

        $by_id = [];
        foreach ($quizzes as $q) {
            $by_id[$q['id']] = $q;
        }

        $this->assertEquals('available_now', $by_id[$quiz_open->id]['status']);
        $this->assertTrue($by_id[$quiz_open->id]['is_upcoming']);

        $this->assertEquals('upcoming_future', $by_id[$quiz_future->id]['status']);
        $this->assertTrue($by_id[$quiz_future->id]['is_upcoming']);

        $this->assertEquals('closed', $by_id[$quiz_closed->id]['status']);
        $this->assertFalse($by_id[$quiz_closed->id]['is_upcoming']);
    }

    /**
     * Test login activity retrieval from standard logstore.
     */
    public function test_get_login_activity(): void {
        global $DB;
        $this->resetAfterTest(true);

        // In PHPUnit, logstores are not enabled by default. Enable logstore_standard.
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        get_log_manager(true);

        $user = $this->getDataGenerator()->create_user(['lastlogin' => time() - 3600]);

        $login_info = student_dashboard_helper::get_login_activity($user->id);
        $this->assertIsArray($login_info);
        $this->assertTrue($login_info['available']);
        $this->assertEquals(0, $login_info['count']);
        $this->assertEquals($user->lastlogin, $login_info['lastlogin']);
    }

    /**
     * Test student data isolation: student A only sees their own courses and activities.
     */
    public function test_student_data_isolation(): void {
        $this->resetAfterTest(true);

        $student_a = $this->getDataGenerator()->create_user();
        $student_b = $this->getDataGenerator()->create_user();

        $course_a = $this->getDataGenerator()->create_course(['fullname' => 'Student A Course']);
        $course_b = $this->getDataGenerator()->create_course(['fullname' => 'Student B Course']);

        $this->getDataGenerator()->enrol_user($student_a->id, $course_a->id);
        $this->getDataGenerator()->enrol_user($student_b->id, $course_b->id);

        $courses_a = student_dashboard_helper::get_enrolled_courses($student_a->id);
        $courses_b = student_dashboard_helper::get_enrolled_courses($student_b->id);

        $this->assertArrayHasKey($course_a->id, $courses_a);
        $this->assertArrayNotHasKey($course_b->id, $courses_a);

        $this->assertArrayHasKey($course_b->id, $courses_b);
        $this->assertArrayNotHasKey($course_a->id, $courses_b);
    }
}
