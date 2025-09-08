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
 * Library of interface functions and constants.
 *
 * @package     mod_siyavula
 * @copyright   2021 Solutto Consulting
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function siyavula_supports($feature) {
    switch ($feature) {
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the mod_siyavula into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_siyavula_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function siyavula_add_instance($moduleinstance, $mform = null) {
    global $DB;
    $moduleinstance->timecreated = time();

    $id = $DB->insert_record('siyavula', $moduleinstance);

    return $id;
}

/**
 * Updates an instance of the mod_siyavula in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_siyavula_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function siyavula_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;
    return $DB->update_record('siyavula', $moduleinstance);
}

/**
 * Removes an instance of the mod_siyavula from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function siyavula_delete_instance($id) {
    global $DB;

    $exists = $DB->get_record('siyavula', array('id' => $id));
    if (!$exists) {
        return false;
    }

    $DB->delete_records('siyavula', array('id' => $id));

    return true;
}

/**
 * Is a given scale used by the instance of mod_siyavula?
 *
 * This function returns if a scale is being used by one mod_siyavula
 * if it has support for grading and scales.
 *
 * @param int $moduleinstanceid ID of an instance of this module.
 * @param int $scaleid ID of the scale.
 * @return bool True if the scale is used by the given mod_siyavula instance.
 */
function siyavula_scale_used($moduleinstanceid, $scaleid) {
    global $DB;

    if ($scaleid && $DB->record_exists('siyavula_grades', array('id' => $moduleinstanceid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of mod_siyavula.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale.
 * @return bool True if the scale is used by any mod_siyavula instance.
 */
function siyavula_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('siyavula_grades', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given mod_siyavula instance.
 *
 * Needed by {@see grade_update_mod_grades()}.
 *
 * @param stdClass $moduleinstance Instance object with extra cmidnumber and modname property.
 * @param bool $reset Reset grades in the gradebook.
 * @return void.
 */
function siyavula_grade_item_update($moduleinstance, $mastery) {
    global $CFG, $DB;
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    $params = array('itemname' => $moduleinstance->name, 'idnumber' => $moduleinstance->id);

    if ($mastery === 'reset') {
        $params['reset'] = true;
        $mastery = null;
    }
    $params['gradetype'] = GRADE_TYPE_VALUE; // If is type_none, then not saved nothing, so left for now VALUE.

    $siyavulagrades = 'siyavula_grades';
    $record = $DB->get_record($siyavulagrades, ['subject' => $mastery->subject,
        'grade' => $mastery->grade, 'userid' => $mastery->userid]);

    if ($record) {
        // If the record exist, update.
        $record->mastery = $mastery->rawgrade;
        $record->timemodified = time();
        $DB->update_record($siyavulagrades, $record);
    } else {
        $record = new stdClass();
        // If not exist, insert.
        $record->subject      = $mastery->subject;
        $record->grade        = $mastery->grade;
        $record->userid       = $mastery->userid;
        $record->mastery      = $mastery->rawgrade;
        $record->timemodified = time();
        $record->timecreated  = time();
        $DB->insert_record($siyavulagrades, $record);
    }
    return grade_update('mod/siyavula', $moduleinstance->course, 'mod', 'siyavula', $moduleinstance->id, 0, $mastery, $params);
}

/**
 * Delete grade item for given mod_siyavula instance.
 *
 * @param stdClass $moduleinstance Instance object.
 * @return grade_item.
 */
function siyavula_grade_item_delete($moduleinstance) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('/mod/siyavula', $moduleinstance->course, 'mod', 'siyavula',
                        $moduleinstance->id, 0, null, array('deleted' => 1));
}

/**
 * Update mod_siyavula grades in the gradebook.
 *
 * Needed by {@see grade_update_mod_grades()}.
 *
 * @param stdClass $moduleinstance Instance object with extra cmidnumber and modname property.
 * @param int $userid Update grade of specific user only, 0 means all participants.
 */
function siyavula_update_grades($siyavula, $userid, $subjectgradetoc) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $mastery = siyavula_get_toc_user_mastery($siyavula, $subjectgradetoc, $userid);
    siyavula_set_completion($siyavula, $userid, $mastery->rawgrade);
    return siyavula_grade_item_update($siyavula, $mastery);
}


/**
 * Sets activity completion state
 *
 * @param object $siyavula object
 * @param int $userid User ID
 * @param int $completionstate Completion state
 */
function siyavula_set_completion($siyavula, $userid, $mastery = 0) {
    $course = new stdClass();
    $course->id = $siyavula->course;
    $completion = new completion_info($course);

    // Check if completion is enabled site-wide, or for the course.
    if (!$completion->is_enabled()) {
        return;
    }

    $cm = get_coursemodule_from_instance('siyavula', $siyavula->id, $siyavula->course);
    if (empty($cm) || !$completion->is_enabled($cm)) {
        return;
    }

    if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
        if ($mastery > 0) {
            if ($mastery >= $siyavula->gradepass) { // COMPLETION_COMPLETE_PASS.
                $completion->update_state($cm, COMPLETION_COMPLETE_PASS, $userid);
            } else { // COMPLETION_COMPLETE_FAIL.
                $completion->update_state($cm, COMPLETION_COMPLETE_FAIL, $userid);
            }
        } else {
            $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
        }
    }

}

/**
 * Return grade for given user or all users.
 *
 * @global stdClass
 * @global object
 * @param int $subject_grade_toc
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function siyavula_get_toc_user_mastery($moduleinstance, $subjectgradetoc, $userid) {
    global $CFG, $DB;

    $info               = explode(':', $moduleinstance->subject_grade_selected);
    $subject            = $info[0];
    $grade              = $info[1];
    $summastery = 0;
    foreach ($subjectgradetoc->chapters as $k => $chapter) {
        $summastery += $chapter->mastery;
    }
    $countchapters = count($subjectgradetoc->chapters);
    $totalgrade = $summastery / $countchapters;

    $mastery = new stdClass();
    $mastery->userid   = $userid;
    $mastery->grade    = $grade;
    $mastery->subject  = $subject;
    $mastery->rawgrade = $totalgrade;
    return $mastery;
}

/**
 * Get the user Toc
 */
function get_subject_grade_toc($subject, $grade, $token, $userid = 0) {
    global $USER;

    $siyavulaconfig = get_config('filter_siyavula');
    $curl = curl_init();

    $user = $userid == 0 ? $USER : \core_user::get_user($userid);
    $externaluserid = siyavula_get_external_user_id($siyavulaconfig, $user);

    curl_setopt_array($curl, array(
      CURLOPT_URL => $siyavulaconfig->url_base."api/siyavula/v1/toc/user/$externaluserid/subject/$subject/grade/$grade",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array('JWT: '.$token),
    ));

    $response = curl_exec($curl);
    $response = json_decode($response);

    curl_close($curl);
    if (isset($response->errors)) {
        return $response->errors;
    } else {
        return $response;
    }
}
