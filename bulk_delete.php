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
 * Bulk deletion of archives older than a number of days.
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

$days = required_param('days', PARAM_INT);
$deletebackups = optional_param('deletebackups', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$pageurl = new url('/local/autoarchive/bulk_delete.php');
admin_externalpage_setup('local_autoarchive_manage', '', null, $pageurl);
require_capability('local/autoarchive:delete', context_system::instance());
require_sesskey();

$returnurl = new url('/local/autoarchive/manage.php');

if ($days < 1 || $days > 7300) {
    redirect($returnurl, get_string('bulkdelete_error', 'local_autoarchive'), null, notification::NOTIFY_ERROR);
}

$archives = archive_manager::get_archives_older_than($days, (bool)$deletebackups);
if (!$archives) {
    redirect($returnurl, get_string('bulkdelete_nomatches', 'local_autoarchive'), null, notification::NOTIFY_INFO);
}

if (!$confirm) {
    $message = get_string('bulkdelete_confirm', 'local_autoarchive', (object)['days' => $days, 'count' => count($archives)]);
    $message .= html_writer::tag('p', get_string(
        $deletebackups ? 'bulkdelete_withbackups' : 'bulkdelete_keepbackups',
        'local_autoarchive'
    ), ['class' => 'mt-3 fw-bold']);
    $message .= html_writer::tag('p', get_string('confirmdelete', 'local_autoarchive'), ['class' => 'text-danger']);

    $continue = new single_button(new url($pageurl, [
        'days' => $days,
        'deletebackups' => (int)$deletebackups,
        'confirm' => 1,
    ]), get_string('bulkdelete_button', 'local_autoarchive'), 'post', single_button::BUTTON_DANGER);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('bulkdelete', 'local_autoarchive'));
    echo $OUTPUT->confirm($message, $continue, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

$task = new \local_autoarchive\task\delete_archives();
$task->set_custom_data([
    'archiveids' => array_keys($archives),
    'deletebackups' => (bool)$deletebackups,
    'requester' => $USER->id,
]);
\core\task\manager::queue_adhoc_task($task, true);

redirect($returnurl, get_string('bulkdelete_queued', 'local_autoarchive', count($archives)), null, notification::NOTIFY_SUCCESS);
