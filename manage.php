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
 * Admin interface listing archived courses.
 *
 * @package    local_autoarchive
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core\context\system as context_system;
use core\output\html_writer;
use core\url;
use local_autoarchive\table\archived_courses_table;

$search = trim(optional_param('search', '', PARAM_TEXT));
$teacher = trim(optional_param('teacher', '', PARAM_TEXT));
$datefilter = optional_param('datefilter', '', PARAM_ALPHANUM);
$perpage = optional_param('perpage', 20, PARAM_INT);

admin_externalpage_setup('local_autoarchive_manage');

$datefrom = 0;
$dateto = 0;
$presets = ['lastweek' => '-1 week', 'lastmonth' => '-1 month', 'last3months' => '-3 months', 'lastyear' => '-1 year'];
if (isset($presets[$datefilter])) {
    $dateto = time();
    $datefrom = strtotime($presets[$datefilter], $dateto);
} else {
    $datefilter = '';
}

$perpageoptions = [20 => 20, 50 => 50, 100 => 100, 500 => 500];
if (!isset($perpageoptions[$perpage])) {
    $perpage = 20;
}

// Keep the filters when paging or sorting.
$baseurl = new url('/local/autoarchive/manage.php', array_filter([
    'search' => $search,
    'teacher' => $teacher,
    'datefilter' => $datefilter,
    'perpage' => $perpage == 20 ? 0 : $perpage,
]));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managearchivedcourses', 'local_autoarchive'));

// Filter form.
echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('searchandfilter', 'local_autoarchive'), ['class' => 'card-title']);
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new url('/local/autoarchive/manage.php'))->out(false),
    'class' => 'd-flex flex-wrap align-items-end gap-3',
]);

echo html_writer::start_div();
echo html_writer::label(get_string('searchcoursename', 'local_autoarchive'), 'search', false, ['class' => 'form-label fw-bold']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'id' => 'search',
    'value' => $search,
    'class' => 'form-control',
]);
echo html_writer::end_div();

echo html_writer::start_div();
echo html_writer::label(get_string('filterteacher', 'local_autoarchive'), 'teacher', false, ['class' => 'form-label fw-bold']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'teacher',
    'id' => 'teacher',
    'value' => $teacher,
    'class' => 'form-control',
]);
echo html_writer::end_div();

$dateoptions = [
    '' => get_string('alldates', 'local_autoarchive'),
    'lastweek' => get_string('lastweek', 'local_autoarchive'),
    'lastmonth' => get_string('lastmonth', 'local_autoarchive'),
    'last3months' => get_string('last3months', 'local_autoarchive'),
    'lastyear' => get_string('lastyear', 'local_autoarchive'),
];
echo html_writer::start_div();
echo html_writer::label(get_string('filterdate', 'local_autoarchive'), 'datefilter', false, ['class' => 'form-label fw-bold']);
echo html_writer::select($dateoptions, 'datefilter', $datefilter, false, ['id' => 'datefilter', 'class' => 'form-select']);
echo html_writer::end_div();

echo html_writer::start_div();
echo html_writer::label(get_string('perpage', 'local_autoarchive'), 'perpage', false, ['class' => 'form-label fw-bold']);
echo html_writer::select($perpageoptions, 'perpage', $perpage, false, ['id' => 'perpage', 'class' => 'form-select']);
echo html_writer::end_div();

echo html_writer::start_div();
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
if ($search !== '' || $teacher !== '' || $datefilter !== '' || $perpage != 20) {
    echo ' ' . html_writer::link(new url('/local/autoarchive/manage.php'), get_string('reset'), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

// Bulk delete form.
if (has_capability('local/autoarchive:delete', context_system::instance())) {
    echo html_writer::start_div('card mb-3 border-danger');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', get_string('bulkdelete_heading', 'local_autoarchive'), ['class' => 'card-title text-danger']);

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new url('/local/autoarchive/bulk_delete.php'))->out(false),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo html_writer::start_div('mb-3');
    echo html_writer::label(
        get_string('bulkdelete_days', 'local_autoarchive'),
        'bulkdelete_days',
        false,
        ['class' => 'form-label fw-bold']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'days',
        'id' => 'bulkdelete_days',
        'min' => '1',
        'max' => '7300',
        'value' => 365,
        'class' => 'form-control',
        'required' => 'required',
        'style' => 'width: 8rem;',
    ]);
    echo html_writer::div(get_string('bulkdelete_days_help', 'local_autoarchive'), 'form-text');
    echo html_writer::end_div();

    echo html_writer::start_div('form-check mb-3');
    echo html_writer::empty_tag('input', [
        'type' => 'checkbox',
        'name' => 'deletebackups',
        'id' => 'deletebackups',
        'value' => '1',
        'class' => 'form-check-input',
    ]);
    echo html_writer::label(
        get_string('bulkdelete_includebackups', 'local_autoarchive'),
        'deletebackups',
        false,
        ['class' => 'form-check-label']
    );
    echo html_writer::div(get_string('bulkdelete_includebackups_help', 'local_autoarchive'), 'form-text');
    echo html_writer::end_div();

    echo html_writer::tag('button', get_string('bulkdelete_button', 'local_autoarchive'), [
        'type' => 'submit',
        'class' => 'btn btn-danger',
    ]);
    echo html_writer::end_tag('form');

    echo html_writer::end_div();
    echo html_writer::end_div();
}

$table = new archived_courses_table('local_autoarchive_archived_courses', $baseurl);
$table->setup_sql($search, $teacher, $datefrom, $dateto);

if (!$table->has_bulk_actions()) {
    $table->out($perpage, false);
} else {
    // The table sits inside the form so that the selected rows are submitted with the bulk action.
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new url('/local/autoarchive/bulk_action.php'))->out(false),
        'id' => 'local_autoarchive_bulkform',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    $table->out($perpage, false);

    if ($table->totalrows > 0) {
        $bulkoptions = [
            'deletearchives' => get_string('bulkaction_deletearchives', 'local_autoarchive'),
            'deletecourses' => get_string('bulkaction_deletecourses', 'local_autoarchive'),
        ];
        echo html_writer::start_div('d-flex flex-wrap align-items-end gap-3 mt-2 mb-4');
        echo html_writer::start_div();
        echo html_writer::label(
            get_string('bulkaction_selected', 'local_autoarchive'),
            'bulkaction',
            false,
            ['class' => 'form-label fw-bold']
        );
        echo html_writer::select(
            $bulkoptions,
            'bulkaction',
            'deletearchives',
            false,
            ['id' => 'bulkaction', 'class' => 'form-select']
        );
        echo html_writer::end_div();
        echo html_writer::start_div();
        echo html_writer::tag('button', get_string('bulkaction_apply', 'local_autoarchive'), [
            'type' => 'submit',
            'class' => 'btn btn-danger',
            'disabled' => 'disabled',
            // Enabled by core/checkbox-toggleall as soon as a row is selected.
            'data-action' => 'toggle',
            'data-toggle' => 'action',
            'data-togglegroup' => archived_courses_table::TOGGLEGROUP,
        ]);
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
