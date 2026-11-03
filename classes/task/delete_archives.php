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
 * Ad-hoc task for the bulk deletion of archives.
 *
 * @package    local_autoarchive
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autoarchive\task;

use core\task\adhoc_task;
use local_autoarchive\archive_manager;

/**
 * Deletes archived courses (and optionally their backups) in the background.
 *
 * Custom data: archiveids (int[]), deletebackups (bool), requester (int user ID).
 */
class delete_archives extends adhoc_task {
    /**
     * Get the name of the task.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('task_delete_archives', 'local_autoarchive');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        $archiveids = array_map('intval', (array)($data->archiveids ?? []));
        $deletebackups = !empty($data->deletebackups);

        mtrace('Processing ' . count($archiveids) . ' archive(s), delete backups: ' . ($deletebackups ? 'yes' : 'no'));
        $result = archive_manager::bulk_delete_archives($archiveids, $deletebackups);
        mtrace('Processed: ' . $result['count'] . ', errors: ' . count($result['errors']));
        foreach ($result['errors'] as $error) {
            mtrace('  Error: ' . $error);
        }

        if (!empty($data->requester)) {
            $summary = get_string('bulkdelete_result', 'local_autoarchive', (object)[
                'count' => $result['count'],
                'total' => count($archiveids),
            ]);
            archive_manager::notify_user(
                (int)$data->requester,
                get_string('bulkdelete_result_subject', 'local_autoarchive'),
                archive_manager::format_result($summary, $result['errors'])
            );
        }
    }
}
