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
 * Prints an instance of mod_siyavula.
 *
 * @package     mod_siyavula
 * @copyright   2021 Solutto Consulting
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

require_once($CFG->dirroot. '/filter/siyavula/lib.php');

use filter_siyavula\renderables\practice_activity_renderable;
use filter_siyavula\renderables\standalone_activity_renderable;

// Course module id.
$courseid = optional_param('id', 0, PARAM_INT);
// Activity instance id.
$s = optional_param('s', 0, PARAM_INT);
// Section id selected.
$sectionid = optional_param('sid', null, PARAM_INT);
$activityid = optional_param('aid', null, PARAM_RAW);
$responseid = optional_param('rid', null, PARAM_RAW);

if ($courseid) {
    $coursemodule = get_coursemodule_from_id('siyavula', $courseid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $coursemodule->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('siyavula', array('id' => $coursemodule->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('siyavula', array('id' => $s), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $coursemodule = get_coursemodule_from_instance('siyavula', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $coursemodule);

$modulecontext = context_module::instance($coursemodule->id);

$event = \mod_siyavula\event\course_module_viewed::create(array(
    'objectid' => $moduleinstance->id,
    'context' => $modulecontext
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('siyavula', $moduleinstance);
$event->trigger();

$PAGE->set_url('/mod/siyavula/view.php', array('id' => $coursemodule->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

$PAGE->requires->css('/filter/siyavula/styles/general.css');

echo $OUTPUT->header();

// Check if user is guest or not logged in.
if (isguestuser() || !isloggedin()) {
    $loginurl = $CFG->wwwroot . '/login/index.php';
    echo '<div class="alert alert-info" role="alert">' .
         '<strong>Siyavula Activity:</strong> Please <a href="' . $loginurl . '">log in</a> to access this content.' .
         '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Subject and grade not configured.
if (!$moduleinstance->subject_grade_selected) {
    echo core\notification::error(get_string('subjectnotdefined', 'siyavula'), false);
    echo $OUTPUT->footer();
    exit;
}

$info = explode(':', $moduleinstance->subject_grade_selected);
$subject = $info[0];
$grade = $info[1];
$clientip = $_SERVER['REMOTE_ADDR'];
$siyavulaconfig = get_config('filter_siyavula');
$baseurl = $siyavulaconfig->url_base;
$token = siyavula_get_user_token($siyavulaconfig, $clientip);
$usertoken = siyavula_get_external_user_token($siyavulaconfig, $clientip, $token);
$subjectgradetoc  = get_subject_grade_toc($subject, $grade, $token);

// If selected one grade.
if ($moduleinstance->subject_grade_selected && $sectionid == null) {

    // Sync grades from Siyavula at most once per hour per activity per session.
    $synckey = 'siyavula_toc_sync_' . $moduleinstance->id;
    if (!isset($SESSION->$synckey) || (time() - $SESSION->$synckey) > 3600) {
        siyavula_update_grades($moduleinstance, $USER->id, $subjectgradetoc);
        $SESSION->$synckey = time();
    }
    siyavula_update_grades($moduleinstance, $USER->id, $subjectgradetoc);

    // Build template context from API data.
    $toccontext = ['chapters' => []];
    foreach ($subjectgradetoc->chapters ?? [] as $k => $chapter) {
        $sections = [];
        foreach ($chapter->sections as $section) {
            $sections[] = [
                'id'      => $section->id,
                'title'   => $section->title,
                'mastery' => round($section->mastery),
                'url'     => (new moodle_url('/mod/siyavula/view.php',
                             ['id' => $coursemodule->id, 'sid' => $section->id]))->out(false),
            ];
        }
        $toccontext['chapters'][] = [
            'index'    => $k,
            'id'       => $chapter->id,
            'title'    => $chapter->title,
            'mastery'  => round($chapter->mastery),
            'sections' => $sections,
        ];
    }
    echo $OUTPUT->render_from_template('mod_siyavula/toc', $toccontext);
}

// If selected one section, render it.
if ($sectionid != null && $activityid === null && $responseid === null) {

    // Ensure the grade structure exists for this instance. This is normally created
    // on the first throttled TOC sync, but a student may navigate directly to a
    // section URL without ever visiting the TOC (e.g. via a bookmark). Without the
    // grade structure the per-answer AJAX updates silently do nothing.
    if (!$DB->record_exists('siyavula_grade_nodes', ['instanceid' => $moduleinstance->id])) {
        siyavula_update_grades($moduleinstance, $USER->id, $subjectgradetoc);
    }

    $activitytype = 'practice';

    // Current version is Moodle 4.0 or higher use the event types. Otherwise use the older versions.
    if ($CFG->version >= 2022041912) {
        $PAGE->requires->js_call_amd('filter_siyavula/initmathjax', 'init', ['issupported' => $CFG->version <= 2025040100]);
    } else {
        $PAGE->requires->js_call_amd('filter_siyavula/initmathjax-backward', 'init');
    }

    $renderer = $PAGE->get_renderer('filter_siyavula');

    $activityrenderable = new practice_activity_renderable();
    $activityrenderable->activitytype = $activitytype;
    $activityrenderable->sectionid = $sectionid;
    $activityrenderable->uniqueid = uniqid('siyavula-activity-');

    $config = new \stdClass();
    $config->wwwroot = $CFG->wwwroot;
    $config->baseurl = $baseurl;
    $config->token = $token;
    $config->usertoken = $usertoken->token;
    $config->cmid = $coursemodule->id;

    echo $renderer->render_practice_activity($activityrenderable);
    echo $renderer->render_assets([$activityrenderable], $config);

}

echo $OUTPUT->footer();
