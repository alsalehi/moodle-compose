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
 * Navigation hooks for local_lms_dashboard_builder.
 *
 * Adds "Manage Dashboards" entry points to Course Administration and Site Navigation
 * for authorized dashboard managers.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend course navigation ("More" menu in Moodle 5.2) with a link to Manage Dashboards.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 */
function local_lms_dashboard_builder_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (has_capability('local/lms_dashboard_builder:manage', context_system::instance())) {
        $url = new moodle_url('/local/lms_dashboard_builder/manage.php');
        $navigation->add(
            get_string('manage_dashboards', 'local_lms_dashboard_builder'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_lms_dashboard_builder_manage',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Extend settings navigation with LMS Dashboard Builder links.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 */
function local_lms_dashboard_builder_extend_settings_navigation(
    settings_navigation $settingsnav,
    context $context
): void {
    if (!has_capability('local/lms_dashboard_builder:manage', context_system::instance())) {
        return;
    }

    if ($coursenode = $settingsnav->get('courseadmin')) {
        $url = new moodle_url('/local/lms_dashboard_builder/manage.php');
        if (!$coursenode->find('local_lms_dashboard_builder_courseadmin', navigation_node::TYPE_SETTING)) {
            $coursenode->add(
                get_string('manage_dashboards', 'local_lms_dashboard_builder'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'local_lms_dashboard_builder_courseadmin',
                new pix_icon('i/report', '')
            );
        }
    }
}
