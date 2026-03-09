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
 * External web service functions for mod_siyavula.
 *
 * @package     mod_siyavula
 * @copyright   2021 Solutto Consulting
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/lib.php');

class mod_siyavula_external extends external_api {

    /**
     * Parameter definition for update_section_grade.
     */
    public static function update_section_grade_parameters() {
        return new external_function_parameters([
            'cmid'          => new external_value(PARAM_INT,   'Course module ID of the siyavula activity'),
            'sectionid'     => new external_value(PARAM_INT,   'Siyavula section ID'),
            'sectionmastery' => new external_value(PARAM_FLOAT, 'Section mastery value (0–100)'),
        ]);
    }

    /**
     * Write mastery for a single section to the Moodle grade book using data
     * already present in the JS response, without making an external API call.
     *
     * Called from the browser via core/ajax after each question submission.
     *
     * @param int   $cmid           Course module ID.
     * @param int   $sectionid      Siyavula section ID.
     * @param float $sectionmastery Section mastery value (0–100).
     * @return array ['success' => bool]
     */
    public static function update_section_grade($cmid, $sectionid, $sectionmastery) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(
            self::update_section_grade_parameters(),
            ['cmid' => $cmid, 'sectionid' => $sectionid, 'sectionmastery' => $sectionmastery]
        );

        $cm = get_coursemodule_from_id('siyavula', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $moduleinstance = $DB->get_record('siyavula', ['id' => $cm->instance], '*', MUST_EXIST);

        if (empty($moduleinstance->subject_grade_selected)) {
            return ['success' => false];
        }

        // Clamp mastery to the valid range.
        $mastery = min(100.0, max(0.0, (float)$params['sectionmastery']));

        // Look up the Moodle grade item for this section.
        $node = $DB->get_record('siyavula_grade_nodes', [
            'instanceid' => $moduleinstance->id,
            'nodetype'   => 'section_item',
            'siyavulaid' => (string)$params['sectionid'],
        ]);

        if (!$node) {
            // Grade structure not yet initialised for this activity; bail gracefully.
            return ['success' => false];
        }

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_item.php');

        $gradeitem = grade_item::fetch(['id' => $node->moodleid]);
        if (!$gradeitem) {
            return ['success' => false];
        }

        if (siyavula_update_section_grade_if_changed($gradeitem, $USER->id, $mastery)) {
            // Do not pass $userid to grade_regrade_final_grades: when $userid is set
            // the function expects $updated_item (the fast raw-grade regrade path)
            // and throws without it. update_final_grade() marks parent categories as
            // needsupdate=1, so this regrade only recomputes items that changed.
            grade_regrade_final_grades($moduleinstance->course);

            // Sync the Moodle-aggregated activity category finalgrade to itemnumber=0
            // so the course total and completion tracking stay current.
            siyavula_sync_module_grade($moduleinstance, $USER->id);
        }

        return ['success' => true];
    }

    /**
     * Return definition for update_section_grade.
     */
    public static function update_section_grade_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the grade update succeeded'),
        ]);
    }
}
