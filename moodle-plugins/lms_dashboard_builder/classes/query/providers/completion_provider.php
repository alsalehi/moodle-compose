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

defined('MOODLE_INTERNAL') || die();

/**
 * Query provider for course and activity completion tracking.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_provider extends base_provider {

    public function get_id(): string {
        return 'completion';
    }

    public function get_name(): string {
        return get_string('provider_completion', 'local_lms_dashboard_builder');
    }

    public function get_description(): string {
        return get_string('provider_completion_desc', 'local_lms_dashboard_builder');
    }

    public function get_supported_widgets(): array {
        return ['kpi', 'chart', 'progress', 'ring', 'table', 'comparison', 'status_distribution', 'ranking'];
    }

    public function get_supported_filters(): array {
        return ['daterange', 'custom_dates', 'course', 'category', 'status', 'groupby'];
    }

    public function execute(array $filters, \context $context): array {
        global $DB;

        [$starttime, $endtime] = $this->resolve_time_window($filters);
        $courseid = $this->resolve_course_id($filters, $context);

        [$coursesql, $courseparams] = $this->build_course_filter_sql('cc.course', $courseid, 'compc', $filters);
        $params = $courseparams;
        $where = $coursesql;

        // Completed courses count.
        $completedparams = $params;
        $completedwhere = $where . ' AND cc.timecompleted IS NOT NULL AND cc.timecompleted > 0';
        if ($starttime > 0) {
            $completedwhere .= ' AND cc.timecompleted >= :starttime';
            $completedparams['starttime'] = $starttime;
        }

        $completedsql = "SELECT COUNT(cc.id) FROM {course_completions} cc WHERE {$completedwhere}";
        $completed = (int)$DB->count_records_sql($completedsql, $completedparams);

        // Total tracked completions (in progress + completed).
        $totalsql = "SELECT COUNT(cc.id) FROM {course_completions} cc WHERE {$where}";
        $total = (int)$DB->count_records_sql($totalsql, $params);

        $inprogress = max(0, $total - $completed);
        $rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        return [
            'metrics' => [
                'completion_rate' => $rate,
                'completed_count' => $completed,
                'in_progress_count' => $inprogress,
                'total_tracked' => $total,
            ],
            'comparison' => [
                'current' => $completed,
                'previous' => $total,
                'label' => 'Completed vs Total Tracked',
            ],
            'chart' => [
                'labels' => ['Completed', 'In Progress'],
                'datasets' => [
                    [
                        'label' => 'Status Distribution',
                        'data' => [$completed, $inprogress],
                        'backgroundColor' => ['#10b981', '#f59e0b'],
                    ],
                ],
            ],
            'table' => [
                'columns' => ['Metric', 'Value'],
                'rows' => [
                    ['name' => 'Completion Rate', 'value' => $rate . '%'],
                    ['name' => 'Completed Learners', 'value' => $completed],
                    ['name' => 'In Progress', 'value' => $inprogress],
                    ['name' => 'Total Enrolled Learners', 'value' => $total],
                ],
            ],
        ];
    }
}
