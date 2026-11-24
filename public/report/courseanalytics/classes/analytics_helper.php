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

/**
 * Analytics helper providing calculation and data querying logic.
 *
 * @package    report_courseanalytics
 * @copyright  2026 Abishek A S (Praskla Technology)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics_helper {

    /**
     * Get SQL for active eligible enrolled students subquery.
     *
     * @param int $now Current timestamp.
     * @return string Subquery returning (courseid, studentcount)
     */
    public static function get_enrolled_students_subquery(int $now): string {
        // Enrolled via enabled enrolment instance, active user enrolment, valid time range,
        // active user account, assigned role with student archetype in course context.
        return "SELECT e.courseid, COUNT(DISTINCT ue.userid) AS studentcount
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                  JOIN {context} ctx ON (ctx.instanceid = e.courseid AND ctx.contextlevel = " . CONTEXT_COURSE . ")
                  JOIN {role_assignments} ra ON (ra.userid = u.id AND ra.contextid = ctx.id)
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE e.status = 0
                   AND ue.status = 0
                   AND (ue.timestart = 0 OR ue.timestart <= $now)
                   AND (ue.timeend = 0 OR ue.timeend >= $now)
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND r.archetype = 'student'
              GROUP BY e.courseid";
    }

    /**
     * Get SQL for assignments, quizzes and tracked activities count.
     *
     * @return string Subquery returning (courseid, assigncount, quizcount, trackedcount)
     */
    public static function get_activities_subquery(): string {
        return "SELECT cm.course AS courseid,
                       SUM(CASE WHEN m.name = 'assign' THEN 1 ELSE 0 END) AS assigncount,
                       SUM(CASE WHEN m.name = 'quiz' THEN 1 ELSE 0 END) AS quizcount,
                       SUM(CASE WHEN cm.completion > 0 THEN 1 ELSE 0 END) AS trackedcount
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.deletioninprogress = 0
              GROUP BY cm.course";
    }

    /**
     * Get SQL for completed activities count across eligible enrolled students.
     *
     * @return string Subquery returning (courseid, completedcount)
     */
    public static function get_completions_subquery(int $now): string {
        // Completed activity state in (1, 2, 3), activity not deleted and completion enabled,
        // user is an active enrolled student.
        return "SELECT cm.course AS courseid,
                       COUNT(DISTINCT CONCAT(cmc.coursemoduleid, '_', cmc.userid)) AS completedcount
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                  JOIN {enrol} e ON e.courseid = cm.course
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = cmc.userid)
                  JOIN {user} u ON u.id = cmc.userid
                  JOIN {context} ctx ON (ctx.instanceid = cm.course AND ctx.contextlevel = " . CONTEXT_COURSE . ")
                  JOIN {role_assignments} ra ON (ra.userid = u.id AND ra.contextid = ctx.id)
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE cmc.completionstate IN (1, 2, 3)
                   AND cm.deletioninprogress = 0
                   AND cm.completion > 0
                   AND e.status = 0
                   AND ue.status = 0
                   AND (ue.timestart = 0 OR ue.timestart <= $now)
                   AND (ue.timeend = 0 OR ue.timeend >= $now)
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND r.archetype = 'student'
              GROUP BY cm.course";
    }

    /**
     * Get SQL for last access by eligible enrolled students.
     *
     * @param int $now Current timestamp.
     * @return string Subquery returning (courseid, lastaccess)
     */
    public static function get_lastaccess_subquery(int $now): string {
        return "SELECT ula.courseid, MAX(ula.timeaccess) AS lastaccess
                  FROM {user_lastaccess} ula
                  JOIN {enrol} e ON e.courseid = ula.courseid
                  JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = ula.userid)
                  JOIN {user} u ON u.id = ula.userid
                  JOIN {context} ctx ON (ctx.instanceid = ula.courseid AND ctx.contextlevel = " . CONTEXT_COURSE . ")
                  JOIN {role_assignments} ra ON (ra.userid = u.id AND ra.contextid = ctx.id)
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE e.status = 0
                   AND ue.status = 0
                   AND (ue.timestart = 0 OR ue.timestart <= $now)
                   AND (ue.timeend = 0 OR ue.timeend >= $now)
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND r.archetype = 'student'
              GROUP BY ula.courseid";
    }

    /**
     * Calculate completion percentage safely.
     *
     * @param int $enrolled Enrolled student count.
     * @param int $tracked Activities with completion tracking enabled.
     * @param int $completed Total completed activity records.
     * @return float Calculated percentage (0.0 - 100.0).
     */
    public static function calculate_completion_percentage(int $enrolled, int $tracked, int $completed): float {
        if ($enrolled <= 0 || $tracked <= 0 || $completed <= 0) {
            return 0.0;
        }
        $total_possible = $enrolled * $tracked;
        $pct = ($completed / $total_possible) * 100.0;
        return round(min(100.0, max(0.0, $pct)), 1);
    }
}

