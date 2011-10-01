<?php

    require_once("../../config.php");
    require_once("lib.php");

    $id         = required_param('id', PARAM_INT);   //moduleid
    $format     = optional_param('format', groupreg_PUBLISH_NAMES, PARAM_INT);
    $download   = optional_param('download', '', PARAM_ALPHA);
    $action     = optional_param('action', '', PARAM_ALPHA);
    $attemptids = optional_param('attemptid', array(), PARAM_INT); //get array of responses to delete.

    $url = new moodle_url('/mod/groupreg/report.php', array('id'=>$id));
    if ($format !== groupreg_PUBLISH_NAMES) {
        $url->param('format', $format);
    }
    if ($download !== '') {
        $url->param('download', $download);
    }
    if ($action !== '') {
        $url->param('action', $action);
    }
    $PAGE->set_url($url);

    if (! $cm = get_coursemodule_from_id('groupreg', $id)) {
        print_error("invalidcoursemodule");
    }

    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error("coursemisconf");
    }

    require_login($course->id, false, $cm);

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    require_capability('mod/groupreg:readresponses', $context);

    if (!$choice = groupreg_get_groupreg($cm->instance)) {
        print_error('invalidcoursemodule');
    }

    $strchoice = get_string("modulename", "groupreg");
    $strchoices = get_string("modulenameplural", "groupreg");
    $strresponses = get_string("responses", "groupreg");

    add_to_log($course->id, "groupreg", "report", "report.php?id=$cm->id", "$choice->id",$cm->id);

    if (data_submitted() && $action == 'delete' && has_capability('mod/groupreg:deleteresponses',$context) && confirm_sesskey()) {
        groupreg_delete_responses($attemptids, $choice, $cm, $course); //delete responses.
        redirect("report.php?id=$cm->id");
    }

    if (!$download) {
        $PAGE->navbar->add($strresponses);
        $PAGE->set_title(format_string($choice->name).": $strresponses");
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        /// Check to see if groups are being used in this groupreg
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode) {
            groups_get_activity_group($cm, true);
            groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/groupreg/report.php?id='.$id);
        }
    } else {
        $groupmode = groups_get_activity_groupmode($cm);
    }
    $users = groupreg_get_response_data($choice, $cm, $groupmode);

    $results = prepare_groupreg_show_results($choice, $course, $cm, $users);
    $renderer = $PAGE->get_renderer('mod_groupreg');
    echo $renderer->display_result($results, has_capability('mod/groupreg:readresponses', $context));
    
    echo $OUTPUT->footer();

