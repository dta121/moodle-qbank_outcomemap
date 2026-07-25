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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Bulk alignment-only outcome mapping for selected questions.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/editlib.php');

use local_outcomemap\api\outcome_search;
use qbank_outcomemap\form\bulk_map_form;
use qbank_outcomemap\local\bulk_mapping_service;

$cmid = optional_param('cmid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$questionids = optional_param('questionids', '', PARAM_SEQUENCE);

\core_question\local\bank\helper::require_plugin_enabled('qbank_outcomemap');

if ($cmid) {
    [$module, $cm] = get_module_from_cmid($cmid);
    require_login($cm->course, false, $cm);
    $thiscontext = context_module::instance($cmid);
} else if ($courseid) {
    require_login($courseid, false);
    $thiscontext = context_course::instance($courseid);
} else {
    throw new moodle_exception('missingcourseorcmid', 'question');
}
require_capability('local/outcomemap:viewdefinitions', $thiscontext);
require_capability('local/outcomemap:mapquestions', $thiscontext);

// The question bank posts one q<id> parameter per selected question.
if ($questionids === '') {
    $selected = [];
    foreach ((array) data_submitted() as $key => $value) {
        if (preg_match('/^q(\d+)$/', $key, $matches)) {
            $selected[] = (int) $matches[1];
        }
    }
    $questionids = implode(',', $selected);
}

$PAGE->set_context($thiscontext);
$PAGE->set_url(new moodle_url('/question/bank/outcomemap/bulk.php'));
$PAGE->set_title(get_string('bulkmaptitle', 'qbank_outcomemap'));
$PAGE->set_heading(get_string('bulkmaptitle', 'qbank_outcomemap'));

$backurl = $returnurl !== ''
    ? new moodle_url($returnurl)
    : new moodle_url('/question/edit.php', $cmid ? ['cmid' => $cmid] : ['courseid' => $courseid]);

$ids = array_values(array_filter(array_map('intval', explode(',', $questionids))));
if (!$ids) {
    redirect($backurl, get_string('bulknoquestions', 'qbank_outcomemap'), null,
        \core\output\notification::NOTIFY_WARNING);
}

$outcomeoptions = ['' => get_string('choosedots')];
foreach (outcome_search::search($thiscontext, '', null, 200) as $outcome) {
    $label = $outcome->frameworkcode . '.' . $outcome->code . ' v' . $outcome->version;
    $label .= ' — ' . ($outcome->shortstatement ?: shorten_text($outcome->statement, 80));
    $outcomeoptions[$outcome->versionuuid] = $label;
}

$form = new bulk_map_form(null, [
    'questionids' => implode(',', $ids),
    'cmid' => $cmid,
    'courseid' => $courseid,
    'returnurl' => $returnurl,
    'outcomes' => $outcomeoptions,
]);

if ($form->is_cancelled()) {
    redirect($backurl);
}

if (($data = $form->get_data()) && !empty($data->outcomeversionuuid)) {
    $result = bulk_mapping_service::add_alignment_drafts($ids, (string) $data->outcomeversionuuid);
    redirect($backurl, get_string('bulkmapresult', 'qbank_outcomemap', $result));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkmapcount', 'qbank_outcomemap', count($ids)), 3);
$form->display();
echo $OUTPUT->footer();
