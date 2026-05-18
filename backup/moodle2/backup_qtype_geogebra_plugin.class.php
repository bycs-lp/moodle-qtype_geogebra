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
 * Backup plugin for qtype_geogebra.
 *
 * @package    qtype_geogebra
 * @author     Christoph Stadlbauer <christoph.stadlbauer@geogebra.org>
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information to backup GeoGebra questions.
 *
 * @package    qtype_geogebra
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_geogebra_plugin extends backup_qtype_plugin {
    /**
     * Returns the qtype information to attach to question element.
     *
     * @return backup_plugin_element The plugin element.
     */
    #[\Override]
    protected function define_question_plugin_structure() {
        $plugin = $this->get_plugin_element(null, '../../qtype', 'geogebra');

        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $this->add_question_question_answers($pluginwrapper);

        $geogebra = new backup_nested_element('geogebra', ['id'], [
            'ggbturl', 'ggbparameters', 'ggbviews', 'ggbcodebaseversion', 'ggbxml',
            'israndomized', 'randomizedvar', 'constraints', 'isexercise',
            'forcedimensions', 'width', 'height',
        ]);

        $pluginwrapper->add_child($geogebra);
        $geogebra->set_source_table('qtype_geogebra_options', ['questionid' => backup::VAR_PARENTID]);

        return $plugin;
    }
}
