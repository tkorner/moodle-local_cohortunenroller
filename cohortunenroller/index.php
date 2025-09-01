<?php
require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/cohort:assign', $context);

$PAGE->set_url(new moodle_url('/local/cohortunenroller/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_cohortunenroller'));
$PAGE->set_heading(get_string('pageheading', 'local_cohortunenroller'));

echo $OUTPUT->header();

require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

class local_cohortunenroller_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        // CSV-Datei.
        $mform->addElement('filepicker', 'csvfile', get_string('uploadcsv', 'local_cohortunenroller'),
            null, ['maxbytes' => 0, 'accepted_types' => ['.csv']]);
        $mform->addRule('csvfile', null, 'required', null, 'client');

        // Hinweistext.
        $mform->addElement('static', 'csvhelp', '', get_string('csvhelp', 'local_cohortunenroller'));

        // Delimiter wie im Core-Importer.
        $mform->addElement('select', 'delimiter', get_string('csvdelimiter', 'admin'), csv_import_reader::get_delimiter_list());
        $mform->setDefault('delimiter', 'comma');

        // Optional: Usernames standardisieren (trim + lowercase).
        $mform->addElement('advcheckbox', 'standardise', get_string('standardise_usernames', 'local_cohortunenroller'), '');

        // Dry run.
        $mform->addElement('advcheckbox', 'dryrun', get_string('dryrun', 'local_cohortunenroller'), '');

        $mform->addElement('submit', 'submitbutton', get_string('submit', 'local_cohortunenroller'));
    }
}

$mform = new local_cohortunenroller_form();

// Download der Result-CSV aus der Session.
$download = optional_param('download', 0, PARAM_BOOL);
if ($download && isset($SESSION->local_cohortunenroller_report)) {
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

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/admin/search.php?query=Cohort+Unenroller'));
} else if ($data = $mform->get_data()) {

    $filecontent = $mform->get_file_content('csvfile');
    if (!$filecontent) {
        echo $OUTPUT->notification(get_string('error_nofile', 'local_cohortunenroller'), 'error');
        $mform->display();
        echo $OUTPUT->footer();
        exit;
    }

    $iid = csv_import_reader::get_new_iid('local_cohortunenroller');
    $cir = new csv_import_reader($iid, 'local_cohortunenroller');

    $encoding = 'utf-8';
    // NEU: Delimiter aus dem Formular wie im Core.
    $delimiter = $data->delimiter ?? 'comma';

    $cir->load_csv_content($filecontent, $encoding, $delimiter);
    $columns = array_map('strtolower', $cir->get_columns() ?? []);

    $hasid = in_array('cohortid', $columns, true);
    $hasidnumber = in_array('cohortidnumber', $columns, true);
    if (!in_array('username', $columns, true) || (!$hasid && !$hasidnumber)) {
        echo $OUTPUT->notification(get_string('error_headers', 'local_cohortunenroller'), 'error');
        $cir->close(); $cir->cleanup();
        $mform->display();
        echo $OUTPUT->footer();
        exit;
    }

    $colmap = array_flip($columns);
    $dryrun = !empty($data->dryrun);
    $standardise = !empty($data->standardise);

    $seenpairs = [];
    $results = [];
    $counters = ['total'=>0,'valid'=>0,'processed'=>0,'skipped'=>0,'errors'=>0];

    $transaction = $dryrun ? null : $DB->start_delegated_transaction();

    while ($row = $cir->next()) {
        $counters['total']++;

        // Username normalisieren.
        $username = $row[$colmap['username']] ?? '';
        $username = trim((string)$username);
        if ($standardise) {
            $username = core_text::strtolower($username);
        }

        $cohortid = null;
        $cohortidnumber = null;

        if ($hasid) {
            $raw = trim((string)($row[$colmap['cohortid']] ?? ''));
            if ($raw !== '' && ctype_digit($raw)) {
                $cohortid = (int)$raw;
            }
        }
        if ($hasidnumber) {
            $cohortidnumber = trim((string)($row[$colmap['cohortidnumber']] ?? ''));
        }

        // Grundvalidierung.
        if ($username === '' || ($cohortid === null && $cohortidnumber === null)) {
            $results[] = ['username'=>$username,'cohortid'=>$cohortid,'cohortidnumber'=>$cohortidnumber,'status'=>'status_invalid'];
            $counters['errors']++; $counters['skipped']++;
            continue;
        }

        // Duplikate pro Datei.
        $pairkey = $username . '|' . ($cohortid !== null ? ('id:'.$cohortid) : ('idn:'.$cohortidnumber));
        if (isset($seenpairs[$pairkey])) {
            $results[] = ['username'=>$username,'cohortid'=>$cohortid,'cohortidnumber'=>$cohortidnumber,'status'=>'status_duplicate'];
            $counters['errors']++; $counters['skipped']++;
            continue;
        }
        $seenpairs[$pairkey] = true;

        // User lookup via username (nicht gelöschte Nutzer).
        $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], 'id', IGNORE_MISSING);
        if (!$user) {
            $results[] = ['username'=>$username,'cohortid'=>$cohortid,'cohortidnumber'=>$cohortidnumber,'status'=>'status_usernotfound'];
            $counters['errors']++; $counters['skipped']++;
            continue;
        }

        // Cohort lookup via id oder idnumber.
        if ($cohortid !== null) {
            $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id', IGNORE_MISSING);
        } else {
            $cohort = $DB->get_record('cohort', ['idnumber' => $cohortidnumber], 'id', IGNORE_MISSING);
        }
        if (!$cohort) {
            $results[] = ['username'=>$username,'cohortid'=>$cohortid,'cohortidnumber'=>$cohortidnumber,'status'=>'status_cohortnotfound'];
            $counters['errors']++; $counters['skipped']++;
            continue;
        }

        // Mitglied?
        $ismember = $DB->record_exists('cohort_members', ['cohortid'=>$cohort->id, 'userid'=>$user->id]);
        if (!$ismember) {
            // Info-Skip (kein Fehler).
            $results[] = ['username'=>$username,'cohortid'=>$cohort->id,'cohortidnumber'=>$cohortidnumber ?? '', 'status'=>'status_notmember'];
            $counters['valid']++; $counters['skipped']++;
            continue;
        }

        // Entfernen, sofern nicht Dry-Run.
        if (!$dryrun) {
            cohort_remove_member($cohort->id, $user->id);
        }

        $results[] = ['username'=>$username,'cohortid'=>$cohort->id,'cohortidnumber'=>$cohortidnumber ?? '', 'status'=>'status_removed'];
        $counters['valid']++; $counters['processed']++;
    }

    if (!$dryrun) {
        $transaction->allow_commit();
    }
    $cir->close();
    $cir->cleanup();

    // Ergebnisse für Download ablegen und anzeigen.
    foreach ($results as &$r) {
        $r['status_readable'] = get_string($r['status'], 'local_cohortunenroller');
    }
    $SESSION->local_cohortunenroller_report = ['rows' => $results, 'counters' => $counters];

    echo $OUTPUT->heading(get_string('results', 'local_cohortunenroller'), 3);
    if ($dryrun) {
        echo $OUTPUT->notification(get_string('dryrun_notice', 'local_cohortunenroller'), 'info');
    }

    $summary = html_writer::alist([
        get_string('rows_total', 'local_cohortunenroller') . ': ' . $counters['total'],
        get_string('rows_valid', 'local_cohortunenroller') . ': ' . $counters['valid'],
        get_string('rows_processed', 'local_cohortunenroller') . ': ' . $counters['processed'],
        get_string('rows_skipped', 'local_cohortunenroller') . ': ' . $counters['skipped'],
        get_string('rows_errors', 'local_cohortunenroller') . ': ' . $counters['errors'],
    ], [], 'ul');
    echo html_writer::div(html_writer::tag('h4', get_string('summary', 'local_cohortunenroller')) . $summary);

    $table = new html_table();
    $table->head = ['username', 'cohortid', 'cohortidnumber', 'status'];
    foreach ($results as $r) {
        $table->data[] = [
            s($r['username'] ?? ''),
            s(isset($r['cohortid']) ? (string)$r['cohortid'] : ''),
            s($r['cohortidnumber'] ?? ''),
            s($r['status_readable'] ?? '')
        ];
    }
    echo html_writer::table($table);

    $dlurl = new moodle_url('/local/cohortunenroller/index.php', ['download' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($dlurl, get_string('download', 'local_cohortunenroller'));

    echo $OUTPUT->single_button(new moodle_url('/local/cohortunenroller/index.php'), get_string('uploadcsv', 'local_cohortunenroller'));
} else {
    $mform->display();
}

echo $OUTPUT->footer();