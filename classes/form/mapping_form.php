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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Draft question-mapping editor form.
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
        $outcomes = $this->_customdata['outcomes'] ?? [];

        $mform->addElement('hidden', 'id', $this->_customdata['questionid'] ?? 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'returnurl', $this->_customdata['returnurl'] ?? '');
        $mform->setType('returnurl', PARAM_LOCALURL);

        $mform->addElement('header', 'addmappingheader', get_string('addmapping', 'qbank_outcomemap'));
        $mform->addElement('static', 'alignmentnote', '', get_string('alignmentnote', 'qbank_outcomemap'));

        $mform->addElement('select', 'outcomeversionuuid',
            get_string('outcome', 'qbank_outcomemap'), $outcomes);
        $mform->addRule('outcomeversionuuid', null, 'required', null, 'client');

        $roles = [];
        foreach (['teaches', 'practices', 'assesses', 'alignment_only'] as $role) {
            $roles[$role] = get_string('mappingrole_' . $role, 'local_outcomemap');
        }
        $mform->addElement('select', 'role', get_string('mappingrole', 'local_outcomemap'), $roles);
        $mform->setDefault('role', 'alignment_only');

        $mform->addElement('text', 'weight', get_string('assessedweight', 'qbank_outcomemap'), ['size' => 16]);
        $mform->setType('weight', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('weight', 'assessedweight', 'qbank_outcomemap');
        $mform->hideIf('weight', 'role', 'noteq', 'assesses');

        $mform->addElement('textarea', 'notes', get_string('notes', 'local_outcomemap'), ['rows' => 2]);
        $mform->setType('notes', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('addmapping', 'qbank_outcomemap'));
    }

    /**
     * Mirror the governed weight rules for friendlier feedback.
     *
     * The local_outcomemap services remain authoritative.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by field.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $weight = trim((string) ($data['weight'] ?? ''));
        if (($data['role'] ?? '') === 'assesses') {
            if ($weight === '') {
                $errors['weight'] = get_string('assessedweightrequired', 'local_outcomemap');
            }
        } else if ($weight !== '') {
            $errors['weight'] = get_string('weightnotallowedforrole', 'local_outcomemap',
                (object) ['field' => 'weight', 'detail' => $data['role'] ?? '']);
        }
        return $errors;
    }
}
