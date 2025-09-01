<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the terms
// of the GNU General Public License as published by the Free Software Foundation,
// either version 3 of the License, or (at your option) any later version.
//
// @package    local_cohortunenroller
// @category   output
// @copyright  2025 Thomas
// @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_cohortunenroller\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;

/**
 * Renderable data object for the result report.
 */
class report implements renderable, templatable {
    public function __construct(
        private array $rows,
        private array $counters
    ) {}

    public function export_for_template(renderer_base $output): array {
        return [
            'rows' => array_values($this->rows),
            'counters' => $this->counters,
        ];
    }
}