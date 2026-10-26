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
 * Settings and admin pages for local_autoarchive plugin.
 *
 * @package    local_autoarchive
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use core\output\html_writer;
use core\url;

$ADMIN->add('localplugins', new admin_category('local_autoarchive_category', get_string('pluginname', 'local_autoarchive')));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_autoarchive', get_string('settings', 'local_autoarchive'));

    if ($ADMIN->fulltree) {
        $intro = html_writer::link(new url('/local/autoarchive/manage.php'),
            get_string('managearchivedcourses', 'local_autoarchive'), ['class' => 'btn btn-primary mb-3 me-2']);
        $intro .= html_writer::link(new url('/local/autoarchive/cleanup.php'),
            get_string('cleanup', 'local_autoarchive'), ['class' => 'btn btn-danger mb-3']);
        $intro .= html_writer::div(get_string('tasks_disabled_info', 'local_autoarchive',
            (new url('/admin/tool/task/scheduledtasks.php'))->out()), 'alert alert-info');
        $warning = \local_autoarchive\archive_manager::get_loglifetime_warning();
        if ($warning !== '') {
            $intro .= html_writer::div($warning, 'alert alert-warning');
        }
        $settings->add(new admin_setting_heading('local_autoarchive/managepage', '', $intro));

        $settings->add(new admin_setting_configtext(
            'local_autoarchive/inactivityperiod',
            get_string('inactivityperiod', 'local_autoarchive'),
            get_string('inactivityperiod_desc', 'local_autoarchive'),
            365,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configmultiselect(
            'local_autoarchive/excludedcategories',
            get_string('excludedcategories', 'local_autoarchive'),
            get_string('excludedcategories_desc', 'local_autoarchive'),
            [],
            fn() => core_course_category::make_categories_list()
        ));

        $settings->add(new admin_setting_configtext(
            'local_autoarchive/maxperrun',
            get_string('maxperrun', 'local_autoarchive'),
            get_string('maxperrun_desc', 'local_autoarchive'),
            50,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_autoarchive/enableautohide',
            get_string('enableautohide', 'local_autoarchive'),
            get_string('enableautohide_desc', 'local_autoarchive'),
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_autoarchive/enablenotifications',
            get_string('enablenotifications', 'local_autoarchive'),
            get_string('enablenotifications_desc', 'local_autoarchive'),
            1
        ));

        $settings->add(new admin_setting_configtextarea(
            'local_autoarchive/notificationemails',
            get_string('notificationemails', 'local_autoarchive'),
            get_string('notificationemails_desc', 'local_autoarchive'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'local_autoarchive/maxbackuptime',
            get_string('maxbackuptime', 'local_autoarchive'),
            get_string('maxbackuptime_desc', 'local_autoarchive'),
            730,
            PARAM_INT
        ));
    }

    $ADMIN->add('local_autoarchive_category', $settings);
}

$ADMIN->add('local_autoarchive_category', new admin_externalpage(
    'local_autoarchive_manage',
    get_string('managearchivedcourses', 'local_autoarchive'),
    new url('/local/autoarchive/manage.php'),
    'local/autoarchive:manage'
));

$ADMIN->add('local_autoarchive_category', new admin_externalpage(
    'local_autoarchive_cleanup',
    get_string('cleanup', 'local_autoarchive'),
    new url('/local/autoarchive/cleanup.php'),
    'local/autoarchive:delete'
));
