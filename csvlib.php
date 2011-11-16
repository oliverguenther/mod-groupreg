<?php

/**
 * @package   groupreg
 * @copyright 2011 onwards Oliver Günther
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $groupreg_csvcols_importgroups;
global $groupreg_csvcols_importassignments;

require_once($CFG->dirroot.'/group/lib.php');
require_once($CFG->dirroot.'/lib/grouplib.php');



$groupreg_csvcols_importgroups = array('name', 'maxanswers');
$groupreg_csvcols_importassignments = array('userid', 'optionid', 'preference', 'usergroup');

function csv_importgroups_columns() {
    global $groupreg_csvcols_importgroups;
    // Only used for get_string, in order to output required and optional columns
    $columns = new stdClass();
    $columns->req = implode(", ", $groupreg_csvcols_importgroups);
    $columns->opt = "grouping";
    
    return $columns;
}

function readCSV($file, $ishandle = false, $delimiter = ',') {
    $handle = $file;
    if (!$ishandle) {               
        if (!file_exists($file) || !is_readable($file))
            return false;
        if (($handle = fopen($file, 'r')) === FALSE)
            return false;
    }
    if (!is_resource($handle))
        return false;

    
    $header = null;
    $data = array();
    // Read line, delimiter is ',' 
    while (($row = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
        if (!$header)
            $header = $row;
        else
            $data[] = array_combine($header, $row);
    }
    fclose($handle);
    return $data;
}

function verifycsv_importgroups($csv, $is_maxanswers) {
    global $groupreg_csvcols_importgroups;
    
    $error = array();
    
    if (!isset($csv) || gettype($csv[0]) != 'array') {
       array_push($error, 'Could not read CSV file');
       return $error;
    }
    
    // TODO: Check all rows [?]
    $columns = $csv[0];
    
    foreach ($groupreg_csvcols_importgroups as $key) {
        if (!array_key_exists($key, $columns)) {
            array_push($error, "Required column " . $key . " is missing"); // TODO translate
        }
    }
    
    
    return (count($error) > 0) ? $error : null;    
}


function display_csv_contents($groupreg, $file, $action, $delimiter = ',') {
    $csv = readCSV($file, false, $delimiter);
    $html = html_writer::start_tag('div', array('class' => 'groupreg-display-csv'));
    

    $html .= html_writer::tag('h2', get_string($action, 'groupreg'));
    //$html .= html_writer::tag('p', get_string("$action-display", 'groupreg'));
       
    $html .= html_writer::start_tag("table", array('class' => 'groupreg-csv-table'));
    
    // Output CSV Columns first
    $html .= html_writer::start_tag("tr");
    foreach ($csv[0] as $col => $data) {
        $html .= html_writer::tag("th", $col);
    }
    $html .= html_writer::end_tag("tr");
    
    // Output CSV Lines
    foreach ($csv as $row) {
         $html .= html_writer::start_tag("tr");
        foreach ($row as $col => $val) {
            $html .= html_writer::tag("td", $val);
        }
        $html .= html_writer::end_tag("tr");
    }
    
    
    $html .= html_writer::end_tag("table");


    $html .= html_writer::start_tag('form', array('action' => 'import.php', 'method' => 'POST'));
    $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $groupreg->id));
    $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => $action));
    $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'file', 'value' => $file));
    $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'doimport', 'value' => 1));
    $html .= html_writer::tag('input', '', array('type' => 'submit'));

    $html .= html_writer::end_tag('form');
    $html .= html_writer::end_tag('div');

    return $html;
}

/** Call this during the upload process, so the user is presented with errors, if returned */
function generate_groups_from_csv($csv, $courseid) {
    $errors = array();
    // Keep record of created groups to rollback in case of errors
    $groups = array();
    foreach ($csv as $row) {
        $group = new stdClass();
        $group->name = trim(filter_var($row['name']));
        $group->courseid = $courseid;
        $group->timemodified = time();
        $group->maxanswers = intval($row['maxanswers']);
        // Optional column: grouping
        if (isset ($row['grouping']))
            $group->maxanswers = trim(filter_var($row['grouping']));
        if (($id = groups_get_group_by_name($courseid, $group->name)) != false) {
            // Group already exists in course, ignoring
            $group->id = $id;
        } else {
            if (($id = groups_create_group($group)) != false) {
                $group->id = $id;
                array_push($groups, $group);
            } else array_push($errors, "Could not create group " . $group->name); // TODO 18
        }
    }
    
    if (count($errors) > 0) {
        // Rollback all groups we have created thus far
        foreach ($groups as $group) {
            if (!groups_delete_group($group->id)) {
                array_push($errors, "Tried to rollback group named " . $group->name . " (id : " . $group->id . ") which was created during the process. Delete manually!"); // TODO 18
            }
        }
        return $errors;
    } else {
        return null;
    }
}
function options_from_csv($groupreg, $courseid, $csv) {
    // Set new option_repeats as count(csv-lines)
    $option_repeats = count($csv);
    
    
    // Initialize settings
    $groupreg->option = array();
    $groupreg->limit = array();
    $groupreg->grouping = array();
    $groupreg->optionid = array();
    $groupreg->option_repeats = $option_repeats;
    foreach ($csv as $row) {
        // get group id
        $groupname = trim(filter_var($row['name']));
        // TODO attention: Duplicate group names will lead to unexpected behavior!
        $option = groups_get_group_by_name($courseid, $groupname);
        // maxanswer
        $limit = intval($row['maxanswers']);
        // grouping
        $grouping = (isset($row['grouping'])) ? filter_var($row['grouping']) : '';
        // default optionid = 0
        $optionid = 0;
        
        array_push($groupreg->option, $option);
        array_push($groupreg->limit, $limit);
        array_push($groupreg->grouping, $grouping);
        array_push($groupreg->optionid, $optionid);
        
    }
    return $groupreg;
}

/**
 * Verifies group assignment import CSV data.
 */
function verifyCSV($csv, $action, $choice, $cm) {
	if ($action = 'importassignments') {
		$data = parse_assignment_csv($csv, $choice, $cm);
		$errors = $data->errors;		
		return $errors;
	} else
		return array('Not implemented!');
}

function parse_assignment_csv($csv, $choice, $cm) {
	global $CFG, $DB;
	$data = new stdClass;
	$data->errors = array();
	
	// pre-fetch users and groups in the course
	$context = get_context_instance(CONTEXT_MODULE, $cm->id);
	$users_db = get_enrolled_users($context, 'mod/groupreg:choose', 0, user_picture::fields('u', array('idnumber')), 'u.lastname ASC,u.firstname ASC');
	$users = array();
	foreach($users_db as $user)
		$users[$user->lastname.', '.$user->firstname][] = $user->id;
		
	if (sizeof($users) == 0) {
		$data->errors[] = 'csvimport-error-nousers';
		return $data;
	}
	
	$groups_db = $DB->get_records_sql('SELECT g.id, g.name, o.id as optionid 
										FROM {groups} g, {groupreg_options} o
										WHERE o.groupregid = ? AND
											  g.id = o.text', array($choice->id));
	$groups = array();
	foreach ($groups_db as $group)
		$groups[$group->name] = $group->optionid;
	
	if (sizeof($groups) == 0) {
		$data->errors[] = 'csvimport-error-nogroups';
		return $data;
	}
	
	// prepare header names to extract data with
	$h_name = get_string('export_header_name', 'groupreg');
	$h_groupmember = array();
	for ($i = 1; $i < $choice->groupmembers; $i++)
		$h_groupmember[] = get_string('export_header_groupmember_n', 'groupreg', $i);	
	$h_group = get_string('export_header_assigned_group', 'groupreg');
		
	// go through all csv lines. ensure all users and groups are validated, and all users are unique
	$data->assignments = array();
	foreach ($csv as $line) {
		if (!isset($line[$h_name])) {
			continue;
		}
		
		if (!isset($line[$h_group])) {
			$data->errors[] = 'csvimport-error-no-assigned-column';
			break;
		}
	
		if (trim($line[$h_name]) == $h_name)
			continue; // that's the header line, skip
		
		$line_users = array();
		$line_users[] = trim($line[$h_name]);
		for ($i = 1; $i < $choice->groupmembers; $i++)
			if (trim($line[$h_groupmember[$i-1]]) != '')
				$line_users[] = $line[$h_groupmember[$i-1]];
		
		$group = $line[$h_group];
		
		// check group
		if (!isset($groups[$group])) {
			$data->errors[] = 'csvimport-error-unknown-group::'.$group;
			continue;
		}	
		
		// check users
		foreach ($line_users as $user) {
			// for handling passing user id, split by |
			$user_chunks = explode('|', $user);
			$user = $user_chunks[0];
			$userid = sizeof($user_chunks) > 1 ? $user_chunks[1] : -1;
		
			// check for possible errors
			if (!isset($users[$user]) || sizeof($users[$user]) == 0)
				$data->errors[] = 'csvimport-error-unknown-user::'.$user;
			else if (sizeof($users[$user]) > 1) {
				// handle multiple users with same name: provide user ID in csv behind a "|"
				if ($userid == -1)
					$data->errors[] = "csvimport-error-non-unique-user::$user";
				else if (!in_array($userid, $users[$user]))
					$data->errors[] = "csvimport-error-unknown-user::$user ($userid)";
			}	
			
			// write assignment into output data
			$assignment = new stdClass;
			$assignment->groupregid = $choice->id;
			$assignment->userid = $userid > -1 ? $userid : $users[$user][0];
			$assignment->optionid = $groups[$group];
			$assignment->timeassigned = 0;
			
			$data->assignments[] = $assignment;
		}
		
		if (sizeof($data->assignments) == 0)
			$data->errors[] = 'csvimport-error-no-data';
	}	
	
	return $data;
}

function import_assignments_from_csv($groupreg, $file, $cm) {
	global $DB;

    $csv = readCSV($file, false, ';');
	if (!$csv)
		return array('csvimport-error-file-not-there::'.$file);
	
	$data = parse_assignment_csv($csv, $groupreg, $cm);
	
	if (sizeof($data->errors) > 0)
		return; // cancel importing if there are errors.
		
	// reset existing assignments
	groupreg_reset_assignment($groupreg);
	
	// import new assignments into database
	foreach ($data->assignments as $asgn)
		$DB->insert_record('groupreg_assigned', $asgn);
		
	$groupreg->timeclose = time();
    $groupreg->timemodified = time();
    $groupreg->allowupdate = 0;
    $groupreg->assigned = 1;
    $DB->update_record("groupreg", $groupreg);
}


?>
