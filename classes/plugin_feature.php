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

namespace qbank_outcomemap;

use core_question\local\bank\plugin_features_base;
use core_question\local\bank\view;
use qbank_outcomemap\local\bank\bulk_map_action;
use qbank_outcomemap\local\bank\copied_condition;
use qbank_outcomemap\local\bank\invalid_weight_condition;
use qbank_outcomemap\local\bank\mapped_condition;
use qbank_outcomemap\local\bank\outcome_column;
use qbank_outcomemap\local\bank\outcome_condition;
use qbank_outcomemap\local\bank\outcome_map_action;
use qbank_outcomemap\local\bank\role_condition;
use qbank_outcomemap\local\bank\status_condition;

/**
 * Question-bank feature registration for governed outcome mappings.
 *
 * Signatures follow the Moodle 4.5 baseline verified against the installed
 * Moodle 5.2 tree in the sibling ADR 0001: columns and question actions
 * always receive a view, while filters and bulk actions accept a nullable
 * view for cross-version compatibility.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_feature extends plugin_features_base {
    /**
     * Return the outcome mapping column.
     *
     * @param view $qbank Question bank view.
     * @return outcome_column[]
     */
    public function get_question_columns(view $qbank): array {
        if (!$this->dependency_available()) {
            return [];
        }
        if (!$this->has_capability_in_view($qbank, 'local/outcomemap:viewdefinitions')) {
            return [];
        }
        return [new outcome_column($qbank)];
    }

    /**
     * Return the per-question mapping editor action.
     *
     * @param view $qbank Question bank view.
     * @return outcome_map_action[]
     */
    public function get_question_actions(view $qbank): array {
        if (!$this->dependency_available()) {
            return [];
        }
        if (!$this->has_capability_in_view($qbank, 'local/outcomemap:mapquestions')) {
            return [];
        }
        return [new outcome_map_action($qbank)];
    }

    /**
     * Return the outcome and mapped-state filter conditions.
     *
     * @param view|null $qbank Question bank view when rendering the UI.
     * @return \core_question\local\bank\condition[]
     */
    public function get_question_filters(?view $qbank = null): array {
        if (!$this->dependency_available()) {
            return [];
        }
        if ($qbank !== null && !$this->has_capability_in_view($qbank, 'local/outcomemap:viewdefinitions')) {
            return [];
        }
        return [
            new outcome_condition($qbank),
            new role_condition($qbank),
            new status_condition($qbank),
            new mapped_condition($qbank),
            new invalid_weight_condition($qbank),
            new copied_condition($qbank),
        ];
    }

    /**
     * Return the bulk alignment-mapping action.
     *
     * @param view|null $qbank Question bank view; Moodle 4.5 omits it.
     * @return bulk_map_action[]
     */
    public function get_bulk_actions(?view $qbank = null): array {
        if (!$this->dependency_available()) {
            return [];
        }
        if ($qbank !== null && !$this->has_capability_in_view($qbank, 'local/outcomemap:mapquestions')) {
            return [];
        }
        return [new bulk_map_action($qbank)];
    }

    /** Fail closed if an administrator has removed the required system-of-record plugin. */
    protected function dependency_available(): bool {
        $directory = \core_component::get_component_directory('local_outcomemap');
        return !empty($directory)
            && class_exists(\local_outcomemap\api\question_mappings::class)
            && class_exists(\local_outcomemap\api\workflow::class);
    }

    /** Check a local capability in at least one authoritative qbank context. */
    private function has_capability_in_view(view $qbank, string $capability): bool {
        foreach ($qbank->contexts->all() as $context) {
            if (has_capability($capability, $context)) {
                return true;
            }
        }
        return false;
    }
}
