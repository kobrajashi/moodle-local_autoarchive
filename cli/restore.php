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
 * CLI script to list and restore archived courses (for large backups that time out in the browser).
 *
 * @package    local_autoarchive
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_autoarchive\archive_manager;

[$options, $unrecognized] = cli_get_params([
    'archiveid' => 0,
    'list' => false,
    'search' => '',
    'force' => false,
    'showdebugging' => false,
    'help' => false,
], [
    'a' => 'archiveid',
    'l' => 'list',
    'f' => 'force',
    'h' => 'help',
]);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

$help = <<<EOL
List and restore archived courses of local_autoarchive.

If the course still exists, it gets its original visibility back. Otherwise it is restored
from the backup file into a new course in its original category (with users and enrolments).
After a successful restore the archive entry and the backup file are removed.

Options:
-l, --list              List archives (ID, status, size, archived on, course name).
    --search=STRING     Only list archives whose course name contains STRING.
-a, --archiveid=INT     Restore the archive with this ID.
-f, --force             Restore even if the free disk space looks insufficient.
    --showdebugging     Show developer level debugging information.
-h, --help              Print out this help.

Examples (run from the Moodle root directory as the web server user):
\$ sudo -u www-data php public/local/autoarchive/cli/restore.php --list --search=informatik
\$ sudo -u www-data php public/local/autoarchive/cli/restore.php --archiveid=42

EOL;

if ($options['help'] || (!$options['list'] && !$options['archiveid'])) {
    echo $help;
    exit(0);
}

if ($options['showdebugging']) {
    set_debugging(DEBUG_DEVELOPER, true);
}

// Run as admin, so that events and course changes are attributed to a real user.
\core\session\manager::set_user(get_admin());

if ($options['list']) {
    $select = '';
    $params = [];
    if ($options['search'] !== '') {
        $select = $DB->sql_like('coursefullname', ':search', false) . ' OR ' . $DB->sql_like('coursename', ':search2', false);
        $params['search'] = $params['search2'] = '%' . $DB->sql_like_escape($options['search']) . '%';
    }
    $archives = $DB->get_records_select(
        'local_autoarchive_courses',
        $select,
        $params,
        'coursefullname',
        'id, courseid, coursefullname, coursename, archived_date, backupfilesize, backupfilename, course_hidden'
    );
    if (!$archives) {
        cli_writeln('No archives found.');
        exit(0);
    }

    $courses = $DB->get_records_list('course', 'id', array_column($archives, 'courseid'), '', 'id, visible');
    cli_writeln(sprintf('%-8s %-14s %-10s %-11s %s', 'ID', 'Status', 'Size', 'Archived', 'Course'));
    foreach ($archives as $archive) {
        $course = $courses[$archive->courseid] ?? null;
        if (!$course) {
            $status = 'backup only';
        } else if (!$course->visible) {
            $status = 'hidden';
        } else {
            $status = archive_manager::is_reactivated($archive, $course) ? 'reactivated' : 'visible';
        }
        if (archive_manager::get_backup_path($archive) === null) {
            $status .= ' !file';
        }
        cli_writeln(sprintf(
            '%-8d %-14s %-10s %-11s %s (%s)',
            $archive->id,
            $status,
            display_size($archive->backupfilesize),
            userdate($archive->archived_date, '%Y-%m-%d'),
            format_string($archive->coursefullname, true, ['escape' => false]),
            $archive->coursename
        ));
    }
    cli_writeln("\n'!file' = backup file missing.");
    exit(0);
}

$archive = $DB->get_record('local_autoarchive_courses', ['id' => (int)$options['archiveid']]);
if (!$archive) {
    cli_error('Archive ' . (int)$options['archiveid'] . ' not found. Use --list to see the available archives.');
}

$name = format_string($archive->coursefullname, true, ['escape' => false]);
cli_heading('Restoring "' . $name . '" (archive ' . $archive->id . ', ' . display_size($archive->backupfilesize) . ')');

// A restore from file first extracts the backup into the temp directory and then copies its files into the file
// storage. Check the free space roughly, as running out of space in the middle leaves an incomplete course behind.
$courseexists = $DB->record_exists('course', ['id' => $archive->courseid]);
if (!$courseexists && !$options['force']) {
    $size = (int)$archive->backupfilesize;
    $checks = [
        'temp directory' => [is_dir($CFG->backuptempdir) ? $CFG->backuptempdir : $CFG->tempdir, 2 * $size],
        'file storage' => [$CFG->filedir ?? $CFG->dataroot . '/filedir', $size],
    ];
    foreach ($checks as $label => [$dir, $needed]) {
        $free = is_dir($dir) ? @disk_free_space($dir) : false;
        if ($free !== false && $free < $needed) {
            cli_error("Not enough free space in the $label ($dir): " . display_size((int)$free) . ' free, about ' .
                display_size($needed) . ' needed. Free up space or use --force.');
        }
    }
}

$start = time();
try {
    $courseid = archive_manager::restore_course($archive->id, fn(string $message) => cli_writeln($message));
} catch (\Throwable $e) {
    cli_error('Restore failed: ' . archive_manager::describe_exception($e));
}

$url = new \core\url('/course/view.php', ['id' => $courseid]);
cli_writeln('Done in ' . format_time(time() - $start) . '. Course: ' . $url->out(false));
exit(0);
