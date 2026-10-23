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

namespace local_lms_dashboard_builder;

use local_lms_dashboard_builder\query\provider_registry;
use local_lms_dashboard_builder\filter\filter_engine;
use local_lms_dashboard_builder\security\security_guard;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager class for handling dashboard widgets, pre-built widget presets, and asynchronous data retrieval.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class widget_manager {

    /** Supported widget types. */
    public const TYPE_KPI = 'kpi';
    public const TYPE_CHART = 'chart';
    public const TYPE_TABLE = 'table';
    public const TYPE_PROGRESS = 'progress';
    public const TYPE_RING = 'ring';
    public const TYPE_COMPARISON = 'comparison';
    public const TYPE_ACTIVITY_LIST = 'activity_list';
    public const TYPE_STATUS_DIST = 'status_distribution';
    public const TYPE_RANKING = 'ranking';
    public const TYPE_TEXT = 'text';

    /**
     * Get catalog of all 10 supported widget types.
     *
     * @return array
     */
    public static function get_widget_types_catalog(): array {
        return [
            self::TYPE_KPI => get_string('widget_type_kpi', 'local_lms_dashboard_builder'),
            self::TYPE_CHART => get_string('widget_type_chart', 'local_lms_dashboard_builder'),
            self::TYPE_TABLE => get_string('widget_type_table', 'local_lms_dashboard_builder'),
            self::TYPE_PROGRESS => get_string('widget_type_progress', 'local_lms_dashboard_builder'),
            self::TYPE_RING => get_string('widget_type_ring', 'local_lms_dashboard_builder'),
            self::TYPE_COMPARISON => get_string('widget_type_comparison', 'local_lms_dashboard_builder'),
            self::TYPE_ACTIVITY_LIST => get_string('widget_type_activity_list', 'local_lms_dashboard_builder'),
            self::TYPE_STATUS_DIST => get_string('widget_type_status_distribution', 'local_lms_dashboard_builder'),
            self::TYPE_RANKING => get_string('widget_type_ranking', 'local_lms_dashboard_builder'),
            self::TYPE_TEXT => get_string('widget_type_text', 'local_lms_dashboard_builder'),
        ];
    }

    /**
     * Get catalog of all customizable chart types.
     *
     * @return array
     */
    public static function get_chart_types_catalog(): array {
        return [
            'bar' => get_string('chart_bar', 'local_lms_dashboard_builder'),
            'horizontalBar' => get_string('chart_horizontalBar', 'local_lms_dashboard_builder'),
            'stackedBar' => get_string('chart_stackedBar', 'local_lms_dashboard_builder'),
            'line' => get_string('chart_line', 'local_lms_dashboard_builder'),
            'area' => get_string('chart_area', 'local_lms_dashboard_builder'),
            'combo' => get_string('chart_combo', 'local_lms_dashboard_builder'),
            'doughnut' => get_string('chart_doughnut', 'local_lms_dashboard_builder'),
            'pie' => get_string('chart_pie', 'local_lms_dashboard_builder'),
            'gauge' => get_string('chart_gauge', 'local_lms_dashboard_builder'),
            'radar' => get_string('chart_radar', 'local_lms_dashboard_builder'),
            'polarArea' => get_string('chart_polarArea', 'local_lms_dashboard_builder'),
            'scatter' => get_string('chart_scatter', 'local_lms_dashboard_builder'),
        ];
    }

    /**
     * Get catalog of pre-built widget presets for rapid 1-click insertion.
     *
     * @return array
     */
    /**
     * Get catalog of pre-built widget presets for rapid 1-click insertion.
     *
     * @return array
     */
    public static function get_prebuilt_widget_presets(): array {
        return [
            // Public visitor-safe KPI presets (can be shown to non-logged in guests & public dashboards).
            'public_total_courses' => [
                'title' => get_string('preset_public_total_courses', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'public_kpis',
                'visitor_safe' => true,
                'grid_width' => 4,
                'configdata' => [
                    'metric' => 'total_courses',
                    'icon' => 'fa-graduation-cap',
                    'accent' => 'primary',
                    'subtitle' => get_string('preset_public_total_courses_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'public_active_courses' => [
                'title' => get_string('preset_public_active_courses', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'public_kpis',
                'visitor_safe' => true,
                'grid_width' => 4,
                'configdata' => [
                    'metric' => 'active_courses',
                    'icon' => 'fa-book',
                    'accent' => 'success',
                    'subtitle' => get_string('preset_public_active_courses_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'public_total_categories' => [
                'title' => get_string('preset_public_total_categories', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'public_kpis',
                'visitor_safe' => true,
                'grid_width' => 4,
                'configdata' => [
                    'metric' => 'total_categories',
                    'icon' => 'fa-folder-open',
                    'accent' => 'info',
                    'subtitle' => get_string('preset_public_total_categories_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'public_total_students' => [
                'title' => get_string('preset_public_total_students', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'public_kpis',
                'visitor_safe' => true,
                'grid_width' => 4,
                'configdata' => [
                    'metric' => 'total_students',
                    'icon' => 'fa-users',
                    'accent' => 'warning',
                    'subtitle' => get_string('preset_public_total_students_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'public_studying_students' => [
                'title' => get_string('preset_public_studying_students', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'public_kpis',
                'visitor_safe' => true,
                'grid_width' => 4,
                'configdata' => [
                    'metric' => 'studying_students',
                    'icon' => 'fa-pencil',
                    'accent' => 'primary',
                    'subtitle' => get_string('preset_public_studying_students_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'public_graduated_students' => [
                'title' => get_string('preset_public_graduated_students', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'public_kpis',
                'visitor_safe' => true,
                'grid_width' => 4,
                'configdata' => [
                    'metric' => 'graduated_students',
                    'icon' => 'fa-trophy',
                    'accent' => 'success',
                    'subtitle' => get_string('preset_public_graduated_students_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'public_total_teachers' => [
                'title' => get_string('preset_public_total_teachers', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'public_kpis',
                'visitor_safe' => true,
                'grid_width' => 4,
                'configdata' => [
                    'metric' => 'total_teachers',
                    'icon' => 'fa-user-circle',
                    'accent' => 'info',
                    'subtitle' => get_string('preset_public_total_teachers_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'public_total_completions' => [
                'title' => get_string('preset_public_total_completions', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'public_kpis',
                'visitor_safe' => true,
                'grid_width' => 4,
                'configdata' => [
                    'metric' => 'total_completions',
                    'icon' => 'fa-check-circle',
                    'accent' => 'success',
                    'subtitle' => get_string('preset_public_total_completions_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'public_total_activities' => [
                'title' => get_string('preset_public_total_activities', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'public_kpis',
                'visitor_safe' => true,
                'grid_width' => 4,
                'configdata' => [
                    'metric' => 'total_activities',
                    'icon' => 'fa-cubes',
                    'accent' => 'warning',
                    'subtitle' => get_string('preset_public_total_activities_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'public_graduation_rate' => [
                'title' => get_string('preset_public_graduation_rate', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'public_kpis',
                'visitor_safe' => true,
                'grid_width' => 4,
                'configdata' => [
                    'metric' => 'graduation_rate',
                    'suffix' => '%',
                    'icon' => 'fa-pie-chart',
                    'accent' => 'primary',
                    'subtitle' => get_string('preset_public_graduation_rate_sub', 'local_lms_dashboard_builder'),
                ],
            ],

            // Standard authenticated KPI & Chart presets.
            'course_enrolled_kpi' => [
                'title' => get_string('preset_course_enrolled_kpi', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'course_stats',
                'visitor_safe' => false,
                'grid_width' => 3,
                'configdata' => [
                    'metric' => 'enrolled_students',
                    'icon' => 'fa-users',
                    'accent' => 'primary',
                    'subtitle' => get_string('preset_course_enrolled_kpi_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'course_completion_ring' => [
                'title' => get_string('preset_course_completion_ring', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_RING,
                'queryid' => 'course_stats',
                'visitor_safe' => false,
                'grid_width' => 3,
                'configdata' => [
                    'metric' => 'completion_rate',
                    'accent' => 'success',
                ],
            ],
            'grading_backlog_kpi' => [
                'title' => get_string('preset_grading_backlog_kpi', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'assessment',
                'visitor_safe' => false,
                'grid_width' => 3,
                'configdata' => [
                    'metric' => 'pending_grading',
                    'icon' => 'fa-inbox',
                    'accent' => 'warning',
                    'subtitle' => get_string('preset_grading_backlog_kpi_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'average_grade_kpi' => [
                'title' => get_string('preset_average_grade_kpi', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_KPI,
                'queryid' => 'assessment',
                'visitor_safe' => false,
                'grid_width' => 3,
                'configdata' => [
                    'metric' => 'average_grade',
                    'suffix' => '%',
                    'icon' => 'fa-graduation-cap',
                    'accent' => 'info',
                    'subtitle' => get_string('preset_average_grade_kpi_sub', 'local_lms_dashboard_builder'),
                ],
            ],
            'enrollment_trend_area' => [
                'title' => get_string('preset_enrollment_trend_area', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_CHART,
                'queryid' => 'enrollment',
                'visitor_safe' => false,
                'grid_width' => 6,
                'configdata' => [
                    'charttype' => 'area',
                    'groupby' => 'time_month',
                    'palette' => 'ocean',
                    'xAxisLabel' => get_string('axis_month', 'local_lms_dashboard_builder'),
                    'yAxisLabel' => get_string('axis_enrolled_learners', 'local_lms_dashboard_builder'),
                    'showGridY' => true,
                    'lineTension' => 0.35,
                ],
            ],
            'top_courses_ranking' => [
                'title' => get_string('preset_top_courses_ranking', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_RANKING,
                'queryid' => 'enrollment',
                'visitor_safe' => false,
                'grid_width' => 6,
                'configdata' => [
                    'limit' => 5,
                    'accent' => 'primary',
                ],
            ],
            'grade_distribution_bar' => [
                'title' => get_string('preset_grade_distribution_bar', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_CHART,
                'queryid' => 'assessment',
                'visitor_safe' => false,
                'grid_width' => 6,
                'configdata' => [
                    'charttype' => 'bar',
                    'groupby' => 'by_grade_band',
                    'palette' => 'emerald',
                    'xAxisLabel' => get_string('axis_grade_band', 'local_lms_dashboard_builder'),
                    'yAxisLabel' => get_string('axis_submissions', 'local_lms_dashboard_builder'),
                    'borderRadius' => 8,
                ],
            ],
            'completion_status_dist' => [
                'title' => get_string('preset_completion_status_dist', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_STATUS_DIST,
                'queryid' => 'completion',
                'visitor_safe' => false,
                'grid_width' => 6,
                'configdata' => [
                    'palette' => 'emerald',
                ],
            ],
            'upcoming_deadlines_list' => [
                'title' => get_string('preset_upcoming_deadlines_list', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_ACTIVITY_LIST,
                'queryid' => 'learner_progress',
                'visitor_safe' => false,
                'grid_width' => 6,
                'configdata' => [
                    'limit' => 6,
                ],
            ],
            'engagement_by_module_chart' => [
                'title' => get_string('preset_engagement_by_module_chart', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_CHART,
                'queryid' => 'engagement',
                'visitor_safe' => false,
                'grid_width' => 6,
                'configdata' => [
                    'charttype' => 'horizontalBar',
                    'palette' => 'modern',
                    'xAxisLabel' => get_string('axis_completions_events', 'local_lms_dashboard_builder'),
                    'yAxisLabel' => get_string('axis_module_type', 'local_lms_dashboard_builder'),
                ],
            ],
            'category_comparison_combo' => [
                'title' => get_string('preset_category_comparison_combo', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_CHART,
                'queryid' => 'category_management',
                'visitor_safe' => false,
                'grid_width' => 6,
                'configdata' => [
                    'charttype' => 'combo',
                    'palette' => 'modern',
                    'legendPosition' => 'top',
                ],
            ],
            'enrollment_period_comparison' => [
                'title' => get_string('preset_enrollment_period_comparison', 'local_lms_dashboard_builder'),
                'widgettype' => self::TYPE_COMPARISON,
                'queryid' => 'enrollment',
                'visitor_safe' => false,
                'grid_width' => 4,
                'configdata' => [
                    'accent' => 'primary',
                ],
            ],
        ];
    }

    /**
     * Get catalog mapping query providers to their available metric keys for KPI metric selection.
     *
     * @return array
     */
    public static function get_provider_metrics_catalog(): array {
        return [
            'public_kpis' => [
                'total_courses' => get_string('metric_total_courses', 'local_lms_dashboard_builder'),
                'active_courses' => get_string('metric_active_courses', 'local_lms_dashboard_builder'),
                'total_categories' => get_string('metric_total_categories', 'local_lms_dashboard_builder'),
                'total_students' => get_string('metric_total_students', 'local_lms_dashboard_builder'),
                'studying_students' => get_string('metric_studying_students', 'local_lms_dashboard_builder'),
                'graduated_students' => get_string('metric_graduated_students', 'local_lms_dashboard_builder'),
                'total_teachers' => get_string('metric_total_teachers', 'local_lms_dashboard_builder'),
                'total_completions' => get_string('metric_total_completions', 'local_lms_dashboard_builder'),
                'total_activities' => get_string('metric_total_activities', 'local_lms_dashboard_builder'),
                'graduation_rate' => get_string('metric_graduation_rate', 'local_lms_dashboard_builder'),
            ],
            'course_stats' => [
                'enrolled_students' => get_string('metric_enrolled_students', 'local_lms_dashboard_builder'),
                'completion_rate' => get_string('metric_completion_rate', 'local_lms_dashboard_builder'),
                'active_students' => get_string('metric_active_students', 'local_lms_dashboard_builder'),
                'completed_students' => get_string('metric_completed_students', 'local_lms_dashboard_builder'),
            ],
            'assessment' => [
                'total_submissions' => get_string('metric_total_submissions', 'local_lms_dashboard_builder'),
                'pending_grading' => get_string('metric_pending_grading', 'local_lms_dashboard_builder'),
                'average_grade' => get_string('metric_average_grade', 'local_lms_dashboard_builder'),
                'pass_rate' => get_string('metric_pass_rate', 'local_lms_dashboard_builder'),
            ],
            'completion' => [
                'completion_rate' => get_string('metric_completion_rate', 'local_lms_dashboard_builder'),
                'completed_count' => get_string('metric_completed_count', 'local_lms_dashboard_builder'),
                'in_progress_count' => get_string('metric_in_progress_count', 'local_lms_dashboard_builder'),
            ],
            'enrollment' => [
                'total_enrolled' => get_string('metric_total_enrolled', 'local_lms_dashboard_builder'),
                'new_enrollments' => get_string('metric_new_enrollments', 'local_lms_dashboard_builder'),
                'active_learners' => get_string('metric_active_learners', 'local_lms_dashboard_builder'),
            ],
            'engagement' => [
                'total_views' => get_string('metric_total_views', 'local_lms_dashboard_builder'),
                'total_posts' => get_string('metric_total_posts', 'local_lms_dashboard_builder'),
                'active_users' => get_string('metric_active_users', 'local_lms_dashboard_builder'),
            ],
        ];
    }

    /**
     * Get all widgets configured for a given dashboard.
     *
     * @param int $dashboardid
     * @return array
     */
    public static function get_widgets_for_dashboard(int $dashboardid): array {
        global $DB;
        return $DB->get_records('local_lms_dashboard_builder_widget', ['dashboardid' => $dashboardid], 'sortorder ASC, id ASC');
    }

    /**
     * Get a single widget record by ID.
     *
     * @param int $widgetid
     * @return \stdClass|false
     */
    public static function get_widget(int $widgetid) {
        global $DB;
        return $DB->get_record('local_lms_dashboard_builder_widget', ['id' => $widgetid]);
    }

    /**
     * Execute widget data retrieval with filter application, widget overrides, caching, and security checks.
     *
     * @param int $widgetid Widget instance ID.
     * @param array $rawfilters Filters submitted by client.
     * @param \context $context Active context.
     * @return array Structured data for widget rendering.
     * @throws \moodle_exception
     */
    public static function load_widget_data(int $widgetid, array $rawfilters, \context $context): array {
        global $USER;

        $widget = self::get_widget($widgetid);
        if (!$widget) {
            throw new \moodle_exception('error_dashboard_not_found', 'local_lms_dashboard_builder');
        }

        // Enforce view authorization on dashboard context.
        security_guard::require_view((int)$widget->dashboardid, $context);

        // For public visitor dashboards, enforce KPI widgets only.
        if (dashboard_manager::is_public_visitor_dashboard((int)$widget->dashboardid) && $widget->widgettype !== self::TYPE_KPI) {
            throw new \moodle_exception('error_permission_denied', 'local_lms_dashboard_builder');
        }

        $widgetconfig = !empty($widget->configdata) ? (json_decode($widget->configdata, true) ?: []) : [];

        // Summary / rich text widgets do not require a query provider.
        if ($widget->widgettype === self::TYPE_TEXT) {
            return [
                'widgetid' => (int)$widget->id,
                'title' => format_string($widget->title),
                'widgettype' => $widget->widgettype,
                'queryid' => (string)$widget->queryid,
                'cssclass' => (string)($widget->cssclass ?? ''),
                'config' => $widgetconfig,
                'grid' => [
                    'col' => (int)$widget->grid_col,
                    'row' => (int)$widget->grid_row,
                    'width' => (int)$widget->grid_width,
                    'height' => (int)$widget->grid_height,
                ],
                'data' => [
                    'text' => (string)($widgetconfig['content'] ?? $widget->title),
                ],
            ];
        }

        // Sanitize global filters and merge widget-specific filter overrides/ignores.
        $sanitized = filter_engine::sanitize_filters($rawfilters, $context);
        $filters = filter_engine::merge_widget_filters($sanitized, $widgetconfig, $context);

        // Apply dashboard-level backend-only filters (from_context, from_user, backend from_request).
        $filters = filter_engine::apply_dashboard_backend_filters((int)$widget->dashboardid, $filters, $context);

        // Lookup provider (built-in or saved custom/visual query).
        $provider = provider_registry::get((string)$widget->queryid);
        if (!$provider) {
            throw new \moodle_exception('error_invalid_query', 'local_lms_dashboard_builder');
        }

        // Check MUC cache first.
        $isadmin = is_siteadmin() || has_capability('local/lms_dashboard_builder:manage', \context_system::instance());
        $userscope = $isadmin ? 'admin' : 'u' . (int)($USER->id ?? 0);
        $cachekey = 'w_' . (int)$widget->id . '_c' . (int)$context->id . '_' . $userscope . '_' . md5(json_encode($filters));

        $data = null;
        try {
            $cache = \cache::make('local_lms_dashboard_builder', 'widgetdata');
            $cached = $cache->get($cachekey);
            if (is_array($cached)) {
                $data = $cached;
            }
        } catch (\Throwable $e) {
            $cache = null;
        }

        if ($data === null) {
            $data = $provider->execute($filters, $context);
            if (isset($cache) && $cache !== null) {
                try {
                    $cache->set($cachekey, $data);
                } catch (\Throwable $e) {
                    // Ignore cache store errors.
                }
            }
        }

        return [
            'widgetid' => (int)$widget->id,
            'title' => format_string($widget->title),
            'widgettype' => $widget->widgettype,
            'queryid' => $widget->queryid,
            'cssclass' => (string)($widget->cssclass ?? ''),
            'config' => $widgetconfig,
            'grid' => [
                'col' => (int)$widget->grid_col,
                'row' => (int)$widget->grid_row,
                'width' => (int)$widget->grid_width,
                'height' => (int)$widget->grid_height,
            ],
            'data' => $data,
        ];
    }

    /**
     * Add a widget to a dashboard.
     *
     * @param \stdClass $data
     * @return int New widget ID.
     */
    public static function add_widget(\stdClass $data): int {
        global $DB;
        security_guard::require_manage();

        $dashboardid = (int)$data->dashboardid;
        $ispublicvisitor = dashboard_manager::is_public_visitor_dashboard($dashboardid);

        $sortorder = isset($data->sortorder) ? (int)$data->sortorder : 0;
        if ($sortorder <= 0) {
            $existing = self::get_widgets_for_dashboard($dashboardid);
            $maxorder = 0;
            foreach ($existing as $ew) {
                if ((int)$ew->sortorder > $maxorder) {
                    $maxorder = (int)$ew->sortorder;
                }
            }
            $sortorder = max(count($existing), $maxorder) + 1;
        }

        $widgettype = clean_param($data->widgettype, PARAM_ALPHANUMEXT);
        if ($ispublicvisitor) {
            $widgettype = self::TYPE_KPI;
        }

        $record = new \stdClass();
        $record->dashboardid = $dashboardid;
        $record->title = clean_param($data->title, PARAM_TEXT);
        $record->widgettype = $widgettype;
        $record->queryid = clean_param($data->queryid ?? ($ispublicvisitor ? 'public_kpis' : 'course_stats'), PARAM_ALPHANUMEXT);
        $record->configdata = !empty($data->configdata)
            ? (is_string($data->configdata) ? $data->configdata : json_encode($data->configdata))
            : '{}';
        $record->cssclass = isset($data->cssclass) ? dashboard_manager::sanitize_css_classes($data->cssclass) : '';
        $record->grid_col = isset($data->grid_col) ? (int)$data->grid_col : 0;
        $record->grid_row = isset($data->grid_row) ? (int)$data->grid_row : 0;
        $record->grid_width = isset($data->grid_width) ? max(2, min(12, (int)$data->grid_width)) : 6;
        $record->grid_height = isset($data->grid_height) ? max(1, min(12, (int)$data->grid_height)) : 4;
        $record->sortorder = $sortorder;
        $record->timecreated = time();
        $record->timemodified = time();

        $id = $DB->insert_record('local_lms_dashboard_builder_widget', $record);
        self::purge_widget_cache();
        security_guard::log_audit($record->dashboardid, 'add_widget', ['widgetid' => $id, 'title' => $record->title]);

        return $id;
    }

    /**
     * Update an existing widget on a dashboard.
     *
     * @param int $widgetid
     * @param \stdClass $data
     * @return bool
     */
    public static function update_widget(int $widgetid, \stdClass $data): bool {
        global $DB;
        security_guard::require_manage();

        $existing = self::get_widget($widgetid);
        if (!$existing) {
            throw new \moodle_exception('error_dashboard_not_found', 'local_lms_dashboard_builder');
        }

        $ispublicvisitor = dashboard_manager::is_public_visitor_dashboard((int)$existing->dashboardid);

        $record = new \stdClass();
        $record->id = $widgetid;
        if (isset($data->title)) {
            $record->title = clean_param($data->title, PARAM_TEXT);
        }
        if (isset($data->widgettype)) {
            $record->widgettype = $ispublicvisitor ? self::TYPE_KPI : clean_param($data->widgettype, PARAM_ALPHANUMEXT);
        }
        if (isset($data->queryid)) {
            $record->queryid = clean_param($data->queryid, PARAM_ALPHANUMEXT);
        }
        if (isset($data->configdata)) {
            $record->configdata = is_string($data->configdata) ? $data->configdata : json_encode($data->configdata);
        }
        if (isset($data->cssclass)) {
            $record->cssclass = dashboard_manager::sanitize_css_classes($data->cssclass);
        }
        if (isset($data->grid_width)) {
            $record->grid_width = max(2, min(12, (int)$data->grid_width));
        }
        if (isset($data->grid_height)) {
            $record->grid_height = max(1, min(12, (int)$data->grid_height));
        }
        if (isset($data->sortorder)) {
            $record->sortorder = (int)$data->sortorder;
        }
        $record->timemodified = time();

        $DB->update_record('local_lms_dashboard_builder_widget', $record);
        self::purge_widget_cache();
        security_guard::log_audit((int)$existing->dashboardid, 'update_widget', ['widgetid' => $widgetid]);

        return true;
    }

    /**
     * Move a widget up or down in the dashboard widget order.
     *
     * @param int $widgetid
     * @param string $direction 'up' or 'down'
     * @return bool
     */
    public static function move_widget(int $widgetid, string $direction): bool {
        global $DB;
        security_guard::require_manage();

        $target = self::get_widget($widgetid);
        if (!$target) {
            return false;
        }

        $dashboardid = (int)$target->dashboardid;
        $widgets = array_values(self::get_widgets_for_dashboard($dashboardid));
        $count = count($widgets);
        if ($count < 2) {
            return true;
        }

        $targetindex = -1;
        foreach ($widgets as $idx => $w) {
            if ((int)$w->id === $widgetid) {
                $targetindex = $idx;
                break;
            }
        }
        if ($targetindex === -1) {
            return false;
        }

        $swapindex = ($direction === 'up') ? $targetindex - 1 : $targetindex + 1;
        if ($swapindex < 0 || $swapindex >= $count) {
            return true;
        }

        // Swap the two elements in the array.
        $temp = $widgets[$targetindex];
        $widgets[$targetindex] = $widgets[$swapindex];
        $widgets[$swapindex] = $temp;

        // Persist normalized 1-based sortorder across all widgets for this dashboard.
        $now = time();
        foreach ($widgets as $pos => $w) {
            $DB->update_record('local_lms_dashboard_builder_widget', (object)[
                'id' => (int)$w->id,
                'sortorder' => $pos + 1,
                'timemodified' => $now,
            ]);
        }

        self::purge_widget_cache();
        security_guard::log_audit($dashboardid, 'move_widget', ['widgetid' => $widgetid, 'direction' => $direction]);
        return true;
    }

    /**
     * Reorder all widgets on a dashboard according to an ordered array of widget IDs.
     *
     * @param int $dashboardid
     * @param array $orderedids
     * @return bool
     */
    public static function reorder_widgets(int $dashboardid, array $orderedids): bool {
        global $DB;
        security_guard::require_manage();

        $existing = self::get_widgets_for_dashboard($dashboardid);
        if (empty($existing)) {
            return true;
        }

        $pos = 1;
        $now = time();
        $seen = [];

        foreach ($orderedids as $rawid) {
            $wid = (int)$rawid;
            if (isset($existing[$wid]) && !isset($seen[$wid])) {
                $DB->update_record('local_lms_dashboard_builder_widget', (object)[
                    'id' => $wid,
                    'sortorder' => $pos++,
                    'timemodified' => $now,
                ]);
                $seen[$wid] = true;
            }
        }

        foreach ($existing as $wid => $w) {
            if (!isset($seen[(int)$wid])) {
                $DB->update_record('local_lms_dashboard_builder_widget', (object)[
                    'id' => (int)$wid,
                    'sortorder' => $pos++,
                    'timemodified' => $now,
                ]);
            }
        }

        self::purge_widget_cache();
        security_guard::log_audit($dashboardid, 'reorder_widgets', ['order' => array_keys($seen)]);
        return true;
    }

    /**
     * Delete a widget by ID.
     *
     * @param int $widgetid
     * @return bool
     */
    public static function delete_widget(int $widgetid): bool {
        global $DB;
        security_guard::require_manage();

        $existing = self::get_widget($widgetid);
        if ($existing) {
            $DB->delete_records('local_lms_dashboard_builder_widget', ['id' => $widgetid]);
            self::purge_widget_cache();
            security_guard::log_audit((int)$existing->dashboardid, 'delete_widget', ['widgetid' => $widgetid]);
        }
        return true;
    }

    /**
     * Purge widget MUC cache when dashboard/widget definitions change.
     */
    public static function purge_widget_cache(): void {
        try {
            $cache = \cache::make('local_lms_dashboard_builder', 'widgetdata');
            $cache->purge();
        } catch (\Throwable $e) {
            // Ignore if cache definition is not initialized in CLI/test bootstrap.
        }
    }
}
