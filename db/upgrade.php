<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * mod_siyavula database upgrade steps.
 *
 * @package     mod_siyavula
 * @copyright   2021 Solutto Consulting
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute mod_siyavula upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_siyavula_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025101701) {
        // Add siyavula_grade_nodes table to map Siyavula TOC nodes (chapters/sections)
        // to their corresponding Moodle grade_category or grade_item IDs.
        $table = new xmldb_table('siyavula_grade_nodes');

        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('instanceid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('nodetype',     XMLDB_TYPE_CHAR,    '16', null, XMLDB_NOTNULL);
        $table->add_field('siyavulaid',   XMLDB_TYPE_CHAR,    '64', null, null);
        $table->add_field('moodleid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary',       XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_instanceid', XMLDB_KEY_FOREIGN, ['instanceid'], 'siyavula', ['id']);

        $table->add_index('instanceid_nodetype_siyavulaid', XMLDB_INDEX_UNIQUE,
            ['instanceid', 'nodetype', 'siyavulaid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2025101701, 'siyavula');
    }

    return true;
}
