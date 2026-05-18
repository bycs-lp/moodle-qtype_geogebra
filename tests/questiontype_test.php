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
 * Unit tests for question/type/geogebra/questiontype.php.
 *
 * @package    qtype_geogebra
 * @author     Christoph Stadlbauer <christoph.stadlbauer@geogebra.org>
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_geogebra;

use advanced_testcase;
use core_question_generator;
use qtype_geogebra;
use qtype_geogebra_edit_form;
use qtype_geogebra_test_helper;
use question_possible_response;
use stdClass;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/geogebra/questiontype.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/geogebra/edit_geogebra_form.php');

/**
 * Unit tests for the GeoGebra question type class.
 *
 * @package    qtype_geogebra
 * @covers     \qtype_geogebra
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class questiontype_test extends advanced_testcase {
    /** @var qtype_geogebra */
    private qtype_geogebra $qtype;

    protected function setUp(): void {
        parent::setUp();
        $this->qtype = new qtype_geogebra();
    }

    protected function tearDown(): void {
        unset($this->qtype);
        parent::tearDown();
    }

    /**
     * Get test question data with two answer variables.
     *
     * @return stdClass
     */
    private function get_test_question_data(): stdClass {
        $q = new stdClass();
        $q->id = 1;
        $q->idnumber = 0;
        $q->options = new stdClass();
        $q->options->answers = [
            13 => (object) [
                'id' => 13, 'answer' => 'e', 'fraction' => 1,
                'feedback' => 'yes', 'feedbackformat' => FORMAT_MOODLE,
            ],
            14 => (object) [
                'id' => 14, 'answer' => 'e1', 'fraction' => 0.5,
                'feedback' => 'yes', 'feedbackformat' => FORMAT_MOODLE,
            ],
        ];
        return $q;
    }

    public function test_name(): void {
        $this->assertEquals('geogebra', $this->qtype->name());
    }

    public function test_is_manual_graded(): void {
        $this->assertTrue($this->qtype->is_manual_graded());
    }

    public function test_can_analyse_responses(): void {
        $this->assertTrue($this->qtype->can_analyse_responses());
    }

    public function test_get_random_guess_score(): void {
        $this->assertNull($this->qtype->get_random_guess_score($this->get_test_question_data()));
    }

    public function test_get_possible_responses(): void {
        $q = $this->get_test_question_data();
        $this->assertEquals([
            $q->id => [
                3 => new question_possible_response('e=true, e1=true', 1),
                2 => new question_possible_response('e=true, e1=false', 1),
                1 => new question_possible_response('e=false, e1=true', 0.5),
                0 => new question_possible_response('e=false, e1=false', 0),
                null => question_possible_response::no_response(),
            ],
        ], $this->qtype->get_possible_responses($q));
    }

    public function test_get_possible_responses_manual(): void {
        $q = new stdClass();
        $q->id = 2;
        $q->options = new stdClass();
        $q->options->answers = [];
        $q->options->isexercise = 0;
        $responses = $this->qtype->get_possible_responses($q);
        $this->assertEquals(get_string('manuallygraded', 'qtype_geogebra'), $responses[$q->id][null]->responseclass);
    }

    public function test_get_possible_responses_exercise(): void {
        $q = new stdClass();
        $q->id = 3;
        $q->options = new stdClass();
        $q->options->answers = [];
        $q->options->isexercise = 1;
        $responses = $this->qtype->get_possible_responses($q);
        $this->assertEquals(get_string('automaticallygraded', 'qtype_geogebra'), $responses[$q->id][null]->responseclass);
    }

    public function test_question_saving_point(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $questiondata = test_question_maker::get_question_data('geogebra');
        $formdata = test_question_maker::get_question_form_data('geogebra');

        /** @var core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category([]);

        $formdata->category = "{$cat->id},{$cat->contextid}";
        qtype_geogebra_edit_form::mock_submit((array) $formdata);

        $form = qtype_geogebra_test_helper::get_question_editing_form($cat, $questiondata);
        $this->assertTrue($form->is_validated());

        $fromform = $form->get_data();
        $returnedfromsave = $this->qtype->save_question($questiondata, $fromform);
        $actualquestionsdata = question_load_questions([$returnedfromsave->id], 'qbe.idnumber');
        $actualquestiondata = end($actualquestionsdata);

        foreach ($questiondata as $property => $value) {
            if (!in_array($property, ['options'])) {
                $this->assertEquals($value, $actualquestiondata->{$property});
            }
        }
        foreach ($questiondata->options as $optionname => $value) {
            if (!in_array($optionname, ['answers'])) {
                $this->assertEquals($value, $actualquestiondata->options->{$optionname});
            }
        }
        foreach ($questiondata->options->answers as $answer) {
            $actualanswer = array_shift($actualquestiondata->options->answers);
            foreach ($answer as $ansproperty => $ansvalue) {
                if (!in_array($ansproperty, ['id', 'question', 'answerformat'])) {
                    $this->assertEquals($ansvalue, $actualanswer->{$ansproperty});
                }
            }
        }
    }
}
