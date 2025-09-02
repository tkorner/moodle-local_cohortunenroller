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

/**
 * Plugin renderer for local_cohortunenroller.
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the report.
     *
     * @param report $report
     * @return string
     * @throws \core\exception\moodle_exception
     */
    public function report(report $report): string {
        return $this->render_from_template('local_cohortunenroller/report', $report->export_for_template($this));
    }
}
