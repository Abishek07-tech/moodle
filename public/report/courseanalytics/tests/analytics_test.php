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
require_once($CFG->dirroot . '/report/courseanalytics/classes/analytics_helper.php');
require_once($CFG->dirroot . '/report/courseanalytics/classes/table_courseanalytics.php');

/**
 * Unit tests for Course Analytics Report.
 *
 * @package    report_courseanalytics
 * @category   test
 * @copyright  2026 Abishek A S (Praskla Technology)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class analytics_test extends \advanced_testcase {

    /**
     * Test mathematical percentage calculation under various scenarios.
     */
    public function test_calculate_completion_percentage(): void {
        // Normal scenario: 10 students, 4 tracked activities, 20 completions -> 50%
        $this->assertEquals(50.0, analytics_helper::calculate_completion_percentage(10, 4, 20));

        // 100% completion scenario
        $this->assertEquals(100.0, analytics_helper::calculate_completion_percentage(5, 2, 10));

        // 0% completion scenario
        $this->assertEquals(0.0, analytics_helper::calculate_completion_percentage(5, 2, 0));

        // Edge case: No enrolled students -> 0%
        $this->assertEquals(0.0, analytics_helper::calculate_completion_percentage(0, 5, 10));

        // Edge case: No tracked activities in course -> 0%
        $this->assertEquals(0.0, analytics_helper::calculate_completion_percentage(10, 0, 10));

        // Boundary cap: Never exceed 100%
        $this->assertEquals(100.0, analytics_helper::calculate_completion_percentage(2, 2, 10));
    }

    /**
     * Test Course Analytics table query returns valid records and handles filters.
     */
    public function test_table_courseanalytics_query(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'PHP Architecture 101', 'shortname' => 'PHP101']);

        $table = new table_courseanalytics('test_tbl', ['search' => 'Architecture']);
        $table->define_baseurl(new \moodle_url('/report/courseanalytics/index.php'));
        $table->setup();
        $table->setup_sql_query();
        $table->query_db(20, false);

        $this->assertCount(1, $table->rawdata);
        $record = reset($table->rawdata);
        $this->assertEquals('PHP Architecture 101', $record->fullname);
        $this->assertEquals(0, $record->enrolledstudents);
        $this->assertEquals(0, $record->assignments);
        $this->assertEquals(0, $record->quizzes);
    }
}

