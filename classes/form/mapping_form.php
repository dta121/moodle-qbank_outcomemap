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

namespace qbank_outcomemap\form;

use local_outcomemap\api\workflow;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Draft question-mapping create/edit form with explicit finalization.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapping_form extends \moodleform {
    /**
     * Define the form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $outcomes = array_map(
            static fn($label): string => s((string) $label),
            $this->_customdata['outcomes'] ?? []
        );
        $mappingid = (int) ($this->_customdata['mappingid'] ?? 0);
        $finalizationmode = !workflow::requires_independent_approval();

        $mform->addElement('hidden', 'id', $this->_customdata['questionid'] ?? 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'mappingid', $mappingid);
        $mform->setType('mappingid', PARAM_INT);
        $mform->addElement('hidden', 'returnurl', $this->_customdata['returnurl'] ?? '');
        $mform->setType('returnurl', PARAM_LOCALURL);

        $mform->addElement('header', 'mappingheader', get_string(
            $mappingid ? 'editmapping' : 'addmapping',
            'qbank_outcomemap'
        ));
        $mform->addElement('static', 'alignmentnote', '', get_string(
            $finalizationmode ? 'alignmentnote_finalization' : 'alignmentnote',
            'qbank_outcomemap'
        ));

        $mform->addElement(
            'select',
            'outcomeversionuuid',
            get_string($finalizationmode ? 'outcome_finalization' : 'outcome', 'qbank_outcomemap'),
            $outcomes
        );
        $mform->setType('outcomeversionuuid', PARAM_ALPHANUMEXT);
        $mform->addRule('outcomeversionuuid', null, 'required', null, 'client');

        $roles = [];
        foreach (['teaches', 'practices', 'assesses', 'remediates', 'alignment_only'] as $role) {
            $roles[$role] = get_string('mappingrole_' . $role, 'local_outcomemap');
        }
        $mform->addElement('select', 'role', get_string('mappingrole', 'local_outcomemap'), $roles);
        $mform->setType('role', PARAM_ALPHAEXT);
        $mform->setDefault('role', 'alignment_only');

        $mform->addElement('text', 'weight', get_string('assessedweight', 'qbank_outcomemap'), ['size' => 16]);
        $mform->setType('weight', PARAM_RAW_TRIMMED);
        $mform->addHelpButton(
            'weight',
            $finalizationmode ? 'assessedweight_finalization' : 'assessedweight',
            'qbank_outcomemap'
        );
        $mform->hideIf('weight', 'role', 'noteq', 'assesses');

        $mform->addElement('textarea', 'notes', get_string('notes', 'local_outcomemap'), ['rows' => 3]);
        $mform->setType('notes', PARAM_TEXT);
        $reviewmessagestring = $finalizationmode ? 'reviewmessage_finalization' : 'reviewmessage';
        $mform->addElement(
            'textarea',
            'reviewmessage',
            get_string($reviewmessagestring, 'qbank_outcomemap'),
            ['rows' => 2]
        );
        $mform->setType('reviewmessage', PARAM_TEXT);
        $mform->addHelpButton('reviewmessage', $reviewmessagestring, 'qbank_outcomemap');

        $buttons = [
            $mform->createElement('submit', 'savedraft', get_string('savedraft', 'qbank_outcomemap')),
            $mform->createElement('submit', 'saveandsubmit', workflow::submit_action_label()),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Mirror governed role/weight rules for immediate field feedback.
     *
     * The local_outcomemap service remains authoritative.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Field errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $expectedmappingid = (int) ($this->_customdata['mappingid'] ?? 0);
        if ((int) ($data['mappingid'] ?? 0) !== $expectedmappingid) {
            $errors['outcomeversionuuid'] = get_string('invalidmappingedit', 'qbank_outcomemap');
        }
        if (empty($data['outcomeversionuuid'])) {
            $errors['outcomeversionuuid'] = get_string('required');
        }
        $roles = ['teaches', 'practices', 'assesses', 'remediates', 'alignment_only'];
        $role = clean_param((string) ($data['role'] ?? ''), PARAM_ALPHAEXT);
        if (!in_array($role, $roles, true)) {
            $errors['role'] = get_string('invalidmappingrole', 'local_outcomemap');
            return $errors;
        }
        $weight = trim((string) ($data['weight'] ?? ''));
        if ($role === 'assesses') {
            if ($weight === '') {
                $errors['weight'] = get_string('assessedweightrequired', 'local_outcomemap');
            }
        } else if ($weight !== '') {
            $errors['weight'] = get_string(
                'weightnotallowedforrole',
                'local_outcomemap',
                (object) ['field' => 'weight', 'detail' => $role]
            );
        }
        return $errors;
    }
}
