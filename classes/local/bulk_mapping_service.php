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

namespace qbank_outcomemap\local;

use local_outcomemap\api\question_mappings;

/**
 * Bulk question-bank workflow orchestration.
 *
 * Governed mapping reads and writes remain behind the public local_outcomemap
 * API. This class only resolves selected core question rows in bulk and applies
 * Moodle plus local capabilities before invoking that API.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_mapping_service {
    /** Maximum IDs accepted by one local_outcomemap bulk read. */
    private const BATCH_SIZE = 1000;

    /**
     * Add one alignment-only draft to every eligible selected question version.
     *
     * @param int[] $questionids Core question IDs selected in the question bank.
     * @param string $outcomeversionuuid Stable approved outcome-version UUID.
     * @return \stdClass Object containing created and skipped counts.
     */
    public static function add_alignment_drafts(array $questionids, string $outcomeversionuuid): \stdClass {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');
        $questionids = array_values(array_unique(array_filter(
            array_map('intval', $questionids),
            static fn(int $id): bool => $id > 0
        )));
        $result = (object) ['created' => 0, 'skipped' => 0];

        foreach (array_chunk($questionids, self::BATCH_SIZE) as $batch) {
            [$insql, $params] = $DB->get_in_or_equal($batch, SQL_PARAMS_NAMED, 'questionid');
            $questions = $DB->get_records_sql(
                "SELECT q.id, qv.id AS versionid, q.createdby, qc.contextid
                   FROM {question} q
                   JOIN {question_versions} qv ON qv.questionid = q.id
                   JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                   JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  WHERE q.id $insql",
                $params
            );

            $eligible = [];
            $capablecontexts = [];
            foreach ($batch as $questionid) {
                if (!isset($questions[$questionid])) {
                    $result->skipped++;
                    continue;
                }
                $question = $questions[$questionid];
                $contextid = (int) $question->contextid;
                if (!array_key_exists($contextid, $capablecontexts)) {
                    $context = \context::instance_by_id($contextid, IGNORE_MISSING);
                    $capablecontexts[$contextid] = $context
                        && has_capability('local/outcomemap:viewdefinitions', $context)
                        && has_capability('local/outcomemap:mapquestions', $context);
                }
                if (!$capablecontexts[$contextid] || !question_has_capability_on($question, 'edit')) {
                    $result->skipped++;
                    continue;
                }
                $eligible[$questionid] = $question;
            }

            $versionids = array_map(
                static fn(\stdClass $question): int => (int) $question->versionid,
                $eligible
            );
            $existing = $versionids ? question_mappings::get_for_question_versions($versionids) : [];
            foreach ($eligible as $question) {
                $versionid = (int) $question->versionid;
                $duplicate = false;
                foreach ($existing[$versionid] ?? [] as $mapping) {
                    if (
                        $mapping->outcomeversionuuid === $outcomeversionuuid
                            && $mapping->role === 'alignment_only'
                    ) {
                        $duplicate = true;
                        break;
                    }
                }
                if ($duplicate) {
                    $result->skipped++;
                    continue;
                }
                try {
                    question_mappings::create_draft($versionid, $outcomeversionuuid, 'alignment_only');
                    $result->created++;
                } catch (\moodle_exception $e) {
                    $result->skipped++;
                }
            }
        }

        return $result;
    }
}
