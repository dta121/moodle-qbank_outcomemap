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

namespace qbank_outcomemap\local\bank;

use core_question\local\bank\question_action_base;

/**
 * Question action menu entry opening the outcome mapping editor.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome_map_action extends question_action_base {
    /** @var string Menu label. */
    protected $label;

    /** @var bool[] Capability memo keyed by context ID. */
    protected $capablecontexts = [];

    /**
     * Initialise the action strings once per view.
     */
    public function init(): void {
        parent::init();
        $this->label = get_string('managemappings', 'qbank_outcomemap');
    }

    /**
     * Return the menu position after core edit actions.
     *
     * @return int
     */
    public function get_menu_position(): int {
        return 380;
    }

    /**
     * Return the editor URL for authorized users only.
     *
     * @param \stdClass $question Question row.
     * @return array URL, icon, and label triple.
     */
    protected function get_url_icon_and_label(\stdClass $question): array {
        if (empty($question->contextid)) {
            return [null, null, null];
        }
        $contextid = (int) $question->contextid;
        if (!array_key_exists($contextid, $this->capablecontexts)) {
            $context = \context::instance_by_id($contextid, IGNORE_MISSING);
            $this->capablecontexts[$contextid] = $context
                && has_capability('local/outcomemap:viewdefinitions', $context)
                && has_capability('local/outcomemap:mapquestions', $context);
        }
        if (!$this->capablecontexts[$contextid]) {
            return [null, null, null];
        }
        $proxy = (object) [
            'id' => (int) $question->id,
            'contextid' => $contextid,
            'createdby' => (int) ($question->createdby ?? 0),
        ];
        if (!question_has_capability_on($proxy, 'edit')) {
            return [null, null, null];
        }
        $url = new \moodle_url('/question/bank/outcomemap/edit.php', [
            'id' => (int) $question->id,
            'returnurl' => $this->qbank->returnurl,
        ]);
        return [$url, 'i/competencies', $this->label];
    }
}
