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

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for dashboard_manager and widget_manager classes.
 *
 * @package    local_lms_dashboard_builder
 * @category   test
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard_manager_test extends \advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest(true);
    }

    /**
     * Test creating, retrieving, and updating a dashboard with scope.
     */
    public function test_create_and_update_dashboard(): void {
        $this->setAdminUser();

        $data = (object)[
            'name' => 'Site Overview',
            'description' => 'Platform wide statistics',
            'scope' => dashboard_manager::SCOPE_COURSE,
            'status' => dashboard_manager::STATUS_PUBLISHED,
        ];

        $id = dashboard_manager::create_dashboard($data);
        $this->assertGreaterThan(0, $id);

        $record = dashboard_manager::get_dashboard($id);
        $this->assertEquals('Site Overview', $record->name);
        $this->assertEquals(dashboard_manager::SCOPE_COURSE, $record->scope);
        $this->assertEquals(dashboard_manager::STATUS_PUBLISHED, (int)$record->status);

        // Update dashboard.
        $updatedata = (object)[
            'name' => 'Updated Site Overview',
            'scope' => dashboard_manager::SCOPE_SYSTEM,
            'status' => dashboard_manager::STATUS_DRAFT,
        ];
        dashboard_manager::update_dashboard($id, $updatedata);

        $updated = dashboard_manager::get_dashboard($id);
        $this->assertEquals('Updated Site Overview', $updated->name);
        $this->assertEquals(dashboard_manager::SCOPE_SYSTEM, $updated->scope);
        $this->assertEquals(dashboard_manager::STATUS_DRAFT, (int)$updated->status);
    }

    /**
     * Test role mapping assignment and duplication.
     */
    public function test_role_mapping_and_duplication(): void {
        $this->setAdminUser();

        $id = dashboard_manager::create_dashboard((object)[
            'name' => 'Teacher Dashboard',
            'scope' => dashboard_manager::SCOPE_COURSE,
            'status' => dashboard_manager::STATUS_PUBLISHED,
        ]);

        dashboard_manager::set_dashboard_roles($id, [3, 4], CONTEXT_COURSE);
        $roles = dashboard_manager::get_dashboard_roles($id);
        $this->assertEquals([3, 4], $roles);

        widget_manager::add_widget((object)[
            'dashboardid' => $id,
            'title' => 'Enrollment KPI',
            'widgettype' => 'kpi',
            'queryid' => 'enrollment',
        ]);

        $newid = dashboard_manager::duplicate_dashboard($id);
        $this->assertEquals([3, 4], dashboard_manager::get_dashboard_roles($newid));
        $this->assertCount(1, widget_manager::get_widgets_for_dashboard($newid));
    }

    /**
     * Test JSON export and import of a dashboard with widgets and filters.
     */
    public function test_export_and_import_dashboard_json(): void {
        $this->setAdminUser();

        $id = dashboard_manager::create_dashboard((object)[
            'name' => 'Exportable Course Dashboard',
            'scope' => dashboard_manager::SCOPE_COURSE,
            'status' => dashboard_manager::STATUS_PUBLISHED,
        ]);

        widget_manager::add_widget((object)[
            'dashboardid' => $id,
            'title' => 'Completion Ring',
            'widgettype' => 'ring',
            'queryid' => 'course_stats',
        ]);

        $json = dashboard_manager::export_dashboard_json($id);
        $this->assertNotEmpty($json);

        $importedid = dashboard_manager::import_dashboard_json($json);
        $this->assertNotEquals($id, $importedid);

        $imported = dashboard_manager::get_dashboard($importedid);
        $this->assertEquals('Exportable Course Dashboard', $imported->name);
        $this->assertCount(1, widget_manager::get_widgets_for_dashboard($importedid));
    }

    /**
     * Test default dashboard seeder and pre-built widget catalogs.
     */
    public function test_seed_default_dashboards_and_catalogs(): void {
        $this->setAdminUser();

        dashboard_manager::seed_default_dashboards(true);
        $dashboards = dashboard_manager::get_dashboards(0, true);
        $this->assertGreaterThanOrEqual(4, count($dashboards));

        $widgettypes = widget_manager::get_widget_types_catalog();
        $this->assertCount(10, $widgettypes);

        $charttypes = widget_manager::get_chart_types_catalog();
        $this->assertArrayHasKey('horizontalBar', $charttypes);
        $this->assertArrayHasKey('stackedBar', $charttypes);
        $this->assertArrayHasKey('gauge', $charttypes);
        $this->assertArrayHasKey('combo', $charttypes);

        $presets = widget_manager::get_prebuilt_widget_presets();
        $this->assertNotEmpty($presets);
    }

    /**
     * Test deleting a dashboard cascades to dependent records.
     */
    public function test_delete_dashboard(): void {
        $this->setAdminUser();

        $id = dashboard_manager::create_dashboard((object)[
            'name' => 'To Be Deleted',
            'status' => dashboard_manager::STATUS_DRAFT,
        ]);

        widget_manager::add_widget((object)[
            'dashboardid' => $id,
            'title' => 'Temporary Widget',
            'widgettype' => 'kpi',
            'queryid' => 'enrollment',
        ]);

        $result = dashboard_manager::delete_dashboard($id);
        $this->assertTrue($result);
        $this->assertFalse(dashboard_manager::get_dashboard($id));
        $this->assertEmpty(widget_manager::get_widgets_for_dashboard($id));
    }
}
