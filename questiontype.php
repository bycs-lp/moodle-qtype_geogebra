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
 * GeoGebra question type class.
 *
 * @package    qtype_geogebra
 * @author     Christoph Stadlbauer <christoph.stadlbauer@geogebra.org>
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/question/type/shortanswer/questiontype.php');

/**
 * GeoGebra question type.
 *
 * @package    qtype_geogebra
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_geogebra extends question_type {

    /**
     * Return an array with all the database fields used by this question type.
     *
     * The first element is the table name.
     *
     * @return array Table name followed by field names.
     */
    public function extra_question_fields(): array {
        return [
            'qtype_geogebra_options',
            'ggbturl',
            'ggbparameters',
            'ggbviews',
            'ggbcodebaseversion',
            'ggbxml',
            'israndomized',
            'randomizedvar',
            'constraints',
            'isexercise',
            'forcedimensions',
            'width',
            'height',
        ];
    }

    /**
     * Saves question-type specific options.
     *
     * This is called by {@see save_question()} to save the question-type specific data.
     *
     * @param object $question This holds the information from the editing form.
     * @return object|null $result->error or $result->notice, or null on success.
     */
    public function save_question_options($question): ?object {
        // Insert all the new answers.
        if (isset($question->answer)) {
            if (!empty($question->feedback) && !is_array($question->feedback[0])) {
                foreach ($question->feedback as $key => $value) {
                    $question->feedback[$key] = [
                        'text' => $value . '<br>',
                        'format' => FORMAT_HTML,
                    ];
                }
            }
            $parentresult = parent::save_question_answers($question);
            if ($parentresult !== null) {
                return $parentresult;
            }
        }

        // Save the question options.
        $parentresult = parent::save_question_options($question);
        if ($parentresult !== null) {
            return $parentresult;
        }

        $this->save_hints($question);

        return null;
    }

    /**
     * Whether this question type sometimes requires manual grading.
     *
     * @return bool True, since GeoGebra questions can be manually graded.
     */
    public function is_manual_graded(): bool {
        return true;
    }

    /**
     * Get the random guess score for this question.
     *
     * @param object $questiondata The question data.
     * @return float|null Null since it is not possible to estimate.
     */
    public function get_random_guess_score($questiondata): ?float {
        return null;
    }

    /**
     * Whether this question type can analyse student responses.
     *
     * @return bool True.
     */
    public function can_analyse_responses(): bool {
        return true;
    }

    /**
     * Get all possible types of response that are recognised for this question.
     *
     * @param object $questiondata The question definition data.
     * @return array Keys are subpartids, values are arrays of possible responses.
     */
    public function get_possible_responses($questiondata): array {
        if (empty($questiondata->options->answers)) {
            if (!empty($questiondata->options->isexercise)) {
                return [
                    $questiondata->id => [
                        null => new \question_possible_response(
                            get_string('automaticallygraded', 'qtype_geogebra'),
                            null
                        ),
                    ],
                ];
            }
            return [
                $questiondata->id => [
                    null => new \question_possible_response(
                        get_string('manuallygraded', 'qtype_geogebra'),
                        null
                    ),
                ],
            ];
        }

        $responses = [];
        $answers = $questiondata->options->answers;
        $count = pow(2, count($answers)) - 1;

        for ($i = $count; $i >= 0; $i--) {
            $response = str_pad(decbin($i), count($answers), '0', STR_PAD_LEFT);
            $j = 0;
            $fraction = 0;
            $responseclass = '';
            foreach ($answers as $answer) {
                $correct = (bool) substr($response, $j, 1);
                if ($responseclass !== '') {
                    $responseclass .= ', ';
                }
                $responseclass .= $answer->answer . '=' . ($correct ? 'true' : 'false');
                if ($correct) {
                    $fraction += $answer->fraction;
                }
                $j++;
            }
            $fraction = min($fraction, 1.0);
            $responses[$i] = new \question_possible_response($responseclass, $fraction);
        }
        $responses[null] = \question_possible_response::no_response();

        return [$questiondata->id => $responses];
    }

    /**
     * Initialise the common question_definition fields.
     *
     * @param question_definition $question The question_definition we are creating.
     * @param object $questiondata The question data loaded from the database.
     */
    protected function initialise_question_instance(question_definition $question, $questiondata): void {
        parent::initialise_question_instance($question, $questiondata);
        $this->initialise_question_answers($question, $questiondata);
    }
}
