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
 * GeoGebra question renderer class.
 *
 * @package    qtype_geogebra
 * @author     Christoph Stadlbauer <christoph.stadlbauer@geogebra.org>
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generates the output for geogebra questions.
 *
 * @package    qtype_geogebra
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_geogebra_renderer extends qtype_renderer {

    /**
     * Generate the display of the formulation part of the question.
     *
     * @param question_attempt $qa The question attempt to display.
     * @param question_display_options $options Controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options): string {
        /** @var qtype_geogebra_question $question */
        $question = $qa->get_question();

        $scalingcontainerclass = $qa->get_qt_field_name('scalingcontainer');
        $b64inputname = $qa->get_qt_field_name('ggbbase64');
        $xmlinputname = $qa->get_qt_field_name('ggbxml');
        $answerinputname = $qa->get_qt_field_name('answer');
        $exerciseinputname = $qa->get_qt_field_name('exerciseresult');
        $ggbdivname = $qa->get_qt_field_name('ggbdiv');
        $appletparametersid = $qa->get_qt_field_name('applet_parameters');

        $responsevars = [];
        if (!empty($question->answers)) {
            foreach ($question->answers as $answer) {
                $responsevars[] = $answer->answer;
            }
        }

        $configuredcodebase = get_config('qtype_geogebra', 'codebase');
        $codebase = !empty($configuredcodebase) ? $configuredcodebase : '';

        $validationerror = '';
        if ($qa->get_state() == question_state::$invalid) {
            $validationerror = $question->get_validation_error([
                'answer' => $qa->get_last_qt_var('answer'),
                'ggbxml' => $qa->get_last_qt_var('ggbxml'),
                'ggbbase64' => $qa->get_last_qt_var('ggbbase64'),
                'exerciseresult' => $qa->get_last_qt_var('exerciseresult'),
            ]);
        }

        $templatecontext = [
            'scalingcontainerclass' => $scalingcontainerclass,
            'b64inputname' => $b64inputname,
            'b64current' => $qa->get_last_qt_var('ggbbase64') ?? '',
            'xmlinputname' => $xmlinputname,
            'xmlcurrent' => $qa->get_last_qt_var('ggbxml') ?? '',
            'answerinputname' => $answerinputname,
            'answercurrent' => $qa->get_last_qt_var('answer') ?? '',
            'exerciseinputname' => $exerciseinputname,
            'exercisecurrent' => $qa->get_last_qt_var('exerciseresult') ?? '',
            'questiontext' => $question->format_questiontext($qa),
            'ggbdivname' => $ggbdivname,
            'appletparametersid' => $appletparametersid,
            'ggbparameters' => $question->ggbparameters ?? '',
            'ggbviews' => $question->ggbviews ?? '',
            'codebase' => $codebase,
            'currentvals' => json_encode($question->currentvals),
            'responsevars' => json_encode($responsevars),
            'slot' => $qa->get_slot(),
            'lang' => current_language(),
            'forcedimensions' => $question->forcedimensions ? 1 : 0,
            'width' => $question->width ?? 0,
            'height' => $question->height ?? 0,
            'haserror' => !empty($validationerror),
            'validationerror' => $validationerror,
        ];

        $this->page->requires->js_call_amd('qtype_geogebra/ggbq', 'init', [$appletparametersid]);

        return $this->output->render_from_template('qtype_geogebra/formulation', $templatecontext);
    }

    /**
     * Generate the specific feedback.
     *
     * @param question_attempt $qa The question attempt to display.
     * @return string HTML fragment.
     */
    public function specific_feedback(question_attempt $qa): string {
        /** @var qtype_geogebra_question $question */
        $question = $qa->get_question();
        $feedback = '';

        if ($qa->get_state()->is_gave_up()) {
            return '';
        }

        $itemid = 0;
        if ($question->isexercise) {
            $exerciseresultraw = $qa->get_last_qt_var('exerciseresult');
            if (empty($exerciseresultraw)) {
                return '';
            }
            $exerciseresult = json_decode($exerciseresultraw);
            if ($exerciseresult === null) {
                return '';
            }

            $singlecorrectignoreothers = false;
            foreach ($exerciseresult as $assignment) {
                if (is_object($assignment) && isset($assignment->fraction) && $assignment->fraction > 0.999) {
                    $singlecorrectignoreothers = true;
                    if (!empty($assignment->hint)) {
                        if ($feedback !== '') {
                            $feedback .= '<br>';
                        }
                        $feedback .= $question->format_text(
                            $assignment->hint,
                            FORMAT_HTML,
                            $qa,
                            'question',
                            'answerfeedback',
                            $itemid++
                        );
                    }
                }
            }
            foreach ($exerciseresult as $assignment) {
                if (!is_object($assignment) || !isset($assignment->fraction)) {
                    continue;
                }
                if (!$singlecorrectignoreothers || $assignment->fraction < 0) {
                    if (!empty($assignment->hint)) {
                        if ($feedback !== '') {
                            $feedback .= '<br>';
                        }
                        $feedback .= $question->format_text(
                            $assignment->hint,
                            FORMAT_HTML,
                            $qa,
                            'question',
                            'answerfeedback',
                            $itemid++
                        );
                    }
                }
            }
        } else {
            $response = $qa->get_last_qt_var('answer') ?? '';
            $i = 0;
            foreach ($question->answers as $answer) {
                if ((bool) substr($response, $i, 1)) {
                    $feedback .= $question->format_text(
                        $answer->feedback,
                        $answer->feedbackformat,
                        $qa,
                        'question',
                        'answerfeedback',
                        $answer->id
                    );
                }
                $i++;
            }
        }
        return $feedback;
    }
}
