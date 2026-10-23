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

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for query providers, provider registry, custom query manager, and grouping engine.
 *
 * @package    local_lms_dashboard_builder
 * @category   test
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_provider_test extends \advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest(true);
        provider_registry::reset();
    }

    /**
     * Test provider registry returns all 7 built-in providers.
     */
    public function test_provider_registry_contains_builtins(): void {
        $providers = provider_registry::get_all();

        $this->assertArrayHasKey('enrollment', $providers);
        $this->assertArrayHasKey('completion', $providers);
        $this->assertArrayHasKey('assessment', $providers);
        $this->assertArrayHasKey('course_stats', $providers);
        $this->assertArrayHasKey('engagement', $providers);
        $this->assertArrayHasKey('learner_progress', $providers);
        $this->assertArrayHasKey('category_management', $providers);
    }

    /**
     * Test grouping engine time buckets, grade bands, custom grouping callbacks, and aggregations.
     */
    public function test_grouping_engine_buckets_and_aggregations(): void {
        $ts = gmmktime(12, 0, 0, 5, 15, 2026);
        $this->assertEquals('2026-05-15', grouping_engine::format_bucket('time_day', $ts));
        $this->assertEquals('2026-05', grouping_engine::format_bucket('time_month', $ts));
        $this->assertEquals('2026-Q2', grouping_engine::format_bucket('time_quarter', $ts));
        $this->assertEquals('2026', grouping_engine::format_bucket('time_year', $ts));

        // Grade and progress bands.
        $this->assertEquals('0-49% (Fail)', grouping_engine::format_bucket('by_grade_band', 42));
        $this->assertEquals('85-100% (Distinction)', grouping_engine::format_bucket('by_grade_band', 91));
        $this->assertEquals('100% (Completed)', grouping_engine::format_bucket('by_progress_band', 100));

        // Custom registered grouping callback.
        grouping_engine::register_grouping('custom_tier', 'By Tier', static function($v) {
            return ((int)$v >= 10) ? 'Enterprise' : 'Standard';
        });
        $this->assertEquals('Enterprise', grouping_engine::format_bucket('custom_tier', 15));

        // Aggregations.
        $vals = [10, 20, 20, 30];
        $this->assertEquals(4, grouping_engine::aggregate_values($vals, 'COUNT'));
        $this->assertEquals(3, grouping_engine::aggregate_values($vals, 'COUNT_DISTINCT'));
        $this->assertEquals(80, grouping_engine::aggregate_values($vals, 'SUM'));
        $this->assertEquals(20, grouping_engine::aggregate_values($vals, 'AVG'));
        $this->assertEquals(10, grouping_engine::aggregate_values($vals, 'MIN'));
        $this->assertEquals(30, grouping_engine::aggregate_values($vals, 'MAX'));
    }

    /**
     * Test creating and resolving Visual and Custom SQL queries via query_manager and provider_registry.
     */
    public function test_custom_visual_and_sql_queries(): void {
        $this->setAdminUser();

        $visid = query_manager::save_query((object)[
            'name' => 'Courses by Format',
            'querytype' => query_manager::TYPE_VISUAL,
            'querydefinition' => [
                'source' => 'course',
                'dimension' => 'c.format',
                'metric' => 'c.id',
                'aggregation' => 'COUNT',
                'groupfunc' => 'by_course',
            ],
        ]);

        $provider = provider_registry::get('custom_' . $visid);
        $this->assertNotNull($provider);
        $this->assertEquals('Courses by Format', $provider->get_name());

        $sqlid = query_manager::save_query((object)[
            'name' => 'Safe Custom SQL Count',
            'querytype' => query_manager::TYPE_CUSTOM_SQL,
            'customsql' => 'SELECT shortname, id FROM {course} WHERE id >= 1',
        ]);

        $sqlprovider = provider_registry::get('custom_' . $sqlid);
        $this->assertNotNull($sqlprovider);
    }
}
