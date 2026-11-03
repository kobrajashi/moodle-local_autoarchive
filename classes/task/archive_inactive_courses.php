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
 * Scheduled task for archiving inactive courses.
 *
 * @package    local_autoarchive
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autoarchive\task;

use core\task\scheduled_task;
use local_autoarchive\archive_manager;

/**
 * Backs up inactive courses, records them and hides them if configured.
 */
class archive_inactive_courses extends scheduled_task {
    /**
     * Get the name of the task.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('task_archive_inactive_courses', 'local_autoarchive');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        mtrace('Starting course archiving task...');

        $warning = archive_manager::get_loglifetime_warning();
        if ($warning !== '') {
            mtrace('Warning: ' . $warning);
        }

        $inactivecourses = archive_manager::get_inactive_courses(archive_manager::get_inactivity_period(), true);
        if (empty($inactivecourses)) {
            mtrace('No inactive courses found.');
            return;
        }

        mtrace('Found ' . count($inactivecourses) . ' inactive course(s) to archive.');

        // Limit the number of courses per run to avoid server overload.
        $maxperrun = (int)get_config('local_autoarchive', 'maxperrun') ?: 50;
        if (count($inactivecourses) > $maxperrun) {
            mtrace('Limiting to ' . $maxperrun . ' courses per run.');
            $inactivecourses = array_slice($inactivecourses, 0, $maxperrun);
        }

        $archivedcourses = [];
        $failed = 0;
        foreach ($inactivecourses as $course) {
            mtrace('Processing course: ' . $course->fullname . ' (ID: ' . $course->id . ')');
            try {
                $result = archive_manager::archive_course($course);
                mtrace('  Archived (Archive ID: ' . $result['archiveid'] . ', hidden: ' . ($result['hidden'] ? 'yes' : 'no') . ')');
                if ($result['warning'] !== '') {
                    mtrace('  Warning: ' . $result['warning']);
                }
                $archivedcourses[] = $course;
            } catch (\Throwable $e) {
                mtrace('  Error: ' . archive_manager::describe_exception($e));
                $failed++;
            }
        }

        mtrace('Archiving task completed. Archived: ' . count($archivedcourses) . ', failed: ' . $failed);

        archive_manager::send_archive_notification($archivedcourses);
    }
}
