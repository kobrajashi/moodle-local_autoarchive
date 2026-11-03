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
 * Delete inactive courses in bulk (preview, confirmation, background processing).
 *
 * @package    local_autoarchive
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core\output\checkbox_toggleall;
use core\output\html_writer;
use core\output\notification;
use core\output\single_button;
use core\url;
use local_autoarchive\archive_manager;

$days = optional_param('days', archive_manager::get_inactivity_period(), PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$createbackups = optional_param('createbackups', 0, PARAM_BOOL);

admin_externalpage_setup('local_autoarchive_cleanup');

$days = max(1, min(7300, $days));
$pageurl = new url('/local/autoarchive/cleanup.php');
$previewurl = new url($pageurl, ['action' => 'preview', 'days' => $days]);

// Step 2: confirm the selection.
if ($action === 'confirm') {
    require_sesskey();
    $courseids = array_filter(optional_param_array('courseids', [], PARAM_INT));
    if (!$courseids) {
        redirect($previewurl, get_string('cleanup_noselection', 'local_autoarchive'), null, notification::NOTIFY_WARNING);
    }

    $message = get_string('cleanup_confirm_message', 'local_autoarchive', count($courseids));
    $message .= html_writer::tag('p', get_string($createbackups ? 'cleanup_withbackups' : 'cleanup_withoutbackups',
        'local_autoarchive'), ['class' => 'mt-3']);
    $message .= html_writer::tag('p', get_string('cleanup_warning', 'local_autoarchive'), ['class' => 'fw-bold text-danger']);

    // The IDs are posted as one hidden field, so long selections do not hit URL length limits.
    $continue = new single_button(new url($pageurl, [
        'action' => 'queue',
        'days' => $days,
        'createbackups' => (int)$createbackups,
        'courseidsstr' => implode(',', $courseids),
    ]), get_string('cleanup_confirm', 'local_autoarchive', count($courseids)), 'post', single_button::BUTTON_DANGER);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('cleanup_heading', 'local_autoarchive'));
    echo $OUTPUT->confirm($message, $continue, $previewurl);
    echo $OUTPUT->footer();
    exit;
}

// Step 3: hand the work over to an ad-hoc task.
if ($action === 'queue') {
    require_sesskey();
    $courseidsstr = required_param('courseidsstr', PARAM_SEQUENCE);
    $courseids = array_values(array_unique(array_filter(array_map('intval', explode(',', $courseidsstr)))));
    if (!$courseids) {
        redirect($previewurl, get_string('cleanup_noselection', 'local_autoarchive'), null, notification::NOTIFY_WARNING);
    }

    $task = new \local_autoarchive\task\delete_courses();
    $task->set_custom_data([
        'courseids' => $courseids,
        'createbackups' => (bool)$createbackups,
        'days' => $days,
        'requester' => $USER->id,
    ]);
    \core\task\manager::queue_adhoc_task($task, true);

    redirect(new url('/local/autoarchive/manage.php'), get_string('cleanup_queued', 'local_autoarchive', count($courseids)),
        null, notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('cleanup_heading', 'local_autoarchive'));
echo html_writer::tag('p', get_string('cleanup_description', 'local_autoarchive'), ['class' => 'lead']);

$warning = archive_manager::get_loglifetime_warning();
if ($warning !== '') {
    echo $OUTPUT->notification($warning, notification::NOTIFY_WARNING);
}

// Step 1: choose the period.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $pageurl->out(false),
    'class' => 'd-flex flex-wrap align-items-end gap-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'preview']);
echo html_writer::start_div();
echo html_writer::label(get_string('cleanup_days', 'local_autoarchive'), 'days', false, ['class' => 'form-label fw-bold']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'days',
    'id' => 'days',
    'min' => '1',
    'max' => '7300',
    'value' => $days,
    'class' => 'form-control',
    'style' => 'width: 8rem;',
    'required' => 'required',
]);
echo html_writer::div(get_string('cleanup_days_help', 'local_autoarchive'), 'form-text');
echo html_writer::end_div();
echo html_writer::div(html_writer::tag('button', get_string('cleanup_preview', 'local_autoarchive'),
    ['type' => 'submit', 'class' => 'btn btn-primary']), 'mb-4');
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

if ($action === 'preview') {
    $courses = archive_manager::get_inactive_courses($days);

    if (empty($courses)) {
        echo $OUTPUT->notification(get_string('cleanup_nopreview', 'local_autoarchive'), notification::NOTIFY_INFO);
    } else {
        echo $OUTPUT->notification(get_string('cleanup_warning', 'local_autoarchive'), notification::NOTIFY_ERROR);
        echo html_writer::tag('h4', get_string('cleanup_preview_description', 'local_autoarchive', count($courses)));

        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'confirm']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'days', 'value' => $days]);

        $master = new checkbox_toggleall('local_autoarchive_courses', true, [
            'id' => 'selectall',
            'checked' => true,
            'label' => get_string('selectall'),
            'labelclasses' => 'visually-hidden',
        ]);

        echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover']);
        echo html_writer::start_tag('thead', ['class' => 'table-dark']);
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', $OUTPUT->render($master), ['style' => 'width: 3rem;']);
        echo html_writer::tag('th', get_string('coursename', 'local_autoarchive'));
        echo html_writer::tag('th', get_string('category', 'local_autoarchive'));
        echo html_writer::tag('th', get_string('teachers', 'local_autoarchive'));
        echo html_writer::tag('th', get_string('lastaccess', 'local_autoarchive'));
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');

        $categories = core_course_category::make_categories_list();
        echo html_writer::start_tag('tbody');
        foreach ($courses as $course) {
            $checkbox = new checkbox_toggleall('local_autoarchive_courses', false, [
                'id' => 'course' . $course->id,
                'name' => 'courseids[]',
                'value' => $course->id,
                'checked' => true,
                'label' => format_string($course->fullname),
                'labelclasses' => 'visually-hidden',
            ]);
            $teachers = array_map(fn($t) => $t->firstname . ' ' . $t->lastname,
                archive_manager::get_course_teachers($course->id));

            $name = html_writer::tag('strong', format_string($course->fullname)) . html_writer::empty_tag('br') .
                html_writer::tag('small', s($course->shortname), ['class' => 'text-muted']);
            if ($course->archiveid) {
                $name .= ' ' . html_writer::span(get_string('cleanup_alreadyarchived', 'local_autoarchive'),
                    'badge bg-info text-dark');
            }

            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', $OUTPUT->render($checkbox));
            echo html_writer::tag('td', $name);
            echo html_writer::tag('td', s($categories[$course->category] ?? ''));
            echo html_writer::tag('td', $teachers ? s(implode(', ', $teachers)) : '-');
            echo html_writer::tag('td', userdate($course->lastaccess, get_string('strftimedatetime', 'langconfig')));
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');

        echo html_writer::start_div('form-check mt-3');
        echo html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'name' => 'createbackups',
            'id' => 'createbackups',
            'value' => '1',
            'checked' => 'checked',
            'class' => 'form-check-input',
        ]);
        echo html_writer::label(get_string('cleanup_createbackups', 'local_autoarchive'), 'createbackups', false,
            ['class' => 'form-check-label fw-bold']);
        echo html_writer::div(get_string('cleanup_createbackups_help', 'local_autoarchive'), 'form-text');
        echo html_writer::end_div();

        echo html_writer::div(
            html_writer::tag('button', get_string('cleanup_continue', 'local_autoarchive'),
                ['type' => 'submit', 'class' => 'btn btn-danger btn-lg']),
            'mt-3'
        );
        echo html_writer::end_tag('form');
    }
}

echo $OUTPUT->footer();
