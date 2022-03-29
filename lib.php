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
    //print_object($moduleinstance);die('test');
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

    if ($scaleid && $DB->record_exists('siyavula', array('id' => $moduleinstanceid, 'grade' => -$scaleid))) {
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

    if ($scaleid and $DB->record_exists('siyavula', array('grade' => -$scaleid))) {
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
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    $params = array('itemname'=>$moduleinstance->name, 'idnumber'=>$moduleinstance->id);

    /*if (!$moduleinstance->assessed or $moduleinstance->scale == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($moduleinstance->scale > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $moduleinstance->scale;
        $params['grademin']  = 0;

    } else if ($moduleinstance->scale < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$moduleinstance->scale;
    }*/

    if ($mastery  === 'reset') {
        $params['reset'] = true;
        $mastery = NULL;
    }
    $params['gradetype'] = GRADE_TYPE_VALUE; // If is type_none, then not saved nothing, so left for now VALUE

    $siyavula_grades = 'siyavula_grades';
    $record = $DB->get_record($siyavula_grades, ['subject' => $mastery->subject, 'grade' => $mastery->grade, 'userid' => $mastery->userid]);
    
    if($record) {
        // If the record exist, update
        $record->mastery = $mastery->rawgrade;
        $record->timemodified = time();
        $DB->update_record($siyavula_grades, $record);
    }
    else {
        // If not exist, insert
        $record->subject      = $mastery->subject;
        $record->grade        = $mastery->grade;
        $record->userid       = $mastery->userid;
        $record->mastery      = $mastery->rawgrade;
        $record->timemodified = time();
        $record->timecreated  = time();
        $DB->insert_record($siyavula_grades, $record);
    }
    return grade_update('mod/siyavula', $moduleinstance->course, 'mod', 'siyavula', $moduleinstance->id, 0, $mastery, $params);
    /*global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($moduleinstance->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($moduleinstance->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $moduleinstance->grade;
        $item['grademin']  = 0;
    } else if ($moduleinstance->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$moduleinstance->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($reset) {
        $item['reset'] = true;
    }

    grade_update('/mod/siyavula', $moduleinstance->course, 'mod', 'mod_siyavula', $moduleinstance->id, 0, null, $item);*/
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
function siyavula_update_grades($siyavula, $userid, $subject_grade_toc) {
    /*global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');
    // Populate array of grade objects indexed by userid.
    $grades = array();
    grade_update('/mod/siyavula', $moduleinstance->course, 'mod', 'mod_siyavula', $moduleinstance->id, 0, $grades);*/
    
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $mastery = siyavula_get_toc_user_mastery($siyavula, $subject_grade_toc, $userid);
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
    if($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
        //var_dump($mastery);
        if($mastery > 0) {
            if($mastery >= $siyavula->gradepass) { // COMPLETION_COMPLETE_PASS
        //var_dump("COMPLETION_COMPLETE_PASS" . COMPLETION_COMPLETE_PASS);
                $completion->update_state($cm, COMPLETION_COMPLETE_PASS, $userid);
            }
            else { // COMPLETION_COMPLETE_FAIL
        //var_dump("COMPLETION_COMPLETE_FAIL" . COMPLETION_COMPLETE_FAIL);
                $completion->update_state($cm, COMPLETION_COMPLETE_FAIL, $userid);
            }
        }
        else {
        //var_dump("COMPLETION_COMPLETE" . COMPLETION_COMPLETE);
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
function siyavula_get_toc_user_mastery($moduleinstance, $subject_grade_toc, $userid) {
    global $CFG, $DB;

    $info               = explode(':', $moduleinstance->subject_grade_selected);
    $subject            = $info[0];
    $grade              = $info[1];
    $sum_mastery = 0;
    foreach($subject_grade_toc->chapters as $k => $chapter) {
        $sum_mastery += $chapter->mastery;
    }
    $count_chapters = count($subject_grade_toc->chapters);
    $total_grade = $sum_mastery / $count_chapters;
    
    $mastery = new stdClass();
    $mastery->userid   = $userid;
    $mastery->grade    = $grade;
    $mastery->subject  = $subject;
    $mastery->rawgrade = $total_grade;
    return $mastery;
}

/**
 * Get the user Toc
 */
function get_subject_grade_toc($subject, $grade, $token, $userid = 0) {
    global $USER;

    $siyavula_config = get_config('filter_siyavula');
    $curl = curl_init();

    if($userid == 0) {
        $email = $USER->email;
    }
    else {
        $user = core_user::get_user($userid);
        $email = $user->email;
    }
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => $siyavula_config->url_base."api/siyavula/v1/toc/user/$email/subject/$subject/grade/$grade",
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
    if(isset($response->errors)){
        return $response->errors;
    }else{
        return $response;
    }
}

//Html render practice session
function get_activity_practice_toc($questionid, $token, $external_token, $baseurl){
    global $USER, $CFG;

    $data = array(
        'template_id' => intval($questionid), 
    );
    
    $payload = json_encode($data);
    
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => $baseurl.'api/siyavula/v1/activity/create/practice/'.$questionid.'',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => array('JWT: ' .$token, 'Authorization: JWT ' .$external_token),
    ));
   
    $response = curl_exec($curl);
    $response = json_decode($response);

    $question_html = $response->response->question_html;
    $new_question_html = '';
    $new_question_html .= '<script src="https://www.siyavula.com/static/themes/emas/node_modules/mathjax/MathJax.js?id=2&config=TeX-MML-AM_HTMLorMML-full"></script>'; // Para cargar el MathJax

    $new_question_html .= $question_html;
        
    $response->response->question_html = $new_question_html;
    
    curl_close($curl);

    return $response;
}

function get_html_question_practice_toc($questionapi, $questionchaptertitle,$questionchaptermastery,$questionsectiontitle,$questionmastery){
    global $CFG, $DB;
    
    //Enabled mathjax loader 
    $siyavula_config = get_config('filter_siyavula');
    
    $to_render_pr = '';
    
    if($siyavula_config->mathjax == 1){
       $to_render  = '<script src="https://www.siyavula.com/static/themes/emas/node_modules/mathjax/MathJax.js?config=TeX-MML-AM_HTMLorMML-full"></script>';
    }
    
    $to_render_pr .= '<link rel="stylesheet" href="https://www.siyavula.com/static/themes/emas/siyavula-api/siyavula-api.min.css"/>';
    $to_render_pr .= '<link rel="stylesheet" href="https://www.siyavula.com/static/themes/emas/question-api/question-api.min.css"/>';
    $to_render_pr .= '<link rel="stylesheet" href="'.$CFG->wwwroot.'/filter/siyavula/styles/general.css"/>';
    
    $to_render_pr .= '<main class="sv-region-main emas sv practice-section-question">
                      <div class="item-psq question">
                        <div id="monassis" class="monassis monassis--practice monassis--maths monassis--siyavula-api">
                          <div class="question-wrapper">
                            <div class="question-content">
                            '.$questionapi.'
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="item-psq">
                        
                        <div class="sv-panel-wrapper sv-panel-wrapper--toc">
                        <div class="sv-panel sv-panel--dashboard sv-panel--toc sv-panel--toc-modern no-secondary-section">
                          <div class="sv-panel__header">
                            <div class="sv-panel__title">
                              Busy practising
                            </div>
                          </div>
                          <div class="sv-panel__section sv-panel__section--primary" id="mini-dashboard-toc-primary">
                            <div class="sv-panel__section-header">
                              <div class="sv-panel__section-title">This exercise is from:</div>
                            </div>
                            <div class="sv-panel__section-body">
                              <div class="sv-toc sv-toc--dashboard-mastery-primary">
                                <ul class="sv-toc__chapters">
                                  <li class="sv-toc__chapter">
                                    <div class="sv-toc__chapter-header">
                                      <div class="sv-toc__chapter-title"><span id="chapter-mastery-title">'.$questionchaptertitle.'</span></div>
                                      <div class="sv-toc__chapter-mastery">
                                        <div class="sv-toc__section-mastery">
                                          <progress class="progress" id="chapter-mastery" value="'.round($questionchaptermastery).'" max="100" data-text="'.round($questionchaptermastery).'%"></progress>
                                        </div>
                                      </div>
                                    </div>
                                    <div class="sv-toc__chapter-body">
                                      <ul class="sv-toc__sections">
                                          <li class="sv-toc__section ">
                                            <div class="sv-toc__section-header">
                                              <div class="sv-toc__section-title">
                                                <span id="section-mastery-title">'.$questionsectiontitle.'</span>
                                              </div>
                                              <div class="sv-toc__section-mastery">
                                                <progress class="progress" id="section-mastery" value="'.round($questionmastery).'" max="100" data-text="'.round($questionmastery).'%"></progress><br>
                                              </div>
                                            </div>
                                          </li>
                                      </ul>
                                    </div>
                                  </li>
                                </ul>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </main>';
                    
    return $to_render_pr;
}


//Html render practice session
function retry_question_html($activityid, $responseid, $token, $external_token, $baseurl){
    global $USER, $CFG;

    $curl = curl_init();
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => $baseurl.'api/siyavula/v1/activity/'.$activityid.'/response/'.$responseid.'/retry',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_HTTPHEADER => array('JWT: ' .$token, 'Authorization: JWT ' .$external_token),
    ));
   
    $response = curl_exec($curl);
    $response = json_decode($response);

    $question_html = $response->response->question_html;
    $new_question_html = '';
    $new_question_html .= '<script src="https://www.siyavula.com/static/themes/emas/node_modules/mathjax/MathJax.js?id=2&config=TeX-MML-AM_HTMLorMML-full"></script>'; // Para cargar el MathJax

    $new_question_html .= $question_html;
        
    $response->response->question_html = $new_question_html;

    curl_close($curl);

    return $response;
}