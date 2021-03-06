<?php

    require_once("../../config.php");
    require_once("lib.php");

    $id         = required_param('id', PARAM_INT);   //moduleid
    $format     = optional_param('format', groupreg_PUBLISH_NAMES, PARAM_INT);
    $action     = optional_param('action', '', PARAM_ALPHA);
    
    $url = new moodle_url('/mod/groupreg/report.php', array('id'=>$id));
    if ($format !== groupreg_PUBLISH_NAMES) {
        $url->param('format', $format);
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

    $PAGE->set_title(format_string($choice->name).": $strresponses");
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    
    /// Check to see if groups are being used in this groupreg
    $groupmode = groups_get_activity_groupmode($cm);
    if ($groupmode) {
        groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/groupreg/report.php?id='.$id);
    }
    
    $renderer = $PAGE->get_renderer('mod_groupreg');
    
    // standard matrix output
    $responsedata = groupreg_get_response_data($choice, $cm, $groupmode);
    echo $renderer->display_result($course, $choice, $responsedata, $cm);
    
    $userlist = $DB->get_records_sql('SELECT 
                                            DISTINCT u.id,
                                            u.firstname, 
                                            u.lastname, 
                                            u.username
                                         FROM 
                                            {user} u,
                                            {groupreg_answers} a
                                         WHERE
                                            u.id = a.userid AND
                                            a.groupregid = ? ORDER BY u.lastname ASC', 
                                         array($choice->id));
    if ($userlist) {
        echo $renderer->display_user_list($course, $cm, $userlist);
    }
    
    if ($choice->assigned) {
        // check for users who have not been assigned
        $users_without_assignment = $DB->get_records_sql('SELECT 
                                            DISTINCT u.id,
                                            u.firstname,
                                            u.lastname,
                                            u.username
                                        FROM
                                            {user} u
                                        WHERE 
                                            EXISTS 
                                                (SELECT * FROM {groupreg_answers} WHERE groupregid = ? AND userid = u.id) AND
                                            NOT EXISTS 
                                                (SELECT * FROM {groupreg_assigned} WHERE groupregid = ? AND userid = u.id) ORDER BY u.lastname ASC',
                                        array($choice->id, $choice->id));
        if ($users_without_assignment) {
            echo $renderer->display_missing_assignments($cm, $users_without_assignment);
        }
        
    }
    
    // check additional report actions
    if ($action == 'groupdetails') {
        $optionid = optional_param('optionid', 0, PARAM_INT);
        // get group name
        $group = $DB->get_record_sql('SELECT g.name FROM {groups} g, {groupreg_options} o WHERE o.id = ? AND g.id = o.text', array($optionid));
        // get group answers with user data and preference
        $groupmembers = $DB->get_records_sql('SELECT 
                                                u.id,
                                                u.firstname, 
                                                u.lastname, 
                                                u.username, 
                                                a.preference
                                             FROM 
                                                {user} u,
                                                {groupreg_answers} a
                                             WHERE
                                                a.optionid = ? AND
                                                u.id = a.userid
                                             ORDER BY a.preference, u.lastname, u.firstname', 
                                             array($optionid));
        $groupassignments = $DB->get_records_sql('SELECT
                                                u.id,
                                                u.firstname, 
                                                u.lastname, 
                                                u.username
                                            FROM
                                                {user} u,
                                                {groupreg_assigned} a
                                            WHERE
                                                a.optionid = ? AND
                                                u.id = a.userid
                                            ORDER BY u.lastname, u.firstname', 
                                            array($optionid));
        echo $renderer->display_option_result($course, $cm, $group, $groupmembers, $groupassignments);
    }
    
    if ($action == 'userdetails') {
        $userid = optional_param('userid', 0, PARAM_INT);
        // get general user data
        $user = $DB->get_record('user', array('id' => $userid));
        // get user choices + preferences
        $choices = $DB->get_records_sql('SELECT
                                            g.name,
                                            o.id,
                                            a.preference
                                        FROM
                                            {groups} g,
                                            {groupreg_options} o,
                                            {groupreg_answers} a
                                        WHERE
                                            a.userid = ? AND
                                            a.groupregid = ? AND
                                            o.id = a.optionid AND
                                            g.id = o.text
                                        ORDER BY a.preference', array($userid, $choice->id));   
        $assignment = $DB->get_record_sql('SELECT 
                                            g.name, 
                                            g.id 
                                        FROM 
                                            {groups} g, 
                                            {groupreg_assigned} a,
                                            {groupreg_options} o
                                        WHERE 
                                            a.groupregid = ? AND
                                            a.userid = ? AND
                                            o.id = a.optionid AND
                                            g.id = o.text',
                                            array($choice->id, $userid));
        // Get usergroup id
        $usergroup = $DB->get_record_sql('SELECT DISTINCT
                                    a.usergroup
                                FROM
                                    {groupreg_answers} a
                                WHERE
                                    a.groupregid = ? AND
                                    a.userid = ?',
                                    array($choice->id , $userid));
        // get all member-ids of the belonging group (if any)
        $members = $DB->get_records_sql('SELECT DISTINCT 
                                    u.username,
                                    u.firstname,
                                    u.lastname
                                FROM 
                                    {groupreg_answers} a,
                                    {user} u
                                WHERE 
                                    a.groupregid = ? AND
                                    a.usergroup = ? AND
                                    u.id = a.userid',
                                    array($choice->id, $usergroup->usergroup));
        echo $renderer->display_user_result($course, $cm, $user, $choices, $assignment, $members);
    }

    // Show enrolled users that did not participate (yet)
    $users_without_votes = $DB->get_records_sql("SELECT DISTINCT 
                                    u.id, 
                                    u.username, 
                                    u.firstname, 
                                    u.lastname
                                FROM 
                                    {user} u, 
                                    (
                                        SELECT * FROM
                                        (
                                                SELECT ra.userid
                                                FROM {context} cx
                                                LEFT OUTER JOIN {role_assignments} ra
                                                ON cx.id = ra.contextid AND ra.roleid = '5'
                                                WHERE cx.instanceid = ? AND cx.contextlevel = '50'
                                        ) enr
                                        WHERE enr.userid NOT IN
                                        (
                                            SELECT DISTINCT a.userid
                                            FROM {groupreg_answers} a
                                            WHERE a.groupregid = ?
                                        )
                                    ) nin
                                    WHERE u.id = nin.userid ORDER BY u.lastname ASC",
                                array($course->id, $choice->id));
    
    echo $renderer->display_missing_votes($course, $cm, $users_without_votes, $choice->assigned);
    
    echo $OUTPUT->footer();

