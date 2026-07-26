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
use local_outcomemap\api\workflow;

/**
 * Question-bank column summarising governed outcome mappings per exact version.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome_column extends column_base {
    /** Maximum IDs accepted by one public local service call. */
    private const LOAD_BATCH_SIZE = 1000;

    /** @var array Mapping DTO lists keyed by question-version ID. */
    protected $mappingsbyversion = [];

    /** @var int[]|null Version IDs loaded by the most recent core callback. */
    protected $loadedversionids = null;

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
     * Bulk-load mappings for the visible page in bounded service calls.
     *
     * Moodle supports up to 4,000 questions per page, while the public local
     * API deliberately limits each cross-database-safe request to 1,000 IDs.
     *
     * @param \stdClass[] $questions Question rows for the visible page.
     */
    public function load_additional_data(array $questions) {
        parent::load_additional_data($questions);
        $versionids = [];
        foreach ($questions as $question) {
            if (!empty($question->versionid)) {
                $versionids[(int) $question->versionid] = (int) $question->versionid;
            }
        }
        $versionids = array_values(array_unique($versionids));
        if ($versionids === $this->loadedversionids) {
            return;
        }
        $this->loadedversionids = $versionids;
        $this->mappingsbyversion = [];
        foreach (array_chunk($versionids, self::LOAD_BATCH_SIZE) as $batch) {
            $this->mappingsbyversion = array_replace(
                $this->mappingsbyversion,
                $this->load_mapping_batch($batch)
            );
        }
    }

    /**
     * Load one public-service batch.
     *
     * Kept as a seam for verifying maximum-page chunking without creating
     * thousands of database fixtures.
     *
     * @param int[] $questionversionids Question-version IDs.
     * @return array Mapping DTO lists keyed by question-version ID.
     */
    protected function load_mapping_batch(array $questionversionids): array {
        return question_mappings::get_for_question_versions($questionversionids);
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
            $status = workflow::status_label($mapping->status);
            $text = $label . ' · ' . $role;
            if ($mapping->weight !== null) {
                $text .= ' ' . self::format_weight($mapping->weight);
            }
            $text .= ' · ' . $status;
            $statusclasses = [
                workflow::DRAFT => 'badge bg-secondary text-white',
                workflow::NEEDS_REVIEW => 'badge bg-warning text-dark',
                workflow::APPROVED => 'badge bg-success text-white',
                workflow::RETIRED => 'badge bg-dark text-white',
            ];
            $roleclass = $mapping->role === 'assesses'
                ? ' qbank-outcomemap-assessed'
                : ' qbank-outcomemap-alignment';
            $class = ($statusclasses[$mapping->status] ?? 'badge bg-secondary text-white') . $roleclass;
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
                && has_capability('local/outcomemap:viewdefinitions', $context)
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
