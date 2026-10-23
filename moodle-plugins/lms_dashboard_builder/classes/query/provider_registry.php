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

use local_lms_dashboard_builder\query\providers\enrollment_provider;
use local_lms_dashboard_builder\query\providers\completion_provider;
use local_lms_dashboard_builder\query\providers\assessment_provider;
use local_lms_dashboard_builder\query\providers\course_stats_provider;
use local_lms_dashboard_builder\query\providers\engagement_provider;
use local_lms_dashboard_builder\query\providers\learner_progress_provider;
use local_lms_dashboard_builder\query\providers\category_management_provider;
use local_lms_dashboard_builder\query\providers\public_kpis_provider;
use local_lms_dashboard_builder\query\providers\visual_query_provider;
use local_lms_dashboard_builder\query\providers\custom_sql_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Provider registry for managing and looking up built-in and custom query providers.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_registry {

    /** @var query_interface[] Cached instances. */
    private static array $providers = [];

    /**
     * Reset cached providers (e.g. after saving a custom query).
     */
    public static function reset(): void {
        self::$providers = [];
    }

    /**
     * Get all registered query providers (both built-in and saved custom queries).
     *
     * @return query_interface[]
     */
    public static function get_all(): array {
        if (empty(self::$providers)) {
            self::register_defaults();
        }
        return self::$providers;
    }

    /**
     * Get a specific provider by its unique identifier (e.g., 'enrollment', 'public_kpis', or 'custom_1').
     *
     * @param string $id
     * @return query_interface|null
     */
    public static function get(string $id): ?query_interface {
        $all = self::get_all();
        if (isset($all[$id])) {
            return $all[$id];
        }

        // Dynamic lookup for saved custom queries by ID ('custom_12' or numeric '12').
        $customid = 0;
        if (strpos($id, 'custom_') === 0) {
            $customid = (int)substr($id, 7);
        } else if (is_numeric($id)) {
            $customid = (int)$id;
        }

        if ($customid > 0) {
            $record = query_manager::get_query($customid);
            if ($record) {
                $instance = ($record->querytype === query_manager::TYPE_CUSTOM_SQL)
                    ? new custom_sql_provider($record)
                    : new visual_query_provider($record);
                self::$providers[$instance->get_id()] = $instance;
                return $instance;
            }
        }

        return null;
    }

    /**
     * Register default built-in query providers and load saved custom queries.
     */
    private static function register_defaults(): void {
        $defaults = [
            new public_kpis_provider(),
            new course_stats_provider(),
            new enrollment_provider(),
            new completion_provider(),
            new assessment_provider(),
            new engagement_provider(),
            new learner_progress_provider(),
            new category_management_provider(),
        ];

        foreach ($defaults as $provider) {
            self::$providers[$provider->get_id()] = $provider;
        }

        try {
            $savedqueries = query_manager::get_all_queries();
            foreach ($savedqueries as $record) {
                $instance = ($record->querytype === query_manager::TYPE_CUSTOM_SQL)
                    ? new custom_sql_provider($record)
                    : new visual_query_provider($record);
                self::$providers[$instance->get_id()] = $instance;
            }
        } catch (\Throwable $e) {
            // Ignore if table is not yet installed during early bootstrap.
        }
    }
}
