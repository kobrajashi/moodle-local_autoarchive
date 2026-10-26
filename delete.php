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
 * Delete a single archive (course, backup and record).
 *
 * @package    local_autoarchive
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core\context\system as context_system;
use core\output\notification;
use core\output\single_button;
use core\url;
use local_autoarchive\archive_manager;

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$pageurl = new url('/local/autoarchive/delete.php', ['id' => $id]);
admin_externalpage_setup('local_autoarchive_manage', '', null, $pageurl);
require_capability('local/autoarchive:delete', context_system::instance());

$returnurl = new url('/local/autoarchive/manage.php');
$archive = $DB->get_record('local_autoarchive_courses', ['id' => $id]);
if (!$archive) {
    redirect($returnurl, get_string('error_invalidid', 'local_autoarchive'), null, notification::NOTIFY_ERROR);
}
$name = format_string($archive->coursefullname);

if (!$confirm) {
    $course = $DB->get_record('course', ['id' => $archive->courseid], 'id, visible');
    $key = $course ? 'confirmdelete_course' : 'confirmdelete_backup';
    $message = get_string($key, 'local_autoarchive', $name) . ' ' . get_string('confirmdelete', 'local_autoarchive');

    $continue = new single_button(new url($pageurl, ['confirm' => 1]), get_string('delete', 'local_autoarchive'),
        'post', single_button::BUTTON_DANGER);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('delete', 'local_autoarchive'));
    echo $OUTPUT->confirm($message, $continue, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

require_sesskey();

try {
    archive_manager::delete_archive($id);
    redirect($returnurl, get_string('coursedeleted', 'local_autoarchive', $name), null, notification::NOTIFY_SUCCESS);
} catch (\core\exception\moodle_exception $e) {
    // Messages of the plugin's own exceptions are already escaped.
    $details = empty($e->debuginfo) ? '' : ' (' . s($e->debuginfo) . ')';
    redirect($returnurl, $e->getMessage() . $details, null, notification::NOTIFY_ERROR);
} catch (\Throwable $e) {
    redirect($returnurl, get_string('error_delete', 'local_autoarchive', $name) . ': ' . s(archive_manager::describe_exception($e)),
        null, notification::NOTIFY_ERROR);
}
