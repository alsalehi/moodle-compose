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
 * Query provider for assessment grading, submissions, and performance distribution.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assessment_provider extends base_provider {

    public function get_id(): string {
        return 'assessment';
    }

    public function get_name(): string {
        return get_string('provider_assessment', 'local_lms_dashboard_builder');
    }

    public function get_description(): string {
        return get_string('provider_assessment_desc', 'local_lms_dashboard_builder');
    }

    public function get_supported_widgets(): array {
        return ['kpi', 'chart', 'table', 'progress', 'ring', 'comparison', 'status_distribution', 'ranking'];
    }

    public function get_supported_filters(): array {
        return ['daterange', 'custom_dates', 'course', 'category', 'status', 'groupby'];
    }

    public function execute(array $filters, \context $context): array {
        global $DB;

        [$starttime, $endtime] = $this->resolve_time_window($filters);
        $courseid = $this->resolve_course_id($filters, $context);

        [$coursesql, $courseparams] = $this->build_course_filter_sql('a.course', $courseid, 'assc', $filters);
        $params = $courseparams;
        $where = "s.status = 'submitted' AND s.latest = 1 AND {$coursesql}";

        if ($starttime > 0) {
            $where .= ' AND s.timemodified >= :starttime';
            $params['starttime'] = $starttime;
        }

        // Submissions count.
        $subsql = "SELECT COUNT(s.id)
                     FROM {assign_submission} s
                     JOIN {assign} a ON a.id = s.assignment
                    WHERE {$where}";
        $totalsubmissions = (int)$DB->count_records_sql($subsql, $params);

        // Graded submissions matching the same submission cohort.
        $gradedsql = "SELECT COUNT(s.id)
                        FROM {assign_submission} s
                        JOIN {assign} a ON a.id = s.assignment
                        JOIN {assign_grades} g
                          ON g.assignment = s.assignment
                         AND g.userid = s.userid
                         AND g.attemptnumber = s.attemptnumber
                       WHERE {$where} AND g.grade IS NOT NULL AND g.grade >= 0";
        $gradedcount = (int)$DB->count_records_sql($gradedsql, $params);

        $pending = max(0, $totalsubmissions - $gradedcount);

        // Average normalized percentage grade across graded submissions in context (for point-based assignments a.grade > 0).
        $avgsql = "SELECT AVG((g.grade / a.grade) * 100.0)
                     FROM {assign_submission} s
                     JOIN {assign} a ON a.id = s.assignment
                     JOIN {assign_grades} g
                       ON g.assignment = s.assignment
                      AND g.userid = s.userid
                      AND g.attemptnumber = s.attemptnumber
                    WHERE {$where} AND g.grade IS NOT NULL AND g.grade >= 0 AND a.grade > 0";
        $rawavg = $DB->get_field_sql($avgsql, $params);
        $averagegrade = $rawavg !== false && $rawavg !== null ? round((float)$rawavg, 1) : 0;

        $chartlabels = ['Graded', 'Pending Review'];
        $chartdata = [$gradedcount, $pending];
        $chartcolors = ['#10b981', '#ef4444'];

        if (($filters['groupby'] ?? '') === 'by_grade_band') {
            $bandsql = "SELECT g.id, ((g.grade / a.grade) * 100.0) AS pctgrade
                          FROM {assign_submission} s
                          JOIN {assign} a ON a.id = s.assignment
                          JOIN {assign_grades} g
                            ON g.assignment = s.assignment
                           AND g.userid = s.userid
                           AND g.attemptnumber = s.attemptnumber
                         WHERE {$where} AND g.grade IS NOT NULL AND g.grade >= 0 AND a.grade > 0";
            $graderows = $DB->get_records_sql($bandsql, $params, 0, 500);
            $grouped = \local_lms_dashboard_builder\query\grouping_engine::group_and_aggregate_rows(
                $graderows,
                'pctgrade',
                'id',
                'by_grade_band',
                'COUNT'
            );
            if (!empty($grouped['labels'])) {
                $chartlabels = $grouped['labels'];
                $chartdata = $grouped['values'];
                $chartcolors = ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
            }
        }

        return [
            'metrics' => [
                'total_submissions' => $totalsubmissions,
                'graded_count' => $gradedcount,
                'pending_grading' => $pending,
                'average_grade' => $averagegrade,
                'completion_rate' => $totalsubmissions > 0 ? round(($gradedcount / $totalsubmissions) * 100, 1) : 0,
            ],
            'comparison' => [
                'current' => $gradedcount,
                'previous' => $totalsubmissions,
                'label' => 'Graded vs Total Submissions',
            ],
            'chart' => [
                'labels' => $chartlabels,
                'datasets' => [
                    [
                        'label' => 'Submissions Status',
                        'data' => $chartdata,
                        'backgroundColor' => $chartcolors,
                    ],
                ],
            ],
            'table' => [
                'columns' => ['Metric', 'Count'],
                'rows' => [
                    ['name' => 'Total Submissions', 'count' => $totalsubmissions],
                    ['name' => 'Graded', 'count' => $gradedcount],
                    ['name' => 'Pending Review', 'count' => $pending],
                    ['name' => 'Average Grade', 'count' => $averagegrade . '%'],
                ],
            ],
        ];
    }
}
