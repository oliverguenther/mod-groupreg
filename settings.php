<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext('groupreg_perlscript', get_string('settings_perlscript', 'groupreg'), get_string('settings_perlscript_desc', 'groupreg'), '/opt/assign_groups.pl'));

}
