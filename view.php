<?php

    require_once("../../config.php");
    require_once("lib.php");
	require_once($CFG->dirroot.'/group/lib.php'); 
    require_once($CFG->libdir . '/completionlib.php');

    $id         = required_param('id', PARAM_INT);                 // Course Module ID
    $action     = optional_param('action', '', PARAM_ALPHA);
    $attemptids = optional_param('attemptid', array(), PARAM_INT); // array of attempt ids for delete action

    $url = new moodle_url('/mod/groupreg/view.php', array('id'=>$id));
    if ($action !== '') {
        $url->param('action', $action);
    }
	
    $PAGE->set_url($url);
	
	/*
	 * Handling errors of misconfiguration and course access
	 */
    if (! $cm = get_coursemodule_from_id('groupreg', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }

    require_course_login($course, false, $cm);

    if (!$choice = groupreg_get_groupreg($cm->instance)) {
        print_error('invalidcoursemodule');
    }
    
    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
    }
	
    $PAGE->set_title(format_string($choice->name));
    $PAGE->set_heading($course->fullname);
		
    /*
	 * Action: delgroupreg (removing all participation by a user)
	 */
    if ($choice->assigned == 0 and $action == 'delgroupreg' 
            and confirm_sesskey() and is_enrolled($context, NULL, 'mod/groupreg:choose') 
            and $choice->allowupdate) {
        if (groupreg_delete_responses($USER->id, $choice, $cm, $course)) {
            redirect("view.php?id=$cm->id", get_string('deleteok', 'groupreg'));
        }
    }
    
    $favorites = null;
    $blanks = null;
    $groupmembers = null;
    
    /// Mark as viewed
    $completion=new completion_info($course);
    $completion->set_module_viewed($cm);
        
    echo $OUTPUT->header();
   
    /*
	 * Action: data submitted, check and save to DB
	 */
    if ($choice->assigned == 0 and data_submitted() && is_enrolled($context, NULL, 'mod/groupreg:choose') && confirm_sesskey()) {   
        $favorites = optional_param('favs', '', PARAM_RAW);
        $blanks = optional_param('blanks', '', PARAM_RAW);
        $groupmembers = optional_param('groupmembers', '', PARAM_RAW);

		$result = groupreg_user_validate_response($favorites, $blanks, $groupmembers, $choice, $course, $cm, $USER->id);
        if ($result === true) { // everything really really OK, save data and redirect
            groupreg_user_submit_response($favorites, $blanks, $groupmembers, $choice, $course, $cm, $USER->id);
            add_to_log($course->id, "groupreg", "choose", "view.php?id=$cm->id", $choice->id, $cm->id);
            echo $OUTPUT->notification(get_string('groupregsaved', 'groupreg'), 'notifysuccess');
        } else if (is_array($result)) {
            foreach($result as $error) // multiple error messages pre-formatted with data
                echo $OUTPUT->notification($error, 'notifyproblem');
        } else { // single error message as lang key
            echo $OUTPUT->notification(get_string($result, 'groupreg'), 'notifyproblem');
        }
    }
    
    
    /*
     * Action: perform assignment.
     */
    if ($choice->assigned == 0 and $action == 'assign' 
            and confirm_sesskey() 
            and has_capability('mod/groupreg:performassignment', $PAGE->cm->context)) {
        echo $OUTPUT->notification(get_string('performingassignment', 'groupreg'), 'notifyproblem');
        
        if (groupreg_perform_assignment($choice)) {
            echo $OUTPUT->notification(get_string('assignmentok', 'groupreg'), 'notifysuccess');
            add_to_log($course->id, "groupreg", "assign", "view.php?id=$cm->id", $choice->id, $cm->id);
        } else
            echo $OUTPUT->notification(get_string('assignmentproblem', 'groupreg'), 'notifyproblem');
        
    }
    
    /*
     * Action: reset assignment.
     */
    if ($action == 'resetassign' 
            and confirm_sesskey() 
            and has_capability('mod/groupreg:performassignment', $PAGE->cm->context)) {
        if ($choice->assigned == 1) {
            groupreg_reset_assignment($choice);
            add_to_log($course->id, "groupreg", "resetassign", "view.php?id=$cm->id", $choice->id, $cm->id);
            echo $OUTPUT->notification(get_string('resetassignmentok', 'groupreg'), 'notifysuccess');
        } else
            echo $OUTPUT->notification(get_string('assignmentnotdone', 'groupreg'), 'notifyproblem');
    }
    
    /*
     * Action: finalize assignment.
     * Closes the activity, and assigns the users into corresponding moodle groups.
     */
    if ($action == 'finalize' 
            and confirm_sesskey() 
            and has_capability('mod/groupreg:performassignment', $PAGE->cm->context)) {
        if ($choice->assigned == 1) {
            groupreg_finalize_assignment($choice);
            add_to_log($course->id, "groupreg", "finalize", "view.php?id=$cm->id", $choice->id, $cm->id);
            echo $OUTPUT->notification(get_string('finalizeassignmentok', 'groupreg'), 'notifysuccess');
        } else
            echo $OUTPUT->notification(get_string('assignmentnotdone', 'groupreg'), 'notifyproblem');
    }

	/// Display the groupreg and possibly results
    add_to_log($course->id, "groupreg", "view", "view.php?id=$cm->id", $choice->id, $cm->id);

    /// Check to see if groups are being used in this groupreg
    $groupmode = groups_get_activity_groupmode($cm);

    // check and prepare activity group mode if neccessary
    if ($groupmode) {
        groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/groupreg/view.php?id='.$id);
    }
    
    if (has_capability('mod/groupreg:readresponses', $context)) {
        groupreg_show_reportlink($cm);
    }

    echo '<div class="clearer"></div>';

    if ($choice->intro) {
        echo $OUTPUT->box(format_module_intro('groupreg', $choice, $cm->id), 'generalbox', 'intro');
    }

    $current = false;  // Initialise for later
    $renderer = $PAGE->get_renderer('mod_groupreg');
	
	// if user has already made a selection, and they are not allowed to update it, show their selected answers.
	if (isloggedin() && ($current = $DB->get_records('groupreg_answers', array('groupregid' => $choice->id, 'userid' => $USER->id))) 
            && (empty($choice->allowupdate) || ($choice->timeclose > 0 && time() > $choice->timeclose) ) {
		echo $renderer->display_current_choice($course, $choice, $current);
    }

    $groupregopen = true;
    $timenow = time();
    if ($choice->timeclose !=0) {
        if ($choice->timeopen > $timenow ) {
            echo $OUTPUT->box(get_string("notopenyet", "groupreg", userdate($choice->timeopen)), "generalbox notopenyet");
            echo $OUTPUT->footer();
            exit;
        } else if ($timenow > $choice->timeclose) {
            echo $OUTPUT->box(get_string("expired", "groupreg", userdate($choice->timeclose)), "generalbox expired");
            $groupregopen = false;
        }
    }

    if ( (!$current or $choice->allowupdate) and $groupregopen and is_enrolled($context, NULL, 'mod/groupreg:choose')) {
        // They haven't made their choice yet or updates allowed and groupreg is open
        $options = groupreg_prepare_options($choice, $USER, $cm, $favorites, $blanks, $groupmembers);
        echo $renderer->display_options($course, $choice, $options, $cm->id, $choice->display);
        $groupregformshown = true;
    } else {
        $groupregformshown = false;
    }

    if (!$groupregformshown) {
        $sitecontext = get_context_instance(CONTEXT_SYSTEM);

        if (isguestuser()) {
            // Guest account
            echo $OUTPUT->confirm(get_string('noguestchoose', 'groupreg').'<br /><br />'.get_string('liketologin'),
                         get_login_url(), new moodle_url('/course/view.php', array('id'=>$course->id)));
        } else if (!is_enrolled($context)) {
            // Only people enrolled can make a groupreg
            $SESSION->wantsurl = $FULLME;
            $SESSION->enrolcancel = (!empty($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '';

            echo $OUTPUT->box_start('generalbox', 'notice');
            echo '<p align="center">'. get_string('notenrolledchoose', 'groupreg') .'</p>';
            echo $OUTPUT->container_start('continuebutton');
            echo $OUTPUT->single_button(new moodle_url('/enrol/index.php?', array('id'=>$course->id)), get_string('enrolme', 'core_enrol', format_string($course->shortname)));
            echo $OUTPUT->container_end();
            echo $OUTPUT->box_end();

        }
    }

    echo $OUTPUT->footer();

