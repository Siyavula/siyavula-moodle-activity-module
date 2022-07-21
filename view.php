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

$PAGE->requires->css('/mod/siyavula/styles.css');

echo $OUTPUT->header();

$info = explode(':', $moduleinstance->subject_grade_selected);
$subject = $info[0];
$grade = $info[1];
$clientip = $_SERVER['REMOTE_ADDR'];
$siyavulaconfig = get_config('filter_siyavula');
$baseurl = $siyavulaconfig->url_base;
$token = siyavula_get_user_token($siyavulaconfig, $clientip);
$subjectgradetoc  = get_subject_grade_toc($subject, $grade, $token);

// If selected one grade.
if ($moduleinstance->subject_grade_selected && $sectionid == null) {
    echo html_writer::start_tag('div', ['class' => 'tabs-toc']);
    foreach ($subjectgradetoc->chapters as $k => $chapter) {
        echo html_writer::start_tag('div', ['class' => 'tab-toc', 'data-cid' => $chapter->id]);
        echo    html_writer::empty_tag('input', ['class' => 'toc', 'type' => 'radio', 'id' => $k, 'name' => "tocs"]);
        echo    html_writer::start_tag('label', ['class' => 'tab-label-toc', 'for' => $k]);
        echo    '<span class="chapter-title">'. $chapter->title.'</span>';
        echo '<div class="sv-toc__chapter-mastery">
              <div class="sv-toc__section-mastery">
                <svg style="display:none;">
                  <defs>
                    <symbol id="mastery-stars-chapter" class="mastery">
                      <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z M0 0 h24 v24 h-24 v-24" fill="#008bb2" fill-rule="evenodd"/>
                      <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z M0 0 h24 v24 h-24 v-24" fill="#008bb2" fill-rule="evenodd" transform="translate(24)"/>
                      <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z M0 0 h24 v24 h-24 v-24" fill="#008bb2" fill-rule="evenodd" transform="translate(48)"/>
                      <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z M0 0 h24 v24 h-24 v-24" fill="#008bb2" fill-rule="evenodd" transform="translate(72)"/>
                      <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z M0 0 h24 v24 h-24 v-24" fill="#008bb2" fill-rule="evenodd"  transform="translate(96)"/>
                    </symbol>
                  </defs>
                </svg>
                <div class="mastery">
                  <progress class="mastery-bg" id="chapter-mastery" value="'.$chapter->mastery.'" max="100" data-text="'.$chapter->mastery.'%"></progress>
                  <svg><use xlink:href="#mastery-stars-chapter"/></svg>
                </div>
              </div>
            </div>';
        echo    html_writer::end_tag('label');
        echo    html_writer::start_tag('div', ['class' => 'tab-content-toc']);
        foreach ($chapter->sections as $section) {
            echo html_writer::start_tag('div', ['class' => 'item-toc', 'data-sid' => $section->id]);
            $url = new moodle_url('/mod/siyavula/view.php', ['id' => $coursemodule->id, 'sid' => $section->id]);
            echo  html_writer::link($url, $section->title);
            echo '<div class="sv-toc__section-mastery">
                    <svg style="display:none;">
                      <defs>
                        <symbol id="mastery-stars-section" class="mastery">
                          <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z M0 0 h24 v24 h-24 v-24" fill="white" fill-rule="evenodd"/>
                          <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z M0 0 h24 v24 h-24 v-24" fill="white" fill-rule="evenodd" transform="translate(24)"/>
                          <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z M0 0 h24 v24 h-24 v-24" fill="white" fill-rule="evenodd" transform="translate(48)"/>
                          <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z M0 0 h24 v24 h-24 v-24" fill="white" fill-rule="evenodd" transform="translate(72)"/>
                          <path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z M0 0 h24 v24 h-24 v-24" fill="white" fill-rule="evenodd"  transform="translate(96)"/>
                        </symbol>
                      </defs>
                    </svg>
                    <div class="mastery">
                      <progress class="mastery" id="chapter-mastery" value="'.$section->mastery.'" max="100" data-text="'.$section->mastery.'%"></progress>
                      <svg><use xlink:href="#mastery-stars-section"/></svg>
                    </div>
                  </div>';
            echo html_writer::end_tag('div');
        }
        echo    html_writer::end_tag('div');

        echo html_writer::end_tag('div');
    }
    echo html_writer::start_tag('div', ['class' => 'tab-toc']);
    echo    html_writer::empty_tag('input', ['type' => 'radio', 'id' => "rd00", 'name' => "tocs"]);
    echo    html_writer::start_tag('label', ['class' => 'tab-close-toc', 'for' => "rd00"]);
    echo        "Close others &times;";
    echo    html_writer::end_tag('label');
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');
}

// If selected one section, render it.
if ($sectionid != null && $activityid === null && $responseid === null) {
    $token = siyavula_get_user_token($siyavulaconfig, $clientip);
    $usertoken = siyavula_get_external_user_token($siyavulaconfig, $clientip, $token);
    $activitytype = 'practice';
    siyavula_update_grades($moduleinstance, $USER->id, $subjectgradetoc);

    $PAGE->requires->js_call_amd('filter_siyavula/initmathjax', 'init');

    $renderer = $PAGE->get_renderer('filter_siyavula');
    $activityrenderable = new practice_activity_renderable();
    $activityrenderable->baseurl = $baseurl;
    $activityrenderable->token = $token;
    $activityrenderable->usertoken = $usertoken->token;
    $activityrenderable->activitytype = $activitytype;
    $activityrenderable->sectionid = $sectionid;

    echo $renderer->render_practice_activity($activityrenderable);
}

echo $OUTPUT->footer();
