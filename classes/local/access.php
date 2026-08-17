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

use local_outcomemap\api\outcome_search;

/**
 * Server-side access guards shared by the question mapping endpoints.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** Maximum question selection supported by the public bulk API. */
    private const MAX_BULK_QUESTIONS = 1000;

    /**
     * Require both local capabilities needed to read and change mappings.
     *
     * @param \context $context Authoritative question-bank context.
     */
    public static function require_mapping_capabilities(\context $context): void {
        require_capability('local/outcomemap:viewdefinitions', $context);
        require_capability('local/outcomemap:mapquestions', $context);
    }

    /**
     * Require all access needed to edit one core question.
     *
     * @param \context $context Authoritative question context.
     * @param int $questionid Core question ID.
     */
    public static function require_question_edit_access(\context $context, int $questionid): void {
        self::require_mapping_capabilities($context);
        if (!question_has_capability_on($questionid, 'edit')) {
            throw new \required_capability_exception(
                $context,
                'moodle/question:editall',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Resolve a bulk selection in one query and authorize every exact question context.
     *
     * The public local service repeats these checks during preview and commit. This
     * endpoint guard additionally requires definition-read access, including when
     * a caller tampers with the posted selection to name another question context.
     *
     * @param int[] $questionids Untrusted selected core question IDs.
     */
    public static function require_bulk_question_access(array $questionids): void {
        global $DB;

        $questionids = array_values(array_unique(array_filter(array_map('intval', $questionids))));
        sort($questionids);
        if (!$questionids || count($questionids) > self::MAX_BULK_QUESTIONS) {
            throw new \invalid_parameter_exception(get_string('invalidbulkselection', 'qbank_outcomemap'));
        }

        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qbaccess');
        $questions = $DB->get_records_sql(
            "SELECT q.id, q.createdby, qc.contextid
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE q.id $insql",
            $params
        );
        if (count($questions) !== count($questionids)) {
            throw new \invalid_parameter_exception(get_string('invalidbulkselection', 'qbank_outcomemap'));
        }

        \context_helper::preload_contexts_by_id(array_unique(array_column($questions, 'contextid')));
        $contexts = [];
        foreach ($questions as $question) {
            $contextid = (int) $question->contextid;
            if (!isset($contexts[$contextid])) {
                $contexts[$contextid] = \context::instance_by_id($contextid, MUST_EXIST);
                self::require_mapping_capabilities($contexts[$contextid]);
            }
            $proxy = (object) [
                'id' => (int) $question->id,
                'contextid' => $contextid,
                'createdby' => (int) $question->createdby,
            ];
            if (!question_has_capability_on($proxy, 'edit')) {
                throw new \required_capability_exception(
                    $contexts[$contextid],
                    'moodle/question:editall',
                    'nopermissions',
                    ''
                );
            }
        }
    }

    /**
     * Revalidate a posted outcome UUID in the authoritative question context.
     *
     * @param \context $context Authoritative question context.
     * @param string $versionuuid Posted exact outcome-version UUID.
     * @param int $effectiveat Mapping effective timestamp.
     */
    public static function require_visible_outcome(
        \context $context,
        string $versionuuid,
        int $effectiveat
    ): void {
        outcome_search::require_visible_version($context, $versionuuid, $effectiveat);
    }

    /**
     * Require state-changing actions to arrive as form POSTs.
     */
    public static function require_post_action(): void {
        if (!data_submitted()) {
            throw new \moodle_exception('postrequired', 'qbank_outcomemap');
        }
    }
}
