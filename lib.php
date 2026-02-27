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

// Load filter_siyavula helper functions
require_once($CFG->dirroot . '/filter/siyavula/lib.php');

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

    siyavula_grade_item_delete($exists);
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
 * Creates or retrieves the grade_category/grade_item hierarchy for one activity instance.
 *
 * Hierarchy:
 *   grade_category  "[name] – Chapters"   aggregation=MEAN  (activity level)
 *     grade_category  "Chapter 1: ..."    aggregation=MEAN  (per chapter)
 *       grade_item    "Section 1.1: ..."  type=VALUE        (per section)
 *       grade_item    "Section 1.2: ..."
 *     grade_category  "Chapter 2: ..."
 *       ...
 *
 * The activity-level category total item has aggregationcoef=0 so it does not
 * contribute to the course total (only the mod grade_item at itemnumber=0 does).
 *
 * Idempotent: safe to call on every grade update request.
 *
 * @param stdClass $moduleinstance  Row from the siyavula table.
 * @param array    $chapters        From $mastery->chapters — see siyavula_get_toc_user_mastery().
 * @return array   Nodemap keyed by 'activity_cat', 'chapter_cat:{id}', 'section_item:{id}'
 *                 with Moodle grade_category.id or grade_item.id as values.
 */
function siyavula_ensure_grade_structure(stdClass $moduleinstance, array $chapters): array {
    global $CFG, $DB;
    require_once($CFG->libdir . '/grade/grade_category.php');
    require_once($CFG->libdir . '/grade/grade_item.php');

    // Load all saved node mappings for this instance.
    $rows    = $DB->get_records('siyavula_grade_nodes', ['instanceid' => $moduleinstance->id]);
    $nodemap = [];
    foreach ($rows as $row) {
        $key = $row->nodetype . (!empty($row->siyavulaid) ? ':' . $row->siyavulaid : '');
        $nodemap[$key] = (int)$row->moodleid;
    }

    $now = time();

    // ── 1. Activity-level grade category ──────────────────────────────────────
    if (!isset($nodemap['activity_cat'])) {
        $actcat              = new grade_category();
        $actcat->courseid    = $moduleinstance->course;
        $actcat->fullname    = $moduleinstance->name . ' – Chapters';
        $actcat->aggregation = GRADE_AGGREGATE_MEAN;
        $actcat->insert('mod/siyavula');

        $DB->insert_record('siyavula_grade_nodes', (object)[
            'instanceid'   => $moduleinstance->id,
            'nodetype'     => 'activity_cat',
            'siyavulaid'   => null,
            'moodleid'     => $actcat->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $nodemap['activity_cat'] = $actcat->id;
    }
    $actcatid = $nodemap['activity_cat'];

    // Always ensure the activity category total item is excluded from the
    // course total. weightoverride=1 is required for Moodle to apply
    // aggregationcoef2=0 under Natural aggregation; without it Moodle
    // auto-calculates the weight from grademax and this category total
    // double-counts against the mod grade item (itemnumber=0).
    $catitem = grade_item::fetch([
        'itemtype'     => 'category',
        'iteminstance' => $actcatid,
        'courseid'     => $moduleinstance->course,
    ]);
    if ($catitem && (!$catitem->weightoverride || $catitem->aggregationcoef2 != 0)) {
        $catitem->aggregationcoef  = 0;
        $catitem->aggregationcoef2 = 0;
        $catitem->weightoverride   = 1;
        $catitem->update();
    }

    // ── 2. Chapter categories and section grade items ─────────────────────────
    foreach ($chapters as $chapter) {
        $chapkey = 'chapter_cat:' . $chapter['id'];

        if (!isset($nodemap[$chapkey])) {
            $chapcat              = new grade_category();
            $chapcat->courseid    = $moduleinstance->course;
            $chapcat->fullname    = $chapter['title'];
            $chapcat->aggregation = GRADE_AGGREGATE_MEAN;
            $chapcat->parent      = $actcatid;
            $chapcat->insert('mod/siyavula');

            $DB->insert_record('siyavula_grade_nodes', (object)[
                'instanceid'   => $moduleinstance->id,
                'nodetype'     => 'chapter_cat',
                'siyavulaid'   => (string)$chapter['id'],
                'moodleid'     => $chapcat->id,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
            $nodemap[$chapkey] = $chapcat->id;
        }
        $chapcatid = $nodemap[$chapkey];

        foreach ($chapter['sections'] as $section) {
            $seckey = 'section_item:' . $section['id'];

            if (!isset($nodemap[$seckey])) {
                $gi             = new grade_item();
                $gi->courseid   = $moduleinstance->course;
                $gi->categoryid = $chapcatid;
                $gi->itemtype   = 'manual';
                $gi->itemname   = $section['title'];
                // idnumber allows external lookup without querying the mapping table.
                $gi->idnumber   = 'siyavula_' . $moduleinstance->id . '_sec_' . $section['id'];
                $gi->gradetype  = GRADE_TYPE_VALUE;
                $gi->grademax   = 100;
                $gi->grademin   = 0;
                $gi->insert('mod/siyavula');

                $DB->insert_record('siyavula_grade_nodes', (object)[
                    'instanceid'   => $moduleinstance->id,
                    'nodetype'     => 'section_item',
                    'siyavulaid'   => (string)$section['id'],
                    'moodleid'     => $gi->id,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
                $nodemap[$seckey] = $gi->id;
            }
        }
    }

    return $nodemap;
}

/**
 * Creates or updates grade item for the given mod_siyavula instance.
 *
 * Writes the overall mastery to the module's grade item (itemnumber=0) for
 * course-total and completion-tracking purposes.
 *
 * Also creates/updates the grade_category hierarchy (activity → chapters →
 * sections) and writes per-section mastery to the leaf grade_items.  Moodle's
 * category aggregation then computes chapter and activity-level totals
 * automatically.
 *
 * Needed by {@see grade_update_mod_grades()}.
 *
 * @param stdClass $moduleinstance Instance object with extra cmidnumber and modname property.
 * @param stdClass|string $mastery  Mastery object from siyavula_get_toc_user_mastery(),
 *                                  or the string 'reset' to reset grades.
 * @return int  grade_update() return code.
 */
function siyavula_grade_item_update($moduleinstance, $mastery) {
    global $CFG, $DB;
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir . '/gradelib.php');
    }
    require_once($CFG->libdir . '/grade/grade_item.php');

    $reset = ($mastery === 'reset');

    // ── Persist overall mastery in siyavula_grades (legacy cache, unchanged) ──
    if (!$reset) {
        $siyavulagrades = 'siyavula_grades';
        $record = $DB->get_record($siyavulagrades, [
            'subject' => $mastery->subject,
            'grade'   => $mastery->grade,
            'userid'  => $mastery->userid,
        ]);
        if ($record) {
            $record->mastery      = $mastery->rawgrade;
            $record->timemodified = time();
            $DB->update_record($siyavulagrades, $record);
        } else {
            $record               = new stdClass();
            $record->subject      = $mastery->subject;
            $record->grade        = $mastery->grade;
            $record->userid       = $mastery->userid;
            $record->mastery      = $mastery->rawgrade;
            $record->timecreated  = time();
            $record->timemodified = time();
            $DB->insert_record($siyavulagrades, $record);
        }
    }

    // ── itemnumber=0: module-linked grade item for course total + completion ──
    $params = [
        'itemname' => $moduleinstance->name,
        'idnumber' => $moduleinstance->id,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => 100,
        'grademin'  => 0,
    ];
    if ($reset) {
        $params['reset'] = true;
        return grade_update('mod/siyavula', $moduleinstance->course, 'mod', 'siyavula',
                            $moduleinstance->id, 0, null, $params);
    }

    // Ensure the itemnumber=0 grade item exists before writing section grades,
    // so Moodle's grade infrastructure is in place for the category hierarchy.
    grade_update('mod/siyavula', $moduleinstance->course, 'mod', 'siyavula',
                 $moduleinstance->id, 0, null, $params);

    // ── Grade category hierarchy: write section mastery to leaf grade_items ──
    if (!empty($mastery->chapters)) {
        $nodemap = siyavula_ensure_grade_structure($moduleinstance, $mastery->chapters);

        foreach ($mastery->chapters as $chapter) {
            foreach ($chapter['sections'] as $section) {
                $seckey = 'section_item:' . $section['id'];
                $itemid = $nodemap[$seckey] ?? null;
                if ($itemid === null) {
                    continue;
                }

                // update_final_grade() is the correct Moodle API for writing to
                // manual grade items. It sets both rawgrade and finalgrade and
                // marks the item so Moodle's aggregation pipeline picks it up.
                $gi = grade_item::fetch(['id' => $itemid]);
                if ($gi) {
                    $gi->update_final_grade($mastery->userid, $section['mastery'], 'mod/siyavula');
                }
            }
        }

        // Recompute chapter and activity category totals immediately from the
        // freshly written section values.
        grade_regrade_final_grades($moduleinstance->course);
    }

    // ── Write the Moodle-aggregated activity category finalgrade to itemnumber=0
    // so the course total and completion tracking use the same value as the
    // User Report. This matches the approach in update_section_grade(). ──
    $overallrawgrade = $mastery->rawgrade;
    $actnode = $DB->get_record('siyavula_grade_nodes', [
        'instanceid' => $moduleinstance->id,
        'nodetype'   => 'activity_cat',
    ]);
    if ($actnode) {
        $catitem = grade_item::fetch([
            'itemtype'     => 'category',
            'iteminstance' => $actnode->moodleid,
            'courseid'     => $moduleinstance->course,
        ]);
        if ($catitem) {
            $catfinalgrade = $DB->get_field('grade_grades', 'finalgrade',
                ['itemid' => $catitem->id, 'userid' => $mastery->userid]);
            if (!is_null($catfinalgrade)) {
                $overallrawgrade = $catfinalgrade;
            }
        }
    }

    $overallgrades = [
        $mastery->userid => (object)[
            'userid'   => $mastery->userid,
            'rawgrade' => $overallrawgrade,
        ],
    ];
    $result = grade_update('mod/siyavula', $moduleinstance->course, 'mod', 'siyavula',
                           $moduleinstance->id, 0, $overallgrades, $params);
    siyavula_set_completion($moduleinstance, $mastery->userid, $overallrawgrade);

    return $result;
}

/**
 * Delete grade item for given mod_siyavula instance.
 *
 * Removes the entire grade_category hierarchy (sections, chapter categories,
 * activity category) as well as the module's itemnumber=0 grade item, and
 * cleans up the siyavula_grade_nodes mapping table.
 *
 * @param stdClass $moduleinstance Instance object.
 * @return int grade_update() return code for the itemnumber=0 deletion.
 */
function siyavula_grade_item_delete($moduleinstance) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->libdir . '/grade/grade_category.php');
    require_once($CFG->libdir . '/grade/grade_item.php');

    // Delete section grade_items first (leaves of the hierarchy).
    $secnodes = $DB->get_records('siyavula_grade_nodes', [
        'instanceid' => $moduleinstance->id,
        'nodetype'   => 'section_item',
    ]);
    foreach ($secnodes as $node) {
        $gi = grade_item::fetch(['id' => $node->moodleid]);
        if ($gi) {
            $gi->delete('mod/siyavula');
        }
    }

    // Delete chapter categories (their auto-created total grade_item is removed
    // automatically by Moodle when the category is deleted).
    $chapnodes = $DB->get_records('siyavula_grade_nodes', [
        'instanceid' => $moduleinstance->id,
        'nodetype'   => 'chapter_cat',
    ]);
    foreach ($chapnodes as $node) {
        $cat = grade_category::fetch(['id' => $node->moodleid]);
        if ($cat) {
            $cat->delete('mod/siyavula');
        }
    }

    // Delete the activity-level category.
    $actnodes = $DB->get_records('siyavula_grade_nodes', [
        'instanceid' => $moduleinstance->id,
        'nodetype'   => 'activity_cat',
    ]);
    foreach ($actnodes as $node) {
        $cat = grade_category::fetch(['id' => $node->moodleid]);
        if ($cat) {
            $cat->delete('mod/siyavula');
        }
    }

    // Clean up the node mapping table.
    $DB->delete_records('siyavula_grade_nodes', ['instanceid' => $moduleinstance->id]);

    // Delete the module's own itemnumber=0 grade item.
    return grade_update('mod/siyavula', $moduleinstance->course, 'mod', 'siyavula',
                        $moduleinstance->id, 0, null, ['deleted' => 1]);
}

/**
 * Update mod_siyavula grades in the gradebook.
 *
 * Needed by {@see grade_update_mod_grades()}.
 *
 * @param stdClass $moduleinstance Instance object with extra cmidnumber and modname property.
 * @param int $userid Update grade of specific user only, 0 means all participants.
 * @param stdClass $subjectgradetoc TOC data from the Siyavula API.
 */
function siyavula_update_grades($siyavula, $userid, $subjectgradetoc) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $mastery = siyavula_get_toc_user_mastery($siyavula, $subjectgradetoc, $userid);
    // Completion is set inside siyavula_grade_item_update() using the
    // Moodle-aggregated category finalgrade, not the simple API mean.
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
 * Returns overall mastery (rawgrade) as well as a full per-chapter and
 * per-section breakdown (chapters array) for use by siyavula_grade_item_update().
 *
 * @param stdClass $moduleinstance  Activity instance row.
 * @param stdClass $subjectgradetoc TOC data from the Siyavula API.
 * @param int      $userid          Moodle user ID.
 * @return stdClass  Object with userid, grade, subject, rawgrade, and chapters.
 */
function siyavula_get_toc_user_mastery($moduleinstance, $subjectgradetoc, $userid) {
    global $CFG, $DB;

    $info    = explode(':', $moduleinstance->subject_grade_selected);
    $subject = $info[0];
    $grade   = $info[1];

    $chapters    = is_array($subjectgradetoc->chapters ?? null) ? $subjectgradetoc->chapters : [];
    $summastery  = 0;
    $chapterdata = [];

    foreach ($chapters as $chapter) {
        $summastery += $chapter->mastery;

        $sections = [];
        foreach ($chapter->sections ?? [] as $section) {
            $sections[] = [
                'id'      => (string)$section->id,
                'title'   => $section->title,
                'mastery' => (float)$section->mastery,
            ];
        }
        $chapterdata[] = [
            'id'       => (string)$chapter->id,
            'title'    => $chapter->title,
            'mastery'  => (float)$chapter->mastery,
            'sections' => $sections,
        ];
    }

    $countchapters = count($chapters);
    $totalgrade    = $countchapters > 0 ? $summastery / $countchapters : 0;

    $mastery           = new stdClass();
    $mastery->userid   = $userid;
    $mastery->grade    = $grade;
    $mastery->subject  = $subject;
    $mastery->rawgrade = $totalgrade;
    $mastery->chapters = $chapterdata;
    return $mastery;
}

/**
 * Get the user Toc
 */
function get_subject_grade_toc($subject, $grade, $token, $userid = 0) {
    global $USER;

    $siyavulaconfig = get_config('filter_siyavula');

    $user = $userid == 0 ? $USER : \core_user::get_user($userid);
    $externaluserid = siyavula_get_external_user_id($siyavulaconfig, $user);

    // Convert stdClass from get_config to object expected by helper
    $configobj = (object)['url_base' => $siyavulaconfig->url_base];

    $response = siyavula_api_request(
        $configobj,
        "api/siyavula/v1/toc/user/$externaluserid/subject/$subject/grade/$grade",
        'GET',
        array(
            'token' => $token,
            'component' => 'mod_siyavula'
        )
    );

    if (isset($response->errors)) {
        return $response->errors;
    } else {
        return $response;
    }
}
