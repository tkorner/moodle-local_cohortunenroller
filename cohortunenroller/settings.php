<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_externalpage(
        'local_cohortunenroller',
        get_string('pluginname', 'local_cohortunenroller'),
        new moodle_url('/local/cohortunenroller/index.php')
    );
    $ADMIN->add('localplugins', $settings);
}