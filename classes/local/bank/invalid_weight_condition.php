<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace qbank_outcomemap\local\bank;

use local_outcomemap\api\question_mapping_filters;

/** Yes/no filter for invalid current assessed-weight totals. */
final class invalid_weight_condition extends binary_mapping_condition {
    protected static function criterion(): string {
        return question_mapping_filters::INVALID_WEIGHT;
    }

    public static function get_condition_key() {
        return 'outcomemapinvalidweight';
    }

    public function get_title() {
        return get_string('invalidweightfiltertitle', 'qbank_outcomemap');
    }
}
