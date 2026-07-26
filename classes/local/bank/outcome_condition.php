<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace qbank_outcomemap\local\bank;

use local_outcomemap\api\question_mapping_filters;

/** Filter exact question versions by governed outcome or framework text. */
final class outcome_condition extends mapping_condition {
    protected static function criterion(): string {
        return question_mapping_filters::OUTCOME;
    }

    public static function get_condition_key() {
        return 'outcomemap';
    }

    public function get_title() {
        return get_string('filtertitle', 'qbank_outcomemap');
    }

    public function get_filter_class() {
        return 'core/datafilter/filtertypes/keyword';
    }

    public function get_initial_values() {
        $values = [];
        foreach ($this->selectedvalues as $value) {
            $values[] = (object) [
                'value' => $value,
                'title' => $value,
                'selected' => true,
            ];
        }
        return $values;
    }
}
