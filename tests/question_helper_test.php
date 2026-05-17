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
 * Unit tests for the question_helper class.
 *
 * @package    qtype_geogebra
 * @copyright  2026 ISB Bayern
 * @author     Fabian Barbuia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_geogebra;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/geogebra/tests/fixtures/ggbstringsfortesting.php');

/**
 * Unit tests for the question_helper class.
 *
 * @package    qtype_geogebra
 * @covers     \qtype_geogebra\question_helper
 * @copyright  2026 ISB Bayern
 * @author     Fabian Barbuia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_helper_test extends advanced_testcase {

    /**
     * Data provider for inequality syntax validation.
     *
     * @return array[]
     */
    public static function inequality_syntax_provider(): array {
        return [
            'less_than' => ['a<b', true],
            'less_than_or_equal' => ['a<=b', true],
            'greater_than' => ['a>b', true],
            'greater_than_or_equal' => ['a>=b', true],
            'with_numbers' => ['var1<var2', true],
            'with_underscores' => ['a_1<=b_2', true],
            'equals_invalid' => ['a==b', false],
            'single_equals_invalid' => ['a=b', false],
            'empty_string' => ['', false],
            'missing_right_operand' => ['a<', false],
            'missing_left_operand' => ['<b', false],
            'spaces_invalid' => ['a < b', false],
        ];
    }

    /**
     * @dataProvider inequality_syntax_provider
     */
    public function test_is_valid_inequality(string $inequality, bool $expected): void {
        $this->assertEquals($expected, question_helper::is_valid_inequality($inequality));
    }

    /**
     * Data provider for inequality variable validation.
     *
     * @return array[]
     */
    public static function inequality_vars_provider(): array {
        return [
            'both_vars_present' => ['a<b', 'a,b', true],
            'left_var_missing' => ['a<c', 'a,b', false],
            'right_var_missing' => ['c<a', 'a,b', false],
            'both_missing' => ['x<y', 'a,b', false],
            'with_spaces_in_varlist' => ['a<b', 'a, b', true],
        ];
    }

    /**
     * @dataProvider inequality_vars_provider
     */
    public function test_is_valid_inequality_for_randomizedvars(
        string $inequality,
        string $randomizedvar,
        bool $expected,
    ): void {
        $this->assertEquals($expected, question_helper::is_valid_inequality_for_randomizedvars($inequality, $randomizedvar));
    }

    /**
     * Data provider for check_inequality.
     *
     * @return array[]
     */
    public static function check_inequality_provider(): array {
        return [
            'lt_true' => ['<', 1, 2, true],
            'lt_false' => ['<', 2, 1, false],
            'lt_equal_false' => ['<', 1, 1, false],
            'lte_true' => ['<=', 1, 1, true],
            'lte_less_true' => ['<=', 0, 1, true],
            'lte_false' => ['<=', 2, 1, false],
            'gt_true' => ['>', 2, 1, true],
            'gt_false' => ['>', 1, 2, false],
            'gte_true' => ['>=', 1, 1, true],
            'gte_greater_true' => ['>=', 2, 1, true],
            'gte_false' => ['>=', 0, 1, false],
            'invalid_operator' => ['!=', 1, 2, false],
            'float_values' => ['<', 1.5, 2.5, true],
        ];
    }

    /**
     * @dataProvider check_inequality_provider
     */
    public function test_check_inequality(string $op, int|float $x, int|float $y, bool $expected): void {
        $this->assertEquals($expected, question_helper::check_inequality($op, $x, $y));
    }

    /**
     * Data provider for deterministic random_incremented_value edge cases.
     *
     * @return array[]
     */
    public static function random_incremented_edge_cases_provider(): array {
        return [
            'zero_increment_returns_min' => [5, 10, 0, 5],
            'max_less_than_min_returns_min' => [10, 5, 1, 10],
            'min_equals_max' => [7, 7, 1, 7],
        ];
    }

    /**
     * @dataProvider random_incremented_edge_cases_provider
     */
    public function test_random_incremented_value_edge_cases(
        int|float $min, int|float $max, int|float $increment, int|float $expected,
    ): void {
        $this->assertEquals($expected, question_helper::random_incremented_value($min, $max, $increment));
    }

    public function test_random_incremented_value_within_bounds(): void {
        for ($i = 0; $i < 200; $i++) {
            $val = question_helper::random_incremented_value(0, 10, 1);
            $this->assertGreaterThanOrEqual(0, $val);
            $this->assertLessThanOrEqual(10, $val);
        }
    }

    public function test_randomize_vars_no_constraints(): void {
        $vars = [
            'a' => ['min' => 0, 'max' => 10, 'increment' => 1],
            'b' => ['min' => 0, 'max' => 10, 'increment' => 1],
        ];
        $result = question_helper::randomize_vars($vars, []);
        $this->assertNull($result);
        $this->assertArrayHasKey('val', $vars['a']);
        $this->assertArrayHasKey('val', $vars['b']);
    }

    public function test_randomize_vars_with_satisfiable_constraints(): void {
        $vars = [
            'a' => ['min' => 0, 'max' => 5, 'increment' => 1],
            'b' => ['min' => 6, 'max' => 10, 'increment' => 1],
        ];
        $inequalities = [[0 => 'a<b', 1 => 'a', 2 => '<', 3 => 'b']];
        $result = question_helper::randomize_vars($vars, $inequalities);
        $this->assertNull($result);
        $this->assertLessThan($vars['b']['val'], $vars['a']['val']);
    }

    public function test_randomize_vars_impossible_constraints(): void {
        $vars = [
            'a' => ['min' => 10, 'max' => 10, 'increment' => 1],
            'b' => ['min' => 10, 'max' => 10, 'increment' => 1],
        ];
        $inequalities = [[0 => 'a<b', 1 => 'a', 2 => '<', 3 => 'b']];
        $result = question_helper::randomize_vars($vars, $inequalities, 0.1);
        $this->assertNotNull($result);
        $this->assertObjectHasProperty('time', $result);
        $this->assertObjectHasProperty('tries', $result);
        $this->assertGreaterThan(0, $result->tries);
    }

    public function test_get_variables_with_minmaxstep(): void {
        $vars = question_helper::get_variables_with_minmaxstep('a,b', ggbstringsfortesting::$pointxml);
        $this->assertCount(2, $vars);
        $this->assertEqualsWithDelta(0, $vars['a']['min'], 0.0001);
        $this->assertEqualsWithDelta(4.5, $vars['a']['max'], 0.0001);
        $this->assertEqualsWithDelta(0.1, $vars['a']['increment'], 0.0001);
        $this->assertEqualsWithDelta(0, $vars['b']['min'], 0.0001);
        $this->assertEqualsWithDelta(2.4, $vars['b']['max'], 0.0001);
    }

    public function test_get_variables_with_minmaxstep_invalid_xml(): void {
        $this->assertEmpty(question_helper::get_variables_with_minmaxstep('a,b', 'not valid xml'));
    }

    public function test_get_variables_with_minmaxstep_empty_vars(): void {
        $this->assertEmpty(question_helper::get_variables_with_minmaxstep('', ggbstringsfortesting::$pointxml));
    }

    public function test_legacy_class_alias(): void {
        $this->assertTrue(class_exists('qtype_geogebra_question_helper'));
        $this->assertTrue(\qtype_geogebra_question_helper::is_valid_inequality('a<b'));
        $this->assertFalse(\qtype_geogebra_question_helper::is_valid_inequality('invalid'));
    }

    public function test_is_valid_inequality_for_slider_minmax(): void {
        $this->assertTrue(question_helper::is_valid_inequality_for_slider_minmax(
            'a<b', 'a,b', ggbstringsfortesting::$pointxml,
        ));
        $this->assertFalse(question_helper::is_valid_inequality_for_slider_minmax(
            'x<y', 'x,y', ggbstringsfortesting::$pointxml,
        ));
        $this->assertFalse(question_helper::is_valid_inequality_for_slider_minmax(
            'a<b', 'a,b', 'not xml',
        ));
    }
}
