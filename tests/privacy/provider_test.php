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
 * Privacy provider test for qtype_geogebra.
 *
 * @package    qtype_geogebra
 * @copyright  2026 ISB Bayern
 * @author     Fabian Barbuia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_geogebra\privacy;

use core_privacy\tests\provider_testcase;

/**
 * Privacy provider test for qtype_geogebra.
 *
 * @package    qtype_geogebra
 * @covers     \qtype_geogebra\privacy\provider
 * @copyright  2026 ISB Bayern
 * @author     Fabian Barbuia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends provider_testcase {
    /**
     * Test that the provider implements null_provider and returns a valid reason string.
     */
    public function test_get_reason(): void {
        $this->assertIsString(provider::get_reason());
        // Verify the lang string actually exists and doesn't throw.
        $reason = get_string(provider::get_reason(), 'qtype_geogebra');
        $this->assertNotEmpty($reason);
    }

    /**
     * Test that the provider correctly implements null_provider.
     */
    public function test_implements_null_provider(): void {
        $this->assertTrue(is_a(
            provider::class,
            \core_privacy\local\metadata\null_provider::class,
            true,
        ));
    }
}
