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

    $strchoice = get_string('modulename', 'groupreg');
    $strchoices = get_string('modulenameplural', 'groupreg');

    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
    }

    if ($action == 'delgroupreg' and confirm_sesskey() and is_enrolled($context, NULL, 'mod/groupreg:choose') and $choice->allowupdate) {
        if ($answer = $DB->get_record('groupreg_answers', array('groupregid' => $choice->id, 'userid' => $USER->id))) {
            $old_option = $DB->get_record('groupreg_options', array('id' => $answer->optionid));
            groups_remove_member($old_option->text, $USER->id);
            $DB->delete_records('groupreg_answers', array('id' => $answer->id));

            // Update completion state
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && $choice->completionsubmit) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE);
            }
        }
    }

    $PAGE->set_title(format_string($choice->name));
    $PAGE->set_heading($course->fullname);

/// Submit any new data if there is any
    if (data_submitted() && is_enrolled($context, NULL, 'mod/groupreg:choose') && confirm_sesskey()) {
        $timenow = time();
        if (has_capability('mod/groupreg:deleteresponses', $context)) {
            if ($action == 'delete') { //some responses need to be deleted
                groupreg_delete_responses($attemptids, $choice, $cm, $course); //delete responses.
                redirect("view.php?id=$cm->id");
            }
        }
        $answer = optional_param('answer', '', PARAM_INT);

        if (empty($answer)) {
            redirect("view.php?id=$cm->id", get_string('mustchooseone', 'groupreg'));
        } else {
            groupreg_user_submit_response($answer, $choice, $USER->id, $course, $cm);
        }
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('groupregsaved', 'groupreg'),'notifysuccess');
    } else {
        echo $OUTPUT->header();
    }


/// Display the groupreg and possibly results
    add_to_log($course->id, "groupreg", "view", "view.php?id=$cm->id", $choice->id, $cm->id);

    /// Check to see if groups are being used in this groupreg
    $groupmode = groups_get_activity_groupmode($cm);

    if ($groupmode) {
        groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/groupreg/view.php?id='.$id);
    }
    $allresponses = groupreg_get_response_data($choice, $cm, $groupmode);   // Big function, approx 6 SQL calls per user


    if (has_capability('mod/groupreg:readresponses', $context)) {
        groupreg_show_reportlink($allresponses, $cm);
    }

    echo '<div class="clearer"></div>';

    if ($choice->intro) {
        echo $OUTPUT->box(format_module_intro('groupreg', $choice, $cm->id), 'generalbox', 'intro');
    }

    $current = false;  // Initialise for later
    //if user has already made a selection, and they are not allowed to update it, show their selected answer.
    if (isloggedin() && ($current = $DB->get_record('groupreg_answers', array('groupregid' => $choice->id, 'userid' => $USER->id))) &&
        empty($choice->allowupdate) ) {
        echo $OUTPUT->box(get_string("yourselection", "groupreg", userdate($choice->timeopen)).": ".format_string(groupreg_get_option_text($choice, $current->optionid)), 'generalbox', 'yourselection');
    }

/// Print the form
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
    // They haven't made their groupreg yet or updates allowed and groupreg is open

        $options = groupreg_prepare_options($choice, $USER, $cm, $allresponses);
        $renderer = $PAGE->get_renderer('mod_groupreg');
        echo $renderer->display_options($options, $cm->id, $choice->display);
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

    // print the results at the bottom of the screen
    if ( $choice->showresults == groupreg_SHOWRESULTS_ALWAYS or
        ($choice->showresults == groupreg_SHOWRESULTS_AFTER_ANSWER and $current) or
        ($choice->showresults == groupreg_SHOWRESULTS_AFTER_CLOSE and !$groupregopen)) {

        if (!empty($choice->showunanswered)) {
            $choice->option[0] = get_string('notanswered', 'groupreg');
            $choice->maxanswers[0] = 0;
        }
        $results = prepare_groupreg_show_results($choice, $course, $cm, $allresponses);
        $renderer = $PAGE->get_renderer('mod_groupreg');
        echo $renderer->display_result($results);

    } else if (!$groupregformshown) {
        echo $OUTPUT->box(get_string('noresultsviewable', 'groupreg'));
    }

    echo $OUTPUT->footer();

/// Mark as viewed
    $completion=new completion_info($course);
    $completion->set_module_viewed($cm);
