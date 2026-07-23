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

use core_question\local\bank\column_base;
use local_outcomemap\api\question_mappings;

/**
 * Question-bank column summarising governed outcome mappings per exact version.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome_column extends column_base {
    /** @var array Mapping DTO lists keyed by question-version ID. */
    protected $mappingsbyversion = [];

    /** @var bool[] Editor capability memo keyed by context ID. */
    protected $editablecontexts = [];

    /**
     * Return the column name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'outcomemap';
    }

    /**
     * Return the column title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('outcomescolumn', 'qbank_outcomemap');
    }

    /**
     * Bulk-load mappings for the visible page in one public service call.
     *
     * @param \stdClass[] $questions Question rows for the visible page.
     */
    public function load_additional_data(array $questions) {
        parent::load_additional_data($questions);
        $versionids = [];
        foreach ($questions as $question) {
            if (!empty($question->versionid)) {
                $versionids[] = (int) $question->versionid;
            }
        }
        $this->mappingsbyversion = $versionids
            ? question_mappings::get_for_question_versions($versionids)
            : [];
    }

    /**
     * Render the outcome summary for one question row.
     *
     * @param \stdClass $question Question row.
     * @param string $rowclasses Row CSS classes.
     */
    protected function display_content($question, $rowclasses): void {
        $mappings = $this->mappingsbyversion[(int) ($question->versionid ?? 0)] ?? [];
        $parts = [];
        foreach ($mappings as $mapping) {
            $label = $mapping->frameworkcode . '.' . $mapping->outcomecode . ' v' . $mapping->outcomeversion;
            $role = get_string('mappingrole_' . $mapping->role, 'local_outcomemap');
            $status = get_string('status_' . $mapping->status, 'local_outcomemap');
            $text = $label . ' · ' . $role;
            if ($mapping->weight !== null) {
                $text .= ' ' . self::format_weight($mapping->weight);
            }
            $text .= ' · ' . $status;
            $class = $mapping->status === 'approved' ? 'badge bg-success text-white' : 'badge bg-secondary text-dark';
            $parts[] = \html_writer::span(s($text), $class, [
                'title' => s($mapping->outcomeshortstatement ?? $mapping->outcomestatement),
            ]);
        }
        if (!$parts) {
            $parts[] = \html_writer::span(get_string('nomappings', 'qbank_outcomemap'), 'text-muted');
        }
        if ($this->can_edit($question)) {
            $url = new \moodle_url('/question/bank/outcomemap/edit.php', [
                'id' => (int) $question->id,
                'returnurl' => $this->qbank->returnurl,
            ]);
            $parts[] = \html_writer::link($url, get_string('managemappings', 'qbank_outcomemap'));
        }
        echo implode(' ', $parts);
    }

    /**
     * Decide whether to offer the editor link without extra queries.
     *
     * The base view rows include the category context and creator, so both the
     * plugin capability and Moodle's ownership-aware question capability are
     * resolved from row data.
     *
     * @param \stdClass $question Question row.
     * @return bool
     */
    protected function can_edit(\stdClass $question): bool {
        if (empty($question->contextid)) {
            return false;
        }
        $contextid = (int) $question->contextid;
        if (!array_key_exists($contextid, $this->editablecontexts)) {
            $context = \context::instance_by_id($contextid, IGNORE_MISSING);
            $this->editablecontexts[$contextid] = $context
                && has_capability('local/outcomemap:mapquestions', $context);
        }
        if (!$this->editablecontexts[$contextid]) {
            return false;
        }
        $proxy = (object) [
            'id' => (int) $question->id,
            'contextid' => $contextid,
            'createdby' => (int) ($question->createdby ?? 0),
        ];
        return question_has_capability_on($proxy, 'edit');
    }

    /**
     * Format a canonical scale-10 weight for compact display.
     *
     * @param string $weight Canonical decimal weight.
     * @return string
     */
    public static function format_weight(string $weight): string {
        if (strpos($weight, '.') === false) {
            return $weight;
        }
        $trimmed = rtrim(rtrim($weight, '0'), '.');
        return $trimmed === '' ? '0' : $trimmed;
    }
}
