<?php

/**
 * @package   groupreg
 * @copyright 2011 onwards Oliver Günther
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $groupreg_IMPORTGROUPS_COLUMN;
global $groupreg_IMPORTASSIGNMENTS_COLUMN;

require_once($CFG->dirroot.'/group/lib.php');
require_once($CFG->dirroot.'/lib/grouplib.php');



$groupreg_IMPORTGROUPS_COLUMN = array("name", "maxanswers");
$groupreg_IMPORTASSIGNMENTS_COLUMN = array("userid", "optionid", "preference", "usergroup");

function readCSV($file) {
    if (!file_exists($file) || !is_readable($file))
        return false;

    $header = null;
    $data = array();
    if (($handle = fopen($file, 'r')) !== FALSE) {
        // Read line, delimiter is ',' 
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (!$header)
                $header = $row;
            else
                $data[] = array_combine($header, $row);
        }
        fclose($handle);
    }
    return $data;
}


function verifyCSV($csv, $action) {
    global $groupreg_IMPORTGROUPS_COLUMN, $groupreg_IMPORTASSIGNMENTS_COLUMN;  
    $error = array();
    
    if (!isset($csv) || gettype($csv[0]) != 'array') {
        array_push($error, 'Could not read CSV file');
        return $error;
    }
    
    // Simple check: Check columns in first row
    // TODO check all lines (?)
    $columns = $csv[0];
    
    $to_check = $groupreg_IMPORTGROUPS_COLUMN;
    if ($action == 'importassignments')
        $to_check = $groupreg_IMPORTASSIGNMENTS_COLUMN;
    
    foreach ($to_check as $key) {
        if (!array_key_exists($key, $columns)) {
            array_push($error, "Required column " . $key . " is missing");
        }
    }
    
    
    return (count($error) > 0) ? $error : null;
        
}

function display_csv_contents($cm, $file, $action) {
    $csv = readCSV($file);
    $html = html_writer::start_tag('div', array('class' => 'groupreg-display-csv'));
    

    $html .= html_writer::tag('h2', get_string($action, 'groupreg'));
    $html .= html_writer::tag('p', get_string("$action-display", 'groupreg'));
    
    
    
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
    $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cm->id));
    $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => $action));
    $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'file', 'value' => $file));
    $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'doimport', 'value' => 1));
    $html .= html_writer::tag('input', '', array('type' => 'submit'));

    $html .= html_writer::end_tag('form');
    $html .= html_writer::end_tag('div');

    return $html;
}

function import_groups_from_csv($courseid, $file) {
    $csv = readCSV($file);
    $error = array();
    // Create groups in course, unless they exist already
    foreach ($csv as $row) {
        $group = new stdClass();
        $group->courseid = $courseid;
        $group->name = trim(format_text($row['name']));
        $group->maxanswers = intval($row['maxanswers']);
        $group->timemodified = time();
        

        if (($id = groups_get_group_by_name($courseid, $group->name)) != false) {
            echo "Group " . $group->name. " already exists, ignoring";
            $group->id = $id;
        } else {
            if (($id = groups_create_group($group)) != false) {
                $group->id = $id;
            } else {
                push($error, "Could not create group " . $group->name);
            }
        }
    }
    
    
    return (count($error) > 0) ? $error : null;
    
    
    
}

function import_assignments_from_csv($cm, $file) {
    
}


?>
