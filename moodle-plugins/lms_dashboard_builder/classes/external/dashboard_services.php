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

namespace local_lms_dashboard_builder\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use local_lms_dashboard_builder\dashboard_manager;
use local_lms_dashboard_builder\widget_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External service endpoints for asynchronous dashboard and widget operations.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard_services extends external_api {

    /**
     * Parameter description for get_dashboard_layout.
     */
    public static function get_dashboard_layout_parameters(): external_function_parameters {
        return new external_function_parameters([
            'dashboardid' => new external_value(PARAM_INT, 'The dashboard record ID'),
            'contextid'   => new external_value(PARAM_INT, 'The execution context ID', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Retrieve dashboard layout and widget list.
     *
     * @param int $dashboardid
     * @param int $contextid
     * @return array
     */
    public static function get_dashboard_layout(int $dashboardid, int $contextid = 1): array {
        $params = self::validate_parameters(self::get_dashboard_layout_parameters(), [
            'dashboardid' => $dashboardid,
            'contextid'   => $contextid,
        ]);

        $context = \context::instance_by_id($params['contextid']);
        try {
            self::validate_context($context);
        } catch (\Throwable $e) {
            // For public visitor dashboards on system context, allow when not logged in.
            if (!dashboard_manager::is_public_visitor_dashboard($params['dashboardid'])) {
                throw $e;
            }
        }

        $layout = dashboard_manager::get_full_dashboard_layout($params['dashboardid'], $context);
        $filters = [];
        foreach ($layout['filters'] as $f) {
            $filters[] = [
                'id' => (int)$f['id'],
                'filtertype' => (string)$f['filtertype'],
                'label' => (string)$f['label'],
                'defaultvalue' => (string)($f['defaultvalue'] ?? ''),
            ];
        }
        $layout['filters'] = $filters;
        return $layout;
    }

    /**
     * Return structure for get_dashboard_layout.
     */
    public static function get_dashboard_layout_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Dashboard ID'),
            'name' => new external_value(PARAM_TEXT, 'Dashboard name'),
            'description' => new external_value(PARAM_RAW, 'Description'),
            'scope' => new external_value(PARAM_ALPHANUMEXT, 'Scope', VALUE_OPTIONAL),
            'category' => new external_value(PARAM_TEXT, 'Category', VALUE_OPTIONAL),
            'status' => new external_value(PARAM_INT, 'Status code'),
            'isdefault' => new external_value(PARAM_INT, 'Default flag', VALUE_OPTIONAL),
            'cssclass' => new external_value(PARAM_TEXT, 'CSS Class', VALUE_OPTIONAL),
            'widgets' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Widget ID'),
                    'title' => new external_value(PARAM_TEXT, 'Widget Title'),
                    'widgettype' => new external_value(PARAM_ALPHANUMEXT, 'Widget Type'),
                    'queryid' => new external_value(PARAM_ALPHANUMEXT, 'Query ID'),
                    'configjson' => new external_value(PARAM_RAW, 'Widget JSON configuration', VALUE_OPTIONAL),
                    'cssclass' => new external_value(PARAM_TEXT, 'CSS Class', VALUE_OPTIONAL),
                    'grid_col' => new external_value(PARAM_INT, 'Col position'),
                    'grid_row' => new external_value(PARAM_INT, 'Row position'),
                    'grid_width' => new external_value(PARAM_INT, 'Grid width'),
                    'grid_height' => new external_value(PARAM_INT, 'Grid height'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order', VALUE_OPTIONAL),
                ])
            ),
            'filters' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Filter ID'),
                    'filtertype' => new external_value(PARAM_ALPHANUMEXT, 'Filter Type'),
                    'label' => new external_value(PARAM_TEXT, 'Label'),
                    'defaultvalue' => new external_value(PARAM_TEXT, 'Default Value', VALUE_OPTIONAL),
                ])
            ),
        ]);
    }

    /**
     * Parameter description for get_widget_data.
     */
    public static function get_widget_data_parameters(): external_function_parameters {
        return new external_function_parameters([
            'widgetid'   => new external_value(PARAM_INT, 'Widget instance ID'),
            'contextid'  => new external_value(PARAM_INT, 'Active context ID', VALUE_DEFAULT, 1),
            'filtersjson'=> new external_value(PARAM_RAW, 'JSON encoded filter parameters', VALUE_DEFAULT, '{}'),
        ]);
    }

    /**
     * Load data for a specific widget.
     *
     * @param int $widgetid
     * @param int $contextid
     * @param string $filtersjson
     * @return array
     */
    public static function get_widget_data(int $widgetid, int $contextid = 1, string $filtersjson = '{}'): array {
        $params = self::validate_parameters(self::get_widget_data_parameters(), [
            'widgetid'   => $widgetid,
            'contextid'  => $contextid,
            'filtersjson'=> $filtersjson,
        ]);

        $context = \context::instance_by_id($params['contextid']);
        try {
            self::validate_context($context);
        } catch (\Throwable $e) {
            $w = widget_manager::get_widget($params['widgetid']);
            if (!$w || !dashboard_manager::is_public_visitor_dashboard((int)$w->dashboardid)) {
                throw $e;
            }
        }

        $filters = json_decode($params['filtersjson'], true) ?: [];
        $result = widget_manager::load_widget_data($params['widgetid'], $filters, $context);

        $payload = $result['data'];
        $payload['config'] = $result['config'] ?? [];

        return [
            'widgetid'   => $result['widgetid'],
            'title'      => $result['title'],
            'widgettype' => $result['widgettype'],
            'configjson' => json_encode($result['config'] ?? []),
            'datajson'   => json_encode($payload),
        ];
    }

    /**
     * Return structure for get_widget_data.
     */
    public static function get_widget_data_returns(): external_single_structure {
        return new external_single_structure([
            'widgetid'   => new external_value(PARAM_INT, 'Widget ID'),
            'title'      => new external_value(PARAM_TEXT, 'Title'),
            'widgettype' => new external_value(PARAM_ALPHANUMEXT, 'Type'),
            'configjson' => new external_value(PARAM_RAW, 'Widget configuration JSON', VALUE_OPTIONAL),
            'datajson'   => new external_value(PARAM_RAW, 'Calculated JSON data payload'),
        ]);
    }

    /**
     * Parameter description for save_dashboard.
     */
    public static function save_dashboard_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id'          => new external_value(PARAM_INT, 'Dashboard ID (0 for new)', VALUE_DEFAULT, 0),
            'name'        => new external_value(PARAM_TEXT, 'Dashboard title'),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
            'status'      => new external_value(PARAM_INT, 'Status (0=draft, 1=published)', VALUE_DEFAULT, 0),
            'scope'       => new external_value(PARAM_ALPHANUMEXT, 'Scope (system, course, category, my)', VALUE_DEFAULT, 'system'),
        ]);
    }

    /**
     * Save or update a dashboard.
     *
     * @param int $id
     * @param string $name
     * @param string $description
     * @param int $status
     * @param string $scope
     * @return array
     */
    public static function save_dashboard(
        int $id,
        string $name,
        string $description = '',
        int $status = 0,
        string $scope = 'system'
    ): array {
        $params = self::validate_parameters(self::save_dashboard_parameters(), [
            'id'          => $id,
            'name'        => $name,
            'description' => $description,
            'status'      => $status,
            'scope'       => $scope,
        ]);

        $data = (object)[
            'name'        => $params['name'],
            'description' => $params['description'],
            'status'      => $params['status'],
            'scope'       => $params['scope'],
        ];

        if ($params['id'] > 0) {
            dashboard_manager::update_dashboard($params['id'], $data);
            $savedid = $params['id'];
        } else {
            $savedid = dashboard_manager::create_dashboard($data);
        }

        return [
            'id' => $savedid,
            'status' => true,
        ];
    }

    /**
     * Return structure for save_dashboard.
     */
    public static function save_dashboard_returns(): external_single_structure {
        return new external_single_structure([
            'id'     => new external_value(PARAM_INT, 'Dashboard ID'),
            'status' => new external_value(PARAM_BOOL, 'Operation success status'),
        ]);
    }
}
