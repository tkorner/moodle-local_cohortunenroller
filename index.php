<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the terms
// of the GNU General Public License as published by the Free Software Foundation,
// either version 3 of the License, or (at your option) any later version.
//
// @package    local_cohortunenroller
// @copyright  2025 Thomas
// @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require('../../config.php');

require_login();
$context = context_system::instance();

// Page access capability (plugin) + cohort assignment capability (core).
require_capability('local/cohortunenroller:run', $context);
require_capability('moodle/cohort:assign', $context);

$PAGE->set_url(new moodle_url('/local/cohortunenroller/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_cohortunenroller'));
$PAGE->set_heading(get_string('pageheading', 'local_cohortunenroller'));

echo $OUTPUT->header();

// Libs needed below.
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/cohort/lib.php');

// Load the upload form (namespaced moodleform subclass).
$mform = new \local_cohortunenroller\form\upload_form();

// Handle secure CSV download of the last run's results (stored in session).
$download = optional_param('download', 0, PARAM_BOOL);
if ($download) {
    require_sesskey(); // CSRF protection for the download action.

    if (!empty($SESSION->local_cohortunenroller_report)) {
        $rows = $SESSION->local_cohortunenroller_report['rows'] ?? [];
        $export = new csv_export_writer();
        $export->set_filename('cohort_unenroller_results');
        $export->add_data(['username', 'cohortid', 'cohortidnumber', 'status']);

        foreach ($rows as $r) {
            $export->add_data([
                $r['username'] ?? '',
                isset($r['cohortid']) ? (string)$r['cohortid'] : '',
                $r['cohortidnumber'] ?? '',
                $r['status_readable'] ?? ($r['status'] ?? '')
            ]);
        }
        $export->download_file();
        exit;
    }
}

// Standard moodleform flow.
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/admin/search.php?query=Cohort%20Unenroller'));
} else if ($data = $mform->get_data()) {
    // Form includes sesskey; still enforce explicitly for clarity.
    require_sesskey();

    // Read uploaded CSV content.
    $filecontent = $mform->get_file_content('csvfile');
    if (!$filecontent) {
        echo $OUTPUT->notification(get_string('error_nofile', 'local_cohortunenroller'), 'error');
        $mform->display();
        echo $OUTPUT->footer();
        exit;
    }

    // Prepare CSV reader.
    $iid = csv_import_reader::get_new_iid('local_cohortunenroller');
    $cir = new csv_import_reader($iid, 'local_cohortunenroller');

    $encoding = 'utf-8';
    $delimiter = $data->delimiter ?? 'comma'; // 'comma'|'semicolon'|'tab' as provided by core.
    $cir->load_csv_content($filecontent, $encoding, $delimiter);

    // Read header columns and initialise iterator.
    $columns = array_map('strtolower', $cir->get_columns() ?? []);
    $cir->init();

    // Validate required headers.
    $hasid = in_array('cohortid', $columns, true);
    $hasidnumber = in_array('cohortidnumber', $columns, true);
    if (!in_array('username', $columns, true) || (!$hasid && !$hasidnumber)) {
        echo $OUTPUT->notification(get_string('error_headers', 'local_cohortunenroller'), 'error');
        $cir->close();
        $cir->cleanup();
        $mform->display();
        echo $OUTPUT->footer();
        exit;
    }

    // Map column names to indices for fast lookup.
    $colmap = array_flip($columns);
    $standardise = !empty($data->standardise);
    $dryrun = !empty($data->dryrun);

    // Collect normalized rows (transport format for the processor).
    $rows = [];
    while ($row = $cir->next()) {
        $rec = [
            'username' => trim((string)($row[$colmap['username']] ?? '')),
        ];
        if ($hasid) {
            $rawid = trim((string)($row[$colmap['cohortid']] ?? ''));
            if ($rawid !== '' && ctype_digit($rawid)) {
                $rec['cohortid'] = (int)$rawid;
            }
        }
        if ($hasidnumber) {
            $rec['cohortidnumber'] = trim((string)($row[$colmap['cohortidnumber']] ?? ''));
        }
        $rows[] = $rec;
    }
    $cir->close();
    $cir->cleanup();

    // Execute business logic via the service class (unit-testable).
    $payload = \local_cohortunenroller\local\processor::process($rows, [
        'standardise' => $standardise,
        'dryrun' => $dryrun,
    ]);

    $results = $payload['results'];
    $counters = $payload['counters'];

    // Human-readable status strings and minimal sanitising before templating.
    foreach ($results as &$r) {
        $r['status_readable'] = get_string($r['status'], 'local_cohortunenroller');
        // Mustache escapes by default; preparing strings defensively is fine.
        $r['username'] = $r['username'] ?? '';
        $r['cohortid'] = isset($r['cohortid']) ? (string)$r['cohortid'] : '';
        $r['cohortidnumber'] = $r['cohortidnumber'] ?? '';
    }

    // Persist for CSV download.
    $SESSION->local_cohortunenroller_report = ['rows' => $results, 'counters' => $counters];

    // Informational notice for dry run.
    if ($dryrun) {
        echo $OUTPUT->notification(get_string('dryrun_notice', 'local_cohortunenroller'), 'info');
    }

    // Render the summary + table via plugin renderer and Mustache template.
    $renderer = $PAGE->get_renderer('local_cohortunenroller');
    echo $renderer->report(new \local_cohortunenroller\output\report($results, $counters));

    // Download button (protected by sesskey) and back-to-upload button.
    $dlurl = new moodle_url('/local/cohortunenroller/index.php', ['download' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($dlurl, get_string('download', 'local_cohortunenroller'));
    echo $OUTPUT->single_button(new moodle_url('/local/cohortunenroller/index.php'), get_string('uploadcsv', 'local_cohortunenroller'));
} else {
    // First page load: show the upload form.
    $mform->display();
}

echo $OUTPUT->footer();