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
use local_lms_dashboard_builder\query\query_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Query provider executing Visual Query Builder definitions safely via Moodle DML.
 *
 * Supports backend-only context filters (from_context) and user filters (from_user).
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class visual_query_provider extends base_provider {

    /** @var \stdClass Database record from local_lms_dashboard_builder_query. */
    private \stdClass $record;

    /** @var array Decoded querydefinition JSON. */
    private array $definition;

    /**
     * Constructor.
     *
     * @param \stdClass $record Query database record.
     */
    public function __construct(\stdClass $record) {
        $this->record = $record;
        $this->definition = !empty($record->querydefinition)
            ? (json_decode($record->querydefinition, true) ?: [])
            : [];
    }

    public function get_id(): string {
        return 'custom_' . (int)$this->record->id;
    }

    public function get_name(): string {
        return format_string($this->record->name);
    }

    public function get_description(): string {
        $source = $this->definition['source'] ?? 'course';
        $agg = $this->definition['aggregation'] ?? 'COUNT';
        $group = $this->definition['groupfunc'] ?? 'by_course';
        return "Visual Query on {$source} ({$agg}, grouped by {$group})";
    }

    public function get_supported_widgets(): array {
        return ['kpi', 'chart', 'table', 'progress', 'ring', 'comparison', 'status_distribution', 'ranking'];
    }

    public function get_supported_filters(): array {
        return ['daterange', 'custom_dates', 'course', 'category', 'status', 'groupby', 'from_context', 'from_user', 'from_request'];
    }

    public function execute(array $filters, \context $context): array {
        global $DB;

        $catalog = query_manager::get_visual_sources_schema();
        $sourcekey = $this->definition['source'] ?? 'course';
        if (!isset($catalog[$sourcekey])) {
            $sourcekey = 'course';
        }
        $schema = $catalog[$sourcekey];

        // Validate dimension and metric columns against schema allowlist.
        $alloweddims = array_keys($schema['dimensions']);
        $allowedmetrics = array_keys($schema['metrics']);

        $dimensioncol = $this->definition['dimension'] ?? $alloweddims[0];
        if (!in_array($dimensioncol, $alloweddims, true)) {
            $dimensioncol = $alloweddims[0];
        }

        $metriccol = $this->definition['metric'] ?? $allowedmetrics[0];
        if (!in_array($metriccol, $allowedmetrics, true)) {
            $metriccol = $allowedmetrics[0];
        }

        $aggfunc = strtoupper($this->definition['aggregation'] ?? 'COUNT');
        if (!isset(grouping_engine::ALLOWED_AGGREGATIONS[$aggfunc])) {
            $aggfunc = 'COUNT';
        }

        $groupfunc = !empty($filters['groupby']) ? $filters['groupby'] : ($this->definition['groupfunc'] ?? 'by_course');
        $sortdir = strtoupper($this->definition['sortdir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $limit = max(1, min(100, (int)($this->definition['limit'] ?? 20)));

        // Resolve backend-only context and user filters configured on this query definition.
        $backend = self::resolve_backend_context_and_user($context);
        $contextfilter = $this->definition['context_filter'] ?? 'none';
        $userfilter = $this->definition['user_filter'] ?? 'none';

        if ($contextfilter === 'current_course' && $backend['context_courseid'] > 1) {
            $filters['courseid'] = $backend['context_courseid'];
        } else if ($contextfilter === 'current_category' && $backend['context_categoryid'] > 0) {
            $filters['categoryid'] = $backend['context_categoryid'];
        }

        [$starttime, $endtime] = $this->resolve_time_window($filters);
        $courseid = $this->resolve_course_id($filters, $context);
        [$coursesql, $courseparams] = $this->build_course_filter_sql($schema['coursecol'], $courseid, 'visc', $filters);

        $where = $schema['basewhere'] . " AND {$coursesql}";
        $params = $courseparams;

        // Enforce backend-only user filter on user-bearing tables.
        $usercol = $schema['usercol'] ?? '';
        if (empty($usercol)) {
            foreach ($allowedmetrics as $mc) {
                if (strpos($mc, '.userid') !== false) {
                    $usercol = $mc;
                    break;
                }
            }
        }

        if ($userfilter === 'current_user' && !empty($usercol) && $backend['userid'] > 0) {
            $where .= " AND {$usercol} = :vis_backend_uid";
            $params['vis_backend_uid'] = $backend['userid'];
        } else if ($userfilter === 'user_enrolled_courses' && !empty($backend['user_courseids'])) {
            [$ucsql, $ucparams] = $DB->get_in_or_equal($backend['user_courseids'], SQL_PARAMS_NAMED, 'vis_uc_');
            $where .= " AND {$schema['coursecol']} {$ucsql}";
            $params = array_merge($params, $ucparams);
        } else if ($userfilter === 'user_cohort' && !empty($usercol) && $backend['user_cohortid'] > 0) {
            $where .= " AND {$usercol} IN (SELECT userid FROM {cohort_members} WHERE cohortid = :vis_backend_cohort)";
            $params['vis_backend_cohort'] = $backend['user_cohortid'];
        } else if ($userfilter === 'user_group' && !empty($usercol) && $backend['user_groupid'] > 0) {
            $where .= " AND {$usercol} IN (SELECT userid FROM {groups_members} WHERE groupid = :vis_backend_group)";
            $params['vis_backend_group'] = $backend['user_groupid'];
        } else if (!empty($filters['userid']) && !empty($usercol)) {
            $where .= " AND {$usercol} = :vis_filter_uid";
            $params['vis_filter_uid'] = (int)$filters['userid'];
        }

        if ($starttime > 0 && !empty($schema['timecol'])) {
            $where .= " AND {$schema['timecol']} >= :vis_start AND {$schema['timecol']} <= :vis_end";
            $params['vis_start'] = $starttime;
            $params['vis_end'] = $endtime;
        }

        // If grouping by a time bucket or grade/progress band, fetch rows and bucket via grouping_engine.
        $needsphpbucketing = (strpos($groupfunc, 'time_') === 0 || in_array($groupfunc, ['by_grade_band', 'by_progress_band', 'custom_buckets'], true));

        if ($needsphpbucketing) {
            $sql = "SELECT {$metriccol} AS recordid, {$dimensioncol} AS dimval, {$metriccol} AS metricval
                      FROM {$schema['from']}
                     WHERE {$where}";
            $rawrows = $DB->get_records_sql($sql, $params, 0, 500);
            $grouped = grouping_engine::group_and_aggregate_rows(
                $rawrows,
                'dimval',
                'metricval',
                $groupfunc,
                $aggfunc,
                $this->definition
            );
            $labels = array_slice($grouped['labels'], 0, $limit);
            $values = array_slice($grouped['values'], 0, $limit);
            $tablerows = array_slice($grouped['rows'], 0, $limit);
        } else {
            $aggsql = grouping_engine::build_aggregate_sql($aggfunc, $metriccol);
            $sql = "SELECT {$dimensioncol} AS dimval, {$aggsql} AS aggval
                      FROM {$schema['from']}
                     WHERE {$where}
                  GROUP BY {$dimensioncol}
                  ORDER BY aggval {$sortdir}";
            $records = $DB->get_records_sql($sql, $params, 0, $limit);

            $labels = [];
            $values = [];
            $tablerows = [];
            foreach ($records as $rec) {
                $lbl = grouping_engine::format_bucket($groupfunc, $rec->dimval, $this->definition);
                $val = is_numeric($rec->aggval) ? round((float)$rec->aggval, 2) : 0;
                $labels[] = $lbl;
                $values[] = $val;
                $tablerows[] = [
                    'group' => $lbl,
                    'value' => $val,
                ];
            }
        }

        $total = array_sum($values);
        $count = count($values);
        $max = $count > 0 ? max($values) : 0;
        $avg = $count > 0 ? round($total / $count, 1) : 0;

        return [
            'metrics' => [
                'total_value' => round($total, 1),
                'group_count' => $count,
                'average_value' => $avg,
                'peak_value' => $max,
                'completion_rate' => $max > 0 ? min(100, round(($avg / $max) * 100, 1)) : 0,
            ],
            'chart' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => $this->get_name(),
                        'data' => $values,
                        'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'],
                    ],
                ],
            ],
            'table' => [
                'columns' => [$schema['dimensions'][$dimensioncol] ?? 'Dimension', $aggfunc],
                'rows' => $tablerows,
            ],
        ];
    }
}
