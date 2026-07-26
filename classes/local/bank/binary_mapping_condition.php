<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace qbank_outcomemap\local\bank;

use core\output\datafilter;

/** Shared yes/no presentation for mapping-state filters. */
abstract class binary_mapping_condition extends mapping_condition {
    public function get_filter_class() {
        return 'core/datafilter/filtertypes/binary';
    }

    public function allow_multiple() {
        return false;
    }

    public function allow_custom() {
        return false;
    }

    public function get_join_list(): array {
        return [datafilter::JOINTYPE_ANY];
    }

    public function get_initial_values() {
        $selected = $this->selectedvalues === [] ? null : (int) reset($this->selectedvalues);
        return [
            (object) ['value' => 1, 'title' => get_string('yes'), 'selected' => $selected === 1],
            (object) ['value' => 0, 'title' => get_string('no'), 'selected' => $selected === 0],
        ];
    }
}
