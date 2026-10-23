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
 * Settings and administration navigation for local_lms_dashboard_builder.
 *
 * Exposes entry points under:
 * 1. Site Administration -> Plugins -> Local plugins -> LMS Dashboard Builder
 * 2. Site Administration -> Reports -> Manage Dashboards & Custom Queries
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig || has_capability('local/lms_dashboard_builder:manage', context_system::instance())) {
    $manageurl = new moodle_url('/local/lms_dashboard_builder/manage.php');
    $queriesurl = new moodle_url('/local/lms_dashboard_builder/queries.php');

    // 1. Register category under Site Administration -> Plugins -> Local plugins.
    $ADMIN->add('localplugins', new admin_category(
        'local_lms_dashboard_builder_cat',
        get_string('pluginname', 'local_lms_dashboard_builder')
    ));

    // Standard settings page (also surfaces the "Settings" link on Plugins overview).
    $settings = new admin_settingpage(
        'local_lms_dashboard_builder',
        get_string('pluginname', 'local_lms_dashboard_builder'),
        'local/lms_dashboard_builder:manage'
    );

    if ($ADMIN->fulltree) {
        $linkshtml = html_writer::div(
            html_writer::link(
                $manageurl,
                '<i class="fa fa-th-large me-1" aria-hidden="true"></i>' .
                get_string('manage_dashboards', 'local_lms_dashboard_builder'),
                ['class' => 'btn btn-primary me-2 mb-2']
            ) .
            html_writer::link(
                $queriesurl,
                '<i class="fa fa-database me-1" aria-hidden="true"></i>' .
                get_string('manage_queries', 'local_lms_dashboard_builder'),
                ['class' => 'btn btn-outline-primary mb-2']
            ),
            'mb-3'
        );

        $settings->add(new admin_setting_heading(
            'local_lms_dashboard_builder/quicklinks',
            get_string('manage_dashboards', 'local_lms_dashboard_builder'),
            $linkshtml
        ));

        $settings->add(new admin_setting_configtext(
            'local_lms_dashboard_builder/cachettl',
            get_string('settings_cachettl', 'local_lms_dashboard_builder'),
            get_string('settings_cachettl_desc', 'local_lms_dashboard_builder'),
            300,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_lms_dashboard_builder/maxrows',
            get_string('settings_maxrows', 'local_lms_dashboard_builder'),
            get_string('settings_maxrows_desc', 'local_lms_dashboard_builder'),
            100,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_lms_dashboard_builder/enablecustomsql',
            get_string('settings_enablecustomsql', 'local_lms_dashboard_builder'),
            get_string('settings_enablecustomsql_desc', 'local_lms_dashboard_builder'),
            1
        ));
    }

    $ADMIN->add('local_lms_dashboard_builder_cat', $settings);

    // Direct external pages under Plugins -> Local plugins -> LMS Dashboard Builder.
    $ADMIN->add('local_lms_dashboard_builder_cat', new admin_externalpage(
        'local_lms_dashboard_builder_manage',
        get_string('manage_dashboards', 'local_lms_dashboard_builder'),
        $manageurl,
        'local/lms_dashboard_builder:manage'
    ));

    $ADMIN->add('local_lms_dashboard_builder_cat', new admin_externalpage(
        'local_lms_dashboard_builder_queries',
        get_string('manage_queries', 'local_lms_dashboard_builder'),
        $queriesurl,
        'local/lms_dashboard_builder:manage'
    ));

    // 2. Also register direct external pages under Site Administration -> Reports.
    $ADMIN->add('reports', new admin_externalpage(
        'local_lms_dashboard_builder_reports_manage',
        get_string('pluginname', 'local_lms_dashboard_builder') . ': ' . get_string('manage_dashboards', 'local_lms_dashboard_builder'),
        $manageurl,
        'local/lms_dashboard_builder:manage'
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'local_lms_dashboard_builder_reports_queries',
        get_string('pluginname', 'local_lms_dashboard_builder') . ': ' . get_string('manage_queries', 'local_lms_dashboard_builder'),
        $queriesurl,
        'local/lms_dashboard_builder:manage'
    ));

    // Prevent Moodle's local plugin loader from re-adding $settings a second time.
    $settings = null;
}
