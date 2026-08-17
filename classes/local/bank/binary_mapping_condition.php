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

use core\output\datafilter;

/**
 * Shared yes/no presentation for mapping-state filters.
 *
 * @package    qbank_outcomemap
 * @copyright  2026 Moodle Learning Outcome Mapping contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class binary_mapping_condition extends mapping_condition {
    /**
     * Return the core binary filter class.
     *
     * @return string
     */
    public function get_filter_class() {
        return 'core/datafilter/filtertypes/binary';
    }

    /**
     * Disallow multiple selected values.
     *
     * @return bool
     */
    public function allow_multiple() {
        return false;
    }

    /**
     * Disallow custom filter values.
     *
     * @return bool
     */
    public function allow_custom() {
        return false;
    }

    /**
     * Return the supported filter join types.
     *
     * @return int[]
     */
    public function get_join_list(): array {
        return [datafilter::JOINTYPE_ANY];
    }

    /**
     * Return the localized yes/no options.
     *
     * @return \stdClass[]
     */
    public function get_initial_values() {
        $selected = $this->selectedvalues === [] ? null : (int) reset($this->selectedvalues);
        return [
            (object) ['value' => 1, 'title' => get_string('yes'), 'selected' => $selected === 1],
            (object) ['value' => 0, 'title' => get_string('no'), 'selected' => $selected === 0],
        ];
    }
}
