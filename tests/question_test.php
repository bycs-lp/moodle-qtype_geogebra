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
 * Unit tests for the GeoGebra question definition class.
 *
 * @package    qtype_geogebra
 * @author     Christoph Stadlbauer <christoph.stadlbauer@geogebra.org>
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_geogebra;

use advanced_testcase;
use qtype_geogebra_question;
use question_state;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/geogebra/question.php');
require_once($CFG->dirroot . '/question/type/geogebra/tests/fixtures/ggbstringsfortesting.php');

/**
 * Unit tests for the GeoGebra question definition class.
 *
 * @package    qtype_geogebra
 * @covers     \qtype_geogebra_question
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_test extends advanced_testcase {

    /**
     * Data provider for is_complete_response on the auto-graded "point" question.
     *
     * @return array[]
     */
    public static function complete_response_point_provider(): array {
        return [
            'empty_response' => [false, []],
            'ggbbase64_zero' => [false, ['ggbbase64' => 0]],
            'ggbbase64_empty_string' => [false, ['ggbbase64' => '']],
            'missing_answer' => [false, [
                'ggbbase64' => 'adf', 'ggbxml' => ggbstringsfortesting::$pointxml,
            ]],
            'valid_answer_zero' => [true, [
                'ggbbase64' => 'adf', 'ggbxml' => ggbstringsfortesting::$pointxml, 'answer' => '0',
            ]],
            'valid_answer_01' => [true, [
                'ggbbase64' => 'adf', 'ggbxml' => ggbstringsfortesting::$pointxml, 'answer' => '01',
            ]],
            'missing_ggbbase64' => [false, ['answer' => 'test']],
            'answer_invalid_chars' => [false, [
                'ggbbase64' => 'adf', 'ggbxml' => ggbstringsfortesting::$pointxml, 'answer' => '123',
            ]],
        ];
    }

    /**
     * @dataProvider complete_response_point_provider
     */
    public function test_is_complete_response_point(bool $expected, array $response): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        $this->assertEquals($expected, $question->is_complete_response($response));
    }

    /**
     * Data provider for is_complete_response on the manually-graded question.
     *
     * @return array[]
     */
    public static function complete_response_manual_provider(): array {
        return [
            'empty_response' => [false, []],
            'ggbbase64_zero' => [false, ['ggbbase64' => 0]],
            'ggbbase64_empty_string' => [false, ['ggbbase64' => '']],
            'valid_manual_response' => [true, [
                'ggbbase64' => 'adf', 'ggbxml' => ggbstringsfortesting::$pointxml,
            ]],
            'valid_with_answer' => [true, [
                'ggbbase64' => 'adf', 'ggbxml' => ggbstringsfortesting::$pointxml, 'answer' => '01',
            ]],
            'missing_ggbbase64' => [false, ['answer' => 'test']],
        ];
    }

    /**
     * @dataProvider complete_response_manual_provider
     */
    public function test_is_complete_response_manual(bool $expected, array $response): void {
        $question = test_question_maker::make_question('geogebra', 'manually');
        $this->assertEquals($expected, $question->is_complete_response($response));
    }

    /**
     * Data provider for is_gradable_response.
     *
     * @return array[]
     */
    public static function gradable_response_point_provider(): array {
        return [
            'empty_response' => [false, []],
            'ggbbase64_zero' => [false, ['ggbbase64' => 0]],
            'ggbbase64_empty' => [false, ['ggbbase64' => '']],
            'missing_answer' => [false, ['ggbbase64' => 'adf']],
            'valid_answer' => [true, [
                'ggbbase64' => 'adf', 'ggbxml' => ggbstringsfortesting::$pointxml, 'answer' => '0',
            ]],
            'answer_only' => [false, ['answer' => 'test']],
        ];
    }

    /**
     * @dataProvider gradable_response_point_provider
     */
    public function test_is_gradable_response_point(bool $expected, array $response): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        $this->assertEquals($expected, $question->is_gradable_response($response));
    }

    /**
     * Data provider for grade_response.
     *
     * @return array[]
     */
    public static function grade_response_provider(): array {
        return [
            'incorrect' => [0, question_state::$gradedwrong, ['ggbbase64' => 'adf', 'answer' => '0']],
            'correct' => [1, question_state::$gradedright, ['ggbbase64' => 'adf', 'answer' => '1']],
        ];
    }

    /**
     * @dataProvider grade_response_provider
     */
    public function test_grading(int|float $expectedfraction, question_state $expectedstate, array $response): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        $this->assertEquals([$expectedfraction, $expectedstate], $question->grade_response($response));
    }

    /**
     * Data provider for summarise_response.
     *
     * @return array[]
     */
    public static function summarise_response_provider(): array {
        return [
            'point_wrong' => ['point', ['ggbbase64' => 'adf', 'answer' => '0'], 'e=false, Grade: 0; Total: 0'],
            'point_correct' => ['point', ['ggbbase64' => 'adf', 'answer' => '1'], 'e=true, Grade: 1.00; Total: 1'],
        ];
    }

    /**
     * @dataProvider summarise_response_provider
     */
    public function test_summarise_response(string $variant, array $response, string $expected): void {
        $question = test_question_maker::make_question('geogebra', $variant);
        $this->assertEquals($expected, $question->summarise_response($response));
    }

    public function test_summarise_response_manual(): void {
        $question = test_question_maker::make_question('geogebra', 'manually');
        $this->assertEquals(
            get_string('manuallygraded', 'qtype_geogebra'),
            $question->summarise_response(['ggbbase64' => 'adf', 'answer' => '']),
        );
    }

    /**
     * Data provider for validation errors.
     *
     * @return array[]
     */
    public static function validation_error_provider(): array {
        return [
            'missing_ggbbase64' => [[], 'ggbfilemissing'],
            'missing_ggbxml' => [['ggbbase64' => 'test'], 'ggbxmlmissing'],
            'missing_answer' => [
                ['ggbbase64' => 'test', 'ggbxml' => ggbstringsfortesting::$pointxml], 'answermissing',
            ],
            'valid_response' => [
                ['ggbbase64' => 'test', 'ggbxml' => ggbstringsfortesting::$pointxml, 'answer' => '1'], '',
            ],
            'invalid_answer_chars' => [
                ['ggbbase64' => 'test', 'ggbxml' => ggbstringsfortesting::$pointxml, 'answer' => 'abc'], 'answerinvalid',
            ],
        ];
    }

    /**
     * @dataProvider validation_error_provider
     */
    public function test_get_validation_error(array $response, string $expectedkey): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        $result = $question->get_validation_error($response);
        if ($expectedkey === '') {
            $this->assertSame('', $result);
        } else {
            $this->assertEquals(get_string($expectedkey, 'qtype_geogebra'), $result);
        }
    }

    /**
     * Data provider for classify_response.
     *
     * @return array[]
     */
    public static function classify_response_provider(): array {
        return [
            'correct' => ['1', 'e=true', 1.0],
            'incorrect' => ['0', 'e=false', 0.0],
        ];
    }

    /**
     * @dataProvider classify_response_provider
     */
    public function test_classify_response(string $answer, string $expectedclass, float $expectedfraction): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        $classified = $question->classify_response(['answer' => $answer]);
        $this->assertArrayHasKey($question->id, $classified);
        $this->assertEquals($expectedclass, $classified[$question->id]->responseclass);
        $this->assertEqualsWithDelta($expectedfraction, $classified[$question->id]->fraction, 0.0001);
    }

    public function test_classify_response_manual(): void {
        $question = test_question_maker::make_question('geogebra', 'manually');
        $classified = $question->classify_response(['answer' => '']);
        $this->assertArrayHasKey($question->id, $classified);
        $this->assertEquals(get_string('manuallygraded', 'qtype_geogebra'), $classified[$question->id]->responseclass);
        $this->assertEquals(0, $classified[$question->id]->fraction);
    }

    public function test_grade_response_manual(): void {
        $question = test_question_maker::make_question('geogebra', 'manually');
        [$fraction, $state] = $question->grade_response(['ggbbase64' => 'adf', 'answer' => '']);
        $this->assertEquals(0, $fraction);
        $this->assertSame(question_state::$needsgrading, $state);
    }

    /**
     * Test is_same_response with various XML and answer combinations.
     */
    public function test_is_same_response_identical(): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        $response = [
            'answer' => '1',
            'ggbxml' => ggbstringsfortesting::$pointxml,
            'ggbbase64' => 'asd',
        ];
        $this->assertTrue($question->is_same_response($response, $response));
    }

    public function test_is_same_response_different_answer(): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        $prev = ['answer' => '0', 'ggbxml' => ggbstringsfortesting::$pointxml, 'ggbbase64' => 'asd'];
        $new = ['answer' => '1', 'ggbxml' => ggbstringsfortesting::$pointxml, 'ggbbase64' => 'asd'];
        $this->assertFalse($question->is_same_response($prev, $new));
    }

    public function test_is_same_response_new_xml_where_prev_empty(): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        $prev = ['answer' => '1'];
        $new = ['answer' => '1', 'ggbxml' => ggbstringsfortesting::$pointxml];
        $this->assertFalse($question->is_same_response($prev, $new));
    }

    public function test_is_same_response_both_empty_xml(): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        $prev = ['answer' => '1'];
        $new = ['answer' => '1'];
        $this->assertTrue($question->is_same_response($prev, $new));
    }

    /**
     * Test that get_expected_data returns the correct keys.
     */
    public function test_get_expected_data(): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        $expected = $question->get_expected_data();
        $this->assertArrayHasKey('answer', $expected);
        $this->assertArrayHasKey('ggbbase64', $expected);
        $this->assertArrayHasKey('ggbxml', $expected);
        $this->assertArrayHasKey('exerciseresult', $expected);
        $this->assertEquals(PARAM_RAW, $expected['answer']);
    }

    /**
     * Test that get_correct_response returns null.
     */
    public function test_get_correct_response(): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        $this->assertNull($question->get_correct_response());
    }

    /**
     * Data provider for exercise grading via grade_response.
     *
     * @return array[]
     */
    public static function exercise_grading_provider(): array {
        return [
            'single_100_percent' => [
                '{"task1":{"result":"correct","fraction":1.0,"hint":""},"task2":{"result":"wrong","fraction":0.0,"hint":""}}',
                1.0,
            ],
            'partial_below_threshold' => [
                '{"task1":{"result":"partial","fraction":0.5,"hint":""},"task2":{"result":"wrong","fraction":0.0,"hint":""}}',
                0.5,
            ],
            'negative_fraction_deducted' => [
                '{"task1":{"result":"correct","fraction":1.0,"hint":""},"task2":{"result":"wrong","fraction":-0.5,"hint":""}}',
                0.5,
            ],
            'all_zero' => [
                '{"task1":{"result":"wrong","fraction":0.0,"hint":""},"task2":{"result":"wrong","fraction":0.0,"hint":""}}',
                0.0,
            ],
        ];
    }

    /**
     * Test exercise grading through grade_response (indirectly tests calculate_exercise_fraction).
     *
     * @dataProvider exercise_grading_provider
     * @param string $exercisejson JSON exercise result.
     * @param float $expectedfraction Expected fraction.
     */
    public function test_exercise_grading(string $exercisejson, float $expectedfraction): void {
        $question = test_question_maker::make_question('geogebra', 'point');
        // Override to exercise mode.
        $question->isexercise = 1;
        $question->answers = [];

        [$fraction, $state] = $question->grade_response([
            'ggbbase64' => 'asd',
            'ggbxml' => ggbstringsfortesting::$pointxml,
            'exerciseresult' => $exercisejson,
            'answer' => '',
        ]);
        $this->assertEqualsWithDelta($expectedfraction, $fraction, 0.001);
    }
}
