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
 * Privacy provider for local_autoarchive plugin.
 *
 * @package    local_autoarchive
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autoarchive\privacy;

use core\context\system as context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * The plugin stores the names of the editing teachers of archived courses. The archived
 * course backups themselves contain user data as described by the core backup subsystem.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_autoarchive_teachers', [
            'archiveid' => 'privacy:metadata:local_autoarchive_teachers:archiveid',
            'userid' => 'privacy:metadata:local_autoarchive_teachers:userid',
            'firstname' => 'privacy:metadata:local_autoarchive_teachers:firstname',
            'lastname' => 'privacy:metadata:local_autoarchive_teachers:lastname',
        ], 'privacy:metadata:local_autoarchive_teachers');

        $collection->add_subsystem_link('core_backup', [], 'privacy:metadata:core_backup');

        return $collection;
    }

    /**
     * Contexts with data of a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
              WHERE ctx.contextlevel = :contextlevel
                AND EXISTS (SELECT 1 FROM {local_autoarchive_teachers} t WHERE t.userid = :userid)",
            ['contextlevel' => CONTEXT_SYSTEM, 'userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_autoarchive_teachers}', []);
    }

    /**
     * Export the archives a user was teacher of.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $context = context_system::instance();
        if (!in_array($context->id, $contextlist->get_contextids())) {
            return;
        }

        $sql = "SELECT t.id, t.firstname, t.lastname, a.coursefullname, a.coursename, a.archived_date
                  FROM {local_autoarchive_teachers} t
                  JOIN {local_autoarchive_courses} a ON a.id = t.archiveid
                 WHERE t.userid = :userid
              ORDER BY a.archived_date";
        $records = $DB->get_records_sql($sql, ['userid' => $contextlist->get_user()->id]);
        if (!$records) {
            return;
        }

        $data = [];
        foreach ($records as $record) {
            $data[] = (object)[
                'coursefullname' => $record->coursefullname,
                'courseshortname' => $record->coursename,
                'archived' => transform::datetime($record->archived_date),
                'firstname' => $record->firstname,
                'lastname' => $record->lastname,
            ];
        }
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_autoarchive')],
            (object)['archives' => $data]
        );
    }

    /**
     * Delete all teacher records.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof context_system) {
            $DB->delete_records('local_autoarchive_teachers');
        }
    }

    /**
     * Delete the teacher records of one user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (in_array(context_system::instance()->id, $contextlist->get_contextids())) {
            $DB->delete_records('local_autoarchive_teachers', ['userid' => $contextlist->get_user()->id]);
        }
    }

    /**
     * Delete the teacher records of several users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if (!$userlist->get_context() instanceof context_system || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_autoarchive_teachers', "userid $insql", $params);
    }
}
