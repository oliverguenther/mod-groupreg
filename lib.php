<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   groupreg
 * @copyright 2011 onwards Olexandr Savchuk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @global int $groupreg_COLUMN_HEIGHT */
global $groupreg_COLUMN_HEIGHT;
$groupreg_COLUMN_HEIGHT = 300;

/** @global int $groupreg_COLUMN_WIDTH */
global $groupreg_COLUMN_WIDTH;
$groupreg_COLUMN_WIDTH = 300;

define('groupreg_PUBLISH_ANONYMOUS', '0');
define('groupreg_PUBLISH_NAMES',     '1');

define('groupreg_SHOWRESULTS_NOT',          '0');
define('groupreg_SHOWRESULTS_AFTER_ANSWER', '1');
define('groupreg_SHOWRESULTS_AFTER_CLOSE',  '2');
define('groupreg_SHOWRESULTS_ALWAYS',       '3');

define('groupreg_DISPLAY_HORIZONTAL',  '0');
define('groupreg_DISPLAY_VERTICAL',    '1');

/** @global array $groupreg_PUBLISH */
global $groupreg_PUBLISH;
$groupreg_PUBLISH = array (groupreg_PUBLISH_ANONYMOUS  => get_string('publishanonymous', 'groupreg'),
                         groupreg_PUBLISH_NAMES      => get_string('publishnames', 'groupreg'));

/** @global array $groupreg_SHOWRESULTS */
global $groupreg_SHOWRESULTS;
$groupreg_SHOWRESULTS = array (groupreg_SHOWRESULTS_NOT          => get_string('publishnot', 'groupreg'),
                         groupreg_SHOWRESULTS_AFTER_ANSWER => get_string('publishafteranswer', 'groupreg'),
                         groupreg_SHOWRESULTS_AFTER_CLOSE  => get_string('publishafterclose', 'groupreg'),
                         groupreg_SHOWRESULTS_ALWAYS       => get_string('publishalways', 'groupreg'));

/** @global array $groupreg_DISPLAY */
global $groupreg_DISPLAY;
$groupreg_DISPLAY = array (groupreg_DISPLAY_HORIZONTAL   => get_string('displayhorizontal', 'groupreg'),
                         groupreg_DISPLAY_VERTICAL     => get_string('displayvertical','groupreg'));

require_once($CFG->dirroot.'/group/lib.php');

/// Standard functions /////////////////////////////////////////////////////////

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $groupreg
 * @return object|null
 */
function groupreg_user_outline($course, $user, $mod, $groupreg) {
    global $DB;
    if ($answer = $DB->get_record('groupreg_answers', array('groupregid' => $groupreg->id, 'userid' => $user->id))) {
        $result = new stdClass();
        $result->info = "'".format_string(groupreg_get_option_text($groupreg, $answer->optionid))."'";
        $result->time = $answer->timemodified;
        return $result;
    }
    return NULL;
}

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $groupreg
 * @return string|void
 */
function groupreg_user_complete($course, $user, $mod, $groupreg) {
    global $DB;
    if ($answer = $DB->get_record('groupreg_answers', array("groupregid" => $groupreg->id, "userid" => $user->id))) {
        $result = new stdClass();
        $result->info = "'".format_string(groupreg_get_option_text($groupreg, $answer->optionid))."'";
        $result->time = $answer->timemodified;
        echo get_string("answered", "groupreg").": $result->info. ".get_string("updated", '', userdate($result->time));
    } else {
        print_string("notanswered", "groupreg");
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $groupreg
 * @return int
 */
function groupreg_add_instance($groupreg) {
    global $DB;

    $groupreg->timemodified = time();

    if (empty($groupreg->timerestrict)) {
        $groupreg->timeopen = 0;
        $groupreg->timeclose = 0;
    }

    //insert answers
    $groupreg->id = $DB->insert_record("groupreg", $groupreg);
    foreach ($groupreg->option as $key => $value) {
        $value = trim($value);
        if (isset($value) && $value <> '') {
            $option = new stdClass();
            $option->text = $value;
            $option->groupregid = $groupreg->id;
            if (isset($groupreg->limit[$key])) {
                $option->maxanswers = $groupreg->limit[$key];
            }
            $option->timemodified = time();
            $DB->insert_record("groupreg_options", $option);
        }
    }

    return $groupreg->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $groupreg
 * @return bool
 */
function groupreg_update_instance($groupreg) {
    global $DB;

    $groupreg->id = $groupreg->instance;
    $groupreg->timemodified = time();


    if (empty($groupreg->timerestrict)) {
        $groupreg->timeopen = 0;
        $groupreg->timeclose = 0;
    }

    //update, delete or insert answers
    foreach ($groupreg->option as $key => $value) {
        $value = trim($value);
        $option = new stdClass();
        $option->text = $value;
        $option->groupregid = $groupreg->id;
        if (isset($groupreg->limit[$key])) {
            $option->maxanswers = $groupreg->limit[$key];
        }
        $option->timemodified = time();
        if (isset($groupreg->optionid[$key]) && !empty($groupreg->optionid[$key])){//existing groupreg record
            $option->id=$groupreg->optionid[$key];
            if (isset($value) && $value <> '') {
                $DB->update_record("groupreg_options", $option);
            } else { //empty old option - needs to be deleted.
                $DB->delete_records("groupreg_options", array("id"=>$option->id));
            }
        } else {
            if (isset($value) && $value <> '') {
                $DB->insert_record("groupreg_options", $option);
            }
        }
    }

    return $DB->update_record('groupreg', $groupreg);

}

function groupreg_perform_assignment($groupreg) {
    global $CFG, $DB;
    
    $script = $CFG->groupreg_perlscript;
    if ($script == '' || !file_exists($script)) {
        return false;
    }
    
    // preparations
    $groupreg->timeclose = time();
    $groupreg->timemodified = time();
    $groupreg->allowupdate = 0;
    $groupreg->assigned = 1;
    
    exec("perl $script $CFG->prefix $groupreg->id");
    
    // fetch options and map option IDs to group IDs
    $groupids = array();
    $db_options = $DB->get_records('groupreg_options', array('groupregid' => $groupreg->id));
    foreach($db_options as $option)
        $groupids[$option->id] = intval($option->text);
    
    // process data from assignment table
    $option_assignments = $DB->get_records('groupreg_assigned', array('groupregid' => $groupreg->id));
    foreach($option_assignments as $option_assignment) {
        // assign user to the appropriate group
        $group = $groupids[$option_assignment->optionid];
        echo("assigning user $option_assignment->userid to group $group<br>");
        groups_add_member($group, $option_assignment->userid);
    }
    
    $DB->update_record("groupreg", $groupreg);
    
    return true;
}

/**
 * @global object
 * @param object $groupreg
 * @param object $user
 * @param object $coursemodule
 * @return array
 */
function groupreg_prepare_options($groupreg, $user, $coursemodule) {
    global $DB;

    $cdisplay = array('options'=>array());

    $cdisplay['limitanswers'] = true;
    $context = get_context_instance(CONTEXT_MODULE, $coursemodule->id);

    // prefetch answers given by this user
    $dbAnswers = $DB->get_records('groupreg_answers', array('groupregid' => $groupreg->id, 'userid' => $user->id));
    $optionPreferences = array();
    foreach($dbAnswers as $answer) {
        $optionPreferences[$answer->optionid] = $answer->preference;
    }
    
    foreach ($groupreg->option as $optionid => $text) {
        if (isset($text)) { //make sure there are no dud entries in the db with blank text values.
            $option = new stdClass;
            $option->optionid = $optionid;
            $option->groupid = intval($text);
            $option->text = $text;
            $option->maxanswers = $groupreg->maxanswers[$optionid];
            $option->displaylayout = $groupreg->display;

            //if ($DB->record_exists('groupreg_answers', array('groupregid' => $groupreg->id, 'userid' => $user->id, 'optionid' => $optionid))) {
            if (isset($optionPreferences[$optionid])) {
                $option->checked = true;
                $option->preference = $optionPreferences[$optionid];
				$option->displayed = false;
            } else {
				$option->checked = false;
			}
            $cdisplay['options'][] = $option;
        }
    }

    $cdisplay['hascapability'] = is_enrolled($context, NULL, 'mod/groupreg:choose'); //only enrolled users are allowed to make a choice

    if ($groupreg->allowupdate && $DB->record_exists('groupreg_answers', array('groupregid'=> $groupreg->id, 'userid'=> $user->id))) {
        $cdisplay['allowupdate'] = true;
    }

    return $cdisplay;
}

function groupreg_user_validate_response($favorites, $blanks, $groupreg) {
    global $DB;

    // check that at least one favorite is chosen
    if ($favorites[0] <= 0)
        return false;
        
    // check that no entries repeat
    foreach ($favorites as $no => $fav) {
        if ($fav == 0) continue;
        foreach ($favorites as $no2 => $fav2)
            if ($no != $no2 && $fav == $fav2)
                return false;
        foreach ($blanks as $blank)
            if ($fav == $blank)
                return false;
    }   
    foreach ($blanks as $no => $blank) {
        if ($blank == 0) continue;
        foreach ($blanks as $no2 => $blank2)
            if ($no != $no2 && $blank == $blank2)
                return false;
    }
    
    // check that all option IDs are valid
    foreach($favorites as $fav) {
        if ($fav == 0) continue;
        $optid = intval($fav);
        if ($optid == 0 || $DB->count_records('groupreg_options', array('id' => $optid, 'groupregid' => $groupreg->id)) == 0)
            return false;
    }
    foreach($blanks as $blank) {
        if ($blank == 0) continue;
        $optid = intval($blank);
        if ($optid == 0 || $DB->count_records('groupreg_options', array('id' => $optid, 'groupregid' => $groupreg->id)) == 0)
            return false;
    }
        
    return true;
}

/**
 * Submit a response by a user, save it to the database. All old entries by the same user are deleted.
 * 
 * @global object
 * @param object $groupreg
 * @param int $userid
 * @param object $course Course object
 * @param object $cm
 */
function groupreg_user_submit_response($favorites, $blanks, $groupreg, $userid, $course, $cm) {
    
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    
    // remove current user answers from the database
    $DB->delete_records('groupreg_answers', array('groupregid' => $groupreg->id, 'userid' => $userid));
    
    // add the new answers
    for ($fav = 0; $fav <= $groupreg->limitfavorites; $fav++) {
        $favorite = new stdClass();
        $favorite->optionid = intval($favorites[$fav]);
        if ($favorite->optionid <= 0)
            continue;
        $favorite->userid = $userid;
        $favorite->groupregid = $groupreg->id;
        $favorite->timemodified = time();
        $favorite->preference = $fav+1;
        
        $DB->insert_record('groupreg_answers', $favorite);
    }
    
    for ($b = 0; $b <= $groupreg->limitblanks; $b++) {
        $blank = new stdClass();
        $blank->optionid = intval($blanks[$b]);
        if ($blank->optionid <= 0)
            continue;
        $blank->userid = $userid;
        $blank->groupregid = $groupreg->id;
        $blank->timemodified = time();
        $blank->preference = 0;
        
        $DB->insert_record('groupreg_answers', $blank);
    }
}

/**
 * @param array $user
 * @param object $cm
 * @return void Output is echo'd
 */
function groupreg_show_reportlink($cm) {
    echo '<div class="reportlink">';
    echo "<a href=\"report.php?id=$cm->id\">".get_string("viewallresponses", "groupreg")."</a>";
    echo '</div>';
}

/**
 * @global object
 * @param object $groupreg
 * @param object $course
 * @param object $coursemodule
 * @param array $allresponses

 *  * @param bool $allresponses
 * @return object
 */
function prepare_groupreg_show_results($groupreg, $course, $cm, $allresponses, $forcepublish=false) {
    global $CFG, $groupreg_COLUMN_HEIGHT, $FULLSCRIPT, $PAGE, $OUTPUT, $DB;

    $display = clone($groupreg);
    $display->coursemoduleid = $cm->id;
    $display->courseid = $course->id;

    //overwrite options value;
    $display->options = array();
    $totaluser = 0;
    foreach ($groupreg->option as $optionid => $optiontext) {
        $display->options[$optionid] = new stdClass;
        $display->options[$optionid]->text = $optiontext;
        $display->options[$optionid]->maxanswer = $groupreg->maxanswers[$optionid];

        if (array_key_exists($optionid, $allresponses)) {
            $display->options[$optionid]->user = $allresponses[$optionid];
            $totaluser += count($allresponses[$optionid]);
        }
    }
    unset($display->option);
    unset($display->maxanswers);

    $display->numberofuser = $totaluser;
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $display->viewresponsecapability = has_capability('mod/groupreg:readresponses', $context);
    $display->deleterepsonsecapability = has_capability('mod/groupreg:deleteresponses',$context);
    $display->fullnamecapability = has_capability('moodle/site:viewfullnames', $context);

    if (empty($allresponses)) {
        echo $OUTPUT->heading(get_string("nousersyet"));
        return false;
    }


    $totalresponsecount = 0;
    foreach ($allresponses as $optionid => $userlist) {
        if ($groupreg->showunanswered || $optionid) {
            $totalresponsecount += count($userlist);
        }
    }

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    $hascapfullnames = has_capability('moodle/site:viewfullnames', $context);

    $viewresponses = has_capability('mod/groupreg:readresponses', $context);
    switch ($forcepublish) {
        case groupreg_PUBLISH_NAMES:
            echo '<div id="tablecontainer">';
            if ($viewresponses) {
                echo '<form id="attemptsform" method="post" action="'.$FULLSCRIPT.'" onsubmit="var menu = document.getElementById(\'menuaction\'); return (menu.options[menu.selectedIndex].value == \'delete\' ? \''.addslashes_js(get_string('deleteattemptcheck','quiz')).'\' : true);">';
                echo '<div>';
                echo '<input type="hidden" name="id" value="'.$cm->id.'" />';
                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                echo '<input type="hidden" name="mode" value="overview" />';
            }

            echo "<table cellpadding=\"5\" cellspacing=\"10\" class=\"results names\">";
            echo "<tr>";

            $columncount = array(); // number of votes in each column
            if ($groupreg->showunanswered) {
                $columncount[0] = 0;
                echo "<th class=\"col0 header\" scope=\"col\">";
                print_string('notanswered', 'groupreg');
                echo "</th>";
            }
            $count = 1;
            foreach ($groupreg->option as $optionid => $optiontext) {
                $columncount[$optionid] = 0; // init counters
                echo "<th class=\"col$count header\" scope=\"col\">";
                echo format_string($optiontext);
                echo "</th>";
                $count++;
            }
            echo "</tr><tr>";

            if ($groupreg->showunanswered) {
                echo "<td class=\"col$count data\" >";
                // added empty row so that when the next iteration is empty,
                // we do not get <table></table> error from w3c validator
                // MDL-7861
                echo "<table class=\"groupregresponse\"><tr><td></td></tr>";
                if (!empty($allresponses[0])) {
                    foreach ($allresponses[0] as $user) {
                        echo "<tr>";
                        echo "<td class=\"picture\">";
                        echo $OUTPUT->user_picture($user, array('courseid'=>$course->id));
                        echo "</td><td class=\"fullname\">";
                        echo "<a href=\"$CFG->wwwroot/user/view.php?id=$user->id&amp;course=$course->id\">";
                        echo fullname($user, $hascapfullnames);
                        echo "</a>";
                        echo "</td></tr>";
                    }
                }
                echo "</table></td>";
            }
            $count = 1;
            foreach ($groupreg->option as $optionid => $optiontext) {
                    echo '<td class="col'.$count.' data" >';

                    // added empty row so that when the next iteration is empty,
                    // we do not get <table></table> error from w3c validator
                    // MDL-7861
                    echo '<table class="groupregresponse"><tr><td></td></tr>';
                    if (isset($allresponses[$optionid])) {
                        foreach ($allresponses[$optionid] as $user) {
                            $columncount[$optionid] += 1;
                            echo '<tr><td class="attemptcell">';
                            if ($viewresponses and has_capability('mod/groupreg:deleteresponses',$context)) {
                                echo '<input type="checkbox" name="attemptid[]" value="'. $user->id. '" />';
                            }
                            echo '</td><td class="picture">';
                            echo $OUTPUT->user_picture($user, array('courseid'=>$course->id));
                            echo '</td><td class="fullname">';
                            echo "<a href=\"$CFG->wwwroot/user/view.php?id=$user->id&amp;course=$course->id\">";
                            echo fullname($user, $hascapfullnames);
                            echo '</a>';
                            echo '</td></tr>';
                       }
                    }
                    $count++;
                    echo '</table></td>';
            }
            echo "</tr><tr>";
            $count = 1;

            if ($groupreg->showunanswered) {
                echo "<td></td>";
            }

            foreach ($groupreg->option as $optionid => $optiontext) {
                echo "<td align=\"center\" class=\"col$count count\">";
                if ($groupreg->limitanswers) {
                    echo get_string("taken", "groupreg").":";
                    echo $columncount[$optionid];
                    echo "<br/>";
                    echo get_string("limit", "groupreg").":";
                    echo $groupreg->maxanswers[$optionid];
                } else {
                    if (isset($columncount[$optionid])) {
                        echo $columncount[$optionid];
                    }
                }
                echo "</td>";
                $count++;
            }
            echo "</tr>";

            /// Print "Select all" etc.
            if ($viewresponses and has_capability('mod/groupreg:deleteresponses',$context)) {
                echo '<tr><td></td><td>';
                echo '<a href="javascript:select_all_in(\'DIV\',null,\'tablecontainer\');">'.get_string('selectall').'</a> / ';
                echo '<a href="javascript:deselect_all_in(\'DIV\',null,\'tablecontainer\');">'.get_string('deselectall').'</a> ';
                echo '&nbsp;&nbsp;';
                echo html_writer::label(get_string('withselected', 'groupreg'), 'menuaction');
                echo html_writer::select(array('delete' => get_string('delete')), 'action', '', array(''=>get_string('withselectedusers')), array('id'=>'menuaction'));
                $PAGE->requires->js_init_call('M.util.init_select_autosubmit', array('attemptsform', 'menuaction', ''));
                echo '<noscript id="noscriptmenuaction" style="display:inline">';
                echo '<div>';
                echo '<input type="submit" value="'.get_string('go').'" /></div></noscript>';
                echo '</td><td></td></tr>';
            }

            echo "</table></div>";
            if ($viewresponses) {
                echo "</form></div>";
            }
            break;
    }
    return $display;
}

/**
 * Deletes all responses by users listed in $attemptids
 * 
 * @global object
 * @param array $attemptids
 * @param object $groupreg Choice main table row
 * @param object $cm Course-module object
 * @param object $course Course object
 * @return bool
 */
function groupreg_delete_responses($userid, $groupreg, $cm, $course) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $completion = new completion_info($course);
    if ($DB->count_records('groupreg_answers', array('groupregid' => $groupreg->id, 'userid' => $userid)) > 0) {
        $DB->delete_records('groupreg_answers', array('groupregid' => $groupreg->id, 'userid' => $userid));
        // Update completion state
        if ($completion->is_enabled($cm) && $groupreg->completionsubmit) {
            $completion->update_state($cm, COMPLETION_INCOMPLETE, $userid);
        }
        return true;
    }
    
    return false;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id
 * @return bool
 */
function groupreg_delete_instance($id) {
    global $DB;

    if (! $groupreg = $DB->get_record("groupreg", array("id"=>"$id"))) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records("groupreg_answers", array("groupregid"=>"$groupreg->id"))) {
        $result = false;
    }
    
    if (! $DB->delete_records("groupreg_assigned", array("groupregid"=>"$groupreg->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("groupreg_options", array("groupregid"=>"$groupreg->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("groupreg", array("id"=>"$groupreg->id"))) {
        $result = false;
    }

    return $result;
}

/**
 * Returns the users with data in one groupreg
 * (users with records in groupreg_responses, students)
 *
 * @param int $groupregid
 * @return array
 */
function groupreg_get_participants($groupregid) {
    global $DB;

    //Get students
    $students = $DB->get_records_sql("SELECT DISTINCT u.id, u.id
                                 FROM {user} u,
                                      {groupreg_answers} a
                                 WHERE a.groupregid = ? AND
                                       u.id = a.userid", array($groupregid));

    //Return students array (it contains an array of unique users)
    return ($students);
}

/**
 * Returns text string which is the answer that matches the id
 *
 * @global object
 * @param object $groupreg
 * @param int $id
 * @return string
 */
function groupreg_get_option_text($groupreg, $id) {
    global $DB;

    if ($result = $DB->get_record("groupreg_options", array("id" => $id))) {
        return $result->text;
    } else {
        return get_string("notanswered", "groupreg");
    }
}

/**
 * Gets a full groupreg record
 *
 * @global object
 * @param int $groupregid
 * @return object|bool The groupreg or false
 */
function groupreg_get_groupreg($groupregid) {
    global $DB;

    if ($groupreg = $DB->get_record("groupreg", array("id" => $groupregid))) {
        if ($options = $DB->get_records("groupreg_options", array("groupregid" => $groupregid), "id")) {
            foreach ($options as $option) {
                $groupreg->option[$option->id] = $option->text;
                $groupreg->maxanswers[$option->id] = $option->maxanswers;
            }
            return $groupreg;
        }
    }
    return false;
}

/**
 * @return array
 */
function groupreg_get_view_actions() {
    return array('view','view all','report');
}

/**
 * @return array
 */
function groupreg_get_post_actions() {
    return array('choose','choose again');
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the groupreg.
 *
 * @param object $mform form passed by reference
 */
function groupreg_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'groupregheader', get_string('modulenameplural', 'groupreg'));
    $mform->addElement('advcheckbox', 'reset_groupreg', get_string('removeresponses','groupreg'));
}

/**
 * Course reset form defaults.
 *
 * @return array
 */
function groupreg_reset_course_form_defaults($course) {
    return array('reset_groupreg'=>1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * groupreg responses for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function groupreg_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'groupreg');
    $status = array();

    if (!empty($data->reset_groupreg)) {
        $groupregssql = "SELECT ch.id
                       FROM {groupreg} ch
                       WHERE ch.course=?";

        $DB->delete_records_select('groupreg_answers', "groupregid IN ($groupregssql)", array($data->courseid));
        $DB->delete_records_select('groupreg_assigned', "groupregid IN ($groupregssql)", array($data->courseid));
        $status[] = array('component'=>$componentstr, 'item'=>get_string('removeresponses', 'groupreg'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('groupreg', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $groupreg
 * @param object $cm
 * @param int $groupmode
 * @return array
 */
function groupreg_get_response_data($groupreg, $cm, $groupmode) {
    global $CFG, $USER, $DB;

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

/// Get the current group
    if ($groupmode > 0) {
        $currentgroup = groups_get_activity_group($cm);
    } else {
        $currentgroup = 0;
    }

/// Initialise the returned array, which is a matrix:  $allresponses[optionid][preference] = number
    $allresponses = array();

/// First get all the users who have access here
/// To start with we assume they are all "unanswered" then move them later
    $users = get_enrolled_users($context, 'mod/groupreg:choose', $currentgroup, user_picture::fields('u', array('idnumber')), 'u.lastname ASC,u.firstname ASC');
        
/// Get all the recorded responses for this groupreg
    $rawresponses = $DB->get_records('groupreg_answers', array('groupregid' => $groupreg->id));

/// Use the responses to move users into the correct column

    if ($rawresponses) {
        foreach ($rawresponses as $response) {
            if (isset($users[$response->userid])) {   // This person is enrolled and in correct group
                if (!isset($allresponses[$response->optionid]))
                    $allresponses[$response->optionid] = array();
                    
                if (!isset($allresponses[$response->optionid][$response->preference]))
                    $allresponses[$response->optionid][$response->preference] = 0;
                
                $allresponses[$response->optionid][$response->preference]++;
            }
        }
    }
    /*echo('<pre>');
    print_r($allresponses);
    echo('</pre>');*/
    return $allresponses;
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function groupreg_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function groupreg_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $groupregnode The node to add module settings to
 */
function groupreg_extend_settings_navigation(settings_navigation $settings, navigation_node $groupregnode) {
    global $PAGE;

    if (has_capability('mod/groupreg:readresponses', $PAGE->cm->context)) {
        $groupregnode->add(get_string("viewallresponses", "groupreg"), new moodle_url('/mod/groupreg/report.php', array('id'=>$PAGE->cm->id)));
    }
    
    if (has_capability('mod/groupreg:performassignment', $PAGE->cm->context)) {
        $groupregnode->add(get_string("performassignment", "groupreg"), new moodle_url('/mod/groupreg/view.php', array('id'=>$PAGE->cm->id, 'action'=>'assign', 'sesskey'=>sesskey())));
    }
}

/**
 * Obtains the automatic completion state for this groupreg based on any conditions
 * in forum settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function groupreg_get_completion_state($course, $cm, $userid, $type) {
    global $CFG,$DB;

    // Get groupreg details
    $groupreg = $DB->get_record('groupreg', array('id'=>$cm->instance), '*',
            MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false
    if($groupreg->completionsubmit) {
        return $DB->record_exists('groupreg_answers', array(
                'groupregid'=>$groupreg->id, 'userid'=>$userid));
    } else {
        // Completion option is not enabled so just return $type
        return $type;
    }
}
