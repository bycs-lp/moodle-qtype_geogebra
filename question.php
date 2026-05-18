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
 * GeoGebra question definition class.
 *
 * @package    qtype_geogebra
 * @author     Christoph Stadlbauer <christoph.stadlbauer@geogebra.org>
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/calculated/question.php');
require_once($CFG->dirroot . '/question/type/calculated/questiontype.php');

/**
 * Represents a GeoGebra question.
 *
 * @package    qtype_geogebra
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_geogebra_question extends question_graded_automatically {
    /** @var question_answer[] The question answers. */
    public array $answers = [];

    /** @var string|null The GeoGebra Tube URL. */
    public ?string $ggbturl = null;

    /** @var string|null GeoGebra parameters JSON. */
    public ?string $ggbparameters = null;

    /** @var string|null GeoGebra views configuration. */
    public ?string $ggbviews = null;

    /** @var string|null GeoGebra codebase version. */
    public ?string $ggbcodebaseversion = null;

    /** @var string|null The GeoGebra XML. */
    public ?string $ggbxml = null;

    /** @var int|bool Whether the question uses randomized variables. */
    public int|bool $israndomized = 0;

    /** @var int|bool Whether this is a GeoGebra exercise. */
    public int|bool $isexercise = 0;

    /** @var string|null Comma-separated list of randomized variables. */
    public ?string $randomizedvar = null;

    /** @var string|null Constraints for randomized variables. */
    public ?string $constraints = null;

    /** @var int|bool|null Whether to force applet dimensions. */
    public int|bool|null $forcedimensions = 0;

    /** @var int|null Forced width. */
    public ?int $width = null;

    /** @var int|null Forced height. */
    public ?int $height = null;

    /** @var array Current values for randomized variables. */
    public array $currentvals = [];

    /** @var \qtype_calculated_variable_substituter|null Stores the dataset we are using. */
    public ?\qtype_calculated_variable_substituter $vs = null;

    /**
     * Determine the appropriate question behaviour.
     *
     * If question->$answer is empty we have to manually grade the question,
     * else we can use the preferred behaviour of the quiz.
     *
     * @param question_attempt $qa The attempt we are creating a behaviour for.
     * @param string $preferredbehaviour The requested type of behaviour.
     * @return question_behaviour The new behaviour object.
     */
    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        if (empty($this->answers) && !$this->isexercise) {
            return question_engine::make_behaviour('manualgraded', $qa, $preferredbehaviour);
        }
        return parent::make_behaviour($qa, $preferredbehaviour);
    }

    /**
     * Randomize variables and substitute them in question text.
     *
     * @param question_attempt_step $step The first step of the question attempt.
     * @param int $variant The variant number.
     */
    public function start_attempt(question_attempt_step $step, $variant): void {
        if ($this->israndomized) {
            $vars = \qtype_geogebra\question_helper::get_variables_with_minmaxstep(
                $this->randomizedvar ?? '',
                $this->ggbxml ?? ''
            );
            $inequalities = [];
            if (!empty($this->constraints)) {
                foreach (explode(',', $this->constraints) as $inequalitystring) {
                    $matches = [];
                    if (preg_match('/^([a-z_0-9]+)(<=|<|>=|>)([a-z_0-9]+)$/i', $inequalitystring, $matches)) {
                        $inequalities[] = $matches;
                    }
                }
            }
            \qtype_geogebra\question_helper::randomize_vars($vars, $inequalities);
            foreach ($vars as $label => $var) {
                $this->currentvals[$label] = $var['val'];
            }
            $this->vs = new \qtype_calculated_variable_substituter(
                $this->currentvals,
                get_string('decsep', 'langconfig')
            );
            $this->calculate_all_expressions();
            foreach ($this->currentvals as $label => $value) {
                $step->set_qt_var('_var_' . $label, $value);
            }
        }
    }

    /**
     * Restore the previous attempt state for randomized variables.
     *
     * @param question_attempt_step $step The step to restore from.
     */
    public function apply_attempt_state(question_attempt_step $step): void {
        if ($this->israndomized) {
            $vars = array_filter(array_map('trim', explode(',', $this->randomizedvar ?? '')));
            foreach ($vars as $label) {
                if (!empty($label)) {
                    $this->currentvals[$label] = (float) $step->get_qt_var('_var_' . $label);
                }
            }
            $this->vs = new \qtype_calculated_variable_substituter(
                $this->currentvals,
                get_string('decsep', 'langconfig')
            );
            $this->calculate_all_expressions();
        }
    }

    /**
     * Replace variable expressions in question text, general feedback and answer feedback.
     */
    public function calculate_all_expressions(): void {
        if ($this->vs === null) {
            return;
        }
        $this->questiontext = $this->vs->replace_expressions_in_text($this->questiontext);
        $this->generalfeedback = $this->vs->replace_expressions_in_text($this->generalfeedback);

        foreach ($this->answers as $ans) {
            if (isset($ans->feedback)) {
                $ans->feedback = $this->vs->replace_expressions_in_text($ans->feedback, null, null);
            }
        }
    }

    /**
     * Get the expected data from the student response.
     *
     * @return array Expected question data fields.
     */
    public function get_expected_data(): array {
        return [
            'answer' => PARAM_RAW,
            'ggbbase64' => PARAM_RAW,
            'ggbxml' => PARAM_RAW,
            'exerciseresult' => PARAM_RAW,
        ];
    }

    /**
     * Get the correct response for this question.
     *
     * @return array|null Null since we have no strategy for generating the answer.
     */
    public function get_correct_response(): ?array {
        return null;
    }

    /**
     * Check whether the response is complete.
     *
     * @param array $response The student response.
     * @return bool Whether this response is a complete answer.
     */
    public function is_complete_response(array $response): bool {
        $ret = !empty($response['ggbbase64']) && !empty($response['ggbxml']);

        if (!empty($this->answers)) {
            $ret = $ret
                && array_key_exists('answer', $response)
                && ($response['answer'] !== '' && $response['answer'] !== null)
                && (preg_replace('/[^01]/', '', $response['answer']) === $response['answer']);
        }
        if ($this->isexercise) {
            $ret = $ret && !empty($response['exerciseresult']);
        }
        return $ret;
    }

    /**
     * Determine whether two responses are the same.
     *
     * @param array $prevresponse The previously recorded responses.
     * @param array $newresponse The new responses.
     * @return bool Whether the two sets of responses are the same.
     */
    public function is_same_response(array $prevresponse, array $newresponse): bool {
        $ret = question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, 'answer');

        $prevxml = null;
        if (!empty($prevresponse['ggbxml'])) {
            $prevxml = @simplexml_load_string($prevresponse['ggbxml']);
        }
        $newxml = null;
        if (!empty($newresponse['ggbxml'])) {
            $newxml = @simplexml_load_string($newresponse['ggbxml']);
        }

        if ($newxml !== null && $newxml !== false && ($prevxml === null || $prevxml === false)) {
            $ret = false;
        } else if ($newxml !== null && $newxml !== false && $prevxml !== null && $prevxml !== false) {
            $ret = $ret && ($prevxml->construction->asXML() === $newxml->construction->asXML());
        }

        if ($this->isexercise) {
            $ret = $ret && question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse,
                $newresponse,
                'exerciseresult'
            );
        }

        return $ret;
    }

    /**
     * Check whether the response is gradable.
     *
     * @param array $response The student response.
     * @return bool Whether the response can be graded.
     */
    public function is_gradable_response(array $response): bool {
        return $this->is_complete_response($response);
    }

    /**
     * Produce a plain text summary of a response.
     *
     * @param array $response A response, as might be passed to {@see grade_response()}.
     * @return string A plain text summary of that response.
     */
    public function summarise_response(array $response): string {
        if (empty($this->answers) && !$this->isexercise) {
            return get_string('manuallygraded', 'qtype_geogebra');
        }

        $gradestr = get_string('gradenoun');
        $resp = $response['answer'] ?? '';

        if ($resp === '' && !$this->isexercise) {
            return get_string('noresponse', 'question');
        }

        if (!$this->isexercise) {
            $j = 0;
            $fraction = 0;
            $summary = '';
            foreach ($this->answers as $answer) {
                $correct = (bool) substr($resp, $j, 1);
                if ($summary !== '') {
                    $summary .= ', ';
                }
                $summary .= $answer->answer . '=';
                if ($correct) {
                    $fraction += $answer->fraction;
                    $summary .= 'true, ' . $gradestr . ': ' . format_float($answer->fraction, 2, false, false);
                } else {
                    $summary .= 'false, ' . $gradestr . ': 0';
                }
                $j++;
            }
            $fraction = min($fraction, 1.0);
            $summary .= '; ' . get_string('total', 'grades') . ': ' . $fraction;
            return $summary;
        }

        // Exercise mode.
        $result = json_decode($response['exerciseresult'] ?? '{}', true);
        $summary = '';
        foreach ($result as $key => $res) {
            if (is_array($res)) {
                if ($summary !== '') {
                    $summary .= ', ';
                }
                $summary .= $key . '=' . $res['result'] . ': '
                    . format_float($res['fraction'], 2, false, false);
            } else {
                $summary .= '; ' . get_string('total', 'grades') . ': ' . $res;
            }
        }
        return $summary;
    }

    /**
     * Get the validation error for an incomplete/invalid response.
     *
     * @param array $response The student response.
     * @return string The validation error message.
     */
    public function get_validation_error(array $response): string {
        if (empty($response['ggbbase64'])) {
            return get_string('ggbfilemissing', 'qtype_geogebra');
        }
        if (empty($response['ggbxml'])) {
            return get_string('ggbxmlmissing', 'qtype_geogebra');
        }
        if (!empty($this->answers)) {
            if (
                !array_key_exists('answer', $response)
                || $response['answer'] === null
                || ($response['answer'] === '' && $response['answer'] !== '0')
            ) {
                return get_string('answermissing', 'qtype_geogebra');
            }

            $answer = (string) $response['answer'];

            if (preg_replace('/[^01]/', '', $answer) !== $answer) {
                return get_string('answerinvalid', 'qtype_geogebra');
            }
        }
        if ($this->isexercise && empty($response['exerciseresult'])) {
            return get_string('exerciseresultmissing', 'qtype_geogebra');
        }
        return '';
    }

    /**
     * Categorise the student's response according to the categories defined by get_possible_responses.
     *
     * @param array $response A response, as might be passed to {@see grade_response()}.
     * @return array Subpartid => {@see question_classified_response} objects.
     */
    public function classify_response(array $response): array {
        if (empty($this->answers)) {
            return [$this->id => new \question_classified_response(
                null,
                get_string('manuallygraded', 'qtype_geogebra'),
                0
            )];
        }

        $resp = $response['answer'] ?? '';
        if ($resp === '') {
            return [$this->id => \question_classified_response::no_response()];
        }

        $j = 0;
        $fraction = 0;
        $responseclass = '';
        foreach ($this->answers as $answer) {
            $correct = (bool) substr($resp, $j, 1);
            if ($responseclass !== '') {
                $responseclass .= ', ';
            }
            $responseclass .= $answer->answer . '=';
            if ($correct) {
                $fraction += $answer->fraction;
                $responseclass .= 'true';
            } else {
                $responseclass .= 'false';
            }
            $j++;
        }
        $fraction = min($fraction, 1.0);
        return [$this->id => new \question_classified_response(bindec($resp), $responseclass, $fraction)];
    }

    /**
     * Grade a response to the question.
     *
     * @param array $response Responses, as returned by question_attempt_step::get_qt_data().
     * @return array Tuple of (float fraction, question_state state).
     */
    public function grade_response(array $response): array {
        $fraction = 0;
        if (empty($this->answers) && !$this->isexercise) {
            return [$fraction, question_state::$needsgrading];
        }

        if (!$this->isexercise) {
            $i = 0;
            foreach ($this->answers as $answer) {
                if ((bool) substr($response['answer'] ?? '', $i, 1)) {
                    $fraction += $answer->fraction;
                }
                $i++;
            }
            $fraction = min($fraction, 1.0);
        } else {
            $exerciseresult = json_decode($response['exerciseresult'] ?? '{}');
            if ($exerciseresult !== null && $exerciseresult !== false) {
                $fraction = $this->calculate_exercise_fraction($exerciseresult);
            }
        }
        return [$fraction, question_state::graded_state_for_fraction($fraction)];
    }

    /**
     * Calculate the overall fraction for a GeoGebra exercise.
     *
     * If one assignment has 100%, the overall fraction will be 1 minus the sum
     * of the fractions of the assignments having negative fractions.
     * Otherwise, the overall fraction will be the sum of all positive fractions
     * capped at 1 minus all negative fractions and then capped at 0.
     *
     * @param \stdClass $exerciseresult The exercise result object.
     * @return float The sum of fractions for all assignments.
     */
    private function calculate_exercise_fraction(\stdClass $exerciseresult): float {
        $fractionsumplus = 0.0;
        $fractionsumminus = 0.0;
        $singlecorrectignoreothers = false;

        foreach ($exerciseresult as $assignment) {
            if (!is_object($assignment) || !isset($assignment->fraction)) {
                continue;
            }
            if ($assignment->fraction >= 0) {
                if ($assignment->fraction > 0.999) {
                    $singlecorrectignoreothers = true;
                }
                $fractionsumplus += $assignment->fraction;
            } else {
                $fractionsumminus += $assignment->fraction;
            }
        }

        $fraction = ($singlecorrectignoreothers || $fractionsumplus >= 0.999) ? 1.0 : $fractionsumplus;
        $fraction += $fractionsumminus;
        return $fraction < 0.001 ? 0.0 : $fraction;
    }
}

// Legacy class alias for backward compatibility — do NOT use in new code.
// This ensures old code referencing qtype_geogebra_question_helper still works.
class_alias(\qtype_geogebra\question_helper::class, 'qtype_geogebra_question_helper');
