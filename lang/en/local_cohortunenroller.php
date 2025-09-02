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

$string['pluginname'] = 'Cohort Unenroller';
$string['cohortunenroller:run'] = 'Run the Cohort Unenroller';
$string['pageheading'] = 'Cohort Unenroller';
$string['uploadcsv'] = 'Upload CSV';
$string['csvhelp'] = 'CSV headers: username,cohortid OR username,cohortidnumber.';
$string['standardise_usernames'] = 'Standardise usernames (trim + lowercase)';
$string['dryrun'] = 'Dry run (no changes)';
$string['submit'] = 'Process CSV';
$string['results'] = 'Results';
$string['summary'] = 'Summary';
$string['rows_total'] = 'Rows in file';
$string['rows_valid'] = 'Valid rows';
$string['rows_processed'] = 'Rows processed';
$string['rows_skipped'] = 'Rows skipped';
$string['rows_errors'] = 'Rows with errors';
$string['download'] = 'Download CSV';
$string['error_nofile'] = 'Please upload a CSV file.';
$string['error_headers'] = 'Missing headers: expect username,cohortid or username,cohortidnumber';
$string['dryrun_notice'] = 'Dry run: no changes were made.';
$string['status_removed'] = 'Removed';
$string['status_notmember'] = 'User not a member';
$string['status_usernotfound'] = 'User not found';
$string['status_cohortnotfound'] = 'Cohort not found';
$string['status_duplicate'] = 'Duplicate in file';
$string['status_invalid'] = 'Invalid data';
$string['privacy:metadata'] = 'This plugin does not store any personal data.';
$string['menulink'] = 'Cohort Unenroller';