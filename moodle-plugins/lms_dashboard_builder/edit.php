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
 * Interactive Dashboard Builder and Configuration page.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_lms_dashboard_builder\dashboard_manager;
use local_lms_dashboard_builder\widget_manager;
use local_lms_dashboard_builder\filter\filter_engine;
use local_lms_dashboard_builder\query\provider_registry;
use local_lms_dashboard_builder\query\grouping_engine;

$context = context_system::instance();
require_login();
require_capability('local/lms_dashboard_builder:manage', $context);

$dashboardid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$editwidgetid = optional_param('editwidgetid', 0, PARAM_INT);

$dashboard = dashboard_manager::get_dashboard($dashboardid);
if (!$dashboard) {
    throw new moodle_exception('error_dashboard_not_found', 'local_lms_dashboard_builder');
}

$baseurl = new moodle_url('/local/lms_dashboard_builder/edit.php', ['id' => $dashboardid]);
$manageurl = new moodle_url('/local/lms_dashboard_builder/manage.php');
$queriesurl = new moodle_url('/local/lms_dashboard_builder/queries.php');

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('edit_dashboard', 'local_lms_dashboard_builder') . ': ' . format_string($dashboard->name));
$PAGE->set_heading(get_string('pluginname', 'local_lms_dashboard_builder'));

$ispost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($ispost && confirm_sesskey()) {
    if ($action === 'savedashboard') {
        $upd = (object)[
            'name' => required_param('name', PARAM_TEXT),
            'description' => optional_param('description', '', PARAM_TEXT),
            'scope' => optional_param('scope', dashboard_manager::SCOPE_SYSTEM, PARAM_ALPHANUMEXT),
            'category' => optional_param('category', get_string('default_category', 'local_lms_dashboard_builder'), PARAM_TEXT),
            'status' => optional_param('status', dashboard_manager::STATUS_PUBLISHED, PARAM_INT),
            'isdefault' => optional_param('isdefault', 0, PARAM_INT),
            'cssclass' => optional_param('cssclass', '', PARAM_TEXT),
        ];
        dashboard_manager::update_dashboard($dashboardid, $upd);

        $roleids = optional_param_array('roleids', [], PARAM_INT);
        $ctxlevel = ($upd->scope === dashboard_manager::SCOPE_COURSE) ? CONTEXT_COURSE : CONTEXT_SYSTEM;
        dashboard_manager::set_dashboard_roles($dashboardid, $roleids, $ctxlevel);

        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'addpreset') {
        $presetkey = required_param('presetkey', PARAM_ALPHANUMEXT);
        $presets = widget_manager::get_prebuilt_widget_presets();
        if (isset($presets[$presetkey])) {
            $preset = $presets[$presetkey];
            widget_manager::add_widget((object)[
                'dashboardid' => $dashboardid,
                'title' => $preset['title'],
                'widgettype' => $preset['widgettype'],
                'queryid' => $preset['queryid'],
                'grid_width' => $preset['grid_width'],
                'configdata' => $preset['configdata'],
            ]);
        }
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'addwidget') {
        $customcolorsraw = trim(optional_param('custom_colors', '', PARAM_TEXT));
        $customcolors = [];
        if ($customcolorsraw !== '') {
            foreach (explode(',', $customcolorsraw) as $hex) {
                $hex = trim($hex);
                if ($hex !== '') {
                    $customcolors[] = $hex;
                }
            }
        }

        $configdata = [
            'charttype' => optional_param('charttype', 'bar', PARAM_ALPHANUMEXT),
            'palette' => optional_param('palette', 'modern', PARAM_ALPHA),
            'customColors' => $customcolors,
            'groupby' => optional_param('groupby', '', PARAM_ALPHANUMEXT),
            'metric' => optional_param('metric', '', PARAM_ALPHANUMEXT),
            'xAxisLabel' => optional_param('xaxis_label', '', PARAM_TEXT),
            'yAxisLabel' => optional_param('yaxis_label', '', PARAM_TEXT),
            'legendPosition' => optional_param('legend_position', 'bottom', PARAM_ALPHA),
            'suffix' => optional_param('suffix', '', PARAM_TEXT),
            'accent' => optional_param('accent', 'primary', PARAM_ALPHA),
            'icon' => optional_param('icon', 'fa-chart-bar', PARAM_TEXT),
            'content' => optional_param('text_content', '', PARAM_TEXT),
        ];

        widget_manager::add_widget((object)[
            'dashboardid' => $dashboardid,
            'title' => required_param('title', PARAM_TEXT),
            'widgettype' => required_param('widgettype', PARAM_ALPHANUMEXT),
            'queryid' => optional_param('queryid', 'course_stats', PARAM_ALPHANUMEXT),
            'grid_width' => optional_param('grid_width', 6, PARAM_INT),
            'sortorder' => optional_param('sortorder', 0, PARAM_INT),
            'cssclass' => optional_param('cssclass', '', PARAM_TEXT),
            'configdata' => $configdata,
        ]);
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'updatewidget') {
        $widgetid = required_param('widgetid', PARAM_INT);
        $existingwidget = widget_manager::get_widget($widgetid);
        if ($existingwidget && (int)$existingwidget->dashboardid === $dashboardid) {
            $existingcfg = !empty($existingwidget->configdata) ? (json_decode($existingwidget->configdata, true) ?: []) : [];

            $customcolorsraw = trim(optional_param('custom_colors', '', PARAM_TEXT));
            $customcolors = [];
            if ($customcolorsraw !== '') {
                foreach (explode(',', $customcolorsraw) as $hex) {
                    $hex = trim($hex);
                    if ($hex !== '') {
                        $customcolors[] = $hex;
                    }
                }
            }

            $configdata = array_merge($existingcfg, [
                'charttype' => optional_param('charttype', $existingcfg['charttype'] ?? 'bar', PARAM_ALPHANUMEXT),
                'palette' => optional_param('palette', $existingcfg['palette'] ?? 'modern', PARAM_ALPHA),
                'customColors' => $customcolors,
                'groupby' => optional_param('groupby', '', PARAM_ALPHANUMEXT),
                'metric' => optional_param('metric', '', PARAM_ALPHANUMEXT),
                'xAxisLabel' => optional_param('xaxis_label', '', PARAM_TEXT),
                'yAxisLabel' => optional_param('yaxis_label', '', PARAM_TEXT),
                'legendPosition' => optional_param('legend_position', $existingcfg['legendPosition'] ?? 'bottom', PARAM_ALPHA),
                'suffix' => optional_param('suffix', '', PARAM_TEXT),
            ]);

            $upd = (object)[
                'title' => required_param('title', PARAM_TEXT),
                'widgettype' => required_param('widgettype', PARAM_ALPHANUMEXT),
                'queryid' => optional_param('queryid', 'course_stats', PARAM_ALPHANUMEXT),
                'grid_width' => optional_param('grid_width', 6, PARAM_INT),
                'sortorder' => optional_param('sortorder', (int)$existingwidget->sortorder, PARAM_INT),
                'cssclass' => optional_param('cssclass', '', PARAM_TEXT),
                'configdata' => $configdata,
            ];
            widget_manager::update_widget($widgetid, $upd);
        }
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'movewidget') {
        $widgetid = required_param('widgetid', PARAM_INT);
        $direction = required_param('direction', PARAM_ALPHA);
        $existingwidget = widget_manager::get_widget($widgetid);
        if ($existingwidget && (int)$existingwidget->dashboardid === $dashboardid && in_array($direction, ['up', 'down'], true)) {
            widget_manager::move_widget($widgetid, $direction);
        }
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'reorderwidgets') {
        $widgetids = optional_param_array('widgetids', [], PARAM_INT);
        if (!empty($widgetids)) {
            widget_manager::reorder_widgets($dashboardid, $widgetids);
        }
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'deletewidget') {
        $widgetid = required_param('widgetid', PARAM_INT);
        $existingwidget = widget_manager::get_widget($widgetid);
        if ($existingwidget && (int)$existingwidget->dashboardid === $dashboardid) {
            widget_manager::delete_widget($widgetid);
        }
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'addfilter') {
        $filtertype = required_param('filtertype', PARAM_ALPHANUMEXT);
        $filterconfig = [
            'paramkey' => optional_param('custom_paramkey', '', PARAM_ALPHANUMEXT),
            'inputtype' => optional_param('custom_inputtype', 'select', PARAM_ALPHA),
            'options' => optional_param('custom_options', '', PARAM_TEXT),
            'context_field' => optional_param('context_field', 'courseid', PARAM_ALPHANUMEXT),
            'user_field' => optional_param('user_field', 'userid', PARAM_ALPHANUMEXT),
            'param_name' => optional_param('request_param_name', '', PARAM_ALPHANUMEXT),
            'visibility' => optional_param('request_visibility', 'backend', PARAM_ALPHA),
        ];
        filter_engine::add_filter((object)[
            'dashboardid' => $dashboardid,
            'filtertype' => $filtertype,
            'label' => required_param('label', PARAM_TEXT),
            'defaultvalue' => optional_param('defaultvalue', '', PARAM_TEXT),
            'sortorder' => optional_param('sortorder', 1, PARAM_INT),
            'configdata' => $filterconfig,
        ]);
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'deletefilter') {
        $filterid = required_param('filterid', PARAM_INT);
        filter_engine::delete_filter($filterid);
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$layout = dashboard_manager::get_full_dashboard_layout($dashboardid, $context);
$assignedroles = dashboard_manager::get_dashboard_roles($dashboardid);
$ispublicvisitor = in_array(dashboard_manager::ROLE_PUBLIC_VISITOR, $assignedroles, true);

// Resolve widget currently being edited (if any).
$editingwidget = null;
$editcfg = [];
if ($editwidgetid > 0) {
    $editrec = widget_manager::get_widget($editwidgetid);
    if ($editrec && (int)$editrec->dashboardid === $dashboardid) {
        $editcfg = !empty($editrec->configdata) ? (json_decode($editrec->configdata, true) ?: []) : [];
        $customcolorsstr = '';
        if (!empty($editcfg['customColors']) && is_array($editcfg['customColors'])) {
            $customcolorsstr = implode(',', $editcfg['customColors']);
        }
        $editingwidget = [
            'id' => (int)$editrec->id,
            'title' => $editrec->title,
            'widgettype' => $editrec->widgettype,
            'queryid' => (string)$editrec->queryid,
            'cssclass' => (string)($editrec->cssclass ?? ''),
            'grid_width' => (int)$editrec->grid_width,
            'sortorder' => (int)$editrec->sortorder,
            'charttype' => (string)($editcfg['charttype'] ?? 'bar'),
            'palette' => (string)($editcfg['palette'] ?? 'modern'),
            'groupby' => (string)($editcfg['groupby'] ?? ''),
            'xaxis_label' => (string)($editcfg['xAxisLabel'] ?? ''),
            'yaxis_label' => (string)($editcfg['yAxisLabel'] ?? ''),
            'metric' => (string)($editcfg['metric'] ?? ''),
            'suffix' => (string)($editcfg['suffix'] ?? ''),
            'legend_position' => (string)($editcfg['legendPosition'] ?? 'bottom'),
            'custom_colors' => $customcolorsstr,
        ];
    }
}

// Build role options including special Public Visitor role.
$allroles = $DB->get_records('role', null, 'sortorder ASC', 'id, name, shortname');
$roleoptions = [
    [
        'id' => dashboard_manager::ROLE_PUBLIC_VISITOR,
        'label' => get_string('role_everyone_visitor', 'local_lms_dashboard_builder'),
        'checked' => $ispublicvisitor,
        'ispublic' => true,
    ],
];
foreach ($allroles as $r) {
    $rlabel = !empty($r->name) ? format_string($r->name) : ucfirst($r->shortname);
    $roleoptions[] = [
        'id' => (int)$r->id,
        'label' => $rlabel . ' (' . $r->shortname . ')',
        'checked' => in_array((int)$r->id, $assignedroles, true),
        'ispublic' => false,
    ];
}

// Build preset options with visitor_safe flag for cascading.
$presetoptions = [];
foreach (widget_manager::get_prebuilt_widget_presets() as $pkey => $pmeta) {
    $presetoptions[] = [
        'key' => $pkey,
        'label' => $pmeta['title'] . ' (' . strtoupper($pmeta['widgettype']) . ')',
        'visitorsafe' => !empty($pmeta['visitor_safe']),
    ];
}

// Build widget type, grid width, chart type, palette, legend, query provider, and grouping options.
$selwidgettype = $editingwidget['widgettype'] ?? widget_manager::TYPE_KPI;
$widgettypeoptions = [];
foreach (widget_manager::get_widget_types_catalog() as $wkey => $wlabel) {
    $widgettypeoptions[] = [
        'value' => $wkey,
        'label' => $wlabel,
        'selected' => ($wkey === $selwidgettype),
    ];
}

$selgridwidth = (int)($editingwidget['grid_width'] ?? 6);
$gridwidthcatalog = [
    3 => get_string('grid_col_3', 'local_lms_dashboard_builder'),
    4 => get_string('grid_col_4', 'local_lms_dashboard_builder'),
    6 => get_string('grid_col_6', 'local_lms_dashboard_builder'),
    8 => get_string('grid_col_8', 'local_lms_dashboard_builder'),
    12 => get_string('grid_col_12', 'local_lms_dashboard_builder'),
];
$gridwidthoptions = [];
foreach ($gridwidthcatalog as $gwval => $gwlabel) {
    $gridwidthoptions[] = [
        'value' => $gwval,
        'label' => $gwlabel,
        'selected' => ($gwval === $selgridwidth),
    ];
}

$selcharttype = $editingwidget['charttype'] ?? 'bar';
$charttypeoptions = [];
foreach (widget_manager::get_chart_types_catalog() as $ckey => $clabel) {
    $charttypeoptions[] = [
        'value' => $ckey,
        'label' => $clabel,
        'selected' => ($ckey === $selcharttype),
    ];
}

$selpalette = $editingwidget['palette'] ?? 'modern';
$palettecatalog = [
    'modern' => get_string('palette_modern', 'local_lms_dashboard_builder'),
    'ocean' => get_string('palette_ocean', 'local_lms_dashboard_builder'),
    'emerald' => get_string('palette_emerald', 'local_lms_dashboard_builder'),
    'sunset' => get_string('palette_sunset', 'local_lms_dashboard_builder'),
    'monochrome' => get_string('palette_monochrome', 'local_lms_dashboard_builder'),
];
$paletteoptions = [];
foreach ($palettecatalog as $palkey => $pallabel) {
    $paletteoptions[] = [
        'value' => $palkey,
        'label' => $pallabel,
        'selected' => ($palkey === $selpalette),
    ];
}

$sellegend = $editingwidget['legend_position'] ?? 'bottom';
$legendcatalog = [
    'bottom' => get_string('legend_bottom', 'local_lms_dashboard_builder'),
    'top' => get_string('legend_top', 'local_lms_dashboard_builder'),
    'right' => get_string('legend_right', 'local_lms_dashboard_builder'),
    'none' => get_string('legend_none', 'local_lms_dashboard_builder'),
];
$legendoptions = [];
foreach ($legendcatalog as $legkey => $leglabel) {
    $legendoptions[] = [
        'value' => $legkey,
        'label' => $leglabel,
        'selected' => ($legkey === $sellegend),
    ];
}

$selqueryid = $editingwidget['queryid'] ?? 'course_stats';
$provideroptions = [];
foreach (provider_registry::get_all() as $prov) {
    $provideroptions[] = [
        'value' => $prov->get_id(),
        'label' => $prov->get_name() . ' [' . $prov->get_id() . ']',
        'selected' => ($prov->get_id() === $selqueryid),
    ];
}

$selgroupby = $editingwidget['groupby'] ?? '';
$groupingoptions = [];
foreach (grouping_engine::get_available_groupings() as $gid => $gmeta) {
    $groupingoptions[] = [
        'value' => $gid,
        'label' => $gmeta['label'],
        'selected' => ($gid === $selgroupby),
    ];
}

$filtertypeoptions = [];
foreach (filter_engine::get_filter_types_catalog() as $fkey => $flabel) {
    $filtertypeoptions[] = ['value' => $fkey, 'label' => $flabel];
}

// Enrich configured widgets with position, reorder state, and edit URLs.
$configuredwidgets = [];
$totalwidgets = count($layout['widgets']);
foreach (array_values($layout['widgets']) as $idx => $w) {
    $editlink = new moodle_url(
        '/local/lms_dashboard_builder/edit.php',
        ['id' => $dashboardid, 'editwidgetid' => $w['id']],
        'widget-editor-card'
    );
    $w['position'] = $idx + 1;
    $w['isfirst'] = ($idx === 0);
    $w['islast'] = ($idx === $totalwidgets - 1);
    $w['isediting'] = ($editwidgetid > 0 && (int)$w['id'] === $editwidgetid);
    $w['editurl'] = $editlink->out(false);
    $w['actionurl'] = $baseurl->out(false);
    $w['sesskey'] = sesskey();
    $configuredwidgets[] = $w;
}

// Initialize live preview AMD controller.
$instanceid = 'preview-' . $dashboardid;
$PAGE->requires->js_call_amd(
    'local_lms_dashboard_builder/dashboard_view',
    'init',
    [$dashboardid, $context->id, ['daterange' => '30d'], $instanceid]
);

$templatedata = [
    'actionurl' => $baseurl->out(false),
    'manageurl' => $manageurl->out(false),
    'queriesurl' => $queriesurl->out(false),
    'sesskey' => sesskey(),
    'dashboardid' => $dashboardid,
    'instanceid' => $instanceid,
    'name' => $dashboard->name,
    'description' => $dashboard->description ?? '',
    'category' => $dashboard->category ?? get_string('default_category', 'local_lms_dashboard_builder'),
    'cssclass' => (string)($dashboard->cssclass ?? ''),
    'scope_course' => ($dashboard->scope ?? '') === dashboard_manager::SCOPE_COURSE,
    'scope_system' => ($dashboard->scope ?? 'system') === dashboard_manager::SCOPE_SYSTEM,
    'scope_category' => ($dashboard->scope ?? '') === dashboard_manager::SCOPE_CATEGORY,
    'scope_my' => ($dashboard->scope ?? '') === dashboard_manager::SCOPE_MY,
    'status_published' => (int)$dashboard->status === dashboard_manager::STATUS_PUBLISHED,
    'status_draft' => (int)$dashboard->status === dashboard_manager::STATUS_DRAFT,
    'status_archived' => (int)$dashboard->status === dashboard_manager::STATUS_ARCHIVED,
    'isdefault' => !empty($dashboard->isdefault),
    'ispublicvisitor' => $ispublicvisitor,
    'roleoptions' => $roleoptions,
    'presetoptions' => $presetoptions,
    'widgettypeoptions' => $widgettypeoptions,
    'gridwidthoptions' => $gridwidthoptions,
    'charttypeoptions' => $charttypeoptions,
    'paletteoptions' => $paletteoptions,
    'legendoptions' => $legendoptions,
    'provideroptions' => $provideroptions,
    'groupingoptions' => $groupingoptions,
    'filtertypeoptions' => $filtertypeoptions,
    'providermetricscatalogjson' => json_encode(widget_manager::get_provider_metrics_catalog()),
    'editingwidget' => $editingwidget,
    'haswidgets' => !empty($configuredwidgets),
    'widgets' => $configuredwidgets,
    'hasfilters' => !empty($layout['filters']),
    'filters' => $layout['filters'],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_lms_dashboard_builder/edit_dashboard', $templatedata);
echo $OUTPUT->footer();
