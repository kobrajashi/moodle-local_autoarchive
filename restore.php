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
 * Restore an archived course.
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

$pageurl = new url('/local/autoarchive/restore.php', ['id' => $id]);
admin_externalpage_setup('local_autoarchive_manage', '', null, $pageurl);
require_capability('local/autoarchive:restore', context_system::instance());

$returnurl = new url('/local/autoarchive/manage.php');
$archive = $DB->get_record('local_autoarchive_courses', ['id' => $id]);
if (!$archive) {
    redirect($returnurl, get_string('error_invalidid', 'local_autoarchive'), null, notification::NOTIFY_ERROR);
}
$name = format_string($archive->coursefullname);
$courseexists = $DB->record_exists('course', ['id' => $archive->courseid]);

if (!$confirm) {
    $message = get_string($courseexists ? 'confirmrestore_existing' : 'confirmrestore_backup', 'local_autoarchive', $name);
    $continue = new single_button(new url($pageurl, ['confirm' => 1]), get_string('restore', 'local_autoarchive'), 'post');

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('restore', 'local_autoarchive'));
    echo $OUTPUT->confirm($message, $continue, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

require_sesskey();

try {
    $courseid = archive_manager::restore_course($id);
    redirect(new url('/course/view.php', ['id' => $courseid]), get_string('courserestored', 'local_autoarchive', $name),
        null, notification::NOTIFY_SUCCESS);
} catch (\core\exception\moodle_exception $e) {
    // Messages of the plugin's own exceptions are already escaped.
    $details = empty($e->debuginfo) ? '' : ' (' . s($e->debuginfo) . ')';
    redirect($returnurl, $e->getMessage() . $details, null, notification::NOTIFY_ERROR);
} catch (\Throwable $e) {
    $message = get_string('error_restore', 'local_autoarchive', $name) . ': ' . s(archive_manager::describe_exception($e));
    redirect($returnurl, $message, null, notification::NOTIFY_ERROR);
}
