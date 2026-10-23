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

namespace local_lms_dashboard_builder\query\providers;

use local_lms_dashboard_builder\query\base_provider;
use local_lms_dashboard_builder\query\grouping_engine;
use local_lms_dashboard_builder\security\security_guard;

defined('MOODLE_INTERNAL') || die();

/**
 * Query provider executing administrator-approved read-only parameterized SQL queries.
 *
 * Supports backend-only context parameters (:contextid, :context_courseid, :context_categoryid)
 * and user parameters (:userid, :user_department, :user_institution, :user_cohortid, :user_groupid, :user_roleid).
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_sql_provider extends base_provider {

    /** @var \stdClass Database record from local_lms_dashboard_builder_query. */
    private \stdClass $record;

    /**
     * Constructor.
     *
     * @param \stdClass $record Query database record.
     */
    public function __construct(\stdClass $record) {
        $this->record = $record;
    }

    public function get_id(): string {
        return 'custom_' . (int)$this->record->id;
    }

    public function get_name(): string {
        return format_string($this->record->name);
    }

    public function get_description(): string {
        return get_string('query_type_sql', 'local_lms_dashboard_builder');
    }

    public function get_supported_widgets(): array {
        return ['kpi', 'chart', 'table', 'progress', 'ring', 'comparison', 'activity_list', 'status_distribution', 'ranking'];
    }

    public function get_supported_filters(): array {
        return ['daterange', 'custom_dates', 'course', 'category', 'role', 'cohort', 'group', 'status', 'groupby', 'from_context', 'from_user', 'from_request', 'custom'];
    }

    public function execute(array $filters, \context $context): array {
        global $DB;

        $sql = trim((string)($this->record->customsql ?? ''));
        security_guard::validate_safe_sql($sql);

        $def = !empty($this->record->querydefinition) ? (json_decode($this->record->querydefinition, true) ?: []) : [];
        $backend = self::resolve_backend_context_and_user($context);

        $contextfilter = $def['context_filter'] ?? 'none';
        $userfilter = $def['user_filter'] ?? 'none';

        if ($contextfilter === 'current_course' && $backend['context_courseid'] > 1) {
            $filters['courseid'] = $backend['context_courseid'];
        } else if ($contextfilter === 'current_category' && $backend['context_categoryid'] > 0) {
            $filters['categoryid'] = $backend['context_categoryid'];
        }

        [$starttime, $endtime] = $this->resolve_time_window($filters);
        $courseid = $this->resolve_course_id($filters, $context);

        $resolveduserid = $backend['userid'];
        $resolvedcohortid = ($userfilter === 'user_cohort' && $backend['user_cohortid'] > 0)
            ? $backend['user_cohortid']
            : (int)($filters['cohortid'] ?? $backend['user_cohortid']);
        $resolvedgroupid = ($userfilter === 'user_group' && $backend['user_groupid'] > 0)
            ? $backend['user_groupid']
            : (int)($filters['groupid'] ?? $backend['user_groupid']);

        $availableparams = [
            'courseid'           => $courseid,
            'categoryid'         => (int)($filters['categoryid'] ?? $backend['context_categoryid']),
            'starttime'          => $starttime,
            'endtime'            => $endtime,
            'contextid'          => $backend['contextid'],
            'context_courseid'   => $backend['context_courseid'],
            'context_categoryid' => $backend['context_categoryid'],
            'userid'             => $resolveduserid,
            'user_department'    => $backend['user_department'],
            'user_institution'   => $backend['user_institution'],
            'user_city'          => $backend['user_city'],
            'user_country'       => $backend['user_country'],
            'user_cohortid'      => $backend['user_cohortid'],
            'user_groupid'       => $backend['user_groupid'],
            'user_roleid'        => $backend['user_roleid'],
            'roleid'             => (int)($filters['roleid'] ?? $backend['user_roleid']),
            'groupid'            => $resolvedgroupid,
            'cohortid'           => $resolvedcohortid,
            'status'             => (string)($filters['status'] ?? ''),
        ];

        // Bind any custom/request filters passed in $filters prefixed with filter_.
        foreach ($filters as $k => $v) {
            if (strpos($k, 'filter_') === 0 && is_scalar($v)) {
                $availableparams[$k] = clean_param((string)$v, PARAM_TEXT);
                $shortkey = substr($k, 7);
                if ($shortkey !== '' && !isset($availableparams[$shortkey])) {
                    $availableparams[$shortkey] = clean_param((string)$v, PARAM_TEXT);
                }
            }
        }

        // Only bind parameters that are actually referenced as :paramname in the SQL string.
        $boundparams = [];
        foreach ($availableparams as $key => $val) {
            if (preg_match('/:' . preg_quote($key, '/') . '\b/', $sql)) {
                $boundparams[$key] = $val;
            }
        }

        $records = $DB->get_records_sql($sql, $boundparams, 0, 100);

        $columns = [];
        $tablerows = [];
        $labels = [];
        $seriesmap = [];
        $metrics = [];

        foreach ($records as $rec) {
            $arr = (array)$rec;
            if (empty($columns)) {
                $columns = array_keys($arr);
            }
            $tablerows[] = $arr;

            $keys = array_keys($arr);
            $firstval = (string)($arr[$keys[0]] ?? '');
            $labels[] = $firstval;

            // Collect all numeric columns after the first column as chart series.
            for ($i = 1; $i < count($keys); $i++) {
                $col = $keys[$i];
                if (is_numeric($arr[$col])) {
                    if (!isset($seriesmap[$col])) {
                        $seriesmap[$col] = [];
                    }
                    $seriesmap[$col][] = round((float)$arr[$col], 2);
                }
            }

            // If single-row query, expose all numeric columns directly as KPI metrics.
            if (count($records) === 1) {
                foreach ($arr as $col => $val) {
                    if (is_numeric($val)) {
                        $metrics[$col] = round((float)$val, 2);
                    }
                }
            }
        }

        // Optional post-query grouping override if requested via filter or query definition.
        $groupfunc = $filters['groupby'] ?? ($def['groupfunc'] ?? '');
        if (!empty($groupfunc) && count($columns) >= 2 && !empty($tablerows)) {
            $grouped = grouping_engine::group_and_aggregate_rows(
                $tablerows,
                $columns[0],
                $columns[1],
                $groupfunc,
                $def['aggregation'] ?? 'SUM',
                $def
            );
            $labels = $grouped['labels'];
            $seriesmap = [$columns[1] => $grouped['values']];
            $tablerows = $grouped['rows'];
            $columns = ['Group', $columns[1]];
        }

        $palette = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];
        $datasets = [];
        $sidx = 0;
        foreach ($seriesmap as $seriesname => $seriesvalues) {
            $datasets[] = [
                'label' => ucfirst(str_replace('_', ' ', $seriesname)),
                'data' => $seriesvalues,
                'backgroundColor' => count($seriesmap) === 1 ? $palette : $palette[$sidx % count($palette)],
            ];
            if (empty($metrics[$seriesname])) {
                $metrics[$seriesname] = array_sum($seriesvalues);
            }
            $sidx++;
        }

        if (empty($metrics)) {
            $metrics['row_count'] = count($tablerows);
        }

        return [
            'metrics' => $metrics,
            'chart' => [
                'labels' => $labels,
                'datasets' => $datasets,
            ],
            'table' => [
                'columns' => $columns,
                'rows' => $tablerows,
            ],
        ];
    }
}
