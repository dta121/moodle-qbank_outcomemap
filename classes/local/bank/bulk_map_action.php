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

use core_question\local\bank\bulk_action_base;
use core_question\local\bank\view;
use core_question\local\bank\view_component;

/**
 * Bulk action adding one alignment-only outcome draft to selected questions.
 *
 * Assessed mappings are excluded from bulk creation on purpose: assessed
 * weights are governed per question version and are never assigned in bulk.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_map_action extends bulk_action_base {
    /**
     * Cross-version constructor.
     *
     * Moodle 4.5 instantiates bulk actions without a view while Moodle 5.2's
     * base class extends view_component and stores one. This action never
     * depends on a stored view: it redirects to a static URL that receives
     * the posted question IDs.
     *
     * @param view|null $qbank Question bank view when the release provides one.
     */
    public function __construct(?view $qbank = null) {
        if ($qbank !== null && is_subclass_of(bulk_action_base::class, view_component::class)) {
            parent::__construct($qbank);
        }
    }

    /**
     * Return the bulk action title.
     *
     * @return string
     */
    public function get_bulk_action_title(): string {
        return get_string('bulkmaptitle', 'qbank_outcomemap');
    }

    /**
     * Return the bulk action key.
     *
     * @return string
     */
    public function get_key(): string {
        return 'outcomemapbulk';
    }

    /**
     * Return the bulk action landing URL.
     *
     * @return \moodle_url
     */
    public function get_bulk_action_url(): \moodle_url {
        return new \moodle_url('/question/bank/outcomemap/bulk.php');
    }

    /**
     * Return the capabilities required to see the bulk action.
     *
     * Per-question Moodle capabilities are re-checked server side when the
     * selection is processed.
     *
     * @return string[]|null
     */
    public function get_bulk_action_capabilities(): ?array {
        return ['local/outcomemap:mapquestions'];
    }
}
