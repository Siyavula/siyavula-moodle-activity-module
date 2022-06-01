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
 * The main mod_siyavula configuration form.
 *
 * @package     mod_siyavula
 * @copyright   2021 Solutto Consulting
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package     mod_siyavula
 * @copyright   2021 Solutto Consulting
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_siyavula_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('siyavulaname', 'mod_siyavula'), array('size' => '64'));

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'siyavulaname', 'mod_siyavula');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Adding the rest of mod_siyavula settings, spreading all them into this fieldset
        // ... or adding more fieldsets ('header' elements) if needed for better logic.
        $mform->addElement('header', 'title_select_subject', get_string('title_select_subject', 'mod_siyavula'));
        $mform->addElement('static', 'label1', '', get_string('desc_select_subject', 'mod_siyavula'));

        $configsubjects = get_config('mod_siyavula');
        $configsubjectsarray = preg_split("/(\r\n|\n|\r)/", $configsubjects->grades_subjects);
        foreach ($configsubjectsarray as $subjectgrade) {
            $radiosubject = [];
            $info = explode(':', $subjectgrade);
            $subject = $info[0];
            $grades = explode(',', $info[1]);
            foreach ($grades as $grade) {
                $radiosubject[] = $mform->createElement('radio', 'subject_grade_selected', '',
                    get_string('grade', 'mod_siyavula') . " $grade", "$subject:$grade");
            }
            $mform->addGroup($radiosubject, 'radioar', ucfirst($subject), array('<br/>'), false);

        }

        // Add here custom grade.
        $mform->addElement('header', 'title_select_grade', get_string('grade', 'mod_siyavula'));
        $gradepassfieldname = 'gradepass';
        $gradefieldname = '';
        $mform->addElement('text', $gradepassfieldname, get_string($gradepassfieldname, 'grades'));
        $mform->addHelpButton($gradepassfieldname, $gradepassfieldname, 'grades');
        $mform->setDefault($gradepassfieldname, '');
        $mform->setType($gradepassfieldname, PARAM_RAW);
        $mform->hideIf($gradepassfieldname, "{$gradefieldname}[modgrade_type]", 'eq', 'none');

        // Add standard elements.
        $this->standard_coursemodule_elements(); // If comment this line, appear error.

        // Add standard buttons.
        $this->add_action_buttons();
    }

}
