<?php
// This file is part of Moodle - https://moodle.org/
//
// @package    local_cohortunenroller
// @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_cohortunenroller\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Processes username+cohort mappings and removes cohort memberships.
 */
class processor {

    /**
     * @param array $rows Each row: ['username' => string, 'cohortid' => int|null, 'cohortidnumber' => string|null]
     * @param array $options ['standardise' => bool, 'dryrun' => bool]
     * @return array [ 'results' => array, 'counters' => array ]
     */
    public static function process(array $rows, array $options = []) : array {
        global $DB;
        require_once($GLOBALS['CFG']->dirroot . '/cohort/lib.php');

        $standardise = !empty($options['standardise']);
        $dryrun = !empty($options['dryrun']);

        $seenpairs = [];
        $results = [];
        $counters = ['total'=>0,'valid'=>0,'processed'=>0,'skipped'=>0,'errors'=>0];

        $transaction = $dryrun ? null : $DB->start_delegated_transaction();

        foreach ($rows as $r) {
            $counters['total']++;

            $username = isset($r['username']) ? trim((string)$r['username']) : '';
            if ($standardise && $username !== '') {
                $username = \core_text::strtolower($username);
            }
            $cohortid = $r['cohortid'] ?? null;
            $cohortidnumber = isset($r['cohortidnumber']) ? trim((string)$r['cohortidnumber']) : null;

            if ($username === '' || ($cohortid === null && ($cohortidnumber === null || $cohortidnumber === ''))) {
                $results[] = ['username'=>$username,'cohortid'=>$cohortid,'cohortidnumber'=>$cohortidnumber,'status'=>'status_invalid'];
                $counters['errors']++; $counters['skipped']++;
                continue;
            }

            $pairkey = $username . '|' . ($cohortid !== null ? ('id:'.$cohortid) : ('idn:'.$cohortidnumber));
            if (isset($seenpairs[$pairkey])) {
                $results[] = ['username'=>$username,'cohortid'=>$cohortid,'cohortidnumber'=>$cohortidnumber,'status'=>'status_duplicate'];
                $counters['errors']++; $counters['skipped']++;
                continue;
            }
            $seenpairs[$pairkey] = true;

            $user = $DB->get_record('user', ['username'=>$username, 'deleted'=>0], 'id', IGNORE_MISSING);
            if (!$user) {
                $results[] = ['username'=>$username,'cohortid'=>$cohortid,'cohortidnumber'=>$cohortidnumber,'status'=>'status_usernotfound'];
                $counters['errors']++; $counters['skipped']++;
                continue;
            }

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

            $ismember = $DB->record_exists('cohort_members', ['cohortid'=>$cohort->id, 'userid'=>$user->id]);
            if (!$ismember) {
                $results[] = ['username'=>$username,'cohortid'=>$cohort->id,'cohortidnumber'=>$cohortidnumber ?? '', 'status'=>'status_notmember'];
                $counters['valid']++; $counters['skipped']++;
                continue;
            }

            if (!$dryrun) {
                cohort_remove_member($cohort->id, $user->id);
            }

            $results[] = ['username'=>$username,'cohortid'=>$cohort->id,'cohortidnumber'=>$cohortidnumber ?? '', 'status'=>'status_removed'];
            $counters['valid']++; $counters['processed']++;
        }

        if (!$dryrun) {
            $transaction->allow_commit();
        }

        return ['results'=>$results, 'counters'=>$counters];
    }
}