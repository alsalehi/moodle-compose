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

/**
 * Custom Queries and Data Sources management page (Visual Query Builder & Admin Custom SQL).
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_lms_dashboard_builder\query\provider_registry;
use local_lms_dashboard_builder\query\query_manager;
use local_lms_dashboard_builder\query\grouping_engine;

$context = context_system::instance();
require_login();
require_capability('local/lms_dashboard_builder:manage', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$baseurl = new moodle_url('/local/lms_dashboard_builder/queries.php');
$manageurl = new moodle_url('/local/lms_dashboard_builder/manage.php');

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manage_queries', 'local_lms_dashboard_builder'));
$PAGE->set_heading(get_string('pluginname', 'local_lms_dashboard_builder'));

$canconfigure = has_capability('local/lms_dashboard_builder:configure', $context);

$ispost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($ispost && confirm_sesskey()) {
    if ($action === 'createvisual') {
        $def = [
            'source' => required_param('source', PARAM_ALPHANUMEXT),
            'dimension' => required_param('dimension', PARAM_RAW),
            'metric' => required_param('metric', PARAM_RAW),
            'aggregation' => optional_param('aggregation', 'COUNT', PARAM_ALPHANUMEXT),
            'groupfunc' => optional_param('groupfunc', 'by_course', PARAM_ALPHANUMEXT),
            'sortdir' => optional_param('sortdir', 'DESC', PARAM_ALPHA),
            'limit' => optional_param('limit', 20, PARAM_INT),
            'context_filter' => optional_param('context_filter', 'none', PARAM_ALPHANUMEXT),
            'user_filter' => optional_param('user_filter', 'none', PARAM_ALPHANUMEXT),
        ];
        query_manager::save_query((object)[
            'name' => required_param('name', PARAM_TEXT),
            'querytype' => query_manager::TYPE_VISUAL,
            'querydefinition' => $def,
        ]);
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'createsql' && $canconfigure) {
        $def = [
            'groupfunc' => optional_param('groupfunc', '', PARAM_ALPHANUMEXT),
            'aggregation' => optional_param('aggregation', 'SUM', PARAM_ALPHANUMEXT),
            'context_filter' => optional_param('context_filter', 'none', PARAM_ALPHANUMEXT),
            'user_filter' => optional_param('user_filter', 'none', PARAM_ALPHANUMEXT),
        ];
        query_manager::save_query((object)[
            'name' => required_param('name', PARAM_TEXT),
            'querytype' => query_manager::TYPE_CUSTOM_SQL,
            'customsql' => required_param('customsql', PARAM_RAW),
            'querydefinition' => $def,
        ]);
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'delete' && $id > 0) {
        query_manager::delete_query($id);
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Built-in providers list.
$builtins = [];
foreach (provider_registry::get_all() as $prov) {
    if (strpos($prov->get_id(), 'custom_') === 0) {
        continue;
    }
    $builtins[] = [
        'id' => $prov->get_id(),
        'name' => $prov->get_name(),
        'description' => $prov->get_description(),
        'widgets' => implode(', ', $prov->get_supported_widgets()),
        'filters' => implode(', ', $prov->get_supported_filters()),
    ];
}

// Saved custom queries list.
$savedqueries = [];
foreach (query_manager::get_all_queries() as $q) {
    $savedqueries[] = [
        'id' => (int)$q->id,
        'providerid' => 'custom_' . (int)$q->id,
        'name' => format_string($q->name),
        'querytype' => $q->querytype,
        'issql' => ($q->querytype === query_manager::TYPE_CUSTOM_SQL),
        'summary' => ($q->querytype === query_manager::TYPE_CUSTOM_SQL)
            ? shorten_text((string)$q->customsql, 120)
            : (string)$q->querydefinition,
        'actionurl' => $baseurl->out(false),
        'sesskey' => sesskey(),
    ];
}

// Schema options for Visual Query Builder with source tagging for client-side cascading.
$sourceschema = query_manager::get_visual_sources_schema();
$sourceoptions = [];
$dimensionoptions = [];
$metricoptions = [];

foreach ($sourceschema as $skey => $smeta) {
    $sourceoptions[] = ['value' => $skey, 'label' => $smeta['label']];
    foreach ($smeta['dimensions'] as $dcol => $dlbl) {
        $dimensionoptions[] = [
            'value' => $dcol,
            'source' => $skey,
            'label' => '[' . $skey . '] ' . $dlbl . ' (' . $dcol . ')',
        ];
    }
    foreach ($smeta['metrics'] as $mcol => $mlbl) {
        $metricoptions[] = [
            'value' => $mcol,
            'source' => $skey,
            'label' => '[' . $skey . '] ' . $mlbl . ' (' . $mcol . ')',
        ];
    }
}

$groupingoptions = [];
foreach (grouping_engine::get_available_groupings() as $gid => $gmeta) {
    $groupingoptions[] = ['value' => $gid, 'label' => $gmeta['label']];
}

$aggoptions = [];
foreach (grouping_engine::get_allowed_aggregations() as $akey => $albl) {
    $aggoptions[] = ['value' => $akey, 'label' => $albl . ' (' . $akey . ')'];
}

$contextfilteroptions = [
    ['value' => 'none', 'label' => get_string('context_filter_none', 'local_lms_dashboard_builder')],
    ['value' => 'courseid', 'label' => get_string('context_filter_courseid', 'local_lms_dashboard_builder')],
    ['value' => 'categoryid', 'label' => get_string('context_filter_categoryid', 'local_lms_dashboard_builder')],
    ['value' => 'contextid', 'label' => get_string('context_filter_contextid', 'local_lms_dashboard_builder')],
];

$userfilteroptions = [
    ['value' => 'none', 'label' => get_string('user_filter_none', 'local_lms_dashboard_builder')],
    ['value' => 'userid', 'label' => get_string('user_filter_userid', 'local_lms_dashboard_builder')],
    ['value' => 'department', 'label' => get_string('user_filter_department', 'local_lms_dashboard_builder')],
    ['value' => 'institution', 'label' => get_string('user_filter_institution', 'local_lms_dashboard_builder')],
    ['value' => 'cohortid', 'label' => get_string('user_filter_cohortid', 'local_lms_dashboard_builder')],
];

$templatedata = [
    'actionurl' => $baseurl->out(false),
    'manageurl' => $manageurl->out(false),
    'sesskey' => sesskey(),
    'canconfigure' => $canconfigure,
    'builtins' => $builtins,
    'hassavedqueries' => !empty($savedqueries),
    'savedqueries' => $savedqueries,
    'sourceoptions' => $sourceoptions,
    'dimensionoptions' => $dimensionoptions,
    'metricoptions' => $metricoptions,
    'sourceschemajson' => json_encode($sourceschema),
    'groupingoptions' => $groupingoptions,
    'aggoptions' => $aggoptions,
    'contextfilteroptions' => $contextfilteroptions,
    'userfilteroptions' => $userfilteroptions,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_lms_dashboard_builder/manage_queries', $templatedata);
echo $OUTPUT->footer();
