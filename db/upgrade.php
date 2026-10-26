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
 * Upgrade script for local_autoarchive plugin.
 *
 * @package    local_autoarchive
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_autoarchive plugin.
 *
 * @param int $oldversion The version we are upgrading from
 * @return bool Always true
 */
function xmldb_local_autoarchive_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026102600) {
        $archives = new xmldb_table('local_autoarchive_courses');

        // Remember the visibility a course had before it was archived.
        $field = new xmldb_field('originalvisible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'archived_date');
        if (!$dbman->field_exists($archives, $field)) {
            $dbman->add_field($archives, $field);
        }

        // Teachers move from a JSON column (incl. email addresses) into their own table.
        $teachers = new xmldb_table('local_autoarchive_teachers');
        $teachers->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $teachers->add_field('archiveid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $teachers->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $teachers->add_field('firstname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $teachers->add_field('lastname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $teachers->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $teachers->add_key('archiveid', XMLDB_KEY_FOREIGN, ['archiveid'], 'local_autoarchive_courses', ['id']);
        $teachers->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        if (!$dbman->table_exists($teachers)) {
            $dbman->create_table($teachers);
        }

        $jsonfield = new xmldb_field('teachers');
        if ($dbman->field_exists($archives, $jsonfield)) {
            $rs = $DB->get_recordset('local_autoarchive_courses', null, '', 'id, teachers');
            foreach ($rs as $archive) {
                $list = json_decode((string)$archive->teachers, true);
                if (!is_array($list)) {
                    continue;
                }
                foreach ($list as $teacher) {
                    if (empty($teacher['id'])) {
                        continue;
                    }
                    $DB->insert_record('local_autoarchive_teachers', (object)[
                        'archiveid' => $archive->id,
                        'userid' => (int)$teacher['id'],
                        'firstname' => core_text::substr((string)($teacher['firstname'] ?? ''), 0, 100),
                        'lastname' => core_text::substr((string)($teacher['lastname'] ?? ''), 0, 100),
                    ]);
                }
            }
            $rs->close();
            $dbman->drop_field($archives, $jsonfield);
        }

        // From now on course_deleted means "the Moodle course no longer exists". Older versions also
        // used it to hide records from the list, which made the kept backups unreachable.
        $DB->execute("UPDATE {local_autoarchive_courses}
                         SET course_deleted = CASE
                             WHEN EXISTS (SELECT 1 FROM {course} c WHERE c.id = {local_autoarchive_courses}.courseid) THEN 0
                             ELSE 1 END");

        // The compression level setting never had any effect and has been removed.
        unset_config('compressionlevel', 'local_autoarchive');

        upgrade_plugin_savepoint(true, 2026102600, 'local', 'autoarchive');
    }

    return true;
}
