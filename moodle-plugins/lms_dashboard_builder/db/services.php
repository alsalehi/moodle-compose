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
 * External service definitions for local_lms_dashboard_builder.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_lms_dashboard_builder_get_dashboard_layout' => [
        'classname'     => 'local_lms_dashboard_builder\external\dashboard_services',
        'methodname'    => 'get_dashboard_layout',
        'description'   => 'Retrieve dashboard layout, widgets, and filters metadata',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => false,
        'capabilities'  => 'local/lms_dashboard_builder:view',
    ],
    'local_lms_dashboard_builder_get_widget_data' => [
        'classname'     => 'local_lms_dashboard_builder\external\dashboard_services',
        'methodname'    => 'get_widget_data',
        'description'   => 'Load widget calculated data and visualizations asynchronously',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => false,
        'capabilities'  => 'local/lms_dashboard_builder:view',
    ],
    'local_lms_dashboard_builder_save_dashboard' => [
        'classname'     => 'local_lms_dashboard_builder\external\dashboard_services',
        'methodname'    => 'save_dashboard',
        'description'   => 'Create or update a dashboard definition',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'local/lms_dashboard_builder:manage',
    ],
];
