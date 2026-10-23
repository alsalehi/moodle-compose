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

namespace local_lms_dashboard_builder\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem implementation for local_lms_dashboard_builder.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns metadata about the personal data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_lms_dashboard_builder_dashboard',
            [
                'usermodified' => 'privacy:metadata:dashboard:usermodified',
                'timemodified' => 'privacy:metadata:dashboard:timemodified',
            ],
            'privacy:metadata:dashboard'
        );

        $collection->add_database_table(
            'local_lms_dashboard_builder_query',
            [
                'usermodified' => 'privacy:metadata:query:usermodified',
                'timemodified' => 'privacy:metadata:query:timemodified',
            ],
            'privacy:metadata:query'
        );

        $collection->add_database_table(
            'local_lms_dashboard_builder_audit',
            [
                'userid' => 'privacy:metadata:audit:userid',
                'action' => 'privacy:metadata:audit:action',
                'details' => 'privacy:metadata:audit:details',
                'ipaddress' => 'privacy:metadata:audit:ipaddress',
                'timecreated' => 'privacy:metadata:audit:timecreated',
            ],
            'privacy:metadata:audit'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :contextlevel
                   AND (
                       EXISTS (SELECT 1 FROM {local_lms_dashboard_builder_audit} a WHERE a.userid = :userid1)
                    OR EXISTS (SELECT 1 FROM {local_lms_dashboard_builder_dashboard} d WHERE d.usermodified = :userid2)
                    OR EXISTS (SELECT 1 FROM {local_lms_dashboard_builder_query} q WHERE q.usermodified = :userid3)
                   )";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }

        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_lms_dashboard_builder_audit}', []);
        $userlist->add_from_sql('usermodified', 'SELECT usermodified FROM {local_lms_dashboard_builder_dashboard}', []);
        $userlist->add_from_sql('usermodified', 'SELECT usermodified FROM {local_lms_dashboard_builder_query}', []);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $systemcontext = \context_system::instance();

        if (!in_array($systemcontext->id, $contextlist->get_contextids(), true)) {
            return;
        }

        $audits = $DB->get_records('local_lms_dashboard_builder_audit', ['userid' => $userid], 'timecreated ASC');
        if (!empty($audits)) {
            writer::with_context($systemcontext)->export_data(
                [get_string('pluginname', 'local_lms_dashboard_builder'), 'audit'],
                (object)['logs' => array_values($audits)]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_lms_dashboard_builder_audit');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->delete_records('local_lms_dashboard_builder_audit', ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!empty($userids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_lms_dashboard_builder_audit', "userid {$insql}", $inparams);
        }
    }
}
