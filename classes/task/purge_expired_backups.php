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
 * Scheduled task removing expired backups of deleted courses.
 *
 * @package    local_autoarchive
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autoarchive\task;

use core\task\scheduled_task;
use local_autoarchive\archive_manager;

/**
 * Deletes backups of deleted courses after the configured retention time.
 */
class purge_expired_backups extends scheduled_task {

    /**
     * Get the name of the task.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('task_purge_expired_backups', 'local_autoarchive');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        if ((int)get_config('local_autoarchive', 'maxbackuptime') <= 0) {
            mtrace('Backup retention is disabled (0 days).');
            return;
        }

        $result = archive_manager::purge_expired_backups();
        mtrace('Removed ' . $result['count'] . ' expired backup(s).');
        foreach ($result['errors'] as $error) {
            mtrace('  Error: ' . $error);
        }
    }
}
