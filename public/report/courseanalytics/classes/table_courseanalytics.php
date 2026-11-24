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
require_once($CFG->libdir . '/tablelib.php');

use table_sql;
use moodle_url;
use html_writer;

/**
 * Table class for displaying Course Analytics report.
 *
 * @package    report_courseanalytics
 * @copyright  2026 Abishek A S (Praskla Technology)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table_courseanalytics extends table_sql {

    /** @var array Filter parameters */
    protected array $filters = [];

    /**
     * Constructor.
     *
     * @param string $uniqueid Table identifier.
     * @param array $filters Array of active filters (search, category, completion, lastaccess).
     */
    public function __construct(string $uniqueid, array $filters = []) {
        parent::__construct($uniqueid);
        $this->filters = $filters;

        // Disassociate user fullname handling and disable user initial alphabet bars.
        $this->userfullnamecolumns = [];
        $this->initialbars(false);

        // Define table columns and headers.
        $columns = [
            'coursename',
            'categoryname',
            'enrolledstudents',
            'assignments',
            'quizzes',
            'completedactivities',
            'lastaccess',
            'completionpercentage',
        ];

        $headers = [
            get_string('coursename', 'report_courseanalytics'),
            get_string('category', 'report_courseanalytics'),
            get_string('enrolledstudents', 'report_courseanalytics'),
            get_string('assignments', 'report_courseanalytics'),
            get_string('quizzes', 'report_courseanalytics'),
            get_string('completedactivities', 'report_courseanalytics'),
            get_string('lastaccess', 'report_courseanalytics'),
            get_string('completionpercentage', 'report_courseanalytics'),
        ];

        $this->define_columns($columns);
        $this->define_headers($headers);

        // Sorting definitions.
        $this->sortable(true, 'coursename', SORT_ASC);
        $this->no_sorting('categoryname');

        $this->set_attribute('class', 'generaltable table-striped table-hover courseanalytics-table');
    }

    /**
     * Setup SQL query using pre-aggregated subqueries.
     */
    public function setup_sql_query(): void {
        global $DB;
        $now = time();

        $enrolled_sub = analytics_helper::get_enrolled_students_subquery($now);
        $activities_sub = analytics_helper::get_activities_subquery();
        $completions_sub = analytics_helper::get_completions_subquery($now);
        $lastaccess_sub = analytics_helper::get_lastaccess_subquery($now);

        $fields = "c.id, c.fullname, c.fullname AS coursename, c.shortname, c.category,
                   cc.name AS categoryname,
                   COALESCE(enr.studentcount, 0) AS enrolledstudents,
                   COALESCE(act.assigncount, 0) AS assignments,
                   COALESCE(act.quizcount, 0) AS quizzes,
                   COALESCE(act.trackedcount, 0) AS trackedactivities,
                   COALESCE(cmp.completedcount, 0) AS completedactivities,
                   ula.lastaccess";

        $from = "{course} c
                 JOIN {course_categories} cc ON cc.id = c.category
            LEFT JOIN ($enrolled_sub) enr ON enr.courseid = c.id
            LEFT JOIN ($activities_sub) act ON act.courseid = c.id
            LEFT JOIN ($completions_sub) cmp ON cmp.courseid = c.id
            LEFT JOIN ($lastaccess_sub) ula ON ula.courseid = c.id";

        $where = ["c.id <> :sitecourseid", "(c.deletioninprogress IS NULL OR c.deletioninprogress = 0)"];
        $params = ['sitecourseid' => SITEID];

        // Search filter.
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $where[] = "(" . $DB->sql_like('c.fullname', ':search1', false, false) .
                       " OR " . $DB->sql_like('c.shortname', ':search2', false, false) . ")";
            $params['search1'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
        }

        // Category filter.
        if (!empty($this->filters['category'])) {
            $where[] = "c.category = :categoryid";
            $params['categoryid'] = (int)$this->filters['category'];
        }

        // Last access filter.
        if (!empty($this->filters['lastaccess'])) {
            $la = $this->filters['lastaccess'];
            if ($la === 'today') {
                $today_start = usergetmidnight($now);
                $where[] = "ula.lastaccess >= :todaystart";
                $params['todaystart'] = $today_start;
            } else if ($la === 'last7days') {
                $seven_days_ago = $now - (7 * 86400);
                $where[] = "ula.lastaccess >= :sevenago";
                $params['sevenago'] = $seven_days_ago;
            } else if ($la === 'last30days') {
                $thirty_days_ago = $now - (30 * 86400);
                $where[] = "ula.lastaccess >= :thirtyago";
                $params['thirtyago'] = $thirty_days_ago;
            } else if ($la === 'never') {
                $where[] = "(ula.lastaccess IS NULL OR ula.lastaccess = 0)";
            }
        }

        // Completion status filter in SQL WHERE.
        if (!empty($this->filters['completion'])) {
            $comp = $this->filters['completion'];
            if ($comp === 'notstarted') {
                $where[] = "(cmp.completedcount IS NULL OR cmp.completedcount = 0)";
            } else if ($comp === 'inprogress') {
                $where[] = "cmp.completedcount > 0 AND cmp.completedcount < (enr.studentcount * act.trackedcount)";
            } else if ($comp === 'completed') {
                $where[] = "enr.studentcount > 0 AND act.trackedcount > 0 AND cmp.completedcount >= (enr.studentcount * act.trackedcount)";
            }
        }

        $where_str = implode(' AND ', $where);

        $this->set_sql($fields, $from, $where_str, $params);
        $this->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where_str", $params);
    }

    /**
     * Format course name column with clickable link.
     */
    public function col_coursename($row): string {
        $courseurl = new moodle_url('/course/view.php', ['id' => $row->id]);
        return html_writer::link($courseurl, format_string($row->fullname));
    }

    /**
     * Format course name column fallback.
     */
    public function col_fullname($row): string {
        return $this->col_coursename($row);
    }

    /**
     * Format last access timestamp.
     */
    public function col_lastaccess($row): string {
        if (empty($row->lastaccess)) {
            return html_writer::span(get_string('never', 'report_courseanalytics'), 'badge bg-secondary text-white');
        }
        return userdate($row->lastaccess, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Format completion percentage with visual progress badge.
     */
    public function col_completionpercentage($row): string {
        $enrolled = (int)$row->enrolledstudents;
        $tracked = (int)$row->trackedactivities;
        $completed = (int)$row->completedactivities;

        $pct = analytics_helper::calculate_completion_percentage($enrolled, $tracked, $completed);

        $badge_class = 'bg-secondary';
        if ($pct == 100.0) {
            $badge_class = 'bg-success';
        } else if ($pct >= 50.0) {
            $badge_class = 'bg-info text-dark';
        } else if ($pct > 0.0) {
            $badge_class = 'bg-warning text-dark';
        }

        return html_writer::span($pct . '%', 'badge ' . $badge_class . ' p-2');
    }
}
