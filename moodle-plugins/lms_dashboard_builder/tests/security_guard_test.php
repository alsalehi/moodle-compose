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

namespace local_lms_dashboard_builder\security;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for security_guard class.
 *
 * @package    local_lms_dashboard_builder
 * @category   test
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class security_guard_test extends \advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest(true);
    }

    /**
     * Test safe SQL validation passes valid SELECT statements.
     */
    public function test_validate_safe_sql_success(): void {
        $valid1 = "SELECT id, fullname FROM {course} WHERE category = 1";
        $valid2 = "WITH stats AS (SELECT id FROM {user}) SELECT COUNT(*) FROM stats";

        $this->assertTrue(security_guard::validate_safe_sql($valid1));
        $this->assertTrue(security_guard::validate_safe_sql($valid2));
    }

    /**
     * Test safe SQL rejects destructive and prohibited keywords.
     */
    public function test_validate_safe_sql_rejects_destructive(): void {
        $queries = [
            "DELETE FROM {course}",
            "DROP TABLE mdl_user",
            "UPDATE {course} SET visible = 0",
            "INSERT INTO {user} (username) VALUES ('test')",
            "TRUNCATE TABLE {logstore_standard_log}",
            "SELECT id FROM {course}; DROP TABLE {course}",
            "SELECT * FROM {user} INTO OUTFILE '/tmp/dump.txt'",
            "SELECT id, password FROM {user}",
            "SELECT * FROM {config}",
            "SELECT * FROM {external_tokens}",
            "SELECT id FROM {course} -- comment",
            "SELECT /* bypass */ id FROM {course}",
        ];

        foreach ($queries as $sql) {
            try {
                security_guard::validate_safe_sql($sql);
                $this->fail("Expected moodle_exception for SQL: {$sql}");
            } catch (\moodle_exception $e) {
                $this->assertEquals('error_invalid_query', $e->errorcode);
            }
        }
    }
}
