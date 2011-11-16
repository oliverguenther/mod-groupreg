<?php

require_once("../../config.php");
require_once("lib.php");
require_once("csvlib.php");

$id = required_param('id', PARAM_INT);
$action = optional_param('action', null, PARAM_ALPHA);
$confirmed = optional_param("doimport", null, PARAM_INT);
$csvfile = optional_param("file", null, PARAM_RAW);

$url = new moodle_url('/mod/groupreg/import.php', array('id' => $id, 'action' => $action));
$PAGE->set_url($url);

if (!$cm = get_coursemodule_from_id('groupreg', $id)) {
    print_error("invalidcoursemodule");
}
if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error("coursemisconf");
}

if (!$choice = groupreg_get_groupreg($cm->instance)) {
    print_error('invalidcoursemodule');
}

require_login($course->id, false, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/groupreg:performassignment', $context);

if (!$choice = groupreg_get_groupreg($cm->instance)) {
    print_error('invalidcoursemodule');
}

$PAGE->set_title(format_string($choice->name) . ': ' . get_string($action, 'groupreg'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

function show_errors($errors) {
	global $OUTPUT;

	$display_errors = array();
	foreach($errors as $error) {
		$chunks = explode('::', $error);
		
		if (sizeof($chunks) == 1)
			$text = get_string($chunks[0], 'groupreg');
		else
			$text = get_string($chunks[0], 'groupreg', $chunks[1]);
		
		$display_errors[] = $text;
	}
	
	echo $OUTPUT->notification(implode("<br/>", $display_errors));
}

// Get Groupreg renderer
$renderer = $PAGE->get_renderer('mod_groupreg');
if (isset($confirmed) && isset($csvfile)) {
    // Returned from confirmation page, import actual file
    if ($action == 'importassignments')
        $error = import_assignments_from_csv($choice, $csvfile, $cm);
    else {
        echo $OUTPUT->notification("Error: Unknown action!");
        exit;
    }
    
    if (isset($error)) {
        show_errors($error);
    } else {
        // successful
        // TODO translate
        echo $OUTPUT->notification(get_string('csv-import-success', 'groupreg'));
    }
    
} else if (!isset($_FILES['csvupload']) || $_FILES['csvupload']['size'] == 0) {
    // Output the confirmation form
    echo $renderer->display_import_assignment_form($cm, $action);
} else {
    if ($_FILES['csvupload']['error'] > 0) {
        echo $OUTPUT->notification("Error: " . $_FILES["csvupload"]["error"]);
        echo $renderer->display_import_assignment_form($cm, $action);
    } else {
        // Move uploaded file, display contents to verify
        $filedest = tempnam("/tmp", "groupreg-importcsv");
        move_uploaded_file($_FILES["csvupload"]["tmp_name"], $filedest);
        
		// Verify contents, print result
        $csv = readCSV($filedest, false, ';');
        $errors = verifyCSV($csv, $action, $choice, $cm);
        if (isset($errors) && is_array($errors) && sizeof($errors) > 0) {
            //echo $OUTPUT->notification(implode("<br/>", $errors));
			show_errors($errors);
			echo $renderer->display_import_assignment_form($cm, $action);
        } else {
            // Verified, print table and continue
            echo display_csv_contents($cm, $filedest, $action, ';');
        }
    }
}

echo $OUTPUT->footer();
