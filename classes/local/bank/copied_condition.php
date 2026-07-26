<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace qbank_outcomemap\local\bank;

use local_outcomemap\api\question_mapping_filters;

/** Yes/no filter for copied mappings still awaiting explicit finalization. */
final class copied_condition extends binary_mapping_condition {
    protected static function criterion(): string {
        return question_mapping_filters::COPIED_PENDING;
    }

    public static function get_condition_key() {
        return 'outcomemapcopiedpending';
    }

    public function get_title() {
        return get_string('copiedfiltertitle', 'qbank_outcomemap');
    }
}
