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
 * Course Analytics Report main controller.
 *
 * @package    report_courseanalytics
 * @copyright  2026 Abishek A S (Praskla Technology)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security & Capability Check.
admin_externalpage_setup('report_courseanalytics');
$context = context_system::instance();
require_capability('report/courseanalytics:view', $context);

// Request parameters.
$search     = optional_param('search', '', PARAM_NOTAGS);
$category   = optional_param('category', 0, PARAM_INT);
$completion = optional_param('completion', '', PARAM_ALPHA);
$lastaccess = optional_param('lastaccess', '', PARAM_ALPHA);
$perpage    = optional_param('perpage', 20, PARAM_INT);

$baseurl = new moodle_url('/report/courseanalytics/index.php', [
    'search'     => $search,
    'category'   => $category,
    'completion' => $completion,
    'lastaccess' => $lastaccess,
    'perpage'    => $perpage,
]);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'report_courseanalytics'));
$PAGE->set_heading(get_string('pluginname', 'report_courseanalytics'));

echo $OUTPUT->header();

// Render Filter Form.
echo html_writer::start_tag('div', ['class' => 'card mb-4 shadow-sm']);
echo html_writer::start_tag('div', ['class' => 'card-body']);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/report/courseanalytics/index.php'),
    'class'  => 'row g-3 align-items-end',
]);

// 1. Search Field.
echo html_writer::start_tag('div', ['class' => 'col-md-3']);
echo html_writer::label(get_string('coursename', 'report_courseanalytics'), 'id_search', false, ['class' => 'form-label fw-bold']);
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'name'        => 'search',
    'id'          => 'id_search',
    'class'       => 'form-control',
    'placeholder' => get_string('searchcourses', 'report_courseanalytics'),
    'value'       => $search,
]);
echo html_writer::end_tag('div');

// 2. Category Filter.
$categories = $DB->get_records_menu('course_categories', null, 'name ASC', 'id, name');
$cat_options = [0 => get_string('allcategories', 'report_courseanalytics')] + $categories;
echo html_writer::start_tag('div', ['class' => 'col-md-2']);
echo html_writer::label(get_string('filter_category', 'report_courseanalytics'), 'id_category', false, ['class' => 'form-label fw-bold']);
echo html_writer::select($cat_options, 'category', $category, false, ['id' => 'id_category', 'class' => 'form-select']);
echo html_writer::end_tag('div');

// 3. Completion Status Filter.
$comp_options = [
    ''           => get_string('status_all', 'report_courseanalytics'),
    'notstarted' => get_string('status_notstarted', 'report_courseanalytics'),
    'inprogress' => get_string('status_inprogress', 'report_courseanalytics'),
    'completed'  => get_string('status_completed', 'report_courseanalytics'),
];
echo html_writer::start_tag('div', ['class' => 'col-md-2']);
echo html_writer::label(get_string('filter_completion', 'report_courseanalytics'), 'id_completion', false, ['class' => 'form-label fw-bold']);
echo html_writer::select($comp_options, 'completion', $completion, false, ['id' => 'id_completion', 'class' => 'form-select']);
echo html_writer::end_tag('div');

// 4. Last Access Filter.
$access_options = [
    ''           => get_string('access_all', 'report_courseanalytics'),
    'today'      => get_string('access_today', 'report_courseanalytics'),
    'last7days'  => get_string('access_last7days', 'report_courseanalytics'),
    'last30days' => get_string('access_last30days', 'report_courseanalytics'),
    'never'      => get_string('access_never', 'report_courseanalytics'),
];
echo html_writer::start_tag('div', ['class' => 'col-md-2']);
echo html_writer::label(get_string('filter_lastaccess', 'report_courseanalytics'), 'id_lastaccess', false, ['class' => 'form-label fw-bold']);
echo html_writer::select($access_options, 'lastaccess', $lastaccess, false, ['id' => 'id_lastaccess', 'class' => 'form-select']);
echo html_writer::end_tag('div');

// 5. Actions (Submit & Reset).
echo html_writer::start_tag('div', ['class' => 'col-md-3 d-flex gap-2']);
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('applyfilters', 'report_courseanalytics'),
    'class' => 'btn btn-primary',
]);
$reseturl = new moodle_url('/report/courseanalytics/index.php');
echo html_writer::link($reseturl, get_string('resetfilters', 'report_courseanalytics'), ['class' => 'btn btn-outline-secondary']);
echo html_writer::end_tag('div');

echo html_writer::end_tag('form');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// Render Table with table_sql.
$table = new \report_courseanalytics\table_courseanalytics('courseanalytics_tbl', [
    'search'     => $search,
    'category'   => $category,
    'completion' => $completion,
    'lastaccess' => $lastaccess,
]);

$table->define_baseurl($baseurl);
$table->setup_sql_query();
$table->out($perpage, true);

echo $OUTPUT->footer();

