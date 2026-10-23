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

use local_lms_dashboard_builder\security\security_guard;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base class providing common query logic, date parsing, backend context/user resolution, and security isolation.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_provider implements query_interface {

    /**
     * Resolve date range filter into timestamp boundaries [start, end].
     *
     * @param array $filters
     * @return array [starttime, endtime]
     */
    protected function resolve_time_window(array $filters): array {
        $now = time();

        // Explicit custom start/end date filters take precedence when provided.
        if (!empty($filters['datefrom']) || !empty($filters['dateto'])) {
            $start = !empty($filters['datefrom'])
                ? (is_numeric($filters['datefrom']) ? (int)$filters['datefrom'] : (int)strtotime((string)$filters['datefrom']))
                : 0;
            $end = !empty($filters['dateto'])
                ? (is_numeric($filters['dateto']) ? (int)$filters['dateto'] : (int)strtotime((string)$filters['dateto'] . ' 23:59:59'))
                : $now;
            if ($start > 0 && $end >= $start) {
                return [$start, $end];
            }
        }

        $range = $filters['daterange'] ?? '30d';

        switch ($range) {
            case '7d':
                $start = strtotime('-7 days', $now);
                break;
            case '90d':
                $start = strtotime('-90 days', $now);
                break;
            case 'quarter':
                $start = strtotime('-3 months', $now);
                break;
            case 'year':
                $start = strtotime('first day of January this year', $now);
                break;
            case 'all':
                $start = 0;
                break;
            case '30d':
            default:
                $start = strtotime('-30 days', $now);
                break;
        }

        return [$start, $now];
    }

    /**
     * Resolve and strictly validate course constraints for the current user and context.
     *
     * @param array $filters
     * @param \context $context
     * @return int Course ID (0 for all authorized courses).
     */
    protected function resolve_course_id(array $filters, \context $context): int {
        $courseid = isset($filters['courseid']) ? (int)$filters['courseid'] : 0;
        return security_guard::validate_course_context($courseid, $context);
    }

    /**
     * Resolve backend-only context and user attributes strictly from server session ($context and $USER).
     *
     * @param \context $context Active Moodle context.
     * @return array Associative array of resolved backend context and user properties.
     */
    public static function resolve_backend_context_and_user(\context $context): array {
        global $DB, $USER;

        $coursecontext = ($context instanceof \context_course) ? $context : $context->get_course_context(false);
        $contextcourseid = ($coursecontext instanceof \context_course && (int)$coursecontext->instanceid > 1)
            ? (int)$coursecontext->instanceid
            : 0;

        $contextcategoryid = 0;
        if ($context->contextlevel === CONTEXT_COURSECAT) {
            $contextcategoryid = (int)$context->instanceid;
        } else if ($contextcourseid > 1) {
            $courserec = $DB->get_record('course', ['id' => $contextcourseid], 'id, category');
            if ($courserec) {
                $contextcategoryid = (int)$courserec->category;
            }
        }

        $userid = (isloggedin() && !isguestuser()) ? (int)($USER->id ?? 0) : 0;
        $usercohortid = 0;
        $usergroupid = 0;
        $userroleid = 0;
        $enrolledcourseids = [];

        if ($userid > 0) {
            try {
                $cohorts = $DB->get_records('cohort_members', ['userid' => $userid], 'id ASC', 'cohortid', 0, 1);
                if (!empty($cohorts)) {
                    $first = reset($cohorts);
                    $usercohortid = (int)$first->cohortid;
                }
            } catch (\Throwable $e) {
                $usercohortid = 0;
            }

            try {
                $gparams = ['userid' => $userid];
                $gsql = "SELECT gm.groupid
                           FROM {groups_members} gm
                           JOIN {groups} g ON g.id = gm.groupid
                          WHERE gm.userid = :userid";
                if ($contextcourseid > 1) {
                    $gsql .= " AND g.courseid = :cid";
                    $gparams['cid'] = $contextcourseid;
                }
                $groups = $DB->get_records_sql($gsql, $gparams, 0, 1);
                if (!empty($groups)) {
                    $firstg = reset($groups);
                    $usergroupid = (int)$firstg->groupid;
                }
            } catch (\Throwable $e) {
                $usergroupid = 0;
            }

            $roles = get_user_roles($context, $userid, true);
            if (!empty($roles)) {
                $firstr = reset($roles);
                $userroleid = (int)$firstr->roleid;
            }

            if (function_exists('enrol_get_all_users_courses')) {
                $ecourses = enrol_get_all_users_courses($userid, true, 'id');
                foreach ($ecourses as $ec) {
                    $enrolledcourseids[] = (int)$ec->id;
                }
            }
        }

        return [
            'contextid'          => (int)$context->id,
            'contextlevel'       => (int)$context->contextlevel,
            'instanceid'         => (int)$context->instanceid,
            'context_courseid'   => $contextcourseid,
            'context_categoryid' => $contextcategoryid,
            'userid'             => $userid,
            'user_department'    => (string)($USER->department ?? ''),
            'user_institution'   => (string)($USER->institution ?? ''),
            'user_city'          => (string)($USER->city ?? ''),
            'user_country'       => (string)($USER->country ?? ''),
            'user_cohortid'      => $usercohortid,
            'user_groupid'       => $usergroupid,
            'user_roleid'        => $userroleid,
            'user_courseids'     => $enrolledcourseids,
        ];
    }

    /**
     * Build SQL condition and parameters to scope a course column to authorized courses and optional category filter.
     *
     * @param string $coursecolumn SQL column expression (e.g., 'e.courseid' or 'a.course').
     * @param int $courseid Resolved course ID (0 if all authorized courses).
     * @param string $paramprefix Unique parameter prefix.
     * @param array $filters Optional filters array (supports categoryid).
     * @return array [$sqlfragment, $params]
     */
    protected function build_course_filter_sql(
        string $coursecolumn,
        int $courseid,
        string $paramprefix = 'cscope',
        array $filters = []
    ): array {
        global $DB, $USER;

        if ($courseid > 0) {
            return ["{$coursecolumn} = :{$paramprefix}_cid", ["{$paramprefix}_cid" => $courseid]];
        }

        $categoryid = !empty($filters['categoryid']) ? (int)$filters['categoryid'] : 0;

        // Site administrators, dashboard managers, or public visitor KPI queries at system scope can view aggregate platform data.
        $isvisitor = !isloggedin() || isguestuser();
        if ($isvisitor || is_siteadmin() || has_capability('local/lms_dashboard_builder:manage', \context_system::instance())) {
            if ($categoryid > 0) {
                return [
                    "{$coursecolumn} IN (SELECT id FROM {course} WHERE category = :{$paramprefix}_catid AND id > 1)",
                    ["{$paramprefix}_catid" => $categoryid],
                ];
            }
            return ["{$coursecolumn} > 1", []];
        }

        // Regular logged-in users at site/my-dashboard level see courses they are enrolled in (or all visible courses if none).
        $enrolledcourses = enrol_get_all_users_courses($USER->id, true, 'id, category');
        if (empty($enrolledcourses)) {
            if ($categoryid > 0) {
                return [
                    "{$coursecolumn} IN (SELECT id FROM {course} WHERE category = :{$paramprefix}_catid AND id > 1 AND visible = 1)",
                    ["{$paramprefix}_catid" => $categoryid],
                ];
            }
            return ["{$coursecolumn} > 1", []];
        }

        $courseids = [];
        foreach ($enrolledcourses as $ec) {
            if ($categoryid > 0 && (int)($ec->category ?? 0) !== $categoryid) {
                continue;
            }
            $courseids[] = (int)$ec->id;
        }

        if (empty($courseids)) {
            return ['1 = 0', []];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, $paramprefix . '_');
        return ["{$coursecolumn} {$insql}", $inparams];
    }
}
