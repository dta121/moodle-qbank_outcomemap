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

use local_outcomemap\api\question_mapping_filters;

/**
 * Yes/no filter for copied mappings still awaiting explicit finalization.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class copied_condition extends binary_mapping_condition {
    /**
     * Return the copied-pending public API criterion.
     *
     * @return string
     */
    protected static function criterion(): string {
        return question_mapping_filters::COPIED_PENDING;
    }

    /**
     * Return the stable filter key.
     *
     * @return string
     */
    public static function get_condition_key() {
        return 'outcomemapcopiedpending';
    }

    /**
     * Return the localized filter title.
     *
     * @return string
     */
    public function get_title() {
        return get_string('copiedfiltertitle', 'qbank_outcomemap');
    }
}
