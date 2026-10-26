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
 * Archive manager class for handling course archiving operations.
 *
 * @package    local_autoarchive
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autoarchive;

use backup;
use backup_controller;
use base_setting;
use core\context\course as context_course;
use core\exception\moodle_exception;
use core\url;
use core_course_category;
use core_text;
use restore_controller;
use restore_dbops;
use stdClass;

/**
 * Archive manager class.
 */
class archive_manager {

    /** @var string Allowed characters of a backup file name (also covers names created by older versions). */
    const FILENAME_PATTERN = '/^[a-zA-Z0-9_\-\.]+\.mbz$/';

    /** @var string Event that every backup writes into the course log; it must not count as course access. */
    const BACKUP_EVENT = '\\core\\event\\course_backup_created';

    /**
     * Backup settings applied to every archive backup (in this order, as some depend on 'users').
     * Settings that are locked by the site configuration are left untouched.
     *
     * @var array
     */
    const BACKUP_SETTINGS = [
        'users' => 1,
        'anonymize' => 0,
        'role_assignments' => 1,
        'permissions' => 1,
        'activities' => 1,
        'blocks' => 1,
        'files' => 1,
        'filters' => 1,
        'comments' => 1,
        'badges' => 1,
        'calendarevents' => 1,
        'userscompletion' => 1,
        'logs' => 0,
        'grade_histories' => 0,
        'groups' => 1,
        'customfield' => 1,
        'contentbankcontent' => 1,
        'xapistate' => 1,
        'legacyfiles' => 1,
    ];

    /**
     * Restore settings, so that a restored course gets its users and enrolments back regardless of the
     * site's restore defaults. Settings the backup does not contain are locked and left untouched.
     *
     * @var array
     */
    const RESTORE_SETTINGS = [
        'users' => 1,
        'role_assignments' => 1,
        'permissions' => 1,
        'activities' => 1,
        'blocks' => 1,
        'filters' => 1,
        'comments' => 1,
        'badges' => 1,
        'calendarevents' => 1,
        'userscompletion' => 1,
        'groups' => 1,
        'customfield' => 1,
        'contentbankcontent' => 1,
        'xapistate' => 1,
        'legacyfiles' => 1,
    ];

    /** @var bool|null Whether the standard log store is enabled (cached per request). */
    private static $standardlogenabled = null;

    /**
     * Load the core libraries needed for backup, restore and course handling.
     */
    private static function load_libraries(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');
    }

    // Identification of inactive courses.

    /**
     * Get all courses without access for the given number of days.
     *
     * Last access is the newest of: user_lastaccess (independent of the log lifetime) and the
     * standard log (ignoring entries written by cron/CLI or restores and backup events).
     * Courses nobody ever accessed fall back to their creation time.
     * Courses in excluded categories (incl. subcategories) are skipped.
     *
     * @param int $days Number of days without access
     * @param bool $skiparchived Skip courses that already have an archive record
     * @return stdClass[] Course records (id, category, fullname, shortname, visible, ...) with lastaccess and archiveid
     */
    public static function get_inactive_courses(int $days, bool $skiparchived = false): array {
        global $DB;

        $cutofftime = time() - ($days * DAYSECS);

        $where = 'c.id <> :siteid';
        $params = ['siteid' => SITEID];

        $excluded = self::get_excluded_category_ids();
        if ($excluded) {
            [$insql, $inparams] = $DB->get_in_or_equal($excluded, SQL_PARAMS_NAMED, 'excat', false);
            $where .= " AND c.category $insql";
            $params += $inparams;
        }

        $sql = "SELECT c.id, c.category, c.fullname, c.shortname, c.visible, c.timecreated, c.timemodified,
                       (SELECT MAX(ul.timeaccess) FROM {user_lastaccess} ul WHERE ul.courseid = c.id) AS useraccess,
                       (SELECT MAX(a.id) FROM {local_autoarchive_courses} a
                         WHERE a.courseid = c.id AND a.course_deleted = 0) AS archiveid
                  FROM {course} c
                 WHERE $where
              ORDER BY c.id";
        $courses = $DB->get_records_sql($sql, $params);

        $inactivecourses = [];
        foreach ($courses as $course) {
            if ($skiparchived && $course->archiveid) {
                continue;
            }
            // Cheap pre-check before the log store is queried.
            if ((int)$course->useraccess >= $cutofftime) {
                continue;
            }
            $course->lastaccess = self::combine_last_access($course->id, (int)$course->useraccess, (int)$course->timecreated);
            if ($course->lastaccess < $cutofftime) {
                $inactivecourses[] = $course;
            }
        }

        // Oldest access first.
        usort($inactivecourses, fn($a, $b) => $a->lastaccess <=> $b->lastaccess);

        return $inactivecourses;
    }

    /**
     * Get the last access time for a course.
     *
     * @param int $courseid Course ID
     * @return int Timestamp of last access, creation time if never accessed, 0 if the course does not exist
     */
    public static function get_course_last_access(int $courseid): int {
        global $DB;

        $timecreated = $DB->get_field('course', 'timecreated', ['id' => $courseid]);
        if ($timecreated === false) {
            return 0;
        }
        $useraccess = (int)$DB->get_field_sql(
            'SELECT MAX(timeaccess) FROM {user_lastaccess} WHERE courseid = :courseid',
            ['courseid' => $courseid]
        );

        return self::combine_last_access($courseid, $useraccess, (int)$timecreated);
    }

    /**
     * Combine the different access sources into one timestamp.
     *
     * @param int $courseid Course ID
     * @param int $useraccess Newest user_lastaccess entry
     * @param int $timecreated Course creation time
     * @return int
     */
    private static function combine_last_access(int $courseid, int $useraccess, int $timecreated): int {
        $lastaccess = max($useraccess, self::get_log_last_access($courseid));
        return $lastaccess ?: $timecreated;
    }

    /**
     * Newest log entry of a course caused by real users.
     *
     * @param int $courseid Course ID
     * @return int Timestamp or 0
     */
    public static function get_log_last_access(int $courseid): int {
        global $DB;

        if (!self::is_standard_log_enabled()) {
            return 0;
        }

        $sql = "SELECT MAX(timecreated)
                  FROM {logstore_standard_log}
                 WHERE courseid = :courseid
                   AND origin <> :origincli
                   AND origin <> :originrestore
                   AND eventname <> :backupevent";

        return (int)$DB->get_field_sql($sql, [
            'courseid' => $courseid,
            'origincli' => 'cli',
            'originrestore' => 'restore',
            'backupevent' => self::BACKUP_EVENT,
        ]);
    }

    /**
     * Whether the standard log store is enabled and readable.
     *
     * @return bool
     */
    private static function is_standard_log_enabled(): bool {
        if (self::$standardlogenabled === null) {
            $readers = get_log_manager()->get_readers('\core\log\sql_reader');
            self::$standardlogenabled = isset($readers['logstore_standard']);
        }
        return self::$standardlogenabled;
    }

    /**
     * Warning text if the standard log keeps entries shorter than the inactivity period.
     *
     * @return string Empty string if there is nothing to warn about
     */
    public static function get_loglifetime_warning(): string {
        $loglifetime = (int)get_config('logstore_standard', 'loglifetime');
        $inactivityperiod = self::get_inactivity_period();
        if ($loglifetime > 0 && $loglifetime < $inactivityperiod) {
            return get_string('loglifetime_warning', 'local_autoarchive', [
                'loglifetime' => $loglifetime,
                'inactivityperiod' => $inactivityperiod,
            ]);
        }
        return '';
    }

    /**
     * Configured inactivity period in days.
     *
     * @return int
     */
    public static function get_inactivity_period(): int {
        $days = (int)get_config('local_autoarchive', 'inactivityperiod');
        return $days > 0 ? $days : 365;
    }

    /**
     * IDs of all excluded categories including their subcategories.
     *
     * @return int[]
     */
    public static function get_excluded_category_ids(): array {
        global $DB;

        $config = (string)get_config('local_autoarchive', 'excludedcategories');
        $excluded = array_filter(array_map('intval', explode(',', $config)));
        if (!$excluded) {
            return [];
        }

        $ids = [];
        foreach ($DB->get_records_menu('course_categories', null, '', 'id, path') as $id => $path) {
            foreach ($excluded as $excludedid) {
                if (strpos($path . '/', '/' . $excludedid . '/') !== false) {
                    $ids[] = (int)$id;
                    break;
                }
            }
        }
        return $ids;
    }

    // Backups.

    /**
     * Create a backup of a course and store it in the archive directory.
     *
     * @param int $courseid Course ID
     * @return string Path to the backup file
     * @throws moodle_exception If the backup could not be created or copied
     */
    public static function create_course_backup(int $courseid): string {
        self::load_libraries();
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $bc = null;
        $tempfile = null;
        try {
            $bc = new backup_controller(
                backup::TYPE_1COURSE,
                $courseid,
                backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO,
                backup::MODE_GENERAL,
                get_admin()->id
            );
            self::apply_plan_settings($bc->get_plan(), self::BACKUP_SETTINGS);

            $bc->execute_plan();
            $results = $bc->get_results();
            $tempfile = $results['backup_destination'] ?? null;
            if (!$tempfile instanceof \stored_file) {
                throw new moodle_exception('error_createbackup', 'local_autoarchive', '', $courseid, 'No backup file produced');
            }

            $filepath = self::get_backup_directory() . '/' . self::generate_backup_filename($courseid);
            $copied = $tempfile->copy_content_to($filepath);
            clearstatcache(true, $filepath);
            if (!$copied || !is_readable($filepath) || filesize($filepath) !== (int)$tempfile->get_filesize()) {
                @unlink($filepath);
                throw new moodle_exception('error_createbackup', 'local_autoarchive', '', $courseid,
                    'Copy to archive directory failed');
            }

            return $filepath;
        } finally {
            // The temporary copy in the course backup area is not needed any more.
            if ($tempfile instanceof \stored_file) {
                $tempfile->delete();
            }
            if ($bc) {
                $bc->destroy();
            }
        }
    }

    /**
     * Apply settings to a backup or restore plan, skipping settings that are locked.
     *
     * @param \base_plan $plan
     * @param array $settings name => value
     */
    private static function apply_plan_settings(\base_plan $plan, array $settings): void {
        foreach ($settings as $name => $value) {
            if (!$plan->setting_exists($name)) {
                continue;
            }
            $setting = $plan->get_setting($name);
            if ($setting->get_status() === base_setting::NOT_LOCKED) {
                $setting->set_value($value);
            }
        }
    }

    /**
     * Get or create the backup directory.
     *
     * @return string Path to backup directory
     */
    public static function get_backup_directory(): string {
        global $CFG;
        return make_writable_directory($CFG->dataroot . '/local_autoarchive/backups');
    }

    /**
     * Generate a readable backup filename from course name and creation year.
     *
     * Example: "Informatik OHI52 Fi" created in 2021 -> "informatik-ohi52-fi-2021.mbz"
     *
     * @param int $courseid Course ID
     * @return string Safe filename
     */
    public static function generate_backup_filename(int $courseid): string {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'fullname, timecreated');
        $name = '';
        $year = date('Y');

        if ($course) {
            $name = core_text::strtolower($course->fullname);
            $name = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $name);
            $name = core_text::specialtoascii($name);
            $name = trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-');
            if (strlen($name) > 80) {
                $name = rtrim(substr($name, 0, 80), '-');
            }
            $year = date('Y', $course->timecreated);
        }

        // Names without latin characters (or a missing course) fall back to the course ID.
        if ($name === '') {
            $name = 'course-' . $courseid;
        }

        $backupdir = self::get_backup_directory();
        $base = $name . '-' . $year;
        $filename = $base . '.mbz';
        $counter = 2;
        while (file_exists($backupdir . '/' . $filename)) {
            $filename = $base . '-' . $counter . '.mbz';
            $counter++;
        }

        return $filename;
    }

    /**
     * Resolve the path of an archive's backup file.
     *
     * @param stdClass $archive Archive record
     * @return string|null Real path inside the backup directory, null if invalid or missing
     */
    public static function get_backup_path(stdClass $archive): ?string {
        $filename = basename((string)$archive->backupfilename);
        if (!preg_match(self::FILENAME_PATTERN, $filename)) {
            return null;
        }

        $backupdir = realpath(self::get_backup_directory());
        $backupfile = realpath($backupdir . '/' . $filename);
        if ($backupdir === false || $backupfile === false || strpos($backupfile, $backupdir . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }
        return is_file($backupfile) ? $backupfile : null;
    }

    /**
     * Resolve the backup path and check its integrity.
     *
     * @param stdClass $archive Archive record
     * @return string|null Path if the file exists and matches the stored hash
     */
    public static function verify_backup(stdClass $archive): ?string {
        $path = self::get_backup_path($archive);
        if ($path === null || sha1_file($path) !== $archive->backupfilehash) {
            return null;
        }
        return $path;
    }

    /**
     * Delete a backup file.
     *
     * @param string $filepath Full path to the backup file
     * @return bool True if the file was deleted or didn't exist
     */
    public static function delete_backup_file(string $filepath): bool {
        if (!file_exists($filepath) || @unlink($filepath)) {
            return true;
        }
        debugging('Failed to delete backup file: ' . $filepath, DEBUG_DEVELOPER);
        return false;
    }

    // Archiving.

    /**
     * Back up a course, record it and hide it if configured.
     *
     * @param stdClass $course Course record from get_inactive_courses()
     * @return array ['archiveid' => int, 'hidden' => bool, 'warning' => string]
     * @throws moodle_exception If backup or metadata could not be written
     */
    public static function archive_course(stdClass $course): array {
        global $DB;

        $hide = (bool)get_config('local_autoarchive', 'enableautohide');
        $path = self::create_course_backup($course->id);
        try {
            $archiveid = self::save_archive_metadata($course, $path, (int)$course->lastaccess, $hide || !$course->visible);
        } catch (\Throwable $e) {
            self::delete_backup_file($path);
            throw $e;
        }

        $result = ['archiveid' => $archiveid, 'hidden' => !$course->visible, 'warning' => ''];
        if ($hide && $course->visible) {
            try {
                self::hide_course($course->id);
                $result['hidden'] = true;
            } catch (\Throwable $e) {
                $DB->update_record('local_autoarchive_courses', (object)[
                    'id' => $archiveid,
                    'course_hidden' => 0,
                    'timemodified' => time(),
                ]);
                $result['warning'] = get_string('error_hidecourse', 'local_autoarchive', $course->id) . ': ' .
                    self::describe_exception($e);
            }
        }
        return $result;
    }

    /**
     * Hide a course through the course API (fires events, purges caches).
     *
     * @param int $courseid Course ID
     */
    public static function hide_course(int $courseid): void {
        self::load_libraries();
        course_change_visibility($courseid, false);
    }

    /**
     * Get editing teachers of a course.
     *
     * @param int $courseid Course ID
     * @return stdClass[] Users with id, firstname, lastname
     */
    public static function get_course_teachers(int $courseid): array {
        global $DB;

        $roleids = array_keys(get_archetype_roles('editingteacher'));
        if (!$roleids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $params['contextid'] = context_course::instance($courseid)->id;

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname
                  FROM {role_assignments} ra
                  JOIN {user} u ON u.id = ra.userid
                 WHERE ra.contextid = :contextid
                   AND ra.roleid $insql
                   AND u.deleted = 0
              ORDER BY u.lastname, u.firstname";

        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Get the teacher names of several archives.
     *
     * @param int[] $archiveids Archive IDs
     * @return array archiveid => string[] full names
     */
    public static function get_archive_teachers(array $archiveids): array {
        global $DB;

        if (!$archiveids) {
            return [];
        }
        $names = [];
        $records = $DB->get_records_list('local_autoarchive_teachers', 'archiveid', $archiveids, 'lastname, firstname');
        foreach ($records as $record) {
            $names[$record->archiveid][] = trim($record->firstname . ' ' . $record->lastname);
        }
        return $names;
    }

    /**
     * Save archive metadata to database.
     *
     * @param stdClass $course Course record (id, category, fullname, shortname, visible)
     * @param string $backupfilepath Path to backup file
     * @param int $lastaccess Last access timestamp
     * @param bool $hidden Whether the course is (going to be) hidden
     * @return int Archive record ID
     */
    public static function save_archive_metadata(stdClass $course, string $backupfilepath, int $lastaccess, bool $hidden): int {
        global $DB;

        $now = time();
        $record = (object)[
            'courseid' => $course->id,
            'coursename' => $course->shortname,
            'coursefullname' => $course->fullname,
            'categoryid' => $course->category,
            'categoryname' => (string)$DB->get_field('course_categories', 'name', ['id' => $course->category]),
            'backupfilename' => basename($backupfilepath),
            'backupfilesize' => filesize($backupfilepath),
            'backupfilehash' => sha1_file($backupfilepath),
            'lastaccess' => $lastaccess,
            'archived_date' => $now,
            'originalvisible' => (int)$course->visible,
            'course_hidden' => (int)$hidden,
            'course_deleted' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $transaction = $DB->start_delegated_transaction();
        $archiveid = $DB->insert_record('local_autoarchive_courses', $record);
        foreach (self::get_course_teachers($course->id) as $teacher) {
            $DB->insert_record('local_autoarchive_teachers', (object)[
                'archiveid' => $archiveid,
                'userid' => $teacher->id,
                'firstname' => core_text::substr($teacher->firstname, 0, 100),
                'lastname' => core_text::substr($teacher->lastname, 0, 100),
            ]);
        }
        $transaction->allow_commit();

        return $archiveid;
    }

    /**
     * Whether an archived course has been made visible again by someone.
     *
     * @param stdClass $archive Archive record
     * @param stdClass|null $course Course record with visible field, null if deleted
     * @return bool
     */
    public static function is_reactivated(stdClass $archive, ?stdClass $course): bool {
        return $course && (int)$archive->course_hidden === 1 && (int)$course->visible === 1;
    }

    // Restore.

    /**
     * Restore an archived course.
     *
     * If the course still exists it gets its original visibility back. Otherwise it is restored
     * from the backup file into a new course. Afterwards the archive entry and its backup file are
     * removed, because the course is complete again.
     *
     * @param int $archiveid Archive record ID
     * @param callable|null $log Receives progress messages (used by the CLI script)
     * @return int ID of the restored course
     * @throws moodle_exception On any failure; the archive and backup stay untouched then
     */
    public static function restore_course(int $archiveid, ?callable $log = null): int {
        // Prevent the same archive from being restored twice at the same time (e.g. web and CLI).
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_autoarchive');
        $lock = $lockfactory->get_lock('restore_' . $archiveid, 0, DAYSECS);
        if (!$lock) {
            throw new moodle_exception('error_restoreinprogress', 'local_autoarchive');
        }
        try {
            return self::do_restore_course($archiveid, $log ?? fn(string $message) => null);
        } finally {
            $lock->release();
        }
    }

    /**
     * Restore an archived course while holding the restore lock.
     *
     * @param int $archiveid Archive record ID
     * @param callable $log Receives progress messages
     * @return int ID of the restored course
     */
    private static function do_restore_course(int $archiveid, callable $log): int {
        global $DB;

        self::load_libraries();
        $archive = $DB->get_record('local_autoarchive_courses', ['id' => $archiveid], '*', MUST_EXIST);

        $course = $DB->get_record('course', ['id' => $archive->courseid], 'id, visible');
        if ($course) {
            $log('Course ' . $course->id . ' still exists, restoring its original visibility.');
            if ((int)$archive->originalvisible === 1 && !$course->visible) {
                course_change_visibility($course->id, true);
            }
            self::remove_archive_after_restore($archive);
            return (int)$course->id;
        }

        $log('Verifying backup file integrity...');
        $backupfile = self::verify_backup($archive);
        if ($backupfile === null) {
            throw new moodle_exception('error_backupintegrity', 'local_autoarchive', '', s($archive->coursefullname));
        }

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $categoryid = $DB->record_exists('course_categories', ['id' => $archive->categoryid])
            ? $archive->categoryid
            : core_course_category::get_default()->id;

        $admin = get_admin();
        $foldername = restore_controller::get_tempdir_name($archive->courseid, $admin->id);
        $tempdir = make_backup_temp_directory($foldername);
        $newcourseid = 0;
        $rc = null;

        try {
            $log('Extracting backup to ' . $tempdir . '...');
            $packer = get_file_packer('application/vnd.moodle.backup');
            if (!$packer->extract_to_pathname($backupfile, $tempdir)) {
                throw new moodle_exception('error_restore', 'local_autoarchive', '', s($archive->coursefullname),
                    'Failed to extract backup file');
            }

            $shortname = self::get_unique_shortname($archive->coursename);
            $newcourseid = restore_dbops::create_new_course($archive->coursefullname, $shortname, $categoryid);

            $rc = new restore_controller(
                $foldername,
                $newcourseid,
                backup::INTERACTIVE_NO,
                backup::MODE_SAMESITE,
                $admin->id,
                backup::TARGET_NEW_COURSE
            );
            self::apply_plan_settings($rc->get_plan(), self::RESTORE_SETTINGS);
            $log('Restoring into new course ' . $newcourseid . '...');
            if (!$rc->execute_precheck()) {
                $errors = $rc->get_precheck_results()['errors'] ?? [];
                if ($errors) {
                    throw new moodle_exception('error_restore', 'local_autoarchive', '', s($archive->coursefullname),
                        implode('; ', $errors));
                }
            }
            $rc->execute_plan();
            $rc->destroy();
            $rc = null;

            // The restore takes names and visibility from the backup; set the archived values.
            update_course((object)[
                'id' => $newcourseid,
                'fullname' => $archive->coursefullname,
                'shortname' => $shortname,
                'visible' => (int)$archive->originalvisible,
            ]);
        } catch (\Throwable $e) {
            if ($rc) {
                $rc->destroy();
            }
            if ($newcourseid) {
                $log('Restore failed, removing the incomplete course ' . $newcourseid . '...');
                delete_course($newcourseid, false);
            }
            throw $e;
        } finally {
            fulldelete($tempdir);
        }

        $log('Removing archive entry and backup file...');
        self::remove_archive_after_restore($archive);
        return $newcourseid;
    }

    /**
     * Remove the archive of a successfully restored course. A failure here must not turn the
     * successful restore into an error, so it is only reported to developers.
     *
     * @param stdClass $archive Archive record
     */
    private static function remove_archive_after_restore(stdClass $archive): void {
        try {
            self::remove_archive($archive);
        } catch (\Throwable $e) {
            debugging('local_autoarchive: archive ' . $archive->id . ' could not be removed after restore: ' .
                self::describe_exception($e), DEBUG_DEVELOPER);
        }
    }

    /**
     * Find an unused course short name based on the archived one.
     *
     * @param string $shortname Archived short name
     * @return string
     */
    private static function get_unique_shortname(string $shortname): string {
        global $DB;

        $candidate = $shortname;
        $counter = 1;
        while ($DB->record_exists('course', ['shortname' => $candidate])) {
            $candidate = $shortname . '_restored' . ($counter > 1 ? '_' . $counter : '');
            $counter++;
        }
        return $candidate;
    }

    // Deletion.

    /**
     * Delete an archive: the Moodle course (if it still exists), the backup file and the record.
     *
     * @param int $archiveid Archive record ID
     * @throws moodle_exception If the course was reactivated or something could not be deleted
     */
    public static function delete_archive(int $archiveid): void {
        $archive = self::delete_archived_course($archiveid);
        self::remove_archive($archive);
    }

    /**
     * Delete the Moodle course of an archive but keep backup and record.
     *
     * @param int $archiveid Archive record ID
     * @return stdClass The updated archive record
     * @throws moodle_exception If the course was reactivated or could not be deleted
     */
    public static function delete_archived_course(int $archiveid): stdClass {
        global $DB;

        $archive = $DB->get_record('local_autoarchive_courses', ['id' => $archiveid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $archive->courseid], 'id, visible');

        if ($course) {
            if (self::is_reactivated($archive, $course)) {
                throw new moodle_exception('error_reactivated', 'local_autoarchive', '', s($archive->coursefullname));
            }
            \core_php_time_limit::raise();
            if (!delete_course($course->id, false)) {
                throw new moodle_exception('error_delete', 'local_autoarchive', '', s($archive->coursefullname));
            }
        }

        if (!$archive->course_deleted) {
            $archive->course_deleted = 1;
            $archive->timemodified = time();
            $DB->update_record('local_autoarchive_courses', $archive);
        }
        return $archive;
    }

    /**
     * Remove an archive record and its backup file.
     *
     * @param stdClass $archive Archive record
     * @throws moodle_exception If the backup file could not be deleted (the record is kept then)
     */
    private static function remove_archive(stdClass $archive): void {
        global $DB;

        $path = self::get_backup_path($archive);
        if ($path !== null && !self::delete_backup_file($path)) {
            throw new moodle_exception('error_backupdelete', 'local_autoarchive', '', s($archive->backupfilename));
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_autoarchive_teachers', ['archiveid' => $archive->id]);
        $DB->delete_records('local_autoarchive_courses', ['id' => $archive->id]);
        $transaction->allow_commit();
    }

    /**
     * Archives that were archived at least the given number of days ago.
     *
     * @param int $days Days since archiving
     * @param bool $deletebackups If false, only archives whose course still exists are returned
     *                            (archives that are already backup-only would not change)
     * @return stdClass[]
     */
    public static function get_archives_older_than(int $days, bool $deletebackups): array {
        global $DB;

        $select = 'archived_date <= :cutofftime';
        $params = ['cutofftime' => time() - ($days * DAYSECS)];
        if (!$deletebackups) {
            $select .= ' AND course_deleted = 0';
        }
        return $DB->get_records_select('local_autoarchive_courses', $select, $params, 'archived_date ASC',
            'id, courseid, coursefullname, archived_date, course_deleted');
    }

    /**
     * Delete several archives (used by the bulk delete ad-hoc task).
     *
     * @param int[] $archiveids Archive IDs
     * @param bool $deletebackups Also delete backup files and records; otherwise only the courses
     *                            are deleted and the backups stay available in the list
     * @return array ['count' => int, 'errors' => string[]]
     */
    public static function bulk_delete_archives(array $archiveids, bool $deletebackups): array {
        global $DB;

        $result = ['count' => 0, 'errors' => []];
        foreach ($archiveids as $archiveid) {
            if (!$DB->record_exists('local_autoarchive_courses', ['id' => $archiveid])) {
                continue; // Already removed in the meantime.
            }
            try {
                if ($deletebackups) {
                    self::delete_archive($archiveid);
                } else {
                    self::delete_archived_course($archiveid);
                }
                $result['count']++;
            } catch (\Throwable $e) {
                $result['errors'][] = get_string('error_archive', 'local_autoarchive', $archiveid) . ': ' .
                    self::describe_exception($e);
            }
        }
        return $result;
    }

    /**
     * Delete courses, optionally backing them up first (used by the cleanup ad-hoc task).
     *
     * A course is only deleted if its backup has been written and verified. Courses that already
     * have a valid archive backup reuse it instead of creating a second one.
     *
     * @param int[] $courseids Course IDs
     * @param bool $createbackups Whether to create backups before deletion
     * @param int $days If > 0, courses that have been accessed within this many days are skipped
     * @return array ['count' => int, 'backed_up' => int, 'reused' => int, 'errors' => string[]]
     */
    public static function delete_courses_by_ids(array $courseids, bool $createbackups, int $days = 0): array {
        global $DB;

        self::load_libraries();
        $result = ['count' => 0, 'backed_up' => 0, 'reused' => 0, 'errors' => []];

        foreach ($courseids as $courseid) {
            $courseid = (int)$courseid;
            $label = (string)$courseid;
            try {
                $course = $DB->get_record('course', ['id' => $courseid],
                    'id, category, fullname, shortname, visible, timecreated');
                if (!$course || $courseid == SITEID) {
                    throw new moodle_exception('error_coursenotfound', 'local_autoarchive', '', $courseid);
                }
                $label = format_string($course->fullname, true, ['escape' => false]) . " (ID {$courseid})";

                $lastaccess = self::get_course_last_access($courseid);
                if ($days > 0 && $lastaccess >= time() - ($days * DAYSECS)) {
                    throw new moodle_exception('error_courseactive', 'local_autoarchive');
                }

                if ($createbackups) {
                    $existing = $DB->get_records('local_autoarchive_courses',
                        ['courseid' => $courseid, 'course_deleted' => 0], 'id DESC', '*', 0, 1);
                    $existing = reset($existing);

                    if ($existing && self::verify_backup($existing) !== null) {
                        $result['reused']++;
                    } else {
                        $path = self::create_course_backup($courseid);
                        try {
                            $archiveid = self::save_archive_metadata($course, $path, $lastaccess, !$course->visible);
                        } catch (\Throwable $e) {
                            self::delete_backup_file($path);
                            throw $e;
                        }
                        $archive = $DB->get_record('local_autoarchive_courses', ['id' => $archiveid], '*', MUST_EXIST);
                        if (self::verify_backup($archive) === null) {
                            throw new moodle_exception('error_backupintegrity', 'local_autoarchive', '', $label);
                        }
                        $result['backed_up']++;
                    }
                }

                \core_php_time_limit::raise();
                if (!delete_course($courseid, false)) {
                    throw new moodle_exception('error_delete', 'local_autoarchive', '', $label);
                }

                // Existing archives of this course now only consist of the backup.
                $DB->execute('UPDATE {local_autoarchive_courses} SET course_deleted = 1, timemodified = :now
                               WHERE courseid = :courseid', ['now' => time(), 'courseid' => $courseid]);
                $result['count']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $label . ': ' . self::describe_exception($e);
            }
        }

        return $result;
    }

    /**
     * Remove backups of deleted courses that are older than the configured retention time.
     *
     * @return array ['count' => int, 'errors' => string[]]
     */
    public static function purge_expired_backups(): array {
        global $DB;

        $result = ['count' => 0, 'errors' => []];
        $days = (int)get_config('local_autoarchive', 'maxbackuptime');
        if ($days <= 0) {
            return $result;
        }

        $archives = $DB->get_records_select('local_autoarchive_courses',
            'course_deleted = 1 AND archived_date <= :cutofftime', ['cutofftime' => time() - ($days * DAYSECS)]);
        foreach ($archives as $archive) {
            // Safety net: never purge the backup of a course that exists.
            if ($DB->record_exists('course', ['id' => $archive->courseid])) {
                continue;
            }
            try {
                self::remove_archive($archive);
                $result['count']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $archive->backupfilename . ': ' . self::describe_exception($e);
            }
        }
        return $result;
    }

    // Notifications.

    /**
     * Send the "courses archived" email to the configured addresses.
     *
     * @param stdClass[] $courses Archived courses
     */
    public static function send_archive_notification(array $courses): void {
        if (!get_config('local_autoarchive', 'enablenotifications') || !$courses) {
            return;
        }
        $emails = (string)get_config('local_autoarchive', 'notificationemails');
        $recipients = array_filter(array_map('trim', preg_split('/[\s,;]+/', $emails)));
        if (!$recipients) {
            return;
        }

        $courselist = '';
        foreach ($courses as $course) {
            $courselist .= '- ' . format_string($course->fullname, true, ['escape' => false]) . " (ID: {$course->id})\n";
        }

        $subject = get_string('notification_subject', 'local_autoarchive');
        $message = get_string('notification_body', 'local_autoarchive', [
            'courses' => $courselist,
            'url' => (new url('/local/autoarchive/manage.php'))->out(false),
        ]);

        $noreply = \core_user::get_noreply_user();
        foreach ($recipients as $recipient) {
            if (!validate_email($recipient)) {
                debugging('local_autoarchive: invalid notification address ' . $recipient, DEBUG_DEVELOPER);
                continue;
            }
            // Email to a plain address: reuse the no-reply user (it has an ID) with the target address.
            $to = clone($noreply);
            $to->email = $recipient;
            $to->firstname = '';
            $to->lastname = '';
            $to->mailformat = FORMAT_PLAIN;
            email_to_user($to, $noreply, $subject, $message);
        }
    }

    /**
     * Notify a user about the result of a queued task.
     *
     * @param int $userid Recipient
     * @param string $subject Subject
     * @param string $body Plain text body
     */
    public static function notify_user(int $userid, string $subject, string $body): void {
        global $DB;

        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            return;
        }
        $url = new url('/local/autoarchive/manage.php');

        $message = new \core\message\message();
        $message->component = 'local_autoarchive';
        $message->name = 'taskcompleted';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $userid;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = nl2br(s($body));
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = get_string('managearchivedcourses', 'local_autoarchive');
        message_send($message);
    }

    /**
     * Message of an exception including the technical details of Moodle exceptions.
     *
     * @param \Throwable $e
     * @return string
     */
    public static function describe_exception(\Throwable $e): string {
        $message = $e->getMessage();
        if ($e instanceof moodle_exception && !empty($e->debuginfo)) {
            $message .= ' (' . $e->debuginfo . ')';
        }
        return $message;
    }

    /**
     * Turn a result array of a task into a readable text.
     *
     * @param string $summary First line
     * @param string[] $errors Error messages
     * @return string
     */
    public static function format_result(string $summary, array $errors): string {
        if (!$errors) {
            return $summary;
        }
        return $summary . "\n\n" . get_string('errors', 'local_autoarchive') . ":\n- " . implode("\n- ", $errors);
    }
}
