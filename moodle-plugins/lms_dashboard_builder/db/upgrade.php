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
 * Upgrade steps for local_lms_dashboard_builder.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for local_lms_dashboard_builder.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Always true on success.
 */
function xmldb_local_lms_dashboard_builder_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026042000) {
        upgrade_plugin_savepoint(true, 2026042000, 'local', 'lms_dashboard_builder');
    }

    if ($oldversion < 2026042001) {
        $table = new xmldb_table('local_lms_dashboard_builder_dashboard');
        $field = new xmldb_field('scope', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'system', 'contextid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('scope_status', XMLDB_INDEX_NOTUNIQUE, ['scope', 'status']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        if ($DB->count_records('local_lms_dashboard_builder_dashboard') === 0) {
            \local_lms_dashboard_builder\dashboard_manager::seed_default_dashboards(false);
        }

        upgrade_plugin_savepoint(true, 2026042001, 'local', 'lms_dashboard_builder');
    }

    if ($oldversion < 2026042004) {
        // Add cssclass field to local_lms_dashboard_builder_dashboard.
        $dashbaordtable = new xmldb_table('local_lms_dashboard_builder_dashboard');
        $dashboardcss = new xmldb_field('cssclass', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'category');
        if ($dbman->table_exists($dashbaordtable) && !$dbman->field_exists($dashbaordtable, $dashboardcss)) {
            $dbman->add_field($dashbaordtable, $dashboardcss);
        }

        // Add cssclass field to local_lms_dashboard_builder_widget.
        $widgettable = new xmldb_table('local_lms_dashboard_builder_widget');
        $widgetcss = new xmldb_field('cssclass', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'queryid');
        if ($dbman->table_exists($widgettable) && !$dbman->field_exists($widgettable, $widgetcss)) {
            $dbman->add_field($widgettable, $widgetcss);
        }

        upgrade_plugin_savepoint(true, 2026042004, 'local', 'lms_dashboard_builder');
    }

    return true;
}
