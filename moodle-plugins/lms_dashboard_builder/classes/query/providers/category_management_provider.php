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
 * Built-in query provider for category and role-level management summaries.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_management_provider extends base_provider {

    public function get_id(): string {
        return 'category_management';
    }

    public function get_name(): string {
        return get_string('provider_category_management', 'local_lms_dashboard_builder');
    }

    public function get_description(): string {
        return get_string('provider_category_management_desc', 'local_lms_dashboard_builder');
    }

    public function get_supported_widgets(): array {
        return ['kpi', 'chart', 'table', 'ranking', 'status_distribution', 'comparison'];
    }

    public function get_supported_filters(): array {
        return ['category', 'course', 'role', 'groupby'];
    }

    public function execute(array $filters, \context $context): array {
        global $DB;

        $courseid = $this->resolve_course_id($filters, $context);
        [$coursesql, $courseparams] = $this->build_course_filter_sql('c.id', $courseid, 'catm', $filters);

        $catsql = "SELECT cc.id, cc.name, COUNT(DISTINCT c.id) AS coursecount, COUNT(DISTINCT ue.userid) AS usercount
                     FROM {course_categories} cc
                LEFT JOIN {course} c ON c.category = cc.id AND c.id > 1 AND {$coursesql}
                LEFT JOIN {enrol} e ON e.courseid = c.id
                LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id
                 GROUP BY cc.id, cc.name
                 ORDER BY coursecount DESC, cc.name ASC";

        $records = $DB->get_records_sql($catsql, $courseparams, 0, 10);

        $labels = [];
        $coursevals = [];
        $uservals = [];
        $tablerows = [];
        $totalcourses = 0;
        $totalcategories = count($records);

        foreach ($records as $r) {
            $catname = format_string($r->name);
            $ccount = (int)$r->coursecount;
            $ucount = (int)$r->usercount;
            $totalcourses += $ccount;

            $labels[] = $catname;
            $coursevals[] = $ccount;
            $uservals[] = $ucount;
            $tablerows[] = [
                'category' => $catname,
                'courses' => $ccount,
                'enrollments' => $ucount,
            ];
        }

        return [
            'metrics' => [
                'total_categories' => $totalcategories,
                'total_courses' => $totalcourses,
                'avg_courses_per_category' => $totalcategories > 0 ? round($totalcourses / $totalcategories, 1) : 0,
            ],
            'chart' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Courses',
                        'data' => $coursevals,
                        'backgroundColor' => '#3b82f6',
                    ],
                    [
                        'label' => 'Enrolled Learners',
                        'data' => $uservals,
                        'backgroundColor' => '#10b981',
                    ],
                ],
            ],
            'table' => [
                'columns' => ['Category', 'Courses', 'Enrolled Learners'],
                'rows' => $tablerows,
            ],
        ];
    }
}
