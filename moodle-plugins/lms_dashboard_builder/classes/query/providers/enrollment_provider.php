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
 * Query provider for enrollment analytics and trends.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollment_provider extends base_provider {

    public function get_id(): string {
        return 'enrollment';
    }

    public function get_name(): string {
        return get_string('provider_enrollment', 'local_lms_dashboard_builder');
    }

    public function get_description(): string {
        return get_string('provider_enrollment_desc', 'local_lms_dashboard_builder');
    }

    public function get_supported_widgets(): array {
        return ['kpi', 'chart', 'table', 'progress', 'ring', 'comparison', 'ranking', 'status_distribution'];
    }

    public function get_supported_filters(): array {
        return ['daterange', 'custom_dates', 'course', 'category', 'status', 'groupby'];
    }

    public function execute(array $filters, \context $context): array {
        global $DB;

        [$starttime, $endtime] = $this->resolve_time_window($filters);
        $courseid = $this->resolve_course_id($filters, $context);

        [$coursesql, $courseparams] = $this->build_course_filter_sql('e.courseid', $courseid, 'enrolc', $filters);

        $enrolstatus = (isset($filters['status']) && $filters['status'] === 'inactive')
            ? ENROL_USER_SUSPENDED
            : ENROL_USER_ACTIVE;

        $params = array_merge([
            'enrolstatus' => $enrolstatus,
            'enrolenabled' => ENROL_INSTANCE_ENABLED,
            'now1' => time(),
            'now2' => time(),
        ], $courseparams);

        $where = "ue.status = :enrolstatus
              AND e.status = :enrolenabled
              AND ue.timestart <= :now1
              AND (ue.timeend = 0 OR ue.timeend > :now2)
              AND {$coursesql}";

        // Total active enrollments.
        $totalsql = "SELECT COUNT(DISTINCT ue.userid)
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                      WHERE {$where}";
        $total = (int)$DB->count_records_sql($totalsql, $params);

        // New enrollments within time window.
        $newparams = $params;
        $newwhere = $where;
        if ($starttime > 0) {
            $newwhere .= ' AND ue.timecreated >= :starttime AND ue.timecreated <= :endtime';
            $newparams['starttime'] = $starttime;
            $newparams['endtime'] = $endtime;
        }
        $newsql = "SELECT COUNT(DISTINCT ue.userid)
                     FROM {user_enrolments} ue
                     JOIN {enrol} e ON e.id = ue.enrolid
                    WHERE {$newwhere}";
        $newcount = (int)$DB->count_records_sql($newsql, $newparams);

        $groupfunc = $filters['groupby'] ?? 'by_course';
        $chartlabels = [];
        $chartvalues = [];
        $tablerows = [];

        if (strpos($groupfunc, 'time_') === 0) {
            $timesql = "SELECT ue.id, ue.timecreated, ue.userid
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON e.id = ue.enrolid
                         WHERE {$newwhere}";
            $rawrows = $DB->get_records_sql($timesql, $newparams, 0, 500);
            $grouped = \local_lms_dashboard_builder\query\grouping_engine::group_and_aggregate_rows(
                $rawrows,
                'timecreated',
                'userid',
                $groupfunc,
                'COUNT_DISTINCT'
            );
            $chartlabels = $grouped['labels'];
            $chartvalues = $grouped['values'];
            $tablerows = $grouped['rows'];
        } else {
            // Distribution by course (top 10 courses).
            $distparams = $params;
            $distsql = "SELECT c.id, c.fullname, c.shortname, COUNT(DISTINCT ue.userid) AS usercount
                          FROM {course} c
                          JOIN {enrol} e ON e.courseid = c.id
                          JOIN {user_enrolments} ue ON ue.enrolid = e.id
                         WHERE {$where} AND c.id > 1
                      GROUP BY c.id, c.fullname, c.shortname
                      ORDER BY usercount DESC";
            $topcourses = $DB->get_records_sql($distsql, $distparams, 0, 10);

            foreach ($topcourses as $c) {
                $chartlabels[] = format_string($c->shortname);
                $chartvalues[] = (int)$c->usercount;
                $tablerows[] = [
                    'name' => format_string($c->fullname),
                    'shortname' => format_string($c->shortname),
                    'count' => (int)$c->usercount,
                ];
            }
        }

        $growthrate = $total > 0 ? round(($newcount / $total) * 100, 1) : 0;

        return [
            'metrics' => [
                'total_enrolled' => $total,
                'new_enrolled' => $newcount,
                'growth_rate' => $growthrate,
                'completion_rate' => $growthrate,
            ],
            'comparison' => [
                'current' => $newcount,
                'previous' => max(0, $total - $newcount),
                'label' => 'New vs Existing Enrollments',
            ],
            'chart' => [
                'labels' => $chartlabels,
                'datasets' => [
                    [
                        'label' => get_string('provider_enrollment', 'local_lms_dashboard_builder'),
                        'data' => $chartvalues,
                        'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'],
                    ],
                ],
            ],
            'table' => [
                'columns' => strpos($groupfunc, 'time_') === 0 ? ['Period', 'Enrollments'] : ['Course', 'Short Name', 'Enrollments'],
                'rows' => $tablerows,
            ],
        ];
    }
}
