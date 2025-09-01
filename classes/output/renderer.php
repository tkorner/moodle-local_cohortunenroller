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

/**
 * Plugin renderer for local_cohortunenroller.
 */
class renderer extends \plugin_renderer_base {
    public function report(report $r): string {
        return $this->render_from_template('local_cohortunenroller/report', $r->export_for_template($this));
    }
}