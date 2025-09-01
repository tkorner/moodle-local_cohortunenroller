<?php
// This file is part of Moodle - https://moodle.org/
//
// @package    local_cohortunenroller
// @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

use local_cohortunenroller\local\processor;

/**
 * @group local_cohortunenroller
 */
class local_cohortunenroller_processor_test extends advanced_testcase {

    public function test_removes_members_and_skips_nonmembers() {
        $this->resetAfterTest(true);

        // Create users.
        $u1 = $this->getDataGenerator()->create_user(['username'=>'alice']);
        $u2 = $this->getDataGenerator()->create_user(['username'=>'bob']);
        $u3 = $this->getDataGenerator()->create_user(['username'=>'charlie']);

        // Create cohorts.
        $c1 = $this->getDataGenerator()->create_cohort(['idnumber' => 'cohortZ']);
        $c2 = $this->getDataGenerator()->create_cohort(['idnumber' => '2016class']);

        // Add memberships: alice in cohortZ, charlie in 2016class.
        cohort_add_member($c1->id, $u1->id);
        cohort_add_member($c2->id, $u3->id);

        // Rows: remove alice from cohortZ (should remove), bob from cohortZ (not member => info skip),
        // bogus user, bogus cohort.
        $rows = [
            ['username'=>'alice','cohortidnumber'=>'cohortZ'],
            ['username'=>'bob','cohortidnumber'=>'cohortZ'],
            ['username'=>'nobody','cohortidnumber'=>'cohortZ'],
            ['username'=>'charlie','cohortidnumber'=>'doesnotexist'],
        ];

        $payload = processor::process($rows, ['standardise'=>true, 'dryrun'=>false]);
        $results = $payload['results'];

        $map = [];
        foreach ($results as $r) {
            $map[$r['username'] . '|' . ($r['cohortidnumber'] ?? '')] = $r['status'];
        }

        $this->assertEquals('status_removed', $map['alice|cohortZ']);
        $this->assertEquals('status_notmember', $map['bob|cohortZ']);
        $this->assertEquals('status_usernotfound', $map['nobody|cohortZ']);
        $this->assertEquals('status_cohortnotfound', $map['charlie|doesnotexist']);

        // Confirm DB state: alice removed from cohortZ.
        $this->assertFalse($this->record_exists('cohort_members', ['cohortid'=>$c1->id, 'userid'=>$u1->id]));
        // charlie still in 2016class (not touched).
        $this->assertTrue($this->record_exists('cohort_members', ['cohortid'=>$c2->id, 'userid'=>$u3->id]));
    }

    private function record_exists(string $table, array $conditions): bool {
        global $DB;
        return $DB->record_exists($table, $conditions);
    }
}