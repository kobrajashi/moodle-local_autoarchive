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
 * Ad-hoc task deleting inactive courses selected on the cleanup page.
 *
 * @package    local_autoarchive
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autoarchive\task;

use core\task\adhoc_task;
use local_autoarchive\archive_manager;

/**
 * Deletes the selected courses in the background, optionally after backing them up.
 *
 * Custom data: courseids (int[]), createbackups (bool), days (int), requester (int user ID).
 */
class delete_courses extends adhoc_task {

    /**
     * Get the name of the task.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('task_delete_courses', 'local_autoarchive');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        $courseids = array_map('intval', (array)($data->courseids ?? []));

        mtrace('Deleting ' . count($courseids) . ' course(s)...');
        $result = archive_manager::delete_courses_by_ids($courseids, !empty($data->createbackups), (int)($data->days ?? 0));
        mtrace('Deleted: ' . $result['count'] . ', backed up: ' . $result['backed_up'] .
            ', existing backups reused: ' . $result['reused'] . ', errors: ' . count($result['errors']));
        foreach ($result['errors'] as $error) {
            mtrace('  Error: ' . $error);
        }

        if (!empty($data->requester)) {
            $summary = get_string('cleanup_result', 'local_autoarchive', (object)[
                'count' => $result['count'],
                'total' => count($courseids),
                'backedup' => $result['backed_up'],
                'reused' => $result['reused'],
            ]);
            archive_manager::notify_user((int)$data->requester, get_string('cleanup_result_subject', 'local_autoarchive'),
                archive_manager::format_result($summary, $result['errors']));
        }
    }
}
