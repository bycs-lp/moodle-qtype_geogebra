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
 * Defines the editing form for the geogebra question type.
 *
 * @package    qtype_geogebra
 * @author     Christoph Stadlbauer <christoph.stadlbauer@geogebra.org>
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/shortanswer/edit_shortanswer_form.php');
require_once($CFG->dirroot . '/question/type/geogebra/question.php');

/**
 * Editing form for the geogebra question type.
 *
 * @package    qtype_geogebra
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_geogebra_edit_form extends question_edit_form {

    /** @var question_type The question type object. */
    public $qtypeobj;

    /** @var bool Whether the form is reloading. */
    public bool $reload = false;

    /** @var string|null The GeoGebra Tube URL. */
    public ?string $ggbturl = null;

    /** @var string|null GeoGebra parameters. */
    public ?string $ggbparameters = null;

    /** @var string|null GeoGebra views configuration. */
    public ?string $ggbviews = null;

    /** @var string|null GeoGebra codebase version. */
    public ?string $ggbcodebaseversion = null;

    /**
     * Constructor.
     *
     * @param string $submiturl The URL to submit the form to.
     * @param object $question The question being edited.
     * @param object $category The category of the question.
     * @param object $contexts The contexts for the question.
     * @param bool $formeditable Whether the form is editable.
     */
    public function __construct($submiturl, $question, $category, $contexts, $formeditable = true) {
        $this->question = $question;
        $this->qtypeobj = question_bank::get_qtype($this->question->qtype);
        $this->reload = optional_param('reload', false, PARAM_BOOL);

        if (!$this->reload) {
            // Use database data as this is first pass.
            if (!empty($question->id)) {
                $this->ggbturl = $question->options->ggbturl ?? null;
                $this->ggbparameters = $question->options->ggbparameters ?? null;
                $this->ggbviews = $question->options->ggbviews ?? null;
                $this->ggbcodebaseversion = $question->options->ggbcodebaseversion ?? null;
            }
        } else {
            $this->ggbturl = optional_param('ggbturl', '', PARAM_URL);
            $this->ggbparameters = optional_param('ggbparameters', '', PARAM_RAW);
            $this->ggbviews = optional_param('ggbviews', '', PARAM_RAW);
            $this->ggbcodebaseversion = optional_param('ggbcodebaseversion', '', PARAM_RAW);
        }
        parent::__construct($submiturl, $question, $category, $contexts, $formeditable);
    }

    /**
     * Get the list of form elements to repeat, one for each answer.
     *
     * @param MoodleQuickForm $mform The form being built.
     * @param string $label The label to use for each option.
     * @param array $gradeoptions The possible grades for each answer.
     * @param array $repeatedoptions Reference to array of repeated options to fill.
     * @param string $answersoption Reference to return the name of $question->options field holding answers.
     * @return array Array of form fields.
     */
    protected function get_per_answer_fields($mform, $label, $gradeoptions,
                                             &$repeatedoptions, &$answersoption): array {
        $gradestr = get_string('gradenoun');

        $repeated = [];
        $answeroptions = [];
        $answeroptions[] = $mform->createElement('text', 'answer', $label, ['size' => 40]);
        $answeroptions[] = $mform->createElement('select', 'fraction', $gradestr, $gradeoptions);
        $repeated[0] = $mform->createElement('group', 'answeroptions', $label, $answeroptions, null, false);
        $repeated[1] = $mform->createElement('hidden', 'feedback');
        $repeated[2] = $mform->createElement('text', 'feedbackfromfile',
            get_string('feedback', 'question'), ['size' => '60', 'disabled' => 'disabled']);
        $repeated[3] = $mform->createElement('hidden', 'format');

        $repeatedoptions['answer']['type'] = PARAM_RAW;
        $repeatedoptions['fraction']['default'] = 0;
        $repeatedoptions['feedback']['type'] = PARAM_RAW;
        $repeatedoptions['feedback']['default'] = '';
        $repeatedoptions['format']['type'] = PARAM_INT;
        $repeatedoptions['format']['default'] = 1;
        $repeatedoptions['feedbackfromfile']['type'] = PARAM_RAW;
        $repeatedoptions['feedbackfromfile']['default'] = get_string('willbereadfromfile', 'qtype_geogebra');

        $answersoption = 'answers';

        return $repeated;
    }

    /**
     * Define the inner form elements.
     *
     * @param MoodleQuickForm $mform The form being built.
     */
    protected function definition_inner($mform): void {
        $this->add_hidden_inputs($mform);

        $mform->addElement('header', 'ggbtheader', get_string('geogebraapplet', 'qtype_geogebra'));

        $this->add_geogebra_file($mform);
        $this->add_applet_elements($mform);
        $this->add_applet_options($mform);
        $this->add_randomizedvar_fields($mform);

        $mform->addElement('selectyesno', 'isexercise', get_string('isexercise', 'qtype_geogebra'));
        $mform->addHelpButton('isexercise', 'isexercise', 'qtype_geogebra');

        $mform->addElement('advcheckbox', 'forcedimensions', get_string('forcedimensionsenable', 'qtype_geogebra'),
            get_string('forcedimensions', 'qtype_geogebra'));
        $mform->setDefault('forcedimensions', 0);

        $mform->addElement('text', 'width', get_string('width', 'qtype_geogebra'));
        $mform->setType('width', PARAM_INT);
        $mform->addHelpButton('width', 'width', 'qtype_geogebra');
        $mform->hideIf('width', 'forcedimensions');

        $mform->addElement('text', 'height', get_string('height', 'qtype_geogebra'));
        $mform->setType('height', PARAM_INT);
        $mform->addHelpButton('height', 'height', 'qtype_geogebra');
        $mform->hideIf('height', 'forcedimensions');

        $this->add_per_answer_fields($mform, get_string('variableno', 'qtype_geogebra', '{no}'),
            question_bank::fraction_options(), 4, 1);

        if (array_key_exists('answeroptions[0]', $mform->_elementIndex)) {
            $mform->addHelpButton('answeroptions[0]', 'answervar', 'qtype_geogebra');
            $mform->addHelpButton('feedbackfromfile[0]', 'feedback', 'qtype_geogebra');
        }

        $this->add_interactive_settings();
        $mform->setExpanded('ggbtheader');
    }

    /**
     * Get the string for the "add more choices" button.
     *
     * @return string The localised string.
     */
    protected function get_more_choices_string(): string {
        return get_string('addmorevarblanks', 'qtype_geogebra');
    }

    /**
     * Preprocess question data before displaying the form.
     *
     * @param object $question The question data.
     * @return object The preprocessed question data.
     */
    protected function data_preprocessing($question): object {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_answers($question);
        $question = $this->data_preprocessing_hints($question);
        return $question;
    }

    /**
     * Validate the form data.
     *
     * @param array $data Array of ("fieldname" => value) of submitted data.
     * @param array $files Array of uploaded files "element_name" => tmp_file_path.
     * @return array Array of "element_name" => "error_description" if there are errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $this->check_is_applet_present($data, $errors);

        if (empty($data['isexercise'])) {
            $this->check_randomized_vars($data, $errors);
            $this->check_constraints($data, $errors);
            $this->check_answer($data, $errors);
            $this->check_fraction($data, $errors);
        } else {
            $this->check_is_exercise_present($data, $errors);
        }

        $this->check_force_dimensions($data, $errors);

        return $errors;
    }

    /**
     * Return the question type name.
     *
     * @return string 'geogebra'.
     */
    public function qtype(): string {
        return 'geogebra';
    }

    /**
     * Check if the applet data is present in the form.
     *
     * @param array $data The form data.
     * @param array $errors Reference to the errors array.
     */
    private function check_is_applet_present(array $data, array &$errors): void {
        if (empty($data['ggbparameters'])
            || empty($data['ggbviews'])
            || empty($data['ggbcodebaseversion'])
            || empty($data['ggbxml'])
        ) {
            $errors['loadappletgroup'] = get_string('noappletloaded', 'qtype_geogebra');
        }
    }

    /**
     * Validate forced dimensions settings.
     *
     * @param array $data The form data.
     * @param array $errors Reference to the errors array.
     */
    private function check_force_dimensions(array $data, array &$errors): void {
        if (!empty($data['forcedimensions'])) {
            if (empty($data['width'])) {
                $errors['width'] = get_string('widthnotzero', 'qtype_geogebra');
            }
            if (empty($data['height'])) {
                $errors['height'] = get_string('heightnotzero', 'qtype_geogebra');
            }
        }
    }

    /**
     * Validate randomized variable settings.
     *
     * @param array $data The form data.
     * @param array $errors Reference to the errors array.
     */
    private function check_randomized_vars(array $data, array &$errors): void {
        if (empty($data['israndomized'])) {
            return;
        }

        $vars = \qtype_geogebra\question_helper::get_variables_with_minmaxstep(
            $data['randomizedvar'] ?? '',
            $data['ggbxml'] ?? ''
        );

        if (empty($vars)) {
            $errors['randomizedvarsgroup'] = get_string('randomizedbutnovars', 'qtype_geogebra');
            return;
        }

        foreach ($vars as $label => $var) {
            if (abs($var['min'] - $var['max']) < PHP_FLOAT_EPSILON) {
                $errors['randomizedvarsgroup'] = get_string('mineqmax', 'qtype_geogebra', $label);
            } else if ($var['min'] + $var['increment'] > $var['max']) {
                $errors['randomizedvarsgroup'] = get_string('minplusstepgtmax', 'qtype_geogebra', $label);
            } else if (abs($var['increment']) < PHP_FLOAT_EPSILON) {
                $errors['randomizedvarsgroup'] = get_string('stepzero', 'qtype_geogebra', $label);
            }
        }
    }

    /**
     * Validate constraint settings.
     *
     * @param array $data The form data.
     * @param array $errors Reference to the errors array.
     */
    private function check_constraints(array $data, array &$errors): void {
        if (empty($data['constraints'])) {
            return;
        }

        $inequalitystrings = explode(',', $data['constraints']);

        // Check if the form of the relation is valid.
        foreach ($inequalitystrings as $inequalitystring) {
            if (!\qtype_geogebra\question_helper::is_valid_inequality($inequalitystring)) {
                $errors['constraints'] = ($errors['constraints'] ?? '') .
                    (isset($errors['constraints']) ? ', ' : '') .
                    get_string('invalidinequality', 'qtype_geogebra', htmlentities($inequalitystring));
            }
        }
        if (isset($errors['constraints'])) {
            return;
        }

        // Check if all vars in constraints are part of randomized vars.
        foreach ($inequalitystrings as $inequalitystring) {
            if (!\qtype_geogebra\question_helper::is_valid_inequality_for_randomizedvars(
                $inequalitystring,
                $data['randomizedvar'] ?? ''
            )) {
                $errors['constraints'] = ($errors['constraints'] ?? '') .
                    (isset($errors['constraints']) ? ', ' : '') .
                    get_string('invalidinequality', 'qtype_geogebra', htmlentities($inequalitystring));
            }
        }
        if (isset($errors['constraints'])) {
            return;
        }

        // Check if constraints are within the sliders min and max.
        foreach ($inequalitystrings as $inequalitystring) {
            if (!\qtype_geogebra\question_helper::is_valid_inequality_for_slider_minmax(
                $inequalitystring,
                $data['randomizedvar'] ?? '',
                $data['ggbxml'] ?? ''
            )) {
                $errors['constraints'] = ($errors['constraints'] ?? '') .
                    (isset($errors['constraints']) ? ', ' : '') .
                    get_string('invalidinequality', 'qtype_geogebra', htmlentities($inequalitystring));
            }
        }
        if (isset($errors['constraints'])) {
            return;
        }

        // Check if constraints can be met.
        $vars = \qtype_geogebra\question_helper::get_variables_with_minmaxstep(
            $data['randomizedvar'] ?? '',
            $data['ggbxml'] ?? ''
        );
        $inequalities = [];
        foreach ($inequalitystrings as $inequalitystring) {
            $matches = [];
            if (preg_match('/^([a-z_0-9]+)(<=|<|>=|>)([a-z_0-9]+)$/i', $inequalitystring, $matches)) {
                $inequalities[] = $matches;
            }
        }
        $a = \qtype_geogebra\question_helper::randomize_vars($vars, $inequalities, 1);
        if ($a !== null) {
            $a->inequalities = htmlentities($data['constraints']);
            $errors['constraints'] = get_string('constraintswrongortoohard', 'qtype_geogebra', $a);
        }
    }

    /**
     * Validate answer variables.
     *
     * @param array $data The form data.
     * @param array $errors Reference to the errors array.
     */
    private function check_answer(array $data, array &$errors): void {
        if (!isset($data['answer']) || empty($data['ggbxml'])) {
            return;
        }

        $i = 0;
        $xml = @simplexml_load_string($data['ggbxml']);
        if ($xml === false) {
            return;
        }

        foreach ($data['answer'] as $label) {
            if (!empty($label)) {
                if (!empty($data['isexercise'])) {
                    $errors['isexercise'] = get_string('noanswersorrandomizationallowed', 'qtype_geogebra');
                } else {
                    $varok = false;
                    foreach ($xml->construction->element as $elem) {
                        if ($label === (string) $elem['label']) {
                            $varok = true;
                            break;
                        }
                    }
                    if (!$varok) {
                        $errors['answeroptions[' . $i . ']'] = get_string('variablenamewrong', 'qtype_geogebra');
                    }
                }
            }
            $i++;
        }
    }

    /**
     * Validate that answer fractions sum correctly.
     *
     * @param array $data The form data.
     * @param array $errors Reference to the errors array.
     */
    private function check_fraction(array $data, array &$errors): void {
        if (!isset($data['fraction'])) {
            return;
        }

        $manuallygraded = true;
        $fractionok = true;

        if (($data['noanswers'] ?? 0) > 1) {
            for ($i = 0; $i < count($data['answer']); $i++) {
                if (!empty($data['answer'][$i]) && $data['fraction'][$i] > 0.000001) {
                    $manuallygraded = false;
                    $fractionok = false;
                }
            }
            if (!$manuallygraded) {
                $fractionok = false;
                $fractions = $data['fraction'];
                $count = pow(2, count($fractions)) - 1;
                for ($i = $count; $i > 0 && !$fractionok; $i--) {
                    $sum = 0;
                    $rem = $i;
                    for ($k = count($fractions) - 1; $k >= 0; $k--) {
                        $sum += ((int) ($rem / pow(2, $k))) * $fractions[$k];
                        $rem = $i % pow(2, $k);
                    }
                    if ($sum <= 1.000001 && $sum >= 0.999999) {
                        $fractionok = true;
                    }
                }
            }
        } else {
            $fractionok = ($data['fraction'][0] ?? 0) > 0.999999
                || (($data['fraction'][0] ?? 0) < 0.000001 && ($data['answer'][0] ?? '') === '');
        }

        if (!$fractionok) {
            $errors['answeroptions[0]'] = get_string('nofractionsumeq1', 'qtype_geogebra');
        }
    }

    /**
     * Add hidden form inputs.
     *
     * @param MoodleQuickForm $mform The form being built.
     */
    private function add_hidden_inputs($mform): void {
        $mform->addElement('hidden', 'reload', 1);
        $mform->setType('reload', PARAM_INT);

        $mform->addElement('hidden', 'ggbparameters');
        $mform->setType('ggbparameters', PARAM_RAW);

        $mform->addElement('hidden', 'ggbviews');
        $mform->setType('ggbviews', PARAM_RAW);

        $mform->addElement('hidden', 'ggbcodebaseversion');
        $mform->setType('ggbcodebaseversion', PARAM_RAW);

        $mform->addElement('hidden', 'ggbxml');
        $mform->setType('ggbxml', PARAM_RAW);

        $mform->addElement('hidden', 'ggbexercise');
        $mform->setType('ggbexercise', PARAM_RAW);
    }

    /**
     * Add randomized variable form fields.
     *
     * @param MoodleQuickForm $mform The form being built.
     */
    private function add_randomizedvar_fields($mform): void {
        $mform->addElement('selectyesno', 'israndomized', get_string('israndomized', 'qtype_geogebra'));

        $randomizedvars = [];
        $randomizedvars[] =& $mform->createElement('button', 'getvars', get_string('getvars', 'qtype_geogebra'));
        $randomizedvars[] =& $mform->createElement('text', 'randomizedvar', null, ['size' => '20']);
        $mform->setType('randomizedvar', PARAM_RAW);
        $mform->addGroup($randomizedvars, 'randomizedvarsgroup', get_string('randomizedvar', 'qtype_geogebra'),
            [' '], false);
        $mform->addHelpButton('randomizedvarsgroup', 'randomizedvar', 'qtype_geogebra');
        $mform->disabledIf('randomizedvarsgroup', 'israndomized', 'neq', 1);

        $mform->addElement('text', 'constraints', get_string('constraints', 'qtype_geogebra'));
        $mform->setType('constraints', PARAM_RAW);
        $mform->disabledIf('constraints', 'israndomized', 'neq', 1);
        $mform->addHelpButton('constraints', 'constraints', 'qtype_geogebra');
    }

    /**
     * Add applet elements to the form.
     *
     * @param MoodleQuickForm $mform The form being built.
     */
    private function add_applet_elements($mform): void {
        global $PAGE;

        $loadappletgroup = [];
        $loadappletgroup[] =& $mform->createElement('button', 'loadapplet', get_string('loadapplet', 'qtype_geogebra'));
        $loadappletgroup[] =& $mform->createElement('html', '<span>&nbsp;</span>');
        $mform->addGroup($loadappletgroup, 'loadappletgroup', get_string('loadapplet', 'qtype_geogebra'), [' '], false);
        $mform->addHelpButton('loadappletgroup', 'loadapplet', 'qtype_geogebra');
        $mform->disabledIf('loadappletgroup', 'usefile', 'checked');

        $mform->addElement('html', '<div class="form-group row fitem" id="applet_container1_fitem"><div class="col-md-3">'
            . get_string('geogebraapplet', 'qtype_geogebra')
            . '</div><div id="applet_container1" class="felement"></div></div>');

        $lang = current_language();

        if (!empty($this->ggbparameters) && !empty($this->ggbviews) && !empty($this->ggbcodebaseversion)) {
            $applet = '<article id="applet_parameters" class="qtype_geogebra-article"'
                . ' data-parameters="' . s($this->ggbparameters) . '"'
                . ' data-views="' . s($this->ggbviews) . '"'
                . ' data-codebase="' . s($this->ggbcodebaseversion) . '"'
                . ' data-lang="' . s($lang) . '"'
                . ' data-html5nowebsimple="true">'
                . '</article>';
            $mform->addElement('html', $applet);
        }
        $PAGE->requires->js_call_amd('qtype_geogebra/ggbt', 'init');
    }

    /**
     * Add applet advanced options to the form.
     *
     * @param MoodleQuickForm $mform The form being built.
     */
    private function add_applet_options($mform): void {
        $appletadvancedsettings = get_string('applet_advanced_settings', 'qtype_geogebra');
        $enablelabeldrags = get_string('enable_label_drags', 'qtype_geogebra');
        $enablerightclick = get_string('enable_right_click', 'qtype_geogebra');
        $enableshiftdragzoom = get_string('enable_shift_drag_zoom', 'qtype_geogebra');
        $showalgebrainput = get_string('show_algebra_input', 'qtype_geogebra');
        $showmenubar = get_string('show_menu_bar', 'qtype_geogebra');
        $showreseticon = get_string('show_reset_icon', 'qtype_geogebra');
        $showtoolbar = get_string('show_tool_bar', 'qtype_geogebra');

        $options = <<<HTML
<div id="applet_options" class="form-group row fitem">
    <div class="col-md-3">
        <div class="fitemtitle"><label for="applet_options">{$appletadvancedsettings}</label></div>
    </div>
    <div class="fitem col-md-9 felement">
        <fieldset class="felement fgroup">
            <input type="checkbox" id="enableRightClick" name="enableRightClick" value="1">
            <label for="enableRightClick">{$enablerightclick}</label><br>
            <input type="checkbox" id="enableLabelDrags" name="enableLabelDrags" value="1">
            <label for="enableLabelDrags">{$enablelabeldrags}</label><br>
            <input type="checkbox" id="showResetIcon" name="showResetIcon" value="1" checked="checked">
            <label for="showResetIcon">{$showreseticon}</label><br>
            <input type="checkbox" id="enableShiftDragZoom" name="enableShiftDragZoom" value="1" checked="checked">
            <label for="enableShiftDragZoom">{$enableshiftdragzoom}</label><br>
            <input type="checkbox" id="showMenuBar" name="showMenuBar" value="1">
            <label for="showMenuBar">{$showmenubar}</label><br>
            <input type="checkbox" id="showToolBar" name="showToolBar" value="1">
            <label for="showToolBar">{$showtoolbar}</label><br>
            <input type="checkbox" id="showAlgebraInput" name="showAlgebraInput" value="1">
            <label for="showAlgebraInput">{$showalgebrainput}</label><br>
        </fieldset>
    </div>
</div>
HTML;

        $mform->addElement('html', $options, 'advanced');
    }

    /**
     * Add GeoGebra file URL input.
     *
     * @param MoodleQuickForm $mform The form being built.
     */
    private function add_geogebra_file($mform): void {
        $ggbturlinput = [];
        $ggbturlinput[] =& $mform->createElement('text', 'ggbturl', '', ['size' => '20']);
        $mform->setType('ggbturl', PARAM_RAW_TRIMMED);
        $mform->addGroup($ggbturlinput, 'ggbturlinput', get_string('ggbturl', 'qtype_geogebra'), [' '], false);
        $mform->addHelpButton('ggbturlinput', 'ggbturl', 'qtype_geogebra');
        $mform->addElement('checkbox', 'usefile', get_string('useafile', 'qtype_geogebra'),
            get_string('dragndrop', 'qtype_geogebra'));
        if (!empty($this->ggbparameters) && empty($this->ggbturl)) {
            $mform->setDefault('usefile', true);
        }
    }

    /**
     * Validate exercise-specific settings.
     *
     * @param array $data The form data.
     * @param array $errors Reference to the errors array.
     */
    private function check_is_exercise_present(array $data, array &$errors): void {
        if (!empty($data['isexercise'])) {
            // Ensure no answer variables are set when exercise mode is active.
            if (!empty($data['answer'])) {
                foreach ($data['answer'] as $answer) {
                    if (!empty($answer)) {
                        $errors['isexercise'] = get_string('noanswersorrandomizationallowed', 'qtype_geogebra');
                        return;
                    }
                }
            }
        }
    }
}
