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
 * Manager for custom saved queries (Visual Query Builder and Authorized Custom SQL).
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_manager {

    /** Query type visual builder. */
    public const TYPE_VISUAL = 'visual';

    /** Query type custom SQL. */
    public const TYPE_CUSTOM_SQL = 'custom_sql';

    /**
     * Allowlisted Moodle schema catalog for the Visual Query Builder.
     *
     * @return array
     */
    public static function get_visual_sources_schema(): array {
        return [
            'course' => [
                'label' => 'Courses & Categories ({course})',
                'from' => '{course} c LEFT JOIN {course_categories} cc ON cc.id = c.category',
                'coursecol' => 'c.id',
                'timecol' => 'c.timecreated',
                'basewhere' => 'c.id > 1',
                'dimensions' => [
                    'cc.name' => 'Category Name',
                    'c.shortname' => 'Course Short Name',
                    'c.fullname' => 'Course Full Name',
                    'c.format' => 'Course Format',
                    'c.visible' => 'Course Visibility (1=Visible, 0=Hidden)',
                    'c.timecreated' => 'Course Created Date (Timestamp)',
                ],
                'metrics' => [
                    'c.id' => 'Course ID',
                    'c.startdate' => 'Course Start Date',
                ],
            ],
            'user_enrolments' => [
                'label' => 'Course Enrollments ({user_enrolments})',
                'from' => '{user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid JOIN {course} c ON c.id = e.courseid LEFT JOIN {course_categories} cc ON cc.id = c.category',
                'coursecol' => 'e.courseid',
                'timecol' => 'ue.timecreated',
                'basewhere' => 'c.id > 1',
                'dimensions' => [
                    'c.shortname' => 'Course Short Name',
                    'c.fullname' => 'Course Full Name',
                    'cc.name' => 'Course Category',
                    'e.enrol' => 'Enrollment Method (manual, self, cohort, etc.)',
                    'ue.status' => 'Enrollment Status (0=Active, 1=Suspended)',
                    'ue.timecreated' => 'Enrollment Date (Timestamp)',
                ],
                'metrics' => [
                    'ue.id' => 'Enrollment Record ID',
                    'ue.userid' => 'Enrolled User ID',
                ],
            ],
            'course_completions' => [
                'label' => 'Course Completions ({course_completions})',
                'from' => '{course_completions} comp JOIN {course} c ON c.id = comp.course LEFT JOIN {course_categories} cc ON cc.id = c.category',
                'coursecol' => 'comp.course',
                'timecol' => 'comp.timecompleted',
                'basewhere' => 'c.id > 1',
                'dimensions' => [
                    'c.shortname' => 'Course Short Name',
                    'cc.name' => 'Course Category',
                    'comp.timecompleted' => 'Completion Timestamp',
                ],
                'metrics' => [
                    'comp.id' => 'Completion ID',
                    'comp.userid' => 'Learner User ID',
                ],
            ],
            'course_modules_completion' => [
                'label' => 'Activity Module Completions ({course_modules_completion})',
                'from' => '{course_modules_completion} cmc JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid JOIN {modules} m ON m.id = cm.module JOIN {course} c ON c.id = cm.course',
                'coursecol' => 'cm.course',
                'timecol' => 'cmc.timemodified',
                'basewhere' => 'cm.deletioninprogress = 0',
                'dimensions' => [
                    'm.name' => 'Activity Module Type (assign, quiz, forum, etc.)',
                    'c.shortname' => 'Course Short Name',
                    'cmc.completionstate' => 'Completion State (0=Incomplete, 1=Complete, 2=Pass, 3=Fail)',
                    'cmc.timemodified' => 'Activity Completion Timestamp',
                ],
                'metrics' => [
                    'cmc.id' => 'Activity Completion ID',
                    'cmc.userid' => 'User ID',
                ],
            ],
            'assign_submission' => [
                'label' => 'Assignment Submissions & Grades ({assign_submission})',
                'from' => '{assign_submission} s JOIN {assign} a ON a.id = s.assignment JOIN {course} c ON c.id = a.course LEFT JOIN {assign_grades} ag ON ag.assignment = s.assignment AND ag.userid = s.userid AND ag.attemptnumber = s.attemptnumber',
                'coursecol' => 'a.course',
                'timecol' => 's.timemodified',
                'basewhere' => 's.latest = 1',
                'dimensions' => [
                    'a.name' => 'Assignment Name',
                    'c.shortname' => 'Course Short Name',
                    's.status' => 'Submission Status (submitted, draft, new)',
                    's.timemodified' => 'Submission Timestamp',
                ],
                'metrics' => [
                    's.id' => 'Submission ID',
                    's.userid' => 'Student User ID',
                    'ag.grade' => 'Assignment Raw Grade',
                ],
            ],
            'quiz_attempts' => [
                'label' => 'Quiz Attempts & Scores ({quiz_attempts})',
                'from' => '{quiz_attempts} qa JOIN {quiz} q ON q.id = qa.quiz JOIN {course} c ON c.id = q.course',
                'coursecol' => 'q.course',
                'timecol' => 'qa.timefinish',
                'basewhere' => 'qa.preview = 0',
                'dimensions' => [
                    'q.name' => 'Quiz Name',
                    'c.shortname' => 'Course Short Name',
                    'qa.state' => 'Attempt State (finished, inprogress, overdue)',
                    'qa.timefinish' => 'Finish Timestamp',
                ],
                'metrics' => [
                    'qa.id' => 'Attempt ID',
                    'qa.userid' => 'Student User ID',
                    'qa.sumgrades' => 'Quiz Sum Grades',
                ],
            ],
            'logstore_standard_log' => [
                'label' => 'Learner Activity Logs ({logstore_standard_log})',
                'from' => '{logstore_standard_log} l JOIN {course} c ON c.id = l.courseid',
                'coursecol' => 'l.courseid',
                'timecol' => 'l.timecreated',
                'basewhere' => 'l.courseid > 1 AND l. anonymous = 0',
                'dimensions' => [
                    'c.shortname' => 'Course Short Name',
                    'l.component' => 'Component (mod_assign, mod_quiz, core, etc.)',
                    'l.action' => 'Event Action (viewed, submitted, created, updated)',
                    'l. crud' => 'CRUD Operation (c, r, u, d)',
                    'l.timecreated' => 'Event Timestamp',
                ],
                'metrics' => [
                    'l.id' => 'Log Event ID',
                    'l.userid' => 'Active User ID',
                ],
            ],
            'role_assignments' => [
                'label' => 'Role Assignments ({role_assignments})',
                'from' => '{role_assignments} ra JOIN {role} r ON r.id = ra.roleid JOIN {context} ctx ON ctx.id = ra.contextid',
                'coursecol' => 'ctx.instanceid',
                'timecol' => 'ra.timemodified',
                'basewhere' => 'ctx.contextlevel = 50',
                'dimensions' => [
                    'r.shortname' => 'Role Shortname (student, editingteacher, teacher, manager)',
                    'r.name' => 'Role Custom Name',
                    'ra.timemodified' => 'Assignment Timestamp',
                ],
                'metrics' => [
                    'ra.id' => 'Role Assignment ID',
                    'ra.userid' => 'User ID',
                ],
            ],
        ];
    }

    /**
     * Get all saved custom queries from the database.
     *
     * @return array
     */
    public static function get_all_queries(): array {
        global $DB;
        return $DB->get_records('local_lms_dashboard_builder_query', null, 'name ASC, id ASC');
    }

    /**
     * Get a single saved query by ID.
     *
     * @param int $id
     * @return \stdClass|false
     */
    public static function get_query(int $id) {
        global $DB;
        return $DB->get_record('local_lms_dashboard_builder_query', ['id' => $id]);
    }

    /**
     * Create or update a custom query (Visual or Custom SQL).
     *
     * @param \stdClass $data
     * @return int Saved query ID.
     */
    public static function save_query(\stdClass $data): int {
        global $DB, $USER;
        security_guard::require_manage();

        $querytype = clean_param($data->querytype ?? self::TYPE_VISUAL, PARAM_ALPHANUMEXT);
        if (!in_array($querytype, [self::TYPE_VISUAL, self::TYPE_CUSTOM_SQL], true)) {
            $querytype = self::TYPE_VISUAL;
        }

        // Custom SQL requires configure capability and strict SQL validation.
        $customsql = '';
        if ($querytype === self::TYPE_CUSTOM_SQL) {
            require_capability('local/lms_dashboard_builder:configure', \context_system::instance());
            $customsql = trim((string)($data->customsql ?? ''));
            security_guard::validate_safe_sql($customsql);
        }

        $now = time();
        $record = new \stdClass();
        $record->name = clean_param($data->name ?? 'Custom Query', PARAM_TEXT);
        $record->provider = clean_param($data->provider ?? 'custom', PARAM_ALPHANUMEXT);
        $record->querytype = $querytype;
        $record->querydefinition = isset($data->querydefinition)
            ? (is_string($data->querydefinition) ? $data->querydefinition : json_encode($data->querydefinition))
            : '{}';
        $record->customsql = $customsql;
        $record->timemodified = $now;
        $record->usermodified = (int)($USER->id ?? 0);

        if (!empty($data->id) && (int)$data->id > 0) {
            $record->id = (int)$data->id;
            $DB->update_record('local_lms_dashboard_builder_query', $record);
            $id = $record->id;
            security_guard::log_audit(null, 'update_query', ['queryid' => $id, 'type' => $querytype]);
        } else {
            $record->timecreated = $now;
            $id = $DB->insert_record('local_lms_dashboard_builder_query', $record);
            // Update provider key to 'custom_' . $id for direct registry lookup.
            $DB->set_field('local_lms_dashboard_builder_query', 'provider', 'custom_' . $id, ['id' => $id]);
            security_guard::log_audit(null, 'create_query', ['queryid' => $id, 'type' => $querytype]);
        }

        provider_registry::reset();
        return $id;
    }

    /**
     * Delete a custom query by ID.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_query(int $id): bool {
        global $DB;
        security_guard::require_manage();

        $DB->delete_records('local_lms_dashboard_builder_query', ['id' => $id]);
        security_guard::log_audit(null, 'delete_query', ['queryid' => $id]);
        provider_registry::reset();
        return true;
    }
}
