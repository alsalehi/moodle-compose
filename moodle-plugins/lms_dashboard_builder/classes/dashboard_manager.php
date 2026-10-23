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

namespace local_lms_dashboard_builder;

use local_lms_dashboard_builder\security\security_guard;
use local_lms_dashboard_builder\filter\filter_engine;

defined('MOODLE_INTERNAL') || die();

/**
 * Dashboard manager class for handling dashboard operations, role targeting, course scoping, and templates.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard_manager {

    /** Status draft. */
    public const STATUS_DRAFT = 0;

    /** Status published. */
    public const STATUS_PUBLISHED = 1;

    /** Status archived. */
    public const STATUS_ARCHIVED = 2;

    /** Scope system/global. */
    public const SCOPE_SYSTEM = 'system';

    /** Scope course dashboard. */
    public const SCOPE_COURSE = 'course';

    /** Scope category dashboard. */
    public const SCOPE_CATEGORY = 'category';

    /** Scope personal my-dashboard. */
    public const SCOPE_MY = 'my';

    /** Special role ID representing public visitors / non-logged-in users. */
    public const ROLE_PUBLIC_VISITOR = -1;

    /**
     * Sanitize user-provided CSS classes.
     *
     * @param string|null $classes
     * @return string
     */
    public static function sanitize_css_classes(?string $classes): string {
        if (empty($classes)) {
            return '';
        }
        $parts = preg_split('/\s+/', trim($classes));
        $valid = [];
        foreach ($parts as $p) {
            $cleaned = clean_param($p, PARAM_ALPHANUMEXT);
            if ($cleaned !== '') {
                $valid[] = $cleaned;
            }
        }
        return implode(' ', array_unique($valid));
    }

    /**
     * Check if a dashboard has the public visitor role assigned.
     *
     * @param int $dashboardid
     * @return bool
     */
    public static function is_public_visitor_dashboard(int $dashboardid): bool {
        global $DB;
        return $DB->record_exists('local_lms_dashboard_builder_rolemap', [
            'dashboardid' => $dashboardid,
            'roleid' => self::ROLE_PUBLIC_VISITOR,
            'allowview' => 1,
        ]);
    }

    /**
     * Get a dashboard by ID.
     *
     * @param int $id The dashboard record ID.
     * @return \stdClass|false The dashboard record or false if not found.
     */
    public static function get_dashboard(int $id) {
        global $DB;
        return $DB->get_record('local_lms_dashboard_builder_dashboard', ['id' => $id]);
    }

    /**
     * Get all active/published dashboards available for a given context.
     *
     * @param int $contextid The context ID (defaults to system context 1, 0 for all contexts).
     * @param bool $publishedonly Whether to restrict to published dashboards.
     * @return array Array of dashboard objects.
     */
    public static function get_dashboards(int $contextid = 1, bool $publishedonly = true): array {
        global $DB;
        $params = [];
        $conditions = [];

        if ($contextid > 0) {
            $conditions[] = 'contextid = :contextid';
            $params['contextid'] = $contextid;
        }

        if ($publishedonly) {
            $conditions[] = 'status = :status';
            $params['status'] = self::STATUS_PUBLISHED;
        }

        $select = !empty($conditions) ? implode(' AND ', $conditions) : '';
        return $DB->get_records_select('local_lms_dashboard_builder_dashboard', $select, $params, 'isdefault DESC, name ASC');
    }

    /**
     * Create a new dashboard.
     *
     * @param \stdClass $data
     * @return int New dashboard record ID.
     */
    public static function create_dashboard(\stdClass $data): int {
        global $DB, $USER;
        security_guard::require_manage();

        return self::insert_dashboard_internal($data, (int)($USER->id ?? 0), true);
    }

    /**
     * Internal helper to insert a dashboard record (used by both create_dashboard and install seeder).
     *
     * @param \stdClass $data
     * @param int $userid
     * @param bool $audit
     * @return int
     */
    private static function insert_dashboard_internal(\stdClass $data, int $userid = 0, bool $audit = true): int {
        global $DB;

        $now = time();
        $scope = isset($data->scope) ? clean_param($data->scope, PARAM_ALPHANUMEXT) : self::SCOPE_SYSTEM;
        if (!in_array($scope, [self::SCOPE_SYSTEM, self::SCOPE_COURSE, self::SCOPE_CATEGORY, self::SCOPE_MY], true)) {
            $scope = self::SCOPE_SYSTEM;
        }

        $record = new \stdClass();
        $record->name = clean_param($data->name, PARAM_TEXT);
        $record->description = isset($data->description) ? clean_param($data->description, PARAM_RAW) : '';
        $record->descriptionformat = isset($data->descriptionformat) ? (int)$data->descriptionformat : FORMAT_HTML;
        $record->contextid = isset($data->contextid) ? (int)$data->contextid : 1;
        $record->scope = $scope;
        $record->category = isset($data->category) ? clean_param($data->category, PARAM_TEXT) : 'General';
        $record->status = isset($data->status) ? (int)$data->status : self::STATUS_DRAFT;
        $record->layout = isset($data->layout) ? (is_string($data->layout) ? $data->layout : json_encode($data->layout)) : '{}';
        $record->isdefault = !empty($data->isdefault) ? 1 : 0;
        $record->cssclass = isset($data->cssclass) ? self::sanitize_css_classes($data->cssclass) : '';
        $record->timecreated = $now;
        $record->timemodified = $now;
        $record->usermodified = $userid;

        $id = (int)$DB->insert_record('local_lms_dashboard_builder_dashboard', $record);
        if ($audit) {
            security_guard::log_audit($id, 'create_dashboard', ['name' => $record->name, 'scope' => $scope]);
        }

        return $id;
    }

    /**
     * Update an existing dashboard.
     *
     * @param int $id
     * @param \stdClass $data
     * @return bool
     */
    public static function update_dashboard(int $id, \stdClass $data): bool {
        global $DB, $USER;
        security_guard::require_manage();

        $existing = self::get_dashboard($id);
        if (!$existing) {
            throw new \moodle_exception('error_dashboard_not_found', 'local_lms_dashboard_builder');
        }

        $record = new \stdClass();
        $record->id = $id;
        if (isset($data->name)) {
            $record->name = clean_param($data->name, PARAM_TEXT);
        }
        if (isset($data->description)) {
            $record->description = clean_param($data->description, PARAM_RAW);
        }
        if (isset($data->category)) {
            $record->category = clean_param($data->category, PARAM_TEXT);
        }
        if (isset($data->scope)) {
            $scope = clean_param($data->scope, PARAM_ALPHANUMEXT);
            if (in_array($scope, [self::SCOPE_SYSTEM, self::SCOPE_COURSE, self::SCOPE_CATEGORY, self::SCOPE_MY], true)) {
                $record->scope = $scope;
            }
        }
        if (isset($data->status)) {
            $record->status = (int)$data->status;
        }
        if (isset($data->isdefault)) {
            $record->isdefault = !empty($data->isdefault) ? 1 : 0;
        }
        if (isset($data->cssclass)) {
            $record->cssclass = self::sanitize_css_classes($data->cssclass);
        }
        if (isset($data->layout)) {
            $record->layout = is_string($data->layout) ? $data->layout : json_encode($data->layout);
        }
        $record->timemodified = time();
        $record->usermodified = (int)($USER->id ?? 0);

        $DB->update_record('local_lms_dashboard_builder_dashboard', $record);
        widget_manager::purge_widget_cache();
        security_guard::log_audit($id, 'update_dashboard', ['name' => $record->name ?? $existing->name]);

        return true;
    }

    /**
     * Duplicate an existing dashboard along with its configured widgets, filters, and role mappings.
     *
     * @param int $id Dashboard ID to clone.
     * @return int New cloned dashboard ID.
     */
    public static function duplicate_dashboard(int $id): int {
        global $DB, $USER;
        security_guard::require_manage();

        $source = self::get_dashboard($id);
        if (!$source) {
            throw new \moodle_exception('error_dashboard_not_found', 'local_lms_dashboard_builder');
        }

        $now = time();
        $copy = clone $source;
        unset($copy->id);
        $copy->name = $source->name . get_string('copy_suffix', 'local_lms_dashboard_builder');
        $copy->status = self::STATUS_DRAFT;
        $copy->isdefault = 0;
        $copy->timecreated = $now;
        $copy->timemodified = $now;
        $copy->usermodified = (int)($USER->id ?? 0);

        $newid = (int)$DB->insert_record('local_lms_dashboard_builder_dashboard', $copy);

        // Copy widgets.
        $widgets = widget_manager::get_widgets_for_dashboard($id);
        foreach ($widgets as $w) {
            $newwidget = clone $w;
            unset($newwidget->id);
            $newwidget->dashboardid = $newid;
            $newwidget->timecreated = $now;
            $newwidget->timemodified = $now;
            $DB->insert_record('local_lms_dashboard_builder_widget', $newwidget);
        }

        // Copy filters.
        $filters = filter_engine::get_dashboard_filters($id);
        foreach ($filters as $f) {
            $newfilter = clone $f;
            unset($newfilter->id);
            $newfilter->dashboardid = $newid;
            $DB->insert_record('local_lms_dashboard_builder_filter', $newfilter);
        }

        // Copy role mappings.
        $rolemaps = $DB->get_records('local_lms_dashboard_builder_rolemap', ['dashboardid' => $id]);
        foreach ($rolemaps as $rm) {
            $newrm = clone $rm;
            unset($newrm->id);
            $newrm->dashboardid = $newid;
            $DB->insert_record('local_lms_dashboard_builder_rolemap', $newrm);
        }

        security_guard::log_audit($newid, 'duplicate_dashboard', ['sourceid' => $id, 'newid' => $newid]);
        return $newid;
    }

    /**
     * Delete a dashboard and all its dependent widgets, filters, and role mappings.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_dashboard(int $id): bool {
        global $DB;
        security_guard::require_manage();

        $DB->delete_records('local_lms_dashboard_builder_widget', ['dashboardid' => $id]);
        $DB->delete_records('local_lms_dashboard_builder_filter', ['dashboardid' => $id]);
        $DB->delete_records('local_lms_dashboard_builder_rolemap', ['dashboardid' => $id]);
        $DB->delete_records('local_lms_dashboard_builder_dashboard', ['id' => $id]);

        widget_manager::purge_widget_cache();
        security_guard::log_audit($id, 'delete_dashboard', ['deletedid' => $id]);
        return true;
    }

    /**
     * Get assigned role IDs for a dashboard.
     *
     * @param int $dashboardid
     * @return int[] Array of allowed role IDs.
     */
    public static function get_dashboard_roles(int $dashboardid): array {
        global $DB;
        $records = $DB->get_records('local_lms_dashboard_builder_rolemap', [
            'dashboardid' => $dashboardid,
            'allowview' => 1,
        ]);
        $roleids = [];
        foreach ($records as $r) {
            $roleids[] = (int)$r->roleid;
        }
        return array_values(array_unique($roleids));
    }

    /**
     * Set allowed role IDs for a dashboard (empty array means visible to all authenticated users with view capability).
     *
     * @param int $dashboardid
     * @param array $roleids
     * @param int $contextlevel
     */
    public static function set_dashboard_roles(int $dashboardid, array $roleids, int $contextlevel = CONTEXT_COURSE): void {
        global $DB;
        security_guard::require_manage();

        $DB->delete_records('local_lms_dashboard_builder_rolemap', ['dashboardid' => $dashboardid]);

        foreach (array_unique(array_map('intval', $roleids)) as $roleid) {
            if ($roleid === 0 || ($roleid < 0 && $roleid !== self::ROLE_PUBLIC_VISITOR)) {
                continue;
            }
            $rec = new \stdClass();
            $rec->dashboardid = $dashboardid;
            $rec->roleid = $roleid;
            $rec->contextlevel = $contextlevel;
            $rec->allowview = 1;
            $DB->insert_record('local_lms_dashboard_builder_rolemap', $rec);
        }

        security_guard::log_audit($dashboardid, 'set_dashboard_roles', ['roles' => array_values($roleids)]);
    }

    /**
     * Resolve all suitable published dashboards for the current user and context, ranked by best match.
     *
     * @param \context $context Active page context.
     * @param int $userid User ID (0 for current $USER).
     * @return array Array of matched dashboard records.
     */
    public static function resolve_dashboards_for_user(\context $context, int $userid = 0): array {
        global $DB, $USER;

        if ($userid <= 0) {
            $userid = (int)($USER->id ?? 0);
        }

        $coursecontext = ($context instanceof \context_course) ? $context : $context->get_course_context(false);
        $incourse = ($coursecontext instanceof \context_course && (int)$coursecontext->instanceid > 1);
        $isvisitor = (!isloggedin() || isguestuser());

        $dashboards = self::get_dashboards(0, true);
        if (empty($dashboards)) {
            return [];
        }

        // For non-logged-in visitors, only dashboards with ROLE_PUBLIC_VISITOR match.
        if ($isvisitor) {
            $visitorboards = [];
            foreach ($dashboards as $d) {
                if (self::is_public_visitor_dashboard((int)$d->id)) {
                    $visitorboards[] = $d;
                }
            }
            return $visitorboards;
        }

        // For authenticated users, collect roles in active context plus global course assignments.
        $userroles = get_user_roles($context, $userid, true);
        $userroleids = array_map(static function($ra) {
            return (int)$ra->roleid;
        }, $userroles);

        if ($userid > 0) {
            $assignedroles = $DB->get_fieldset_select(
                'role_assignments',
                'DISTINCT roleid',
                'userid = :userid',
                ['userid' => $userid]
            );
            foreach ($assignedroles as $arid) {
                $userroleids[] = (int)$arid;
            }
            $userroleids = array_values(array_unique($userroleids));
        }

        $scored = [];

        foreach ($dashboards as $d) {
            $rolemaps = self::get_dashboard_roles((int)$d->id);
            $haspublicmap = in_array(self::ROLE_PUBLIC_VISITOR, $rolemaps, true);
            $rolematch = false;

            if (!empty($rolemaps)) {
                if ($haspublicmap) {
                    $rolematch = true;
                } else {
                    foreach ($rolemaps as $rid) {
                        if (in_array($rid, $userroleids, true)) {
                            $rolematch = true;
                            break;
                        }
                    }
                }
                // Skip role-restricted dashboards if user doesn't hold any mapped role (unless site admin).
                if (!$rolematch && !is_siteadmin($userid)) {
                    continue;
                }
            }

            $score = 0;
            $scope = $d->scope ?? self::SCOPE_SYSTEM;
            if ($incourse) {
                if ($scope === self::SCOPE_COURSE) {
                    $score += 60;
                } else if ($scope === self::SCOPE_SYSTEM) {
                    $score += 30;
                }
            } else {
                if ($scope === self::SCOPE_SYSTEM) {
                    $score += 50;
                } else if ($scope === self::SCOPE_MY) {
                    $score += 45;
                }
            }

            if ($rolematch) {
                $score += 40;
            }
            if (!empty($d->isdefault)) {
                $score += 10;
            }

            $scored[] = [
                'score' => $score,
                'dashboard' => $d,
            ];
        }

        // Sort matched dashboards by suitability score descending.
        usort($scored, static function($a, $b) {
            if ($a['score'] === $b['score']) {
                return strcmp($a['dashboard']->name, $b['dashboard']->name);
            }
            return ($a['score'] > $b['score']) ? -1 : 1;
        });

        $result = [];
        foreach ($scored as $item) {
            $result[] = $item['dashboard'];
        }

        return $result;
    }

    /**
     * Automatically resolve the best published dashboard for the current user and page context.
     *
     * @param \context $context Active page/block context.
     * @param int $userid User ID (0 for current $USER).
     * @return \stdClass|null Resolved dashboard record or null.
     */
    public static function resolve_best_dashboard_for_user(\context $context, int $userid = 0): ?\stdClass {
        $matches = self::resolve_dashboards_for_user($context, $userid);
        return !empty($matches) ? $matches[0] : null;
    }

    /**
     * Export a complete dashboard definition (dashboard, widgets, filters, role shortnames) as JSON.
     *
     * @param int $dashboardid
     * @return string JSON string.
     */
    public static function export_dashboard_json(int $dashboardid): string {
        global $DB;
        security_guard::require_manage();

        $dashboard = self::get_dashboard($dashboardid);
        if (!$dashboard) {
            throw new \moodle_exception('error_dashboard_not_found', 'local_lms_dashboard_builder');
        }

        $widgets = [];
        foreach (widget_manager::get_widgets_for_dashboard($dashboardid) as $w) {
            $widgets[] = [
                'title' => $w->title,
                'widgettype' => $w->widgettype,
                'queryid' => $w->queryid,
                'configdata' => json_decode($w->configdata ?: '{}', true),
                'cssclass' => $w->cssclass ?? '',
                'grid_col' => (int)$w->grid_col,
                'grid_row' => (int)$w->grid_row,
                'grid_width' => (int)$w->grid_width,
                'grid_height' => (int)$w->grid_height,
                'sortorder' => (int)$w->sortorder,
            ];
        }

        $filters = [];
        foreach (filter_engine::get_dashboard_filters($dashboardid) as $f) {
            $filters[] = [
                'filtertype' => $f->filtertype,
                'label' => $f->label,
                'defaultvalue' => $f->defaultvalue,
                'configdata' => json_decode($f->configdata ?: '{}', true),
                'sortorder' => (int)$f->sortorder,
            ];
        }

        return json_encode([
            'version' => '1.1',
            'dashboard' => [
                'name' => $dashboard->name,
                'description' => $dashboard->description,
                'scope' => $dashboard->scope ?? self::SCOPE_SYSTEM,
                'category' => $dashboard->category,
                'status' => (int)$dashboard->status,
                'cssclass' => $dashboard->cssclass ?? '',
            ],
            'widgets' => $widgets,
            'filters' => $filters,
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Import a dashboard definition from JSON.
     *
     * @param string $json
     * @return int Imported dashboard ID.
     */
    public static function import_dashboard_json(string $json): int {
        global $DB;
        security_guard::require_manage();

        $payload = json_decode($json, true);
        if (!is_array($payload) || empty($payload['dashboard']['name'])) {
            throw new \moodle_exception('error_invalid_query', 'local_lms_dashboard_builder');
        }

        $ddata = (object)$payload['dashboard'];
        if (isset($payload['dashboard']['cssclass'])) {
            $ddata->cssclass = self::sanitize_css_classes($payload['dashboard']['cssclass']);
        }
        $newid = self::create_dashboard($ddata);

        foreach ($payload['widgets'] ?? [] as $w) {
            $wobj = (object)$w;
            $wobj->dashboardid = $newid;
            widget_manager::add_widget($wobj);
        }

        foreach ($payload['filters'] ?? [] as $f) {
            $fobj = (object)$f;
            $fobj->dashboardid = $newid;
            filter_engine::add_filter($fobj);
        }

        return $newid;
    }

    /**
     * Seed the 4 pre-built default dashboards (Course, Executive, Teacher, Student) and starter custom queries.
     *
     * @param bool $requireauth Whether to enforce require_manage capability check (false during install/upgrade).
     */
    public static function seed_default_dashboards(bool $requireauth = true): void {
        global $DB;

        if ($requireauth) {
            security_guard::require_manage();
        }

        $now = time();

        // Look up standard Moodle role IDs by shortname if available.
        $roles = $DB->get_records_menu('role', null, '', 'shortname, id');

        // 1. Course Overview Dashboard (Course-scoped template).
        $courseid = self::insert_dashboard_internal((object)[
            'name' => 'Course Overview & Analytics Dashboard',
            'description' => 'Comprehensive course-scoped dashboard displaying enrolled learners, completion ring, grade band distribution, activity engagement, and upcoming deadlines.',
            'scope' => self::SCOPE_COURSE,
            'category' => 'Course',
            'status' => self::STATUS_PUBLISHED,
            'isdefault' => 1,
        ], 0, false);

        $coursewidgets = [
            ['title' => 'Enrolled Learners', 'widgettype' => 'kpi', 'queryid' => 'course_stats', 'grid_width' => 3, 'sortorder' => 1, 'configdata' => ['metric' => 'enrolled_students', 'icon' => 'fa-users', 'accent' => 'primary']],
            ['title' => 'Course Completion Rate', 'widgettype' => 'ring', 'queryid' => 'course_stats', 'grid_width' => 3, 'sortorder' => 2, 'configdata' => ['metric' => 'completion_rate', 'accent' => 'success']],
            ['title' => 'Pending Grading', 'widgettype' => 'kpi', 'queryid' => 'assessment', 'grid_width' => 3, 'sortorder' => 3, 'configdata' => ['metric' => 'pending_grading', 'icon' => 'fa-inbox', 'accent' => 'warning']],
            ['title' => 'Average Course Grade', 'widgettype' => 'kpi', 'queryid' => 'assessment', 'grid_width' => 3, 'sortorder' => 4, 'configdata' => ['metric' => 'average_grade', 'suffix' => '%', 'icon' => 'fa-graduation-cap', 'accent' => 'info']],
            ['title' => 'Grade Band Distribution', 'widgettype' => 'chart', 'queryid' => 'assessment', 'grid_width' => 6, 'sortorder' => 5, 'configdata' => ['charttype' => 'bar', 'groupby' => 'by_grade_band', 'palette' => 'emerald', 'xAxisLabel' => 'Grade Band', 'yAxisLabel' => 'Submissions']],
            ['title' => 'Activity Engagement by Module Type', 'widgettype' => 'chart', 'queryid' => 'engagement', 'grid_width' => 6, 'sortorder' => 6, 'configdata' => ['charttype' => 'horizontalBar', 'palette' => 'ocean']],
            ['title' => 'Completion Status Breakdown', 'widgettype' => 'status_distribution', 'queryid' => 'completion', 'grid_width' => 6, 'sortorder' => 7, 'configdata' => ['palette' => 'emerald']],
            ['title' => 'Upcoming Course Deadlines & Activities', 'widgettype' => 'activity_list', 'queryid' => 'learner_progress', 'grid_width' => 6, 'sortorder' => 8, 'configdata' => ['limit' => 6]],
        ];
        foreach ($coursewidgets as $cw) {
            $DB->insert_record('local_lms_dashboard_builder_widget', (object)[
                'dashboardid' => $courseid,
                'title' => $cw['title'],
                'widgettype' => $cw['widgettype'],
                'queryid' => $cw['queryid'],
                'configdata' => json_encode($cw['configdata']),
                'grid_col' => 0,
                'grid_row' => 0,
                'grid_width' => $cw['grid_width'],
                'grid_height' => 4,
                'sortorder' => $cw['sortorder'],
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        $coursefilters = [
            ['filtertype' => 'daterange', 'label' => 'Period', 'defaultvalue' => '30d', 'sortorder' => 1],
            ['filtertype' => 'groupid', 'label' => 'Course Group', 'defaultvalue' => '0', 'sortorder' => 2],
            ['filtertype' => 'status', 'label' => 'Status', 'defaultvalue' => 'all', 'sortorder' => 3],
            ['filtertype' => 'groupby', 'label' => 'Group By', 'defaultvalue' => '', 'sortorder' => 4],
        ];
        foreach ($coursefilters as $cf) {
            $DB->insert_record('local_lms_dashboard_builder_filter', (object)([
                'dashboardid' => $courseid,
                'configdata' => '{}',
            ] + $cf));
        }

        // 2. Executive Site & Category Manager Dashboard (System-scoped).
        $execid = self::insert_dashboard_internal((object)[
            'name' => 'Executive Site & Category Analytics',
            'description' => 'Site-wide and category-level dashboard for Administrators and Managers featuring enrollment growth trends, category comparisons, and top course rankings.',
            'scope' => self::SCOPE_SYSTEM,
            'category' => 'Administration',
            'status' => self::STATUS_PUBLISHED,
            'isdefault' => 1,
        ], 0, false);

        if (!empty($roles['manager'])) {
            $DB->insert_record('local_lms_dashboard_builder_rolemap', (object)[
                'dashboardid' => $execid,
                'roleid' => (int)$roles['manager'],
                'contextlevel' => CONTEXT_SYSTEM,
                'allowview' => 1,
            ]);
        }

        $execwidgets = [
            ['title' => 'Total Active Enrollments', 'widgettype' => 'kpi', 'queryid' => 'enrollment', 'grid_width' => 4, 'sortorder' => 1, 'configdata' => ['metric' => 'total_enrolled', 'icon' => 'fa-users', 'accent' => 'primary']],
            ['title' => 'New vs Existing Enrollment Growth', 'widgettype' => 'comparison', 'queryid' => 'enrollment', 'grid_width' => 4, 'sortorder' => 2, 'configdata' => ['accent' => 'success']],
            ['title' => 'Platform Completion Rate', 'widgettype' => 'ring', 'queryid' => 'completion', 'grid_width' => 4, 'sortorder' => 3, 'configdata' => ['metric' => 'completion_rate', 'accent' => 'info']],
            ['title' => 'Enrollment Trend Over Time', 'widgettype' => 'chart', 'queryid' => 'enrollment', 'grid_width' => 6, 'sortorder' => 4, 'configdata' => ['charttype' => 'area', 'groupby' => 'time_month', 'palette' => 'ocean', 'xAxisLabel' => 'Month', 'yAxisLabel' => 'Enrollments']],
            ['title' => 'Courses & Enrollments by Category', 'widgettype' => 'chart', 'queryid' => 'category_management', 'grid_width' => 6, 'sortorder' => 5, 'configdata' => ['charttype' => 'combo', 'palette' => 'modern', 'legendPosition' => 'top']],
            ['title' => 'Top Courses Leaderboard', 'widgettype' => 'ranking', 'queryid' => 'enrollment', 'grid_width' => 6, 'sortorder' => 6, 'configdata' => ['limit' => 5, 'accent' => 'primary']],
            ['title' => 'Category Summary Table', 'widgettype' => 'table', 'queryid' => 'category_management', 'grid_width' => 6, 'sortorder' => 7, 'configdata' => []],
        ];
        foreach ($execwidgets as $ew) {
            $DB->insert_record('local_lms_dashboard_builder_widget', (object)[
                'dashboardid' => $execid,
                'title' => $ew['title'],
                'widgettype' => $ew['widgettype'],
                'queryid' => $ew['queryid'],
                'configdata' => json_encode($ew['configdata']),
                'grid_col' => 0,
                'grid_row' => 0,
                'grid_width' => $ew['grid_width'],
                'grid_height' => 4,
                'sortorder' => $ew['sortorder'],
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        $execfilters = [
            ['filtertype' => 'categoryid', 'label' => 'Category', 'defaultvalue' => '0', 'sortorder' => 1],
            ['filtertype' => 'courseid', 'label' => 'Course', 'defaultvalue' => '0', 'sortorder' => 2],
            ['filtertype' => 'daterange', 'label' => 'Period', 'defaultvalue' => '30d', 'sortorder' => 3],
            ['filtertype' => 'groupby', 'label' => 'Group By', 'defaultvalue' => '', 'sortorder' => 4],
        ];
        foreach ($execfilters as $ef) {
            $DB->insert_record('local_lms_dashboard_builder_filter', (object)([
                'dashboardid' => $execid,
                'configdata' => '{}',
            ] + $ef));
        }

        // 3. Instructor Teaching & Assessment Dashboard (Role-mapped to Teachers).
        $teacherid = self::insert_dashboard_internal((object)[
            'name' => 'Instructor Teaching & Assessment Dashboard',
            'description' => 'Role-based dashboard tailored for Editing Teachers and Non-editing Teachers to track grading queues, assessment scores, and student completion.',
            'scope' => self::SCOPE_COURSE,
            'category' => 'Teaching',
            'status' => self::STATUS_PUBLISHED,
            'isdefault' => 0,
        ], 0, false);

        foreach (['editingteacher', 'teacher'] as $rshort) {
            if (!empty($roles[$rshort])) {
                $DB->insert_record('local_lms_dashboard_builder_rolemap', (object)[
                    'dashboardid' => $teacherid,
                    'roleid' => (int)$roles[$rshort],
                    'contextlevel' => CONTEXT_COURSE,
                    'allowview' => 1,
                ]);
            }
        }

        $teacherwidgets = [
            ['title' => 'Total Submissions', 'widgettype' => 'kpi', 'queryid' => 'assessment', 'grid_width' => 4, 'sortorder' => 1, 'configdata' => ['metric' => 'total_submissions', 'icon' => 'fa-file-text', 'accent' => 'primary']],
            ['title' => 'Pending Grading Queue', 'widgettype' => 'kpi', 'queryid' => 'assessment', 'grid_width' => 4, 'sortorder' => 2, 'configdata' => ['metric' => 'pending_grading', 'icon' => 'fa-clock-o', 'accent' => 'danger']],
            ['title' => 'Graded vs Total Comparison', 'widgettype' => 'comparison', 'queryid' => 'assessment', 'grid_width' => 4, 'sortorder' => 3, 'configdata' => ['accent' => 'success']],
            ['title' => 'Assessment Grade Band Breakdown', 'widgettype' => 'chart', 'queryid' => 'assessment', 'grid_width' => 6, 'sortorder' => 4, 'configdata' => ['charttype' => 'doughnut', 'groupby' => 'by_grade_band', 'palette' => 'sunset', 'legendPosition' => 'bottom']],
            ['title' => 'Assessment Metrics Table', 'widgettype' => 'table', 'queryid' => 'assessment', 'grid_width' => 6, 'sortorder' => 5, 'configdata' => []],
        ];
        foreach ($teacherwidgets as $tw) {
            $DB->insert_record('local_lms_dashboard_builder_widget', (object)[
                'dashboardid' => $teacherid,
                'title' => $tw['title'],
                'widgettype' => $tw['widgettype'],
                'queryid' => $tw['queryid'],
                'configdata' => json_encode($tw['configdata']),
                'grid_col' => 0,
                'grid_row' => 0,
                'grid_width' => $tw['grid_width'],
                'grid_height' => 4,
                'sortorder' => $tw['sortorder'],
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        $DB->insert_record('local_lms_dashboard_builder_filter', (object)[
            'dashboardid' => $teacherid,
            'filtertype' => 'daterange',
            'label' => 'Submission Period',
            'defaultvalue' => '30d',
            'configdata' => '{}',
            'sortorder' => 1,
        ]);

        // 4. Learner Personal Progress Dashboard (Role-mapped to Students).
        $studentid = self::insert_dashboard_internal((object)[
            'name' => 'Learner Personal Progress Dashboard',
            'description' => 'Student-facing dashboard showing personal completion progress, activity milestones, and upcoming assignment deadlines.',
            'scope' => self::SCOPE_MY,
            'category' => 'Learner',
            'status' => self::STATUS_PUBLISHED,
            'isdefault' => 0,
        ], 0, false);

        if (!empty($roles['student'])) {
            $DB->insert_record('local_lms_dashboard_builder_rolemap', (object)[
                'dashboardid' => $studentid,
                'roleid' => (int)$roles['student'],
                'contextlevel' => CONTEXT_COURSE,
                'allowview' => 1,
            ]);
        }

        $studentwidgets = [
            ['title' => 'Overall Completion Progress', 'widgettype' => 'progress', 'queryid' => 'completion', 'grid_width' => 6, 'sortorder' => 1, 'configdata' => ['accent' => 'success']],
            ['title' => 'Activity Completion Gauge', 'widgettype' => 'chart', 'queryid' => 'learner_progress', 'grid_width' => 6, 'sortorder' => 2, 'configdata' => ['charttype' => 'gauge', 'palette' => 'emerald']],
            ['title' => 'Upcoming Deadlines & Assignments', 'widgettype' => 'activity_list', 'queryid' => 'learner_progress', 'grid_width' => 12, 'sortorder' => 3, 'configdata' => ['limit' => 5]],
        ];
        foreach ($studentwidgets as $sw) {
            $DB->insert_record('local_lms_dashboard_builder_widget', (object)[
                'dashboardid' => $studentid,
                'title' => $sw['title'],
                'widgettype' => $sw['widgettype'],
                'queryid' => $sw['queryid'],
                'configdata' => json_encode($sw['configdata']),
                'grid_col' => 0,
                'grid_row' => 0,
                'grid_width' => $sw['grid_width'],
                'grid_height' => 4,
                'sortorder' => $sw['sortorder'],
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        $DB->insert_record('local_lms_dashboard_builder_filter', (object)[
            'dashboardid' => $studentid,
            'filtertype' => 'courseid',
            'label' => 'My Course',
            'defaultvalue' => '0',
            'configdata' => '{}',
            'sortorder' => 1,
        ]);

        // 5. Public Site Overview & Learner KPIs (Public Visitors & Everyone).
        $publicid = self::insert_dashboard_internal((object)[
            'name' => 'Public Site Overview & Learner KPIs',
            'description' => 'Public-facing high-level statistics and performance metrics visible to everyone and prospective visitors without logging in.',
            'scope' => self::SCOPE_SYSTEM,
            'category' => 'Public',
            'status' => self::STATUS_PUBLISHED,
            'isdefault' => 0,
            'cssclass' => 'dashboard-public-visitors',
        ], 0, false);

        $DB->insert_record('local_lms_dashboard_builder_rolemap', (object)[
            'dashboardid' => $publicid,
            'roleid' => self::ROLE_PUBLIC_VISITOR,
            'contextlevel' => CONTEXT_SYSTEM,
            'allowview' => 1,
        ]);

        $publicwidgets = [
            ['title' => 'Available Courses', 'widgettype' => 'kpi', 'queryid' => 'public_kpis', 'grid_width' => 4, 'sortorder' => 1, 'configdata' => ['metric' => 'total_courses', 'icon' => 'fa-graduation-cap', 'accent' => 'primary', 'subtitle' => 'Published learning offerings']],
            ['title' => 'Active Courses', 'widgettype' => 'kpi', 'queryid' => 'public_kpis', 'grid_width' => 4, 'sortorder' => 2, 'configdata' => ['metric' => 'active_courses', 'icon' => 'fa-book', 'accent' => 'success', 'subtitle' => 'Currently running courses']],
            ['title' => 'Subject Categories', 'widgettype' => 'kpi', 'queryid' => 'public_kpis', 'grid_width' => 4, 'sortorder' => 3, 'configdata' => ['metric' => 'total_categories', 'icon' => 'fa-folder-open', 'accent' => 'info', 'subtitle' => 'Academic disciplines']],
            ['title' => 'Total Registered Students', 'widgettype' => 'kpi', 'queryid' => 'public_kpis', 'grid_width' => 4, 'sortorder' => 4, 'configdata' => ['metric' => 'total_students', 'icon' => 'fa-users', 'accent' => 'warning', 'subtitle' => 'Global community of learners']],
            ['title' => 'Active Studying Students', 'widgettype' => 'kpi', 'queryid' => 'public_kpis', 'grid_width' => 4, 'sortorder' => 5, 'configdata' => ['metric' => 'studying_students', 'icon' => 'fa-pencil', 'accent' => 'primary', 'subtitle' => 'Active learners with enrollments']],
            ['title' => 'Graduated Students', 'widgettype' => 'kpi', 'queryid' => 'public_kpis', 'grid_width' => 4, 'sortorder' => 6, 'configdata' => ['metric' => 'graduated_students', 'icon' => 'fa-trophy', 'accent' => 'success', 'subtitle' => 'Learners with completed courses']],
        ];
        foreach ($publicwidgets as $pw) {
            $DB->insert_record('local_lms_dashboard_builder_widget', (object)[
                'dashboardid' => $publicid,
                'title' => $pw['title'],
                'widgettype' => $pw['widgettype'],
                'queryid' => $pw['queryid'],
                'configdata' => json_encode($pw['configdata']),
                'grid_col' => 0,
                'grid_row' => 0,
                'grid_width' => $pw['grid_width'],
                'grid_height' => 4,
                'sortorder' => $pw['sortorder'],
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Get complete dashboard layout, rendered filters, role mappings, and widgets structure for rendering.
     *
     * @param int $dashboardid
     * @param \context $context
     * @return array
     */
    public static function get_full_dashboard_layout(int $dashboardid, \context $context): array {
        security_guard::require_view($dashboardid, $context);

        $dashboard = self::get_dashboard($dashboardid);
        if (!$dashboard) {
            throw new \moodle_exception('error_dashboard_not_found', 'local_lms_dashboard_builder');
        }

        $ispublicvisitor = self::is_public_visitor_dashboard($dashboardid);
        $widgets = widget_manager::get_widgets_for_dashboard($dashboardid);
        $widgetlist = [];
        foreach ($widgets as $w) {
            // For dashboards shown to non-logged in visitors, enforce pre-built KPI widgets only.
            if ($ispublicvisitor && $w->widgettype !== widget_manager::TYPE_KPI) {
                continue;
            }

            $configjson = !empty($w->configdata) ? (string)$w->configdata : '{}';
            $widgetlist[] = [
                'id' => (int)$w->id,
                'title' => format_string($w->title),
                'widgettype' => $w->widgettype,
                'queryid' => (string)($w->queryid ?? ''),
                'configjson' => $configjson,
                'cssclass' => (string)($w->cssclass ?? ''),
                'grid_col' => (int)$w->grid_col,
                'grid_row' => (int)$w->grid_row,
                'grid_width' => (int)$w->grid_width,
                'grid_height' => (int)$w->grid_height,
                'sortorder' => (int)$w->sortorder,
            ];
        }

        $filterlist = filter_engine::get_rendered_filters_for_dashboard($dashboardid, $context);

        return [
            'id' => (int)$dashboard->id,
            'name' => format_string($dashboard->name),
            'description' => (string)($dashboard->description ?? ''),
            'scope' => (string)($dashboard->scope ?? self::SCOPE_SYSTEM),
            'category' => (string)($dashboard->category ?? 'General'),
            'status' => (int)$dashboard->status,
            'isdefault' => (int)($dashboard->isdefault ?? 0),
            'cssclass' => (string)($dashboard->cssclass ?? ''),
            'widgets' => $widgetlist,
            'filters' => $filterlist,
        ];
    }
}
