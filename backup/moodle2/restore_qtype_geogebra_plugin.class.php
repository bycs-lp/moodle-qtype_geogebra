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
 * Restore plugin for qtype_geogebra.
 *
 * @package    qtype_geogebra
 * @author     Christoph Stadlbauer <christoph.stadlbauer@geogebra.org>
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the necessary information to restore one GeoGebra qtype plugin.
 *
 * @package    qtype_geogebra
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_geogebra_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level.
     *
     * @return restore_path_element[] The restore paths.
     */
    #[\Override]
    protected function define_question_plugin_structure(): array {
        $paths = [];
        $this->add_question_question_answers($paths);

        $elename = 'geogebra';
        $elepath = $this->get_pathfor('/geogebra');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    /**
     * Process the qtype/geogebra element.
     *
     * @param array|object $data The data to process.
     */
    public function process_geogebra($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $oldquestionid = $this->get_old_parentid('question');
        $newquestionid = $this->get_new_parentid('question');
        $questioncreated = (bool) $this->get_mappingid('question_created', $oldquestionid);

        if ($questioncreated) {
            $data->questionid = $newquestionid;
            $newitemid = $DB->insert_record('qtype_geogebra_options', $data);
            $this->set_mapping('qtype_geogebra_options', $oldid, $newitemid);
        }
    }
}
