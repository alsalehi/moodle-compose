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

namespace local_lms_dashboard_builder\security;

defined('MOODLE_INTERNAL') || die();

/**
 * Security guard class for access control, context enforcement, and query auditing.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class security_guard {

    /**
     * Verify that current user has capability to view a given dashboard in its context.
     *
     * @param int $dashboardid The dashboard ID.
     * @param \context|null $context Context to check, defaults to system context if null.
     * @return bool True if permitted, throws exception or returns false.
     */
    public static function require_view(int $dashboardid, ?\context $context = null): bool {
        global $DB, $USER;

        if ($context === null) {
            $context = \context_system::instance();
        }

        $isvisitor = (!isloggedin() || isguestuser());

        if ($dashboardid > 0) {
            $dashboard = $DB->get_record('local_lms_dashboard_builder_dashboard', ['id' => $dashboardid]);
            if (!$dashboard) {
                throw new \moodle_exception('error_dashboard_not_found', 'local_lms_dashboard_builder');
            }

            $rolemaps = $DB->get_records('local_lms_dashboard_builder_rolemap', ['dashboardid' => $dashboardid]);
            $ispublicvisitor = false;
            foreach ($rolemaps as $rm) {
                if ((int)$rm->allowview === 1 && (int)$rm->roleid === \local_lms_dashboard_builder\dashboard_manager::ROLE_PUBLIC_VISITOR) {
                    $ispublicvisitor = true;
                    break;
                }
            }

            // Non-logged-in visitors can ONLY view published dashboards explicitly marked for Everyone/Visitors (-1).
            if ($isvisitor) {
                if ($ispublicvisitor && (int)$dashboard->status === \local_lms_dashboard_builder\dashboard_manager::STATUS_PUBLISHED) {
                    return true;
                }
                throw new \moodle_exception('error_permission_denied', 'local_lms_dashboard_builder');
            }

            require_capability('local/lms_dashboard_builder:view', $context);

            $canmanage = has_capability('local/lms_dashboard_builder:manage', \context_system::instance());

            // Non-managers can only view published dashboards.
            if (!$canmanage && (int)$dashboard->status !== \local_lms_dashboard_builder\dashboard_manager::STATUS_PUBLISHED) {
                throw new \moodle_exception('error_permission_denied', 'local_lms_dashboard_builder');
            }

            // Enforce role-based visibility rules if configured for this dashboard.
            if (!$canmanage && !is_siteadmin() && !$ispublicvisitor && !empty($rolemaps)) {
                $userroles = get_user_roles($context, $USER->id, true);
                $userroleids = array_map(static function($ra) {
                    return (int)$ra->roleid;
                }, $userroles);

                if ((int)($USER->id ?? 0) > 0) {
                    $assignedroles = $DB->get_fieldset_select(
                        'role_assignments',
                        'DISTINCT roleid',
                        'userid = :userid',
                        ['userid' => (int)$USER->id]
                    );
                    foreach ($assignedroles as $arid) {
                        $userroleids[] = (int)$arid;
                    }
                    $userroleids = array_values(array_unique($userroleids));
                }

                $allowed = false;
                foreach ($rolemaps as $rm) {
                    if ((int)$rm->allowview === 1 && in_array((int)$rm->roleid, $userroleids, true)) {
                        $allowed = true;
                        break;
                    }
                }

                if (!$allowed) {
                    throw new \moodle_exception('error_permission_denied', 'local_lms_dashboard_builder');
                }
            }

            return true;
        }

        if ($isvisitor) {
            throw new \moodle_exception('error_permission_denied', 'local_lms_dashboard_builder');
        }

        require_capability('local/lms_dashboard_builder:view', $context);
        return true;
    }

    /**
     * Verify that current user has capability to manage dashboards.
     *
     * @param \context|null $context Context to check, defaults to system context.
     * @return bool True if permitted.
     */
    public static function require_manage(?\context $context = null): bool {
        if ($context === null) {
            $context = \context_system::instance();
        }

        require_capability('local/lms_dashboard_builder:manage', $context);
        return true;
    }

    /**
     * Enforce course context security. Prevents a user in course A from requesting course B data.
     *
     * @param int $requestedcourseid Course ID requested by client.
     * @param \context $actualcontext Current Moodle context (may be course, block inside course, etc.).
     * @return int Validated, permitted course ID.
     * @throws \moodle_exception If course access violation occurs.
     */
    public static function validate_course_context(int $requestedcourseid, \context $actualcontext): int {
        // Check if current context is a course context or a child context (e.g. block inside a course).
        $coursecontext = ($actualcontext instanceof \context_course)
            ? $actualcontext
            : $actualcontext->get_course_context(false);

        if ($coursecontext instanceof \context_course && (int)$coursecontext->instanceid > 1) {
            $actualcourseid = (int)$coursecontext->instanceid;
            if ($requestedcourseid !== 0 && $requestedcourseid !== $actualcourseid) {
                throw new \moodle_exception('error_permission_denied', 'local_lms_dashboard_builder');
            }
            return $actualcourseid;
        }

        // If a specific course is requested from a system/user context, verify access to that course.
        if ($requestedcourseid > 1) {
            $targetcoursecontext = \context_course::instance($requestedcourseid, IGNORE_MISSING);
            if (!$targetcoursecontext) {
                throw new \moodle_exception('error_permission_denied', 'local_lms_dashboard_builder');
            }
            $canaccess = is_siteadmin()
                || has_capability('local/lms_dashboard_builder:manage', \context_system::instance())
                || is_enrolled($targetcoursecontext, null, '', true)
                || has_capability('moodle/course:view', $targetcoursecontext);
            if (!$canaccess || !has_capability('local/lms_dashboard_builder:view', $targetcoursecontext)) {
                throw new \moodle_exception('error_permission_denied', 'local_lms_dashboard_builder');
            }
            return $requestedcourseid;
        }

        return 0; // All permitted courses.
    }

    /**
     * Validate custom SQL query ensuring it is strictly read-only and free of destructive statements.
     *
     * @param string $sql Raw SQL string.
     * @return bool True if strictly read-only SELECT.
     * @throws \moodle_exception If SQL violates safety constraints.
     */
    public static function validate_safe_sql(string $sql): bool {
        // Strip SQL comments before inspection and reject comment-based obfuscation.
        if (preg_match('/(--|\/\*|\*\/|#)/', $sql)) {
            throw new \moodle_exception('error_invalid_query', 'local_lms_dashboard_builder', '', null, 'SQL comments are prohibited');
        }

        $trimmed = trim($sql);

        // Must begin with SELECT or WITH (for CTEs).
        if (!preg_match('/^(SELECT|WITH)\b/i', $trimmed)) {
            throw new \moodle_exception('error_invalid_query', 'local_lms_dashboard_builder', '', null, 'Query must be a SELECT statement');
        }

        // Prohibited destructive and administration keywords.
        $forbidden = [
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE',
            'REPLACE', 'CREATE', 'GRANT', 'REVOKE', 'EXEC', 'EXECUTE',
            'INTO OUTFILE', 'INTO DUMPFILE', 'INFORMATION_SCHEMA',
            'PG_CATALOG', 'PG_SLEEP', 'LOAD_FILE', 'BENCHMARK', 'SLEEP',
        ];

        foreach ($forbidden as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $trimmed)) {
                throw new \moodle_exception('error_invalid_query', 'local_lms_dashboard_builder', '', null, "Forbidden SQL keyword: {$word}");
            }
        }

        // Prohibited sensitive Moodle tables (credentials, tokens, sessions, config).
        $sensitivetables = [
            'config', 'config_plugins', 'sessions', 'external_tokens',
            'user_password_history', 'user_password_resets', ' user_private_key',
        ];
        foreach ($sensitivetables as $tbl) {
            $tbl = trim($tbl);
            if (preg_match('/(\{\s*' . preg_quote($tbl, '/') . '\s*\}|\bmdl_' . preg_quote($tbl, '/') . '\b)/i', $trimmed)) {
                throw new \moodle_exception('error_invalid_query', 'local_lms_dashboard_builder', '', null, "Access to sensitive table '{$tbl}' is prohibited");
            }
        }

        // Block selecting password or secret fields from user table.
        if (preg_match('/\b(password|secret|sessdata|sid)\b/i', $trimmed)) {
            throw new \moodle_exception('error_invalid_query', 'local_lms_dashboard_builder', '', null, 'Selecting sensitive credential columns is prohibited');
        }

        // Must not contain multiple statements (semicolon followed by another query).
        if (preg_match('/;(?!\s*$)/', $trimmed)) {
            throw new \moodle_exception('error_invalid_query', 'local_lms_dashboard_builder', '', null, 'Multiple statements are prohibited');
        }

        return true;
    }

    /**
     * Record an audit log entry for security and change tracking.
     *
     * @param int|null $dashboardid
     * @param string $action
     * @param array $details
     */
    public static function log_audit(?int $dashboardid, string $action, array $details = []): void {
        global $DB, $USER;

        $record = new \stdClass();
        $record->dashboardid = $dashboardid;
        $record->userid = (int)($USER->id ?? 0);
        $record->action = clean_param($action, PARAM_ALPHAEXT);
        $record->details = json_encode($details);
        $record->ipaddress = getremoteaddr();
        $record->timecreated = time();

        $DB->insert_record('local_lms_dashboard_builder_audit', $record);
    }
}
