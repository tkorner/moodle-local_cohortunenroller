# Cohort Unenroller (Moodle local plugin)

Removes users from cohorts via CSV (UI + CLI).  
**Compatibility:** Moodle 4.5 and 5.x.

## Features
- CSV (UI): `username,cohortid` **or** `username,cohortidnumber`
- Dry run, info-skip when not a member
- HTML report + downloadable CSV
- CLI: `cli/unenrol.php`

## Install
1. Place folder in `moodle/local/cohortunenroller`.
2. Site administration → Notifications.
3. Open: Site administration → Plugins → Local plugins → Cohort Unenroller.

## CLI
```bash
php local/cohortunenroller/cli/unenrol.php --csv=/path/in.csv [--report=/path/out.csv] [--dry-run] [--username-standardise]

## Tests & CI
This plugin uses Moodle Plugin CI on GitHub Actions:
- PHP lint, Moodle coding style (moodle-cs), PHPUnit, optional Behat.

Run locally: