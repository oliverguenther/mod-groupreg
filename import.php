<?php

require_once("../../config.php");
require_once("lib.php");
require_once("renderer.php");

$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

$url = new moodle_url('/mod/groupreg/import.php', array('id' => $id, 'action' => $action));
$PAGE->set_url($url);

if (!$cm = get_coursemodule_from_id('groupreg', $id)) {
    print_error("invalidcoursemodule");
}
if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error("coursemisconf");
}

require_login($course->id, false, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/groupreg:performassignment', $context);

if (!$choice = groupreg_get_groupreg($cm->instance)) {
    print_error('invalidcoursemodule');
}


if (isset($_FILES['csvupload'])) {
    // User has returned from CSV upload form
    if ($action == 'importgroups') {
        // handle CSV file for settings import
    } else if ($action == 'importassignments') {
        // handle CSV file for assignments import
    }
} else {
    // Show upload form
    // Output the confirmation form

    $PAGE->set_title(format_string($choice->name) . ': ' . get_string($action, 'groupreg'));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    $renderer = $PAGE->get_renderer('mod_groupreg');

    echo $renderer->display_import_assignment_form($cm, $action);

    echo $OUTPUT->footer();
}