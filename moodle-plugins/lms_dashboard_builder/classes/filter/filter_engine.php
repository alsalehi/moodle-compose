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

namespace local_lms_dashboard_builder\filter;

use local_lms_dashboard_builder\query\grouping_engine;
use local_lms_dashboard_builder\security\security_guard;

defined('MOODLE_INTERNAL') || die();

/**
 * Filter engine for validating, standardizing, and rendering per-dashboard global and custom filters.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_engine {

    /** Allowed date range options. */
    public const ALLOWED_DATERANGES = ['7d', '30d', '90d', 'quarter', 'year', 'all'];

    /** Supported filter types catalog. */
    public const FILTER_TYPES = [
        'daterange' => 'Date Range (Relative Period)',
        'custom_dates' => 'Custom Start & End Date Range',
        'categoryid' => 'Course Category Selector',
        'courseid' => 'Course Selector',
        'roleid' => 'Moodle Role Selector',
        'cohortid' => 'Cohort Selector',
        'groupid' => 'Course Group Selector',
        'status' => 'Completion / Enrollment Status',
        'modtype' => 'Activity Module Type',
        'groupby' => 'Dynamic Grouping Function Selector',
        'custom' => 'Custom Parameter Filter (Select / Text / Number)',
        'from_request' => 'HTTP Request Parameter (URL Param)',
        'from_context' => 'Backend Context Parameter (Backend Only)',
        'from_user' => 'Backend User Parameter (Backend Only)',
    ];

    /**
     * Get localized catalog of supported filter types.
     *
     * @return array
     */
    public static function get_filter_types_catalog(): array {
        return [
            'daterange' => get_string('filter_type_daterange', 'local_lms_dashboard_builder'),
            'custom_dates' => get_string('filter_type_custom_dates', 'local_lms_dashboard_builder'),
            'categoryid' => get_string('filter_type_categoryid', 'local_lms_dashboard_builder'),
            'courseid' => get_string('filter_type_courseid', 'local_lms_dashboard_builder'),
            'roleid' => get_string('filter_type_roleid', 'local_lms_dashboard_builder'),
            'cohortid' => get_string('filter_type_cohortid', 'local_lms_dashboard_builder'),
            'groupid' => get_string('filter_type_groupid', 'local_lms_dashboard_builder'),
            'status' => get_string('filter_type_status', 'local_lms_dashboard_builder'),
            'modtype' => get_string('filter_type_modtype', 'local_lms_dashboard_builder'),
            'groupby' => get_string('filter_type_groupby', 'local_lms_dashboard_builder'),
            'custom' => get_string('filter_type_custom', 'local_lms_dashboard_builder'),
            'from_request' => get_string('filter_type_from_request', 'local_lms_dashboard_builder'),
            'from_context' => get_string('filter_type_from_context', 'local_lms_dashboard_builder'),
            'from_user' => get_string('filter_type_from_user', 'local_lms_dashboard_builder'),
        ];
    }

    /**
     * Validate and sanitize client-provided filter values.
     *
     * @param array $rawfilters Input filters from client.
     * @param \context $context Context in which filters are evaluated.
     * @return array Cleaned and validated filter parameters.
     */
    public static function sanitize_filters(array $rawfilters, \context $context): array {
        $clean = [];

        // 1. Date range filter.
        $daterange = isset($rawfilters['daterange']) ? clean_param($rawfilters['daterange'], PARAM_ALPHANUM) : '30d';
        if (!in_array($daterange, self::ALLOWED_DATERANGES, true)) {
            $daterange = '30d';
        }
        $clean['daterange'] = $daterange;

        // 2. Explicit custom date boundaries (YYYY-MM-DD or timestamp).
        if (!empty($rawfilters['datefrom'])) {
            $clean['datefrom'] = clean_param((string)$rawfilters['datefrom'], PARAM_TEXT);
        }
        if (!empty($rawfilters['dateto'])) {
            $clean['dateto'] = clean_param((string)$rawfilters['dateto'], PARAM_TEXT);
        }

        // 3. Course ID filter (strictly validated against active Moodle context).
        $courseid = isset($rawfilters['courseid']) ? clean_param($rawfilters['courseid'], PARAM_INT) : 0;
        $clean['courseid'] = security_guard::validate_course_context($courseid, $context);

        // 4. Category ID filter.
        if (isset($rawfilters['categoryid']) && $rawfilters['categoryid'] !== '') {
            $clean['categoryid'] = clean_param($rawfilters['categoryid'], PARAM_INT);
        }

        // 5. Role, Cohort, and Group filters.
        if (isset($rawfilters['roleid']) && $rawfilters['roleid'] !== '') {
            $clean['roleid'] = clean_param($rawfilters['roleid'], PARAM_INT);
        }
        if (isset($rawfilters['cohortid']) && $rawfilters['cohortid'] !== '') {
            $clean['cohortid'] = clean_param($rawfilters['cohortid'], PARAM_INT);
        }
        if (isset($rawfilters['groupid']) && $rawfilters['groupid'] !== '') {
            $clean['groupid'] = clean_param($rawfilters['groupid'], PARAM_INT);
        }

        // 6. Status and Activity Module Type filters.
        if (isset($rawfilters['status']) && $rawfilters['status'] !== '') {
            $clean['status'] = clean_param($rawfilters['status'], PARAM_ALPHAEXT);
        }
        if (isset($rawfilters['modtype']) && $rawfilters['modtype'] !== '') {
            $clean['modtype'] = clean_param($rawfilters['modtype'], PARAM_ALPHANUMEXT);
        }

        // 7. Dynamic Grouping function filter.
        if (!empty($rawfilters['groupby'])) {
            $groupby = clean_param($rawfilters['groupby'], PARAM_ALPHANUMEXT);
            $available = grouping_engine::get_available_groupings();
            if (isset($available[$groupby])) {
                $clean['groupby'] = $groupby;
            }
        }

        // 8. Custom user/admin-defined filters (prefixed with filter_).
        foreach ($rawfilters as $key => $val) {
            if (strpos((string)$key, 'filter_') === 0 && is_scalar($val) && $val !== '') {
                $cleankey = clean_param((string)$key, PARAM_ALPHANUMEXT);
                $clean[$cleankey] = clean_param((string)$val, PARAM_TEXT);
            }
        }

        return $clean;
    }

    /**
     * Merge global dashboard filters with widget-specific overrides and ignored filter rules.
     *
     * @param array $sanitizedfilters Already sanitized global filters.
     * @param array $widgetconfig Decoded widget configdata array.
     * @param \context $context Active Moodle context.
     * @return array Final filter array for widget query execution.
     */
    public static function merge_widget_filters(array $sanitizedfilters, array $widgetconfig, \context $context): array {
        $merged = $sanitizedfilters;

        // Remove any non-security filters that the widget explicitly ignores.
        if (!empty($widgetconfig['ignore_filters']) && is_array($widgetconfig['ignore_filters'])) {
            foreach ($widgetconfig['ignore_filters'] as $ignored) {
                if ($ignored === 'courseid') {
                    // Never allow ignoring course context lock when inside a course.
                    continue;
                }
                if ($ignored === 'daterange') {
                    $merged['daterange'] = 'all';
                    unset($merged['datefrom'], $merged['dateto']);
                } else {
                    unset($merged[$ignored]);
                }
            }
        }

        // Apply widget-level default grouping or filter overrides if not overridden by user.
        if (!empty($widgetconfig['groupby']) && empty($merged['groupby'])) {
            $merged['groupby'] = clean_param((string)$widgetconfig['groupby'], PARAM_ALPHANUMEXT);
        }

        if (!empty($widgetconfig['filter_overrides']) && is_array($widgetconfig['filter_overrides'])) {
            foreach ($widgetconfig['filter_overrides'] as $k => $v) {
                if ($k === 'courseid') {
                    continue;
                }
                $merged[clean_param((string)$k, PARAM_ALPHANUMEXT)] = clean_param((string)$v, PARAM_TEXT);
            }
        }

        // Always re-enforce course context isolation.
        $merged['courseid'] = security_guard::validate_course_context((int)($merged['courseid'] ?? 0), $context);
        return $merged;
    }

    /**
     * Get list of global filters configured for a given dashboard.
     *
     * @param int $dashboardid
     * @return array
     */
    public static function get_dashboard_filters(int $dashboardid): array {
        global $DB;
        return $DB->get_records('local_lms_dashboard_builder_filter', ['dashboardid' => $dashboardid], 'sortorder ASC, id ASC');
    }

    /**
     * Add a global or custom filter definition to a dashboard.
     *
     * @param \stdClass $data
     * @return int New filter ID.
     */
    public static function add_filter(\stdClass $data): int {
        global $DB;
        security_guard::require_manage();

        $record = new \stdClass();
        $record->dashboardid = (int)$data->dashboardid;
        $record->filtertype = clean_param($data->filtertype ?? 'daterange', PARAM_ALPHANUMEXT);
        $record->label = clean_param($data->label ?? get_string('filters', 'local_lms_dashboard_builder'), PARAM_TEXT);
        $record->defaultvalue = clean_param((string)($data->defaultvalue ?? ''), PARAM_TEXT);
        $record->configdata = !empty($data->configdata)
            ? (is_string($data->configdata) ? $data->configdata : json_encode($data->configdata))
            : '{}';
        $record->sortorder = isset($data->sortorder) ? (int)$data->sortorder : 0;

        $id = $DB->insert_record('local_lms_dashboard_builder_filter', $record);
        security_guard::log_audit($record->dashboardid, 'add_filter', ['filterid' => $id, 'type' => $record->filtertype]);
        return $id;
    }

    /**
     * Delete a filter definition from a dashboard.
     *
     * @param int $filterid
     * @return bool
     */
    public static function delete_filter(int $filterid): bool {
        global $DB;
        security_guard::require_manage();

        $existing = $DB->get_record('local_lms_dashboard_builder_filter', ['id' => $filterid]);
        if ($existing) {
            $DB->delete_records('local_lms_dashboard_builder_filter', ['id' => $filterid]);
            security_guard::log_audit((int)$existing->dashboardid, 'delete_filter', ['filterid' => $filterid]);
        }
        return true;
    }

    /**
     * Apply dashboard-defined backend-only filters (from_context, from_user, and backend from_request).
     *
     * @param int $dashboardid
     * @param array $filters Current filter values
     * @param \context $context Active execution context
     * @return array Enriched filters array
     */
    public static function apply_dashboard_backend_filters(int $dashboardid, array $filters, \context $context): array {
        global $DB, $USER;

        $records = self::get_dashboard_filters($dashboardid);
        if (empty($records)) {
            return $filters;
        }

        $coursecontext = ($context instanceof \context_course) ? $context : $context->get_course_context(false);
        $catcontext = ($context instanceof \context_coursecat) ? $context : null;

        foreach ($records as $f) {
            $config = !empty($f->configdata) ? (json_decode($f->configdata, true) ?: []) : [];

            if ($f->filtertype === 'from_context') {
                $field = $config['context_field'] ?? 'courseid';
                switch ($field) {
                    case 'courseid':
                        if ($coursecontext instanceof \context_course && (int)$coursecontext->instanceid > 1) {
                            $filters['courseid'] = (int)$coursecontext->instanceid;
                        }
                        break;
                    case 'categoryid':
                        if ($catcontext instanceof \context_coursecat && (int)$catcontext->instanceid > 0) {
                            $filters['categoryid'] = (int)$catcontext->instanceid;
                        } else if ($coursecontext instanceof \context_course && (int)$coursecontext->instanceid > 1) {
                            $c = $DB->get_record('course', ['id' => (int)$coursecontext->instanceid], 'id, category');
                            if ($c) {
                                $filters['categoryid'] = (int)$c->category;
                            }
                        }
                        break;
                    case 'contextid':
                        $filters['contextid'] = (int)$context->id;
                        break;
                    case 'contextlevel':
                        $filters['contextlevel'] = (int)$context->contextlevel;
                        break;
                    case 'instanceid':
                        $filters['instanceid'] = (int)$context->instanceid;
                        break;
                }
            } else if ($f->filtertype === 'from_user') {
                $field = $config['user_field'] ?? 'userid';
                $userid = (int)($USER->id ?? 0);
                switch ($field) {
                    case 'userid':
                        $filters['userid'] = $userid;
                        break;
                    case 'department':
                        $filters['user_department'] = clean_param((string)($USER->department ?? ''), PARAM_TEXT);
                        break;
                    case 'institution':
                        $filters['user_institution'] = clean_param((string)($USER->institution ?? ''), PARAM_TEXT);
                        break;
                    case 'cohortid':
                        if ($userid > 0) {
                            $cohortid = (int)$DB->get_field_sql("SELECT cohortid FROM {cohort_members} WHERE userid = :uid ORDER BY id ASC", ['uid' => $userid], IGNORE_MULTIPLE);
                            $filters['cohortid'] = $cohortid;
                        }
                        break;
                    case 'roleid':
                        if ($userid > 0) {
                            $roles = get_user_roles($context, $userid, true);
                            if (!empty($roles)) {
                                $firstrole = reset($roles);
                                $filters['roleid'] = (int)$firstrole->roleid;
                            }
                        }
                        break;
                }
            } else if ($f->filtertype === 'from_request') {
                $paramname = !empty($config['param_name']) ? clean_param($config['param_name'], PARAM_ALPHANUMEXT) : 'param_' . $f->id;
                $visibility = $config['visibility'] ?? 'backend';
                $defaultval = (string)($f->defaultvalue ?? '');

                if ($visibility === 'backend') {
                    // Strictly backend-only: always extract from GET/POST request if not already explicitly set.
                    $rawval = optional_param($paramname, $defaultval, PARAM_RAW_TRIMMED);
                    $filters[$paramname] = clean_param($rawval, PARAM_TEXT);
                } else if (!isset($filters[$paramname]) || $filters[$paramname] === '') {
                    // Visible: if not provided by client widget payload, fall back to request param.
                    $rawval = optional_param($paramname, $defaultval, PARAM_RAW_TRIMMED);
                    if ($rawval !== '') {
                        $filters[$paramname] = clean_param($rawval, PARAM_TEXT);
                    }
                }
            }
        }

        return $filters;
    }

    /**
     * Build Mustache-ready filter descriptors with populated option lists for a dashboard.
     *
     * @param int $dashboardid
     * @param \context $context
     * @return array
     */
    public static function get_rendered_filters_for_dashboard(int $dashboardid, \context $context): array {
        global $DB;

        $records = self::get_dashboard_filters($dashboardid);
        $rendered = [];

        $coursecontext = ($context instanceof \context_course) ? $context : $context->get_course_context(false);
        $activecourseid = ($coursecontext instanceof \context_course && (int)$coursecontext->instanceid > 1)
            ? (int)$coursecontext->instanceid
            : 0;

        foreach ($records as $f) {
            $type = $f->filtertype;
            $default = (string)($f->defaultvalue ?? '');
            $config = !empty($f->configdata) ? (json_decode($f->configdata, true) ?: []) : [];

            // Backend-only filters are never rendered in the client filter bar.
            if ($type === 'from_context' || $type === 'from_user') {
                continue;
            }
            if ($type === 'from_request' && ($config['visibility'] ?? 'backend') === 'backend') {
                continue;
            }

            $paramname = $type;
            $isselect = true;
            $isdaterange = false;
            $istext = false;
            $options = [];

            switch ($type) {
                case 'daterange':
                case 'date':
                    $paramname = 'daterange';
                    if ($default === '') {
                        $default = '30d';
                    }
                    $periods = [
                        '7d' => get_string('period_last_7_days', 'local_lms_dashboard_builder'),
                        '30d' => get_string('period_last_30_days', 'local_lms_dashboard_builder'),
                        '90d' => get_string('period_last_90_days', 'local_lms_dashboard_builder'),
                        'quarter' => get_string('period_last_quarter', 'local_lms_dashboard_builder'),
                        'year' => get_string('period_this_year', 'local_lms_dashboard_builder'),
                        'all' => get_string('period_all_time', 'local_lms_dashboard_builder'),
                    ];
                    foreach ($periods as $val => $lbl) {
                        $options[] = ['value' => $val, 'label' => $lbl, 'selected' => ($val === $default)];
                    }
                    break;

                case 'custom_dates':
                    $isselect = false;
                    $isdaterange = true;
                    break;

                case 'categoryid':
                case 'category':
                    $paramname = 'categoryid';
                    $options[] = ['value' => '0', 'label' => get_string('all_categories', 'local_lms_dashboard_builder'), 'selected' => ($default === '' || $default === '0')];
                    $cats = $DB->get_records('course_categories', ['visible' => 1], 'name ASC', 'id, name', 0, 50);
                    foreach ($cats as $cat) {
                        $options[] = [
                            'value' => (string)$cat->id,
                            'label' => format_string($cat->name),
                            'selected' => ((string)$cat->id === $default),
                        ];
                    }
                    break;

                case 'courseid':
                case 'course':
                    $paramname = 'courseid';
                    if ($activecourseid > 0) {
                        // In a course dashboard context, lock the course selector to the current course.
                        $courserec = $DB->get_record('course', ['id' => $activecourseid], 'id, shortname, fullname, category');
                        if ($courserec) {
                            $options[] = [
                                'value' => (string)$courserec->id,
                                'label' => format_string($courserec->fullname),
                                'categoryid' => (string)$courserec->category,
                                'selected' => true,
                            ];
                        }
                    } else {
                        $options[] = [
                            'value' => '0',
                            'label' => get_string('all_courses', 'local_lms_dashboard_builder'),
                            'categoryid' => '0',
                            'selected' => ($default === '' || $default === '0'),
                        ];
                        $courses = $DB->get_records_select('course', 'id > 1 AND visible = 1', [], 'fullname ASC', 'id, fullname, shortname, category', 0, 100);
                        foreach ($courses as $c) {
                            $options[] = [
                                'value' => (string)$c->id,
                                'label' => format_string($c->fullname),
                                'categoryid' => (string)$c->category,
                                'selected' => ((string)$c->id === $default),
                            ];
                        }
                    }
                    break;

                case 'roleid':
                case 'role':
                    $paramname = 'roleid';
                    $options[] = ['value' => '0', 'label' => get_string('all_roles', 'local_lms_dashboard_builder'), 'selected' => ($default === '' || $default === '0')];
                    $roles = $DB->get_records('role', null, 'sortorder ASC', 'id, name, shortname');
                    foreach ($roles as $r) {
                        $rname = !empty($r->name) ? format_string($r->name) : ucfirst($r->shortname);
                        $options[] = [
                            'value' => (string)$r->id,
                            'label' => $rname,
                            'selected' => ((string)$r->id === $default),
                        ];
                    }
                    break;

                case 'groupid':
                case 'group':
                    $paramname = 'groupid';
                    $options[] = ['value' => '0', 'label' => get_string('all_groups', 'local_lms_dashboard_builder'), 'courseid' => '0', 'selected' => true];
                    if ($activecourseid > 0) {
                        $groups = $DB->get_records('groups', ['courseid' => $activecourseid], 'name ASC', 'id, name, courseid');
                        foreach ($groups as $g) {
                            $options[] = [
                                'value' => (string)$g->id,
                                'label' => format_string($g->name),
                                'courseid' => (string)$g->courseid,
                                'selected' => ((string)$g->id === $default),
                            ];
                        }
                    } else {
                        $groups = $DB->get_records('groups', null, 'name ASC', 'id, name, courseid', 0, 100);
                        foreach ($groups as $g) {
                            $options[] = [
                                'value' => (string)$g->id,
                                'label' => format_string($g->name),
                                'courseid' => (string)$g->courseid,
                                'selected' => ((string)$g->id === $default),
                            ];
                        }
                    }
                    break;

                case 'cohortid':
                case 'cohort':
                    $paramname = 'cohortid';
                    $options[] = ['value' => '0', 'label' => get_string('all_cohorts', 'local_lms_dashboard_builder'), 'selected' => true];
                    $cohorts = $DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name', 0, 50);
                    foreach ($cohorts as $ch) {
                        $options[] = [
                            'value' => (string)$ch->id,
                            'label' => format_string($ch->name),
                            'selected' => ((string)$ch->id === $default),
                        ];
                    }
                    break;

                case 'status':
                    $paramname = 'status';
                    $statuses = [
                        'all' => get_string('all_statuses', 'local_lms_dashboard_builder'),
                        'active' => get_string('status_opt_active', 'local_lms_dashboard_builder'),
                        'inprogress' => get_string('status_opt_inprogress', 'local_lms_dashboard_builder'),
                        'pending' => get_string('status_opt_pending', 'local_lms_dashboard_builder'),
                        'inactive' => get_string('status_opt_inactive', 'local_lms_dashboard_builder'),
                    ];
                    foreach ($statuses as $val => $lbl) {
                        $options[] = ['value' => $val, 'label' => $lbl, 'selected' => ($val === ($default ?: 'all'))];
                    }
                    break;

                case 'modtype':
                    $paramname = 'modtype';
                    $mods = [
                        'all' => get_string('all_activities', 'local_lms_dashboard_builder'),
                        'assign' => get_string('mod_assign', 'local_lms_dashboard_builder'),
                        'quiz' => get_string('mod_quiz', 'local_lms_dashboard_builder'),
                        'forum' => get_string('mod_forum', 'local_lms_dashboard_builder'),
                        'scorm' => get_string('mod_scorm', 'local_lms_dashboard_builder'),
                        'resource' => get_string('mod_resource', 'local_lms_dashboard_builder'),
                    ];
                    foreach ($mods as $val => $lbl) {
                        $options[] = ['value' => $val, 'label' => $lbl, 'selected' => ($val === ($default ?: 'all'))];
                    }
                    break;

                case 'groupby':
                    $paramname = 'groupby';
                    $options[] = ['value' => '', 'label' => get_string('default_grouping', 'local_lms_dashboard_builder'), 'selected' => ($default === '')];
                    foreach (grouping_engine::get_available_groupings() as $gid => $gmeta) {
                        $options[] = [
                            'value' => $gid,
                            'label' => $gmeta['label'],
                            'selected' => ($gid === $default),
                        ];
                    }
                    break;

                case 'from_request':
                    $paramname = !empty($config['param_name']) ? clean_param($config['param_name'], PARAM_ALPHANUMEXT) : 'param_' . $f->id;
                    $reqval = optional_param($paramname, $default, PARAM_RAW_TRIMMED);
                    $default = clean_param($reqval, PARAM_TEXT);
                    $isselect = false;
                    $istext = true;
                    break;

                case 'custom':
                default:
                    $customkey = !empty($config['paramkey']) ? clean_param($config['paramkey'], PARAM_ALPHANUMEXT) : 'custom_' . $f->id;
                    $paramname = strpos($customkey, 'filter_') === 0 ? $customkey : 'filter_' . $customkey;
                    $inputtype = $config['inputtype'] ?? 'select';
                    if ($inputtype === 'text') {
                        $isselect = false;
                        $istext = true;
                    } else {
                        $options[] = ['value' => '', 'label' => get_string('all', 'local_lms_dashboard_builder'), 'selected' => ($default === '')];
                        $rawopts = $config['options'] ?? [];
                        if (is_string($rawopts)) {
                            // Parse comma-separated or newline key:label pairs.
                            $pairs = preg_split('/[\r\n,]+/', $rawopts);
                            foreach ($pairs as $pair) {
                                $pair = trim($pair);
                                if ($pair === '') {
                                    continue;
                                }
                                $parts = explode(':', $pair, 2);
                                $oval = trim($parts[0]);
                                $olbl = isset($parts[1]) ? trim($parts[1]) : $oval;
                                $options[] = ['value' => $oval, 'label' => $olbl, 'selected' => ($oval === $default)];
                            }
                        } else if (is_array($rawopts)) {
                            foreach ($rawopts as $oval => $olbl) {
                                $options[] = ['value' => (string)$oval, 'label' => (string)$olbl, 'selected' => ((string)$oval === $default)];
                            }
                        }
                    }
                    break;
            }

            $rendered[] = [
                'id' => (int)$f->id,
                'filtertype' => $type,
                'paramname' => $paramname,
                'label' => format_string($f->label),
                'defaultvalue' => $default,
                'isselect' => $isselect,
                'isdaterange' => $isdaterange,
                'istext' => $istext,
                'options' => $options,
            ];
        }

        return $rendered;
    }
}
