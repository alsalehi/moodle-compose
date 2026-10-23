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
 * Built-in query provider for learner progress distributions and upcoming course deadlines.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_progress_provider extends base_provider {

    public function get_id(): string {
        return 'learner_progress';
    }

    public function get_name(): string {
        return get_string('provider_learner_progress', 'local_lms_dashboard_builder');
    }

    public function get_description(): string {
        return get_string('provider_learner_progress_desc', 'local_lms_dashboard_builder');
    }

    public function get_supported_widgets(): array {
        return ['kpi', 'chart', 'table', 'progress', 'ring', 'activity_list', 'status_distribution'];
    }

    public function get_supported_filters(): array {
        return ['daterange', 'custom_dates', 'course', 'category', 'status', 'groupby'];
    }

    public function execute(array $filters, \context $context): array {
        global $DB;

        $courseid = $this->resolve_course_id($filters, $context);
        [$coursesql, $courseparams] = $this->build_course_filter_sql('a.course', $courseid, 'lpc', $filters);

        // Fetch upcoming or recent assignments as course deadlines / activities.
        $assignsql = "SELECT a.id, a.name, a.duedate, c.shortname
                        FROM {assign} a
                        JOIN {course} c ON c.id = a.course
                       WHERE {$coursesql}
                    ORDER BY a.duedate DESC, a.id DESC";
        $assignments = $DB->get_records_sql($assignsql, $courseparams, 0, 8);

        $activities = [];
        $tablerows = [];
        $now = time();
        $upcomingcount = 0;

        foreach ($assignments as $a) {
            $duedate = (int)$a->duedate;
            $isupcoming = $duedate >= $now;
            if ($isupcoming) {
                $upcomingcount++;
            }
            $datestr = $duedate > 0 ? userdate($duedate, '%d %b %Y') : 'No due date';
            $statuslabel = $duedate === 0 ? 'Open' : ($isupcoming ? 'Upcoming' : 'Past Due');
            $activities[] = [
                'title' => format_string($a->name),
                'course' => format_string($a->shortname),
                'date' => $datestr,
                'status' => $statuslabel,
                'badge' => $isupcoming ? 'primary' : 'secondary',
            ];
            $tablerows[] = [
                'activity' => format_string($a->name),
                'course' => format_string($a->shortname),
                'duedate' => $datestr,
                'status' => $statuslabel,
            ];
        }

        // Activity completion breakdown across course modules.
        [$cmcoursesql, $cmcourseparams] = $this->build_course_filter_sql('cm.course', $courseid, 'lpcm', $filters);
        $completedacts = (int)$DB->count_records_sql(
            "SELECT COUNT(cmc.id)
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE {$cmcoursesql} AND cmc.completionstate IN (1, 2)",
            $cmcourseparams
        );
        $incompleteacts = (int)$DB->count_records_sql(
            "SELECT COUNT(cmc.id)
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE {$cmcoursesql} AND cmc.completionstate IN (0, 3)",
            $cmcourseparams
        );
        $totalacts = $completedacts + $incompleteacts;
        $pct = $totalacts > 0 ? round(($completedacts / $totalacts) * 100, 1) : 0;

        return [
            'metrics' => [
                'completion_rate' => $pct,
                'completed_count' => $completedacts,
                'in_progress_count' => $incompleteacts,
                'total_tracked' => $totalacts,
                'upcoming_deadlines' => $upcomingcount,
            ],
            'activities' => $activities,
            'chart' => [
                'labels' => ['Completed Activities', 'In Progress / Incomplete', 'Upcoming Deadlines'],
                'datasets' => [
                    [
                        'label' => 'Learner Progress Overview',
                        'data' => [$completedacts, $incompleteacts, $upcomingcount],
                        'backgroundColor' => ['#10b981', '#f59e0b', '#3b82f6'],
                    ],
                ],
            ],
            'table' => [
                'columns' => ['Activity', 'Course', 'Due Date', 'Status'],
                'rows' => $tablerows,
            ],
        ];
    }
}
