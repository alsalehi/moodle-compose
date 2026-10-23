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

namespace local_lms_dashboard_builder\query;

defined('MOODLE_INTERNAL') || die();

/**
 * Extensible Grouping and Aggregation Engine for LMS Dashboard Builder.
 *
 * Provides cross-database time-series bucketing, Moodle entity dimension grouping,
 * numeric/grade band bucketing, custom registered grouping functions, and aggregations.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grouping_engine {

    /** Supported aggregation functions. */
    public const ALLOWED_AGGREGATIONS = [
        'COUNT' => 'Count',
        'COUNT_DISTINCT' => 'Count Distinct',
        'SUM' => 'Sum',
        'AVG' => 'Average',
        'MIN' => 'Minimum',
        'MAX' => 'Maximum',
        'PERCENTAGE_RATE' => 'Percentage Rate (%)',
    ];

    /**
     * Get localized catalog of allowed aggregation functions.
     *
     * @return array
     */
    public static function get_allowed_aggregations(): array {
        return [
            'COUNT' => get_string('agg_count', 'local_lms_dashboard_builder'),
            'COUNT_DISTINCT' => get_string('agg_count_distinct', 'local_lms_dashboard_builder'),
            'SUM' => get_string('agg_sum', 'local_lms_dashboard_builder'),
            'AVG' => get_string('agg_avg', 'local_lms_dashboard_builder'),
            'MIN' => get_string('agg_min', 'local_lms_dashboard_builder'),
            'MAX' => get_string('agg_max', 'local_lms_dashboard_builder'),
            'PERCENTAGE_RATE' => get_string('agg_percentage_rate', 'local_lms_dashboard_builder'),
        ];
    }

    /** @var array Custom developer/admin registered grouping callbacks. */
    private static array $customgroupings = [];

    /**
     * Get all built-in and custom registered grouping functions.
     *
     * @return array Associative array of grouping metadata keyed by grouping ID.
     */
    public static function get_available_groupings(): array {
        $builtins = [
            'by_course' => [
                'id' => 'by_course',
                'label' => get_string('group_by_course', 'local_lms_dashboard_builder'),
                'category' => 'dimension',
                'description' => get_string('group_by_course_desc', 'local_lms_dashboard_builder'),
            ],
            'by_category' => [
                'id' => 'by_category',
                'label' => get_string('group_by_category', 'local_lms_dashboard_builder'),
                'category' => 'dimension',
                'description' => get_string('group_by_category_desc', 'local_lms_dashboard_builder'),
            ],
            'by_role' => [
                'id' => 'by_role',
                'label' => get_string('group_by_role', 'local_lms_dashboard_builder'),
                'category' => 'dimension',
                'description' => get_string('group_by_role_desc', 'local_lms_dashboard_builder'),
            ],
            'by_cohort' => [
                'id' => 'by_cohort',
                'label' => get_string('group_by_cohort', 'local_lms_dashboard_builder'),
                'category' => 'dimension',
                'description' => get_string('group_by_cohort_desc', 'local_lms_dashboard_builder'),
            ],
            'by_group' => [
                'id' => 'by_group',
                'label' => get_string('group_by_group', 'local_lms_dashboard_builder'),
                'category' => 'dimension',
                'description' => get_string('group_by_group_desc', 'local_lms_dashboard_builder'),
            ],
            'by_activity_type' => [
                'id' => 'by_activity_type',
                'label' => get_string('group_by_activity_type', 'local_lms_dashboard_builder'),
                'category' => 'dimension',
                'description' => get_string('group_by_activity_type_desc', 'local_lms_dashboard_builder'),
            ],
            'by_status' => [
                'id' => 'by_status',
                'label' => get_string('group_by_status', 'local_lms_dashboard_builder'),
                'category' => 'dimension',
                'description' => get_string('group_by_status_desc', 'local_lms_dashboard_builder'),
            ],
            'time_day' => [
                'id' => 'time_day',
                'label' => get_string('group_time_day', 'local_lms_dashboard_builder'),
                'category' => 'timeseries',
                'description' => get_string('group_time_day_desc', 'local_lms_dashboard_builder'),
            ],
            'time_week' => [
                'id' => 'time_week',
                'label' => get_string('group_time_week', 'local_lms_dashboard_builder'),
                'category' => 'timeseries',
                'description' => get_string('group_time_week_desc', 'local_lms_dashboard_builder'),
            ],
            'time_month' => [
                'id' => 'time_month',
                'label' => get_string('group_time_month', 'local_lms_dashboard_builder'),
                'category' => 'timeseries',
                'description' => get_string('group_time_month_desc', 'local_lms_dashboard_builder'),
            ],
            'time_quarter' => [
                'id' => 'time_quarter',
                'label' => get_string('group_time_quarter', 'local_lms_dashboard_builder'),
                'category' => 'timeseries',
                'description' => get_string('group_time_quarter_desc', 'local_lms_dashboard_builder'),
            ],
            'time_year' => [
                'id' => 'time_year',
                'label' => get_string('group_time_year', 'local_lms_dashboard_builder'),
                'category' => 'timeseries',
                'description' => get_string('group_time_year_desc', 'local_lms_dashboard_builder'),
            ],
            'by_grade_band' => [
                'id' => 'by_grade_band',
                'label' => get_string('group_by_grade_band', 'local_lms_dashboard_builder'),
                'category' => 'bucket',
                'description' => get_string('group_by_grade_band_desc', 'local_lms_dashboard_builder'),
            ],
            'by_progress_band' => [
                'id' => 'by_progress_band',
                'label' => get_string('group_by_progress_band', 'local_lms_dashboard_builder'),
                'category' => 'bucket',
                'description' => get_string('group_by_progress_band_desc', 'local_lms_dashboard_builder'),
            ],
            'custom_buckets' => [
                'id' => 'custom_buckets',
                'label' => get_string('group_custom_buckets', 'local_lms_dashboard_builder'),
                'category' => 'bucket',
                'description' => get_string('group_custom_buckets_desc', 'local_lms_dashboard_builder'),
            ],
        ];

        foreach (self::$customgroupings as $id => $custom) {
            $builtins[$id] = $custom['meta'];
        }

        return $builtins;
    }

    /**
     * Register a custom grouping function at runtime.
     *
     * @param string $id Unique grouping identifier.
     * @param string $label Human-readable name.
     * @param callable $callback Function accepting ($rawvalue, $row, $config) and returning bucket label string.
     * @param string $description Optional description.
     */
    public static function register_grouping(string $id, string $label, callable $callback, string $description = ''): void {
        $cleanid = clean_param($id, PARAM_ALPHANUMEXT);
        self::$customgroupings[$cleanid] = [
            'meta' => [
                'id' => $cleanid,
                'label' => $label,
                'category' => 'custom',
                'description' => $description,
            ],
            'callback' => $callback,
        ];
    }

    /**
     * Transform a raw value (timestamp, numeric score, status code, or string) into a grouped bucket label.
     *
     * @param string $groupfunc Grouping function identifier (e.g., 'time_month', 'by_grade_band').
     * @param mixed $value Raw field value.
     * @param array $customconfig Optional custom bucket configuration.
     * @return string Formatted bucket label.
     */
    public static function format_bucket(string $groupfunc, $value, array $customconfig = []): string {
        if (isset(self::$customgroupings[$groupfunc]['callback'])) {
            return (string)call_user_func(self::$customgroupings[$groupfunc]['callback'], $value, $customconfig);
        }

        switch ($groupfunc) {
            case 'time_day':
                $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
                return $ts > 0 ? gmdate('Y-m-d', $ts) : 'Unscheduled';

            case 'time_week':
                $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
                return $ts > 0 ? gmdate('o-\WW', $ts) : 'Unscheduled';

            case 'time_month':
                $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
                return $ts > 0 ? gmdate('Y-m', $ts) : 'Unscheduled';

            case 'time_quarter':
                $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
                if ($ts <= 0) {
                    return 'Unscheduled';
                }
                $month = (int)gmdate('n', $ts);
                $quarter = (int)ceil($month / 3);
                return gmdate('Y', $ts) . '-Q' . $quarter;

            case 'time_year':
                $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
                return $ts > 0 ? gmdate('Y', $ts) : 'Unscheduled';

            case 'by_grade_band':
                $score = (float)$value;
                if ($score < 50.0) {
                    return '0-49% (Fail)';
                } else if ($score < 70.0) {
                    return '50-69% (Pass)';
                } else if ($score < 85.0) {
                    return '70-84% (Merit)';
                }
                return '85-100% (Distinction)';

            case 'by_progress_band':
                $pct = (float)$value;
                if ($pct <= 0.0) {
                    return '0% (Not Started)';
                } else if ($pct < 50.0) {
                    return '1-49% (Early Progress)';
                } else if ($pct < 100.0) {
                    return '50-99% (Near Completion)';
                }
                return '100% (Completed)';

            case 'by_status':
                if (is_numeric($value)) {
                    $intval = (int)$value;
                    return $intval === 1 ? 'Completed / Active' : ($intval === 0 ? 'Pending / Inactive' : 'Status ' . $intval);
                }
                return ucfirst(trim((string)$value)) ?: 'Unknown';

            case 'custom_buckets':
                $ranges = $customconfig['buckets'] ?? [];
                if (is_array($ranges) && !empty($ranges) && is_numeric($value)) {
                    $num = (float)$value;
                    foreach ($ranges as $range) {
                        $min = isset($range['min']) ? (float)$range['min'] : -INF;
                        $max = isset($range['max']) ? (float)$range['max'] : INF;
                        if ($num >= $min && $num <= $max) {
                            return (string)($range['label'] ?? "{$min}-{$max}");
                        }
                    }
                }
                return (string)$value ?: 'Other';

            default:
                $str = trim((string)$value);
                return $str !== '' ? $str : 'Uncategorized';
        }
    }

    /**
     * Build a safe SQL aggregation expression for an allowlisted column expression.
     *
     * @param string $aggfunc Aggregation function name (COUNT, COUNT_DISTINCT, SUM, AVG, MIN, MAX).
     * @param string $columnexpression Safe SQL column identifier (e.g., 'ue.userid').
     * @return string SQL expression.
     */
    public static function build_aggregate_sql(string $aggfunc, string $columnexpression): string {
        $agg = strtoupper(trim($aggfunc));
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $columnexpression)) {
            $columnexpression = 'id';
        }

        switch ($agg) {
            case 'COUNT_DISTINCT':
                return "COUNT(DISTINCT {$columnexpression})";
            case 'SUM':
                return "COALESCE(SUM({$columnexpression}), 0)";
            case 'AVG':
                return "COALESCE(AVG({$columnexpression}), 0)";
            case 'MIN':
                return "COALESCE(MIN({$columnexpression}), 0)";
            case 'MAX':
                return "COALESCE(MAX({$columnexpression}), 0)";
            case 'COUNT':
            default:
                return "COUNT({$columnexpression})";
        }
    }

    /**
     * Aggregate an array of numeric or scalar values in PHP using the specified aggregation function.
     *
     * @param array $values Array of raw values in a bucket.
     * @param string $aggfunc Aggregation function (COUNT, COUNT_DISTINCT, SUM, AVG, MIN, MAX, PERCENTAGE_RATE).
     * @param float $totaloverall Optional overall denominator for PERCENTAGE_RATE.
     * @return float|int Aggregated numeric result.
     */
    public static function aggregate_values(array $values, string $aggfunc = 'COUNT', float $totaloverall = 0.0) {
        $agg = strtoupper(trim($aggfunc));
        $count = count($values);
        if ($count === 0) {
            return 0;
        }

        switch ($agg) {
            case 'COUNT_DISTINCT':
                return count(array_unique($values));

            case 'SUM':
                return round(array_sum(array_map('floatval', $values)), 2);

            case 'AVG':
                $sum = array_sum(array_map('floatval', $values));
                return round($sum / $count, 2);

            case 'MIN':
                return round((float)min(array_map('floatval', $values)), 2);

            case 'MAX':
                return round((float)max(array_map('floatval', $values)), 2);

            case 'PERCENTAGE_RATE':
                $sum = array_sum(array_map('floatval', $values));
                $denom = $totaloverall > 0 ? $totaloverall : $count;
                return $denom > 0 ? round(($sum / $denom) * 100.0, 1) : 0;

            case 'COUNT':
            default:
                return $count;
        }
    }

    /**
     * Group and aggregate an array of raw records/rows into standardized chart & table structures.
     *
     * @param array $rows Array of associative arrays or stdClass objects.
     * @param string $dimensionfield Key/property to extract dimension value from.
     * @param string $metricfield Key/property to extract metric value from.
     * @param string $groupfunc Grouping transformation function (e.g. 'time_month', 'by_grade_band', 'by_course').
     * @param string $aggfunc Aggregation function ('COUNT', 'SUM', 'AVG', etc.).
     * @param array $customconfig Optional custom bucket config.
     * @return array ['labels' => string[], 'values' => (int|float)[], 'rows' => array[]]
     */
    public static function group_and_aggregate_rows(
        array $rows,
        string $dimensionfield,
        string $metricfield,
        string $groupfunc = 'by_course',
        string $aggfunc = 'SUM',
        array $customconfig = []
    ): array {
        $buckets = [];
        $overallsum = 0.0;

        foreach ($rows as $row) {
            $arr = (array)$row;
            $rawdim = $arr[$dimensionfield] ?? reset($arr);
            $rawval = isset($arr[$metricfield]) ? $arr[$metricfield] : 1;

            $label = self::format_bucket($groupfunc, $rawdim, $customconfig);
            if (!isset($buckets[$label])) {
                $buckets[$label] = [];
            }
            $buckets[$label][] = $rawval;
            if (is_numeric($rawval)) {
                $overallsum += (float)$rawval;
            }
        }

        // Sort time-series buckets chronologically.
        if (strpos($groupfunc, 'time_') === 0) {
            ksort($buckets);
        }

        $labels = [];
        $values = [];
        $tablerows = [];

        foreach ($buckets as $label => $bucketvals) {
            $aggval = self::aggregate_values($bucketvals, $aggfunc, $overallsum);
            $labels[] = (string)$label;
            $values[] = $aggval;
            $tablerows[] = [
                'group' => (string)$label,
                'value' => $aggval,
            ];
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'rows' => $tablerows,
        ];
    }
}
