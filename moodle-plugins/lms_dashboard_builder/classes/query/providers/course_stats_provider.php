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
 * Query provider for course-scoped overview dashboards.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_stats_provider extends base_provider {

    public function get_id(): string {
        return 'course_stats';
    }

    public function get_name(): string {
        return get_string('provider_course_stats', 'local_lms_dashboard_builder');
    }

    public function get_description(): string {
        return get_string('provider_course_stats_desc', 'local_lms_dashboard_builder');
    }

    public function get_supported_widgets(): array {
        return ['kpi', 'chart', 'table', 'progress', 'ring', 'comparison', 'status_distribution', 'ranking'];
    }

    public function get_supported_filters(): array {
        return ['daterange', 'custom_dates', 'course', 'category', 'groupby'];
    }

    public function execute(array $filters, \context $context): array {
        global $DB;

        [$starttime, $endtime] = $this->resolve_time_window($filters);
        $courseid = $this->resolve_course_id($filters, $context);

        [$enrolcoursesql, $enrolcourseparams] = $this->build_course_filter_sql('e.courseid', $courseid, 'csc_e', $filters);
        [$compcoursesql, $compcourseparams] = $this->build_course_filter_sql('course', $courseid, 'csc_c', $filters);
        [$modcoursesql, $modcourseparams] = $this->build_course_filter_sql('course', $courseid, 'csc_m', $filters);

        // 1. Enrolled learners.
        $enrolledsql = "SELECT COUNT(DISTINCT ue.userid)
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON e.id = ue.enrolid
                         WHERE {$enrolcoursesql}
                           AND ue.status = :active
                           AND e.status = :enrolenabled";
        $enrolled = (int)$DB->count_records_sql($enrolledsql, array_merge($enrolcourseparams, [
            'active' => ENROL_USER_ACTIVE,
            'enrolenabled' => ENROL_INSTANCE_ENABLED,
        ]));

        // 2. Course completions.
        $completedwhere = "{$compcoursesql} AND timecompleted IS NOT NULL AND timecompleted > 0";
        $completedparams = $compcourseparams;
        if ($starttime > 0) {
            $completedwhere .= ' AND timecompleted >= :starttime';
            $completedparams['starttime'] = $starttime;
        }
        $completedsql = "SELECT COUNT(id)
                           FROM {course_completions}
                          WHERE {$completedwhere}";
        $completed = (int)$DB->count_records_sql($completedsql, $completedparams);
        $completionrate = $enrolled > 0 ? round(($completed / $enrolled) * 100, 1) : 0;

        // 3. Total course modules / activities.
        $modulesql = "SELECT COUNT(id) FROM {course_modules} WHERE {$modcoursesql} AND deletioninprogress = 0";
        $modulecount = (int)$DB->count_records_sql($modulesql, $modcourseparams);

        return [
            'metrics' => [
                'enrolled_students' => $enrolled,
                'completion_rate' => $completionrate,
                'completed_count' => $completed,
                'total_tracked' => $enrolled,
                'total_activities' => $modulecount,
            ],
            'comparison' => [
                'current' => $completed,
                'previous' => $enrolled,
                'label' => 'Completed Learners vs Total Enrolled',
            ],
            'chart' => [
                'labels' => ['Completed', 'In Progress'],
                'datasets' => [
                    [
                        'label' => 'Learner Progress',
                        'data' => [$completed, max(0, $enrolled - $completed)],
                        'backgroundColor' => ['#10b981', '#3b82f6'],
                    ],
                ],
            ],
            'table' => [
                'columns' => ['Metric', 'Current Value'],
                'rows' => [
                    ['name' => 'Active Enrolled Learners', 'val' => $enrolled],
                    ['name' => 'Completed Learners', 'val' => $completed],
                    ['name' => 'Overall Completion Rate', 'val' => $completionrate . '%'],
                    ['name' => 'Published Activities', 'val' => $modulecount],
                ],
            ],
        ];
    }
}
