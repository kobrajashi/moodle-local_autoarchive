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
 * English language strings for local_autoarchive.
 *
 * @package    local_autoarchive
 * @category   string
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['alldates'] = 'All dates';
$string['archivedate'] = 'Archived on';
$string['autoarchive:delete'] = 'Delete archived and inactive courses permanently';
$string['autoarchive:download'] = 'Download course backups';
$string['autoarchive:manage'] = 'Manage archived courses';
$string['autoarchive:restore'] = 'Restore archived courses';
$string['backupnotfound'] = 'Backup file missing';
$string['bulkaction_apply'] = 'Apply to selection';
$string['bulkaction_confirm_deletearchives'] = 'The following {$a} archive(s) will be deleted permanently: the course (if it still exists), the backup file and the archive entry. The deletion runs in the background.';
$string['bulkaction_confirm_deletecourses'] = 'For the following {$a} archive(s) the courses will be deleted. Backups remain available in the list. The deletion runs in the background.';
$string['bulkaction_deletearchives'] = 'Delete permanently (course, backup and entry)';
$string['bulkaction_deletecourses'] = 'Delete courses only, keep backups';
$string['bulkaction_noselection'] = 'No archives selected.';
$string['bulkaction_note_backuponly'] = 'backup only';
$string['bulkaction_note_course'] = 'course exists';
$string['bulkaction_note_reactivated'] = 'reactivated, will be skipped';
$string['bulkaction_reactivatedinfo'] = '{$a} course(s) were made visible again after archiving and will not be deleted.';
$string['bulkaction_selected'] = 'With selected archives';
$string['bulkdelete'] = 'Delete archives';
$string['bulkdelete_button'] = 'Delete';
$string['bulkdelete_confirm'] = '{$a->count} archive(s) were archived more than {$a->days} days ago.';
$string['bulkdelete_days'] = 'Days since archiving';
$string['bulkdelete_days_help'] = 'Archives created more than this number of days ago are processed. Their courses are deleted if they still exist.';
$string['bulkdelete_error'] = 'Please enter a number of days between 1 and 7300.';
$string['bulkdelete_heading'] = 'Delete archived courses';
$string['bulkdelete_includebackups'] = 'Also delete backup files';
$string['bulkdelete_includebackups_help'] = 'If checked, backup files and archive entries are removed as well. Otherwise the backups remain available in this list (status "Backup only").';
$string['bulkdelete_keepbackups'] = 'The courses will be deleted. The backups remain available for download and restore.';
$string['bulkdelete_nomatches'] = 'No archives found matching the criteria.';
$string['bulkdelete_queued'] = 'The deletion of {$a} archive(s) has been queued. You will receive a notification with the result.';
$string['bulkdelete_result'] = '{$a->count} of {$a->total} archive(s) processed.';
$string['bulkdelete_result_subject'] = 'Course archive: bulk deletion finished';
$string['bulkdelete_withbackups'] = 'Courses, backup files and archive entries will be deleted.';
$string['category'] = 'Category';
$string['cleanup'] = 'Delete inactive courses';
$string['cleanup_alreadyarchived'] = 'Already archived, existing backup is reused';
$string['cleanup_confirm'] = 'Delete {$a} course(s)';
$string['cleanup_confirm_message'] = 'Do you really want to delete {$a} course(s)? The deletion runs in the background.';
$string['cleanup_continue'] = 'Continue to confirmation';
$string['cleanup_createbackups'] = 'Create backups before deletion';
$string['cleanup_createbackups_help'] = 'If checked, every course is backed up first and only deleted once its backup has been written and verified. Courses whose backup fails are kept.';
$string['cleanup_days'] = 'Days of inactivity';
$string['cleanup_days_help'] = 'Courses with no access for this many days are listed.';
$string['cleanup_description'] = 'Delete all courses that have been inactive for a specified number of days. A preview is shown before anything is deleted. Excluded categories are not listed.';
$string['cleanup_heading'] = 'Delete inactive courses';
$string['cleanup_nopreview'] = 'No inactive courses found for the specified period.';
$string['cleanup_noselection'] = 'No courses selected.';
$string['cleanup_preview'] = 'Show preview';
$string['cleanup_preview_description'] = '{$a} inactive course(s) found:';
$string['cleanup_queued'] = 'The deletion of {$a} course(s) has been queued. You will receive a notification with the result.';
$string['cleanup_result'] = '{$a->count} of {$a->total} course(s) deleted. New backups: {$a->backedup}, existing backups reused: {$a->reused}.';
$string['cleanup_result_subject'] = 'Course archive: deletion of inactive courses finished';
$string['cleanup_warning'] = 'Warning: the selected courses will be deleted permanently.';
$string['cleanup_withbackups'] = 'Each course is backed up first. Courses whose backup fails are not deleted.';
$string['cleanup_withoutbackups'] = 'No backups will be created!';
$string['confirmdelete'] = 'This action cannot be undone!';
$string['confirmdelete_backup'] = 'Delete the backup of "{$a}" permanently?';
$string['confirmdelete_course'] = 'Delete the course "{$a}" and its backup permanently?';
$string['confirmrestore_backup'] = 'Restore "{$a}" from its backup into a new course? After a successful restore the archive entry and the backup file are removed.';
$string['confirmrestore_existing'] = 'Make "{$a}" available again with its original visibility? The course still exists, so the archive entry and the backup file are removed afterwards.';
$string['coursedeleted'] = 'Archive of "{$a}" has been deleted permanently.';
$string['coursename'] = 'Course name';
$string['courserestored'] = 'Course "{$a}" has been restored.';
$string['delete'] = 'Delete permanently';
$string['download'] = 'Download';
$string['enableautohide'] = 'Hide archived courses';
$string['enableautohide_desc'] = 'Hide courses after the scheduled task has backed them up.';
$string['enablenotifications'] = 'Enable email notifications';
$string['enablenotifications_desc'] = 'Send an email to the addresses below when the scheduled task has archived courses.';
$string['error_archive'] = 'Archive {$a}';
$string['error_backupdelete'] = 'The backup file {$a} could not be deleted.';
$string['error_backupintegrity'] = 'The backup of "{$a}" is missing or damaged (integrity check failed).';
$string['error_courseactive'] = 'The course has been accessed recently and was skipped.';
$string['error_coursenotfound'] = 'Course {$a} not found.';
$string['error_createbackup'] = 'Error creating backup for course {$a}';
$string['error_delete'] = 'Error deleting course "{$a}"';
$string['error_hidecourse'] = 'Error hiding course {$a}';
$string['error_invalidid'] = 'Invalid archive ID';
$string['error_reactivated'] = 'The course "{$a}" has been made visible again since it was archived and is therefore not deleted. Restore the archive to dissolve it.';
$string['error_restore'] = 'Error restoring course "{$a}"';
$string['error_restoreinprogress'] = 'This archive is already being restored (in the browser or on the command line). Please wait until that restore has finished.';
$string['errors'] = 'Errors';
$string['excludedcategories'] = 'Excluded categories';
$string['excludedcategories_desc'] = 'Courses in these categories (including all subcategories) are never archived or listed for deletion, e.g. course templates.';
$string['filesize'] = 'File size';
$string['filterdate'] = 'Archived';
$string['filterteacher'] = 'Filter by teacher';
$string['inactivityperiod'] = 'Inactivity period (days)';
$string['inactivityperiod_desc'] = 'Number of days without access before a course is archived by the scheduled task. Access is determined from the last course access of users and the standard log (cron and backup entries are ignored).';
$string['last3months'] = 'Last 3 months';
$string['lastaccess'] = 'Last access';
$string['lastmonth'] = 'Last month';
$string['lastweek'] = 'Last week';
$string['lastyear'] = 'Last year';
$string['loglifetime_warning'] = 'The standard log keeps entries for only {$a->loglifetime} days, but the inactivity period is {$a->inactivityperiod} days. Access is then only known from the users\' last course access, which is removed when users are unenrolled. Check the inactivity results carefully.';
$string['managearchivedcourses'] = 'Manage archived courses';
$string['maxbackuptime'] = 'Backup retention time (days)';
$string['maxbackuptime_desc'] = 'Backups of courses that no longer exist are deleted by the task "Delete expired backups" once they are older than this number of days. 0 keeps them forever. The task is disabled by default.';
$string['maxperrun'] = 'Courses per run';
$string['maxperrun_desc'] = 'Maximum number of courses the scheduled task archives per run.';
$string['messageprovider:taskcompleted'] = 'Course archive: result of background deletions';
$string['noarchives'] = 'No archived courses found.';
$string['notification_body'] = 'The following courses have been archived automatically:

{$a->courses}
You can manage the archived courses here: {$a->url}';
$string['notification_subject'] = 'Courses archived automatically';
$string['notificationemails'] = 'Notification email addresses';
$string['notificationemails_desc'] = 'Email addresses that receive notifications, separated by commas or line breaks.';
$string['perpage'] = 'Entries per page';
$string['pluginname'] = 'Course auto-archive';
$string['privacy:metadata:core_backup'] = 'The archived course backups are created with the core backup system and contain the user data included in a course backup.';
$string['privacy:metadata:local_autoarchive_teachers'] = 'Editing teachers of a course at the time it was archived.';
$string['privacy:metadata:local_autoarchive_teachers:archiveid'] = 'The archive entry.';
$string['privacy:metadata:local_autoarchive_teachers:firstname'] = 'First name of the teacher.';
$string['privacy:metadata:local_autoarchive_teachers:lastname'] = 'Last name of the teacher.';
$string['privacy:metadata:local_autoarchive_teachers:userid'] = 'The user ID of the teacher.';
$string['restore'] = 'Restore';
$string['searchandfilter'] = 'Search and filter';
$string['searchcoursename'] = 'Search by course name';
$string['selectarchive'] = 'Select {$a}';
$string['settings'] = 'Settings';
$string['status'] = 'Status';
$string['status_backup_only'] = 'Backup only';
$string['status_course_hidden'] = 'Course hidden';
$string['status_course_visible'] = 'Course visible (not hidden)';
$string['status_reactivated'] = 'Reactivated';
$string['task_archive_inactive_courses'] = 'Archive inactive courses';
$string['task_delete_archives'] = 'Delete archives (background)';
$string['task_delete_courses'] = 'Delete inactive courses (background)';
$string['task_purge_expired_backups'] = 'Delete expired backups';
$string['tasks_disabled_info'] = 'The scheduled tasks "Archive inactive courses" and "Delete expired backups" are disabled after installation. Enable them under <a href="{$a}">Scheduled tasks</a> once the settings below are checked.';
$string['teachers'] = 'Teachers';
