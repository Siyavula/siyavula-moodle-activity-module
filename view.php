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
// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$s = optional_param('s', 0, PARAM_INT);

// Section id selected
$section_id = optional_param('sid', null, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('siyavula', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('siyavula', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('siyavula', array('id' => $s), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('siyavula', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);

$event = \mod_siyavula\event\course_module_viewed::create(array(
    'objectid' => $moduleinstance->id,
    'context' => $modulecontext
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('siyavula', $moduleinstance);
$event->trigger();

$PAGE->set_url('/mod/siyavula/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

$PAGE->requires->css('/mod/siyavula/styles.css');

echo $OUTPUT->header();

$info       = explode(':', $moduleinstance->subject_grade_selected);
$subject    = $info[0];
$grade      = $info[1];
$client_ip          = $_SERVER['REMOTE_ADDR'];
$siyavula_config    = get_config('filter_siyavula');
$token              = siyavula_get_user_token($siyavula_config, $client_ip);
$subject_grade_toc  = get_subject_grade_toc($subject, $grade, $token);
// If selected one grade
if($moduleinstance->subject_grade_selected && $section_id == null) {
  
  echo html_writer::start_tag('div', ['class' => 'tabs-toc']);
  foreach($subject_grade_toc->chapters as $k => $chapter) {
      echo html_writer::start_tag('div', ['class' => 'tab-toc', 'data-cid' => $chapter->id]);
      echo    html_writer::empty_tag('input', ['class' => 'toc', 'type' => 'radio', 'id' => $k, 'name' => "tocs"]);
      echo    html_writer::start_tag('label', ['class' => 'tab-label-toc', 'for' => $k]);
      echo    '<span class="chapter-title">'. $chapter->title.'</span>';
      echo '<div class="sv-toc__chapter-mastery">
              <div class="sv-toc__section-mastery">
                <progress class="progress" id="chapter-mastery" value="'.$chapter->mastery.'" max="100" data-text="'.$chapter->mastery.'%"></progress>
              </div>
            </div>';
      echo    html_writer::end_tag('label');
      echo    html_writer::start_tag('div', ['class' => 'tab-content-toc']);
      foreach($chapter->sections as $section) {
          echo html_writer::start_tag('div', ['class' => 'item-toc', 'data-sid' => $section->id]);
          $url = new moodle_url('/mod/siyavula/view.php', ['id' => $cm->id, 'sid' => $section->id]);
          echo  html_writer::link($url, $section->title);  
          echo '<div class="sv-toc__section-mastery">
                  <progress class="progress" id="section-mastery" value="'.$section->mastery.'" max="100" data-text="'.$section->mastery.'%"></progress><br>
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
// If selected one section, render it
if($section_id != null) {
  siyavula_update_grades($moduleinstance, $USER->id, $subject_grade_toc);
  $user_token = siyavula_get_external_user_token($siyavula_config, $client_ip, $token);
  $url = new moodle_url('/mod/siyavula/view.php', ['id' => $cm->id]);
  $templatecontext[] = [
    'section_id'  => $section_id,
    'user_token'  => $user_token->token,
    'token'       => $token,
    'baseUrl'     => $siyavula_config->url_base,
    'randomSeed'  => random_int(1000, 9999),
    'back_url'    => $url,
  ];
  
  echo '<script src="https://www.siyavula.com/static/themes/emas/node_modules/mathjax/MathJax.js?config=TeX-MML-AM_HTMLorMML-full"></script>'; // This line avoid "[Math Error]" appear in the rendered question (i think)
  // Renderizar el html
  echo $OUTPUT->render_from_template('mod_siyavula/practice_section', ["renderall" => $templatecontext]);
}

echo $OUTPUT->footer();
