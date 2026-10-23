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

use local_lms_dashboard_builder\dashboard_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for filter_engine class (per-dashboard global filters, custom filters, and widget overrides).
 *
 * @package    local_lms_dashboard_builder
 * @category   test
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_engine_test extends \advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest(true);
    }

    /**
     * Test filter sanitization falls back to valid defaults on invalid input.
     */
    public function test_sanitize_filters_defaults(): void {
        $context = \context_system::instance();

        $raw = [
            'daterange' => 'invalid_period_123',
            'courseid'  => '0',
            'groupby'   => 'time_month',
            'filter_department' => 'STEM',
        ];

        $clean = filter_engine::sanitize_filters($raw, $context);

        $this->assertEquals('30d', $clean['daterange']);
        $this->assertEquals(0, $clean['courseid']);
        $this->assertEquals('time_month', $clean['groupby']);
        $this->assertEquals('STEM', $clean['filter_department']);
    }

    /**
     * Test allowed date ranges and widget filter overrides.
     */
    public function test_sanitize_and_merge_widget_filters(): void {
        $context = \context_system::instance();

        foreach (['7d', '30d', '90d', 'quarter', 'year', 'all'] as $period) {
            $clean = filter_engine::sanitize_filters(['daterange' => $period], $context);
            $this->assertEquals($period, $clean['daterange']);
        }

        // Widget config ignoring daterange and providing default groupby.
        $global = filter_engine::sanitize_filters(['daterange' => '7d'], $context);
        $widgetcfg = [
            'ignore_filters' => ['daterange'],
            'groupby' => 'by_grade_band',
        ];
        $merged = filter_engine::merge_widget_filters($global, $widgetcfg, $context);
        $this->assertEquals('all', $merged['daterange']);
        $this->assertEquals('by_grade_band', $merged['groupby']);
    }

    /**
     * Test per-dashboard filter CRUD.
     */
    public function test_dashboard_filter_crud(): void {
        $this->setAdminUser();
        $dashid = dashboard_manager::create_dashboard((object)['name' => 'Filter Test']);

        $fid = filter_engine::add_filter((object)[
            'dashboardid' => $dashid,
            'filtertype' => 'custom',
            'label' => 'Department',
            'defaultvalue' => 'stem',
            'configdata' => ['paramkey' => 'dept', 'options' => 'stem:STEM,arts:Arts'],
        ]);

        $filters = filter_engine::get_dashboard_filters($dashid);
        $this->assertCount(1, $filters);

        filter_engine::delete_filter($fid);
        $this->assertEmpty(filter_engine::get_dashboard_filters($dashid));
    }
}
