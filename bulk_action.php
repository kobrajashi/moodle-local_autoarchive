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
 * Bulk actions on archives selected in the archive list.
 *
 * @package    local_autoarchive
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core\context\system as context_system;
use core\output\html_writer;
use core\output\notification;
use core\output\single_button;
use core\url;
use local_autoarchive\archive_manager;

$bulkaction = required_param('bulkaction', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$pageurl = new url('/local/autoarchive/bulk_action.php');
admin_externalpage_setup('local_autoarchive_manage', '', null, $pageurl);
require_capability('local/autoarchive:delete', context_system::instance());
require_sesskey();

$returnurl = new url('/local/autoarchive/manage.php');

// Delete course, backup file and archive entry, or only the course and keep the backup.
$actions = ['deletearchives' => true, 'deletecourses' => false];
if (!isset($actions[$bulkaction])) {
    redirect($returnurl, get_string('bulkaction_noselection', 'local_autoarchive'), null, notification::NOTIFY_ERROR);
}
$deletebackups = $actions[$bulkaction];

if ($confirm) {
    $archiveidsstr = required_param('archiveidsstr', PARAM_SEQUENCE);
    $archiveids = array_filter(array_map('intval', explode(',', $archiveidsstr)));
} else {
    $archiveids = optional_param_array('archiveids', [], PARAM_INT);
}
$archiveids = array_values(array_unique(array_filter($archiveids)));
$archives = $archiveids ? $DB->get_records_list('local_autoarchive_courses', 'id', $archiveids, 'coursefullname') : [];
if (!$archives) {
    redirect($returnurl, get_string('bulkaction_noselection', 'local_autoarchive'), null, notification::NOTIFY_WARNING);
}

if (!$confirm) {
    $courses = $DB->get_records_list('course', 'id', array_column($archives, 'courseid'), '', 'id, visible');

    $items = '';
    $skipped = 0;
    foreach ($archives as $archive) {
        $course = $courses[$archive->courseid] ?? null;
        if (archive_manager::is_reactivated($archive, $course)) {
            $note = get_string('bulkaction_note_reactivated', 'local_autoarchive');
            $skipped++;
        } else if ($course) {
            $note = get_string('bulkaction_note_course', 'local_autoarchive');
        } else {
            $note = get_string('bulkaction_note_backuponly', 'local_autoarchive');
        }
        $items .= html_writer::tag('li', format_string($archive->coursefullname) . ' ' .
            html_writer::span('(' . $note . ')', 'text-muted'));
    }

    $message = html_writer::tag('p', get_string('bulkaction_confirm_' . $bulkaction, 'local_autoarchive', count($archives)));
    $message .= html_writer::tag('ul', $items, ['class' => 'mb-3', 'style' => 'max-height: 20rem; overflow-y: auto;']);
    if ($skipped) {
        $message .= html_writer::tag('p', get_string('bulkaction_reactivatedinfo', 'local_autoarchive', $skipped));
    }
    $message .= html_writer::tag('p', get_string('confirmdelete', 'local_autoarchive'), ['class' => 'fw-bold text-danger']);

    // The IDs are posted as one hidden field, so long selections do not hit URL length limits.
    $continue = new single_button(new url($pageurl, [
        'bulkaction' => $bulkaction,
        'archiveidsstr' => implode(',', array_keys($archives)),
        'confirm' => 1,
    ]), get_string('bulkaction_apply', 'local_autoarchive'), 'post', single_button::BUTTON_DANGER);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('bulkaction_' . $bulkaction, 'local_autoarchive'));
    echo $OUTPUT->confirm($message, $continue, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

$task = new \local_autoarchive\task\delete_archives();
$task->set_custom_data([
    'archiveids' => array_keys($archives),
    'deletebackups' => $deletebackups,
    'requester' => $USER->id,
]);
\core\task\manager::queue_adhoc_task($task, true);

redirect($returnurl, get_string('bulkdelete_queued', 'local_autoarchive', count($archives)), null, notification::NOTIFY_SUCCESS);
