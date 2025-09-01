<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the terms
// of the GNU General Public License as published by the Free Software Foundation,
// either version 3 of the License, or (at your option) any later version.
//
// @package    local_cohortunenroller
// @category   form
// @copyright  2025 Thomas
// @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_cohortunenroller\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Upload form for cohort unenroller CSV.
 */
class upload_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        // CSV file input.
        $mform->addElement(
            'filepicker',
            'csvfile',
            get_string('uploadcsv', 'local_cohortunenroller'),
            null,
            ['maxbytes' => 0, 'accepted_types' => ['.csv']]
        );
        $mform->addRule('csvfile', null, 'required', null, 'client');

        // Help text.
        $mform->addElement('static', 'csvhelp', '', get_string('csvhelp', 'local_cohortunenroller'));

        // CSV delimiter (same as core "Upload users").
        $mform->addElement(
            'select',
            'delimiter',
            get_string('csvdelimiter', 'admin'),
            \csv_import_reader::get_delimiter_list()
        );
        $mform->setDefault('delimiter', 'comma');

        // Username normalisation (trim + lowercase).
        $mform->addElement('advcheckbox', 'standardise', get_string('standardise_usernames', 'local_cohortunenroller'), '');

        // Dry run (no DB changes).
        $mform->addElement('advcheckbox', 'dryrun', get_string('dryrun', 'local_cohortunenroller'), '');

        // Submit.
        $mform->addElement('submit', 'submitbutton', get_string('submit', 'local_cohortunenroller'));
    }
}