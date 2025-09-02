<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the terms
// of the GNU General Public License as published by the Free Software Foundation,
// either version 3 of the License, or (at your option) any later version.
//
// @package    local_cohortunenroller
// @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Capabilities for local_cohortunenroller plugin.
 *
 * Defines the custom capability 'local/cohortunenroller:run'.
 * This allows controlling which roles are permitted to run the plugin.
 * By default, the Manager role is granted this capability.
 */
$capabilities = [
    'local/cohortunenroller:run' => [
        'riskbitmask'  => RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];