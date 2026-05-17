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
 * GeoGebra question type upgrade code.
 *
 * @package    qtype_geogebra
 * @author     Christoph Stadlbauer <christoph.stadlbauer@geogebra.org>
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade code for the geogebra question type.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool True on success.
 */
function xmldb_qtype_geogebra_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2014081908) {
        $table = new xmldb_table('qtype_geogebra_options');
        $field = new xmldb_field('isexercise', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'constraints');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2014081908, 'qtype', 'geogebra');
    }

    if ($oldversion < 2022040800) {
        $table = new xmldb_table('qtype_geogebra_options');

        $field = new xmldb_field('forcedimensions', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'isexercise');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('width', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'forcedimensions');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('height', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'width');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2022040800, 'qtype', 'geogebra');
    }

    if ($oldversion < 2026051400) {
        // Code refactoring only — no schema changes.
        upgrade_plugin_savepoint(true, 2026051400, 'qtype', 'geogebra');
    }

    return true;
}
