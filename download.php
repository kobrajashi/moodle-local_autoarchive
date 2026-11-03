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
 * Download the backup file of an archive.
 *
 * @package    local_autoarchive
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use core\context\system as context_system;
use core\exception\moodle_exception;
use local_autoarchive\archive_manager;

$id = required_param('id', PARAM_INT);

require_login();
require_capability('local/autoarchive:download', context_system::instance());
require_sesskey();

$archive = $DB->get_record('local_autoarchive_courses', ['id' => $id]);
if (!$archive) {
    throw new moodle_exception('error_invalidid', 'local_autoarchive');
}

$backupfile = archive_manager::get_backup_path($archive);
if ($backupfile === null) {
    throw new moodle_exception('backupnotfound', 'local_autoarchive');
}
if (sha1_file($backupfile) !== $archive->backupfilehash) {
    throw new moodle_exception('error_backupintegrity', 'local_autoarchive', '', format_string($archive->coursefullname));
}

\core\session\manager::write_close();
send_file($backupfile, basename($backupfile), 0, 0, false, true, 'application/vnd.moodle.backup');
