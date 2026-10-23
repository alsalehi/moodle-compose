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
 * Built-in query provider for public / visitor-safe platform & course KPI metrics.
 *
 * Computes aggregate counts (total courses, active courses, total students, studying students,
 * graduated students, teachers, categories, completions, activities, graduation rate) safely
 * for both public/non-logged-in visitor dashboards and authenticated dashboards.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class public_kpis_provider extends base_provider {

    public function get_id(): string {
        return 'public_kpis';
    }

    public function get_name(): string {
        return get_string('provider_public_kpis', 'local_lms_dashboard_builder');
    }

    public function get_description(): string {
        return get_string('provider_public_kpis_desc', 'local_lms_dashboard_builder');
    }

    public function get_supported_widgets(): array {
        return ['kpi', 'ring', 'progress', 'comparison', 'chart', 'table'];
    }

    public function get_supported_filters(): array {
        return ['daterange', 'categoryid', 'courseid', 'from_context', 'from_request'];
    }

    public function execute(array $filters, \context $context): array {
        global $DB;

        $now = time();
        $coursecontext = ($context instanceof \context_course) ? $context : $context->get_course_context(false);
        $courseid = ($coursecontext instanceof \context_course && (int)$coursecontext->instanceid > 1)
            ? (int)$coursecontext->instanceid
            : (!empty($filters['courseid']) ? (int)$filters['courseid'] : 0);
        $categoryid = !empty($filters['categoryid']) ? (int)$filters['categoryid'] : 0;

        $coursewhere = 'c.id > 1';
        $courseparams = [];
        if ($courseid > 1) {
            $coursewhere .= ' AND c.id = :cid';
            $courseparams['cid'] = $courseid;
        } else if ($categoryid > 0) {
            $coursewhere .= ' AND c.category = :catid';
            $courseparams['catid'] = $categoryid;
        }

        // 1. Total Courses.
        $totalcourses = (int)$DB->count_records_sql(
            "SELECT COUNT(c.id) FROM {course} c WHERE {$coursewhere}",
            $courseparams
        );

        // 2. Active Courses (visible = 1 and startdate <= now and (enddate = 0 or enddate > now)).
        $activecourses = (int)$DB->count_records_sql(
            "SELECT COUNT(c.id)
               FROM {course} c
              WHERE {$coursewhere}
                AND c.visible = 1
                AND (c.startdate = 0 OR c.startdate <= :now1)
                AND (c.enddate = 0 OR c.enddate > :now2)",
            array_merge($courseparams, ['now1' => $now, 'now2' => $now])
        );

        // 3. Total Visible Course Categories.
        $totalcategories = (int)$DB->count_records('course_categories', ['visible' => 1]);

        // 4. Total Students (distinct users holding a student role).
        $totalstudents = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT ra.userid)
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
               JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE (r.archetype = 'student' OR r.shortname = 'student')"
        );

        // 5. Studying Students (currently active enrolled learners in active courses).
        $studyingstudents = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
               JOIN {course} c ON c.id = e.courseid
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE {$coursewhere}
                AND c.visible = 1
                AND ue.status = 0
                AND ue.timestart <= :now1
                AND (ue.timeend = 0 OR ue.timeend > :now2)",
            array_merge($courseparams, ['now1' => $now, 'now2' => $now])
        );

        // If role_assignments was empty in a fresh environment, ensure total_students is at least studying_students.
        if ($totalstudents < $studyingstudents) {
            $totalstudents = $studyingstudents;
        }

        // 6. Graduated Students (distinct learners who have completed at least one course).
        $graduatedstudents = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT cc.userid)
               FROM {course_completions} cc
               JOIN {course} c ON c.id = cc.course
              WHERE {$coursewhere}
                AND cc.timecompleted IS NOT NULL
                AND cc.timecompleted > 0",
            $courseparams
        );

        // 7. Total Course Completions.
        $totalcompletions = (int)$DB->count_records_sql(
            "SELECT COUNT(cc.id)
               FROM {course_completions} cc
               JOIN {course} c ON c.id = cc.course
              WHERE {$coursewhere}
                AND cc.timecompleted IS NOT NULL
                AND cc.timecompleted > 0",
            $courseparams
        );

        // 8. Teachers & Instructors (distinct users with teacher / editingteacher role).
        $totalteachers = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT ra.userid)
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
               JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE (r.archetype IN ('teacher', 'editingteacher') OR r.shortname IN ('teacher', 'editingteacher'))"
        );

        // 9. Total Learning Activities (active course modules).
        $totalactivities = (int)$DB->count_records_sql(
            "SELECT COUNT(cm.id)
               FROM {course_modules} cm
               JOIN {course} c ON c.id = cm.course
              WHERE {$coursewhere}
                AND cm.deletioninprogress = 0",
            $courseparams
        );

        // 10. Graduation Rate (%).
        $denom = max($totalstudents, $studyingstudents);
        $graduationrate = $denom > 0 ? round(($graduatedstudents / $denom) * 100, 1) : 0.0;

        $labels = [
            get_string('kpi_total_courses', 'local_lms_dashboard_builder'),
            get_string('kpi_active_courses', 'local_lms_dashboard_builder'),
            get_string('kpi_total_students', 'local_lms_dashboard_builder'),
            get_string('kpi_studying_students', 'local_lms_dashboard_builder'),
            get_string('kpi_graduated_students', 'local_lms_dashboard_builder'),
            get_string('kpi_total_teachers', 'local_lms_dashboard_builder'),
        ];
        $values = [
            $totalcourses,
            $activecourses,
            $totalstudents,
            $studyingstudents,
            $graduatedstudents,
            $totalteachers,
        ];

        $tablerows = [];
        foreach ($labels as $idx => $lbl) {
            $tablerows[] = [
                'metric' => $lbl,
                'value' => $values[$idx],
            ];
        }

        return [
            'metrics' => [
                'total_courses'      => $totalcourses,
                'active_courses'     => $activecourses,
                'total_categories'   => $totalcategories,
                'total_students'     => $totalstudents,
                'studying_students'  => $studyingstudents,
                'graduated_students' => $graduatedstudents,
                'total_teachers'     => $totalteachers,
                'total_completions'  => $totalcompletions,
                'total_activities'   => $totalactivities,
                'graduation_rate'    => $graduationrate,
                'completion_rate'    => $graduationrate,
            ],
            'chart' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => $this->get_name(),
                        'data' => $values,
                        'backgroundColor' => ['#3b82f6', '#10b981', '#0ea5e9', '#6366f1', '#22c55e', '#f59e0b'],
                    ],
                ],
            ],
            'table' => [
                'columns' => ['KPI Metric', 'Count'],
                'rows' => $tablerows,
            ],
        ];
    }
}
