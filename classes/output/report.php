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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Renderer file.
 *
 * @package   local_cohortunenroller
 * @copyright Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortunenroller\output;

use renderable;
use templatable;
use renderer_base;

/**
 * Renderable data object for the result report.
 */
class report implements renderable, templatable {
    /**
     * Constructor.
     *
     * @param array $rows The processed rows
     * @param array $counters The counters
     */
    public function __construct(
        array $rows,
        array $counters
    ) {

    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'rows' => array_values($this->rows),
            'counters' => $this->counters,
        ];
    }
}
