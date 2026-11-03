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
 * Table of archived courses.
 *
 * @package    local_autoarchive
 * @copyright  2026 Tobias Sanders
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autoarchive\table;

use core\context\system as context_system;
use core\output\checkbox_toggleall;
use core\output\html_writer;
use core\url;
use core_table\sql_table;
use local_autoarchive\archive_manager;

/**
 * Table class for archived courses.
 */
class archived_courses_table extends sql_table {
    /** @var array archiveid => teacher names, loaded once per page */
    protected $teachers = [];

    /** @var array courseid => visible of courses that still exist, loaded once per page */
    protected $coursevisibility = [];

    /** @var bool[] Capabilities of the current user */
    protected $can = [];

    /** @var string Toggle group of the selection checkboxes */
    const TOGGLEGROUP = 'local_autoarchive_archives';

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique ID for the table
     * @param url $baseurl Base URL including the active filters
     */
    public function __construct($uniqueid, url $baseurl) {
        global $OUTPUT;

        parent::__construct($uniqueid);

        $context = context_system::instance();
        foreach (['restore', 'download', 'delete'] as $action) {
            $this->can[$action] = has_capability('local/autoarchive:' . $action, $context);
        }

        // Column names match database fields so that sorting works.
        $columns = ['coursefullname', 'categoryname', 'teachers', 'lastaccess', 'archived_date', 'backupfilesize',
            'status', 'actions'];
        $headers = [
            get_string('coursename', 'local_autoarchive'),
            get_string('category', 'local_autoarchive'),
            get_string('teachers', 'local_autoarchive'),
            get_string('lastaccess', 'local_autoarchive'),
            get_string('archivedate', 'local_autoarchive'),
            get_string('filesize', 'local_autoarchive'),
            get_string('status', 'local_autoarchive'),
            get_string('actions', 'local_autoarchive'),
        ];

        // Selection column for bulk actions, only for users who may delete.
        if ($this->can['delete']) {
            $toggler = new checkbox_toggleall(self::TOGGLEGROUP, true, [
                'id' => 'local_autoarchive_selectall',
                'label' => get_string('selectall'),
                'labelclasses' => 'visually-hidden',
            ]);
            array_unshift($columns, 'select');
            array_unshift($headers, $OUTPUT->render($toggler));
        }

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl($baseurl);

        $this->sortable(true, 'archived_date', SORT_DESC);
        $this->no_sorting('select');
        $this->no_sorting('teachers');
        $this->no_sorting('status');
        $this->no_sorting('actions');
        $this->collapsible(false);
        $this->pageable(true);
        $this->is_downloadable(false);
    }

    /**
     * Whether the selection column and bulk actions are shown.
     *
     * @return bool
     */
    public function has_bulk_actions(): bool {
        return $this->can['delete'];
    }

    /**
     * Setup the table with SQL query.
     *
     * @param string $search Search term (course full or short name)
     * @param string $teacher Teacher name filter
     * @param int $datefrom Archived on or after
     * @param int $dateto Archived on or before
     */
    public function setup_sql(string $search = '', string $teacher = '', int $datefrom = 0, int $dateto = 0): void {
        global $DB;

        $params = [];
        $conditions = ['1 = 1'];

        if ($search !== '') {
            $conditions[] = '(' . $DB->sql_like('a.coursefullname', ':search1', false) . ' OR ' .
                $DB->sql_like('a.coursename', ':search2', false) . ')';
            $params['search1'] = $params['search2'] = '%' . $DB->sql_like_escape($search) . '%';
        }

        if ($teacher !== '') {
            $conditions[] = 'EXISTS (SELECT 1 FROM {local_autoarchive_teachers} t
                                      WHERE t.archiveid = a.id AND ' .
                $DB->sql_like($DB->sql_concat('t.firstname', "' '", 't.lastname'), ':teacher', false) . ')';
            $params['teacher'] = '%' . $DB->sql_like_escape($teacher) . '%';
        }

        if ($datefrom > 0) {
            $conditions[] = 'a.archived_date >= :datefrom';
            $params['datefrom'] = $datefrom;
        }

        if ($dateto > 0) {
            $conditions[] = 'a.archived_date <= :dateto';
            $params['dateto'] = $dateto;
        }

        $fields = 'a.id, a.courseid, a.coursename, a.coursefullname, a.categoryname, a.lastaccess, a.archived_date, ' .
            'a.backupfilename, a.backupfilesize, a.course_hidden, a.course_deleted';
        $this->set_sql($fields, '{local_autoarchive_courses} a', implode(' AND ', $conditions), $params);
    }

    /**
     * Load the rows and preload teachers and course states for the page.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        parent::query_db($pagesize, $useinitialsbar);

        $rows = is_array($this->rawdata) ? $this->rawdata : [];
        $this->teachers = archive_manager::get_archive_teachers(array_keys($rows));
        $courseids = array_unique(array_column($rows, 'courseid'));
        $this->coursevisibility = $courseids
            ? $DB->get_records_list('course', 'id', $courseids, '', 'id, visible')
            : [];
    }

    /**
     * Message if there are no archives.
     */
    public function print_nothing_to_display() {
        global $OUTPUT;
        echo $OUTPUT->notification(get_string('noarchives', 'local_autoarchive'), 'info');
    }

    /**
     * Format selection column.
     *
     * @param \stdClass $row Table row
     * @return string
     */
    public function col_select($row) {
        global $OUTPUT;

        $checkbox = new checkbox_toggleall(self::TOGGLEGROUP, false, [
            'id' => 'local_autoarchive_archive' . $row->id,
            'name' => 'archiveids[]',
            'value' => $row->id,
            'label' => get_string('selectarchive', 'local_autoarchive', format_string($row->coursefullname)),
            'labelclasses' => 'visually-hidden',
        ]);
        return $OUTPUT->render($checkbox);
    }

    /**
     * Format course name column.
     *
     * @param \stdClass $row Table row
     * @return string
     */
    public function col_coursefullname($row) {
        return html_writer::div(s($row->coursefullname), 'fw-bold') .
               html_writer::div(s($row->coursename), 'small text-muted');
    }

    /**
     * Format category column.
     *
     * @param \stdClass $row Table row
     * @return string
     */
    public function col_categoryname($row) {
        return s($row->categoryname);
    }

    /**
     * Format teachers column.
     *
     * @param \stdClass $row Table row
     * @return string
     */
    public function col_teachers($row) {
        if (empty($this->teachers[$row->id])) {
            return '-';
        }
        return implode('<br>', array_map('s', $this->teachers[$row->id]));
    }

    /**
     * Format last access column.
     *
     * @param \stdClass $row Table row
     * @return string
     */
    public function col_lastaccess($row) {
        return $row->lastaccess ? userdate($row->lastaccess, get_string('strftimedatetime', 'langconfig')) : '-';
    }

    /**
     * Format archive date column.
     *
     * @param \stdClass $row Table row
     * @return string
     */
    public function col_archived_date($row) {
        return userdate($row->archived_date, get_string('strftimedatetime', 'langconfig'));
    }

    /**
     * Format file size column.
     *
     * @param \stdClass $row Table row
     * @return string
     */
    public function col_backupfilesize($row) {
        return display_size($row->backupfilesize);
    }

    /**
     * Format status column.
     *
     * @param \stdClass $row Table row
     * @return string
     */
    public function col_status($row) {
        $course = $this->coursevisibility[$row->courseid] ?? null;

        if (!$course) {
            [$key, $class] = ['status_backup_only', 'bg-secondary'];
        } else if (!$course->visible) {
            [$key, $class] = ['status_course_hidden', 'bg-warning text-dark'];
        } else if (archive_manager::is_reactivated($row, $course)) {
            [$key, $class] = ['status_reactivated', 'bg-info text-dark'];
        } else {
            [$key, $class] = ['status_course_visible', 'bg-light text-dark'];
        }

        $badge = html_writer::span(get_string($key, 'local_autoarchive'), 'badge ' . $class);
        if (archive_manager::get_backup_path($row) === null) {
            $badge .= ' ' . html_writer::span(get_string('backupnotfound', 'local_autoarchive'), 'badge bg-danger');
        }
        return $badge;
    }

    /**
     * Format actions column.
     *
     * @param \stdClass $row Table row
     * @return string
     */
    public function col_actions($row) {
        global $OUTPUT;

        $actions = '';
        $hasbackup = archive_manager::get_backup_path($row) !== null;
        $courseexists = isset($this->coursevisibility[$row->courseid]);

        if ($this->can['restore'] && ($courseexists || $hasbackup)) {
            $actions .= html_writer::link(
                new url('/local/autoarchive/restore.php', ['id' => $row->id]),
                $OUTPUT->pix_icon('t/restore', get_string('restore', 'local_autoarchive')),
                ['class' => 'btn btn-sm btn-secondary me-1']
            );
        }

        if ($this->can['download'] && $hasbackup) {
            $actions .= html_writer::link(
                new url('/local/autoarchive/download.php', ['id' => $row->id, 'sesskey' => sesskey()]),
                $OUTPUT->pix_icon('t/download', get_string('download', 'local_autoarchive')),
                ['class' => 'btn btn-sm btn-secondary me-1']
            );
        }

        if ($this->can['delete']) {
            $actions .= html_writer::link(
                new url('/local/autoarchive/delete.php', ['id' => $row->id]),
                $OUTPUT->pix_icon('t/delete', get_string('delete', 'local_autoarchive')),
                ['class' => 'btn btn-sm btn-danger']
            );
        }

        return $actions;
    }
}
