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
 * Administrative dashboard management catalog page.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_lms_dashboard_builder\dashboard_manager;
use local_lms_dashboard_builder\widget_manager;
use local_lms_dashboard_builder\filter\filter_engine;

$context = context_system::instance();
require_login();
require_capability('local/lms_dashboard_builder:manage', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$baseurl = new moodle_url('/local/lms_dashboard_builder/manage.php');
$editurl = new moodle_url('/local/lms_dashboard_builder/edit.php');
$queriesurl = new moodle_url('/local/lms_dashboard_builder/queries.php');

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manage_dashboards', 'local_lms_dashboard_builder'));
$PAGE->set_heading(get_string('pluginname', 'local_lms_dashboard_builder'));

// Handle JSON export download via GET with sesskey.
if ($action === 'export' && $id > 0 && confirm_sesskey()) {
    $json = dashboard_manager::export_dashboard_json($id);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="lms_dashboard_' . $id . '.json"');
    echo $json;
    exit;
}

// Handle state-mutating actions via POST with sesskey validation.
$ispost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($ispost && confirm_sesskey()) {
    if ($action === 'delete' && $id > 0) {
        dashboard_manager::delete_dashboard($id);
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'duplicate' && $id > 0) {
        $newid = dashboard_manager::duplicate_dashboard($id);
        redirect(new moodle_url('/local/lms_dashboard_builder/edit.php', ['id' => $newid]), get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'togglepublish' && $id > 0) {
        $existing = dashboard_manager::get_dashboard($id);
        if ($existing) {
            $newstatus = ((int)$existing->status === dashboard_manager::STATUS_PUBLISHED)
                ? dashboard_manager::STATUS_DRAFT
                : dashboard_manager::STATUS_PUBLISHED;
            dashboard_manager::update_dashboard($id, (object)['status' => $newstatus]);
        }
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'create') {
        $defaultname = get_string('new_dashboard_default_name', 'local_lms_dashboard_builder');
        $defaultcat = get_string('default_category', 'local_lms_dashboard_builder');
        $data = (object)[
            'name' => optional_param('name', $defaultname, PARAM_TEXT) ?: $defaultname,
            'description' => optional_param('description', '', PARAM_TEXT),
            'scope' => optional_param('scope', dashboard_manager::SCOPE_SYSTEM, PARAM_ALPHANUMEXT),
            'category' => optional_param('category', $defaultcat, PARAM_TEXT) ?: $defaultcat,
            'status' => optional_param('status', dashboard_manager::STATUS_PUBLISHED, PARAM_INT),
            'cssclass' => optional_param('cssclass', '', PARAM_TEXT),
        ];
        $newid = dashboard_manager::create_dashboard($data);
        // Add a default date range global filter on new dashboards.
        filter_engine::add_filter((object)[
            'dashboardid' => $newid,
            'filtertype' => 'daterange',
            'label' => get_string('filter_daterange', 'local_lms_dashboard_builder'),
            'defaultvalue' => '30d',
            'sortorder' => 1,
        ]);
        redirect(new moodle_url('/local/lms_dashboard_builder/edit.php', ['id' => $newid]), get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'seeddefaults') {
        dashboard_manager::seed_default_dashboards(true);
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'import') {
        $importjson = optional_param('importjson', '', PARAM_RAW);
        if (!empty(trim($importjson))) {
            $importedid = dashboard_manager::import_dashboard_json($importjson);
            redirect(new moodle_url('/local/lms_dashboard_builder/edit.php', ['id' => $importedid]), get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

// Auto-seed starter templates if no dashboards exist yet.
$dashboards = dashboard_manager::get_dashboards(0, false);
if (empty($dashboards)) {
    dashboard_manager::seed_default_dashboards(false);
    $dashboards = dashboard_manager::get_dashboards(0, false);
}

$rolesmenu = $DB->get_records_menu('role', null, '', 'id, shortname');
$dashboardlist = [];

foreach ($dashboards as $d) {
    $widgets = widget_manager::get_widgets_for_dashboard((int)$d->id);
    $filters = filter_engine::get_dashboard_filters((int)$d->id);
    $roleids = dashboard_manager::get_dashboard_roles((int)$d->id);
    $rolenames = [];
    $ispublicvisitor = false;
    foreach ($roleids as $rid) {
        if ($rid === dashboard_manager::ROLE_PUBLIC_VISITOR) {
            $rolenames[] = get_string('role_everyone_visitor', 'local_lms_dashboard_builder');
            $ispublicvisitor = true;
        } else if (isset($rolesmenu[$rid])) {
            $rolenames[] = ucfirst($rolesmenu[$rid]);
        }
    }

    $scope = $d->scope ?? dashboard_manager::SCOPE_SYSTEM;
    $dashboardlist[] = [
        'id' => (int)$d->id,
        'name' => format_string($d->name),
        'description' => format_string((string)($d->description ?? '')),
        'scope' => ucfirst($scope),
        'iscoursescope' => ($scope === dashboard_manager::SCOPE_COURSE),
        'category' => format_string($d->category ?: get_string('default_category', 'local_lms_dashboard_builder')),
        'cssclass' => (string)($d->cssclass ?? ''),
        'ispublicvisitor' => $ispublicvisitor,
        'widgetcount' => count($widgets),
        'filtercount' => count($filters),
        'roleslabel' => !empty($rolenames) ? implode(', ', $rolenames) : get_string('all_permitted_roles', 'local_lms_dashboard_builder'),
        'ispublished' => (int)$d->status === dashboard_manager::STATUS_PUBLISHED,
        'isdraft' => (int)$d->status === dashboard_manager::STATUS_DRAFT,
        'isarchived' => (int)$d->status === dashboard_manager::STATUS_ARCHIVED,
        'editlink' => (new moodle_url('/local/lms_dashboard_builder/edit.php', ['id' => $d->id]))->out(false),
        'exportlink' => (new moodle_url('/local/lms_dashboard_builder/manage.php', ['action' => 'export', 'id' => $d->id, 'sesskey' => sesskey()]))->out(false),
        'actionurl' => $baseurl->out(false),
        'sesskey' => sesskey(),
    ];
}

$templatedata = [
    'actionurl' => $baseurl->out(false),
    'queriesurl' => $queriesurl->out(false),
    'sesskey' => sesskey(),
    'hasdashboards' => !empty($dashboardlist),
    'dashboards' => $dashboardlist,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_lms_dashboard_builder/manage_dashboards', $templatedata);
echo $OUTPUT->footer();
