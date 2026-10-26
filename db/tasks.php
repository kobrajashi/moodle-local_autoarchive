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
 * Scheduled task definitions for local_autoarchive plugin.
 *
 * Both tasks are disabled after installation and must be enabled consciously
 * under Site administration > Server > Scheduled tasks.
 *
 * @package    local_autoarchive
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_autoarchive\task\archive_inactive_courses',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '3',
        'day' => '*',
        'dayofweek' => '0', // Sunday.
        'month' => '*',
        'disabled' => 1,
    ],
    [
        'classname' => 'local_autoarchive\task\purge_expired_backups',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '4',
        'day' => '*',
        'dayofweek' => '0', // Sunday.
        'month' => '*',
        'disabled' => 1,
    ],
];
