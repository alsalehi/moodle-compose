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
 * Interface defining contract for all dashboard query providers.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface query_interface {

    /**
     * Unique identifier for the provider.
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Human-readable name.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Provider description.
     *
     * @return string
     */
    public function get_description(): string;

    /**
     * List of widget types supported by this query provider.
     *
     * @return array
     */
    public function get_supported_widgets(): array;

    /**
     * List of filter types accepted by this provider.
     *
     * @return array
     */
    public function get_supported_filters(): array;

    /**
     * Execute the query and return standardized structured data.
     *
     * @param array $filters Validated filter inputs.
     * @param \context $context Context in which query is executed.
     * @return array Formatted result containing metrics, series, and tables.
     */
    public function execute(array $filters, \context $context): array;
}
