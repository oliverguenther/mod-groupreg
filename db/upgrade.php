<?php

// This file keeps track of upgrades to
// the groupreg module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

function xmldb_groupreg_upgrade($oldversion) {
    global $CFG, $DB;
    
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    if ($oldversion < 2011100201) {
        $table = new xmldb_table('groupreg');
        $field = new xmldb_field('groupmembers');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '5', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '4', 'assigned');

        if(!$dbman->field_exists($table,$field)) {
            $dbman->add_field($table, $field);
        }
    }
	
	if ($oldversion < 2011112601) {
		// Adding DB field: boolean (int-1) "finalized", telling whether the students have already been put in their groups.
		$table = new xmldb_table('groupreg');
        $field = new xmldb_field('finalized');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'groupmembers');

        if(!$dbman->field_exists($table,$field)) {
            $dbman->add_field($table, $field);
        }
	}
    
    return true;
}


