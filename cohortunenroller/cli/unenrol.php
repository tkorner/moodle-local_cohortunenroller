<?php
// phpcs:ignoreFile
// CLI script to unenrol users from cohorts based on CSV.
// Usage examples:
//   php local/cohortunenroller/cli/unenrol.php --csv=/path/in.csv --dry-run --delimiter=comma
//   php local/cohortunenroller/cli/unenrol.php --csv=/path/in.csv --report=/path/out.csv --username-standardise --delimiter=semicolon
//
// CSV headers (one of):
//   username,cohortid
//   username,cohortidnumber

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/cohort/lib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'csv' => null,
        'report' => null,
        'dry-run' => false,
        'username-standardise' => false,
        'delimiter' => 'comma', // comma | semicolon | tab
        'help' => false,
    ],
    [
        'h' => 'help',
    ]
);

$help = "Cohort Unenroller (CLI)
Removes users from cohorts by CSV mapping (username + cohort id or idnumber).

Options:
  --csv=PATH                   Path to CSV input file (required)
  --report=PATH                Optional path to write a result CSV (status per row)
  --dry-run                    Validate only; do not change the database
  --username-standardise       Trim + lowercase usernames before lookup
  --delimiter=comma|semicolon|tab  CSV delimiter (default: comma)
  -h, --help                   Show this help

CSV formats:
  username,cohortid
  username,cohortidnumber

Examples:
  php local/cohortunenroller/cli/unenrol.php --csv=/data/in.csv --dry-run --delimiter=tab
  php local/cohortunenroller/cli/unenrol.php --csv=/data/in.csv --report=/data/out.csv --delimiter=semicolon
";

if (!empty($options['help'])) {
    echo $help;
    exit(0);
}

if (empty($options['csv'])) {
    cli_error("Missing required --csv option.\n\n" . $help, 1);
}

$csvpath = $options['csv'];
$reportpath = $options['report'] ?? null;
$dryrun = !empty($options['dry-run']);
$standardise = !empty($options['username-standardise']);
$delimiter = $options['delimiter'] ?? 'comma';

// Validate delimiter using core list.
$allowed = array_keys(csv_import_reader::get_delimiter_list());
if (!in_array($delimiter, $allowed, true)) {
    cli_error("Invalid --delimiter. Allowed: " . implode('|', $allowed), 1);
}

// Require site admin privileges for safety.
require_admin();

if (!is_readable($csvpath)) {
    cli_error("CSV not readable: {$csvpath}", 2);
}

$content = file_get_contents($csvpath);
if ($content === false || $content === '') {
    cli_error("CSV is empty or cannot be read: {$csvpath}", 4);
}

$iid = csv_import_reader::get_new_iid('local_cohortunenroller_cli');
$cir = new csv_import_reader($iid, 'local_cohortunenroller_cli');

$encoding = 'utf-8';
// NEU: Delimiter vom CLI-Flag (wie im Core).
$cir->load_csv_content($content, $encoding, $delimiter);
$columns = array_map('strtolower', $cir->get_columns() ?? []);

$hasid = in_array('cohortid', $columns, true);
$hasidnumber = in_array('cohortidnumber', $columns, true);
if (!in_array('username', $columns, true) || (!$hasid && !$hasidnumber)) {
    $cir->close(); $cir->cleanup();
    cli_error("Invalid headers. Expect 'username,cohortid' or 'username,cohortidnumber'.", 5);
}

$colmap = array_flip($columns);
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

    // Duplikate vermeiden.
    $pairkey = $username . '|' . ($cohortid !== null ? ('id:'.$cohortid) : ('idn:'.$cohortidnumber));
    if (isset($seenpairs[$pairkey])) {
        $results[] = ['username'=>$username,'cohortid'=>$cohortid,'cohortidnumber'=>$cohortidnumber,'status'=>'status_duplicate'];
        $counters['errors']++; $counters['skipped']++;
        continue;
    }
    $seenpairs[$pairkey] = true;

    // User lookup via username (nicht gelöschte Nutzer).
    $user = $DB->get_record('user', ['username'=>$username, 'deleted'=>0], 'id', IGNORE_MISSING);
    if (!$user) {
        $results[] = ['username'=>$username,'cohortid'=>$cohortid,'cohortidnumber'=>$cohortidnumber,'status'=>'status_usernotfound'];
        $counters['errors']++; $counters['skipped']++;
        continue;
    }

    // Cohort lookup via id oder idnumber.
    if ($cohortid !== null) {
        $cohort = $DB->get_record('cohort', ['id'=>$cohortid], 'id', IGNORE_MISSING);
    } else {
        $cohort = $DB->get_record('cohort', ['idnumber'=>$cohortidnumber], 'id', IGNORE_MISSING);
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
$cir->close(); $cir->cleanup();

// Zusammenfassung (STDOUT).
cli_writeln("Cohort Unenroller (CLI) finished.");
cli_writeln("- Total rows    : {$counters['total']}");
cli_writeln("- Valid rows    : {$counters['valid']}");
cli_writeln("- Processed     : {$counters['processed']}");
cli_writeln("- Skipped       : {$counters['skipped']}");
cli_writeln("- Error rows    : {$counters['errors']}");
cli_writeln("- Delimiter     : {$delimiter}");
if ($dryrun) {
    cli_writeln("- Mode          : DRY RUN (no changes)");
}

// Optionaler Report.
if (!empty($reportpath)) {
    $dir = dirname($reportpath);
    if (!is_dir($dir) || !is_writable($dir)) {
        cli_problem("Report path not writable: {$reportpath}");
    } else {
        $fp = fopen($reportpath, 'w');
        if ($fp === false) {
            cli_problem("Failed to open report for writing: {$reportpath}");
        } else {
            fputcsv($fp, ['username', 'cohortid', 'cohortidnumber', 'status']);
            foreach ($results as $r) {
                $status = $r['status'];
                $map = [
                    'status_removed' => 'Removed',
                    'status_notmember' => 'User not a member',
                    'status_usernotfound' => 'User not found',
                    'status_cohortnotfound' => 'Cohort not found',
                    'status_duplicate' => 'Duplicate in file',
                    'status_invalid' => 'Invalid data',
                ];
                $readable = $map[$status] ?? $status;
                fputcsv($fp, [
                    $r['username'] ?? '',
                    isset($r['cohortid']) ? (string)$r['cohortid'] : '',
                    $r['cohortidnumber'] ?? '',
                    $readable
                ]);
            }
            fclose($fp);
            cli_writeln("Report written to: {$reportpath}");
        }
    }
}

// Exit-Code: 0 wenn keine Fehlerzeilen; sonst 2.
exit($counters['errors'] > 0 ? 2 : 0);