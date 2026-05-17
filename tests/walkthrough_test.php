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
 * Overall tests of GeoGebra questions.
 *
 * @package    qtype_geogebra
 * @author     Christoph Stadlbauer <christoph.stadlbauer@geogebra.org>
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_geogebra;

use qbehaviour_walkthrough_test_base;
use question_state;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/geogebra/tests/helper.php');

/**
 * Overall walkthrough tests of GeoGebra questions.
 *
 * @package    qtype_geogebra
 * @covers     \qtype_geogebra_question
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class walkthrough_test extends qbehaviour_walkthrough_test_base {

    public function test_interactive_point(): void {
        $q = test_question_maker::make_question('geogebra', 'point');
        $this->start_attempt_at_question($q, 'interactive', 1);

        $data = $this->quba->get_question_attempt($this->slot)->get_last_step()->get_qt_data();
        $summaryexpected = 'Drag the point to (' . $data['_var_a'] . '/' . $data['_var_b'] . ')';
        $this->assertEquals($summaryexpected, $q->get_question_summary());

        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
            $this->get_does_not_contain_try_again_button_expectation(),
            $this->get_no_hint_visible_expectation(),
        );

        // Submit blank.
        $this->process_submission(['-submit' => 1, 'answer' => '', 'ggbbase64' => 'asd']);
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);

        // Submit invalid answer.
        $this->process_submission([
            '-submit' => 1, 'answer' => 'asd',
            'ggbxml' => ggbstringsfortesting::$pointxml, 'ggbbase64' => 'asd',
        ]);
        $this->check_current_state(question_state::$invalid);

        // Now get it right.
        $this->process_submission([
            '-submit' => 1, 'answer' => '1',
            'ggbxml' => ggbstringsfortesting::$pointxml, 'ggbbase64' => 'asd',
        ]);
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);
        $this->assertEquals('e=true, Grade: 1.00; Total: 1', $this->quba->get_response_summary($this->slot));
    }

    public function test_deferredfeedback_point(): void {
        $q = test_question_maker::make_question('geogebra', 'point');
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);

        $this->check_current_state(question_state::$todo);

        $this->process_submission(['answer' => '']);
        $this->check_current_state(question_state::$todo);

        $this->process_submission(['answer' => '1', 'ggbxml' => ggbstringsfortesting::$pointxml]);
        $this->check_current_state(question_state::$todo);

        $this->process_submission(['answer' => '123', 'ggbxml' => ggbstringsfortesting::$pointxml, 'ggbbase64' => 'asd']);
        $this->check_current_state(question_state::$todo);

        $this->process_submission(['answer' => '1', 'ggbxml' => ggbstringsfortesting::$pointxml, 'ggbbase64' => 'asd']);
        $this->check_current_state(question_state::$complete);

        $this->finish();
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);
        $this->assertEquals('e=true, Grade: 1.00; Total: 1', $this->quba->get_response_summary($this->slot));
    }

    public function test_interactive_manually(): void {
        $q = test_question_maker::make_question('geogebra', 'manually');
        $this->start_attempt_at_question($q, 'interactive', 1);

        $behaviour = $this->quba->get_question_attempt($this->slot)->get_behaviour()->get_name();
        $this->assertEquals('manualgraded', $behaviour);

        $this->check_current_state(question_state::$todo);
        $this->render();

        $this->process_submission([
            '-submit' => 1, 'answer' => '',
            'ggbxml' => ggbstringsfortesting::$pointxml, 'ggbbase64' => 'asd',
        ]);
        $this->check_current_state(question_state::$complete);

        $this->finish();
        $this->check_current_state(question_state::$needsgrading);

        $this->quba->manual_grade($this->slot, 'Not right, but at least some response', 0.5, FORMAT_HTML);
        $this->check_current_state(question_state::$mangrpartial);
        $this->check_current_mark(0.5);

        $this->assertEquals(
            get_string('manuallygraded', 'qtype_geogebra'),
            $this->quba->get_response_summary($this->slot),
        );
    }
}
