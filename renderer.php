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
 * Moodle renderer used to display special elements of the lesson module
 *
 * @package   groupreg
 * @copyright 2011 onwards Olexandr Savchuk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

class mod_groupreg_renderer extends plugin_renderer_base {

	/**
	 * Returns HTML to display the choice results for a single user
	 * @global type $DB
	 * @global type $OUTPUT
	 * @param type $course
	 * @param type $groupreg
	 * @param type $current DB records from groupreg_answers to display
	 */
	public function display_current_choice($course, $groupreg, $current) {
		global $DB, $OUTPUT, $USER;
		$html = '';
		
		// fetch all group names from this course
		$groupnames = array();
        $db_groups = $DB->get_records('groups', array('courseid' => $course->id));
        foreach ($db_groups as $group)
            $groupnames[$group->id] = $group->name;
		
		// fetch options and map option IDs to group IDs
		$groupids = array();
		$db_options = $DB->get_records('groupreg_options', array('groupregid' => $groupreg->id));
		foreach($db_options as $option)
			$groupids[$option->id] = intval($option->text);
		
		// map group names to every given answer
		$favorites = array();
		$blanks = array();
        $usergroup = 0;
		foreach ($current as $answer) {
            $usergroup = $answer->usergroup;
			if ($answer->preference > 0)
				$favorites[$answer->preference] = $groupnames[$groupids[$answer->optionid]];
			else
				$blanks[] = $groupnames[$groupids[$answer->optionid]];
		}
		
        // display final results, if available
        if ($groupreg->assigned == 1) {
            if ($result = $DB->get_record('groupreg_assigned', array('groupregid' => $groupreg->id, 'userid' => $USER->id))) {
                $groupname = $groupnames[$groupids[$result->optionid]];
                $html .= get_string("assignment_result", "groupreg", $groupname)."<br><br>";
            } else {
                // assignment complete, but no result available
                $html .= get_string("assignment_no_result", "groupreg")."<br><br>";
            }
        } 
        
		// display header
		$html .= "<h2>".get_string("yourselection", "groupreg")."</h2><ul>";
		
		// display favorites
		for ($i = 0; $i <= $groupreg->limitfavorites; $i++) {
			if (isset($favorites[$i+1])) {
				$html .= "<li>".get_string('favorite_n', 'groupreg', $i+1).": <span class='groupreg-favorite'>".$favorites[$i+1]."</span></li>";
			} else {
				$html .= "<li>".get_string('favorite_n', 'groupreg', $i+1).": <i>".get_string('no_choice', 'groupreg')."</i></li>";
			}
		}
		
		// display blanks
		for ($i = 0; $i <= $groupreg->limitblanks; $i++) {
			if (isset($blanks[$i])) {
				$html .= "<li>".get_string('blank_n', 'groupreg', $i+1).": <span class='groupreg-blank'>".$blanks[$i]."</span></li>";
			}
		}
        
        $html .= "</ul>";
		
        // display group names
        
        // get all users who registered together with the user
        $usernames = array();
        $full_usernames = array($USER->firstname.' '.$USER->lastname);
        $dbanswers = $DB->get_records('groupreg_answers', array('usergroup' => $usergroup));
        if ($dbanswers) foreach($dbanswers as $answer) {
            if ($answer->userid == $USER->id)
                continue;
                
            $otheruser = $DB->get_record('user', array('id' => $answer->userid));
            if ($otheruser && !in_array($otheruser->username, $usernames)) {
                $usernames[] = $otheruser->username;
                $full_usernames[] = $otheruser->firstname.' '.$otheruser->lastname;
            }
        }
        
        $html .= "<h2>".get_string("groupmembers2", "groupreg")."</h2><ul>";
        foreach($full_usernames as $name) {
            $html .= "<li>$name</li>";
        }
		$html .= "</ul>";
		
		
		return '<div>'.$html.'</div>';
	}
	
    /**
     * Returns HTML to display the choice form with all available options for the user
     * @param object $options
     * @param int  $coursemoduleid
     * @param bool $vertical
     * @return string
     */
    public function display_options($course, $groupreg, $options, $coursemoduleid, $vertical = true) {
        global $DB, $USER;
        $target = new moodle_url('/mod/groupreg/view.php');
        $attributes = array('method'=>'POST', 'action'=>$target);

        $html = html_writer::start_tag('form', $attributes);
        
        // get all group names
        $groups = array();
        $db_groups = $DB->get_records('groups', array('courseid' => $course->id));
        foreach ($db_groups as $group) {
            $groups[$group->id] = $group->name;
        }
        
        // favorite choices
        $html .= html_writer::tag('h3', get_string('favorites', 'groupreg'), array());
        $html .= html_writer::tag('p', get_string('favorites_desc', 'groupreg'), array());
        $html .= html_writer::start_tag('table', array('class'=>'groupregs' ));
        for ($i = 0; $i <= $groupreg->limitfavorites; $i++) {
			$preference = $i+1;
            $html .= html_writer::start_tag('tr', array('class'=>'option'));
            
            $html .= html_writer::tag('td', get_string('favorite_n', 'groupreg', $preference).':', array());
            
            $html .= html_writer::start_tag('td', array());
            $html .= html_writer::start_tag('select', array('name' => "favs[$i]"));
            $html .= html_writer::tag('option', get_string('no_choice', 'groupreg'), array('value' => 0));
            foreach ($options['options'] as $option) {
                $groupname = $groups[$option->groupid];
                
				if ($option->maxanswers > 0) $max = $option->maxanswers;
                else $max = "&#8734;";
				
				$attributes = array('value' => $option->optionid);
				if ($option->checked && $preference == $option->preference)
					$attributes['selected'] = true;
				
                $html .= html_writer::tag('option', $groupname.' ('.$max.')', $attributes);
            }
            $html .= html_writer::end_tag('select');
            $html .= html_writer::end_tag('td');
            
            $html .= html_writer::end_tag('tr');
        }
        $html .= html_writer::end_tag('table');
        
        // blank choices
        $html .= html_writer::tag('h3', get_string('blanks', 'groupreg'), array());
        $html .= html_writer::tag('p', get_string('blanks_desc', 'groupreg'), array());
        $html .= html_writer::start_tag('table', array('class'=>'groupregs' ));
        for ($i = 0; $i <= $groupreg->limitblanks; $i++) {
			$blank_shown = false; // whether this blank field already displays one chosen blank option
            
			$html .= html_writer::start_tag('tr', array('class'=>'option'));
            
            $html .= html_writer::tag('td', get_string('blank_n', 'groupreg', $i+1).':', array());
            
            $html .= html_writer::start_tag('td', array());
            $html .= html_writer::start_tag('select', array('name' => "blanks[$i]"));
            $html .= html_writer::tag('option', get_string('no_choice', 'groupreg'), array('value' => 0));
            foreach ($options['options'] as $option) {
                $groupname = $groups[$option->groupid];
				
                if ($option->maxanswers > 0) $max = $option->maxanswers;
                else $max = "&#8734;";
				
				$attributes = array('value' => $option->optionid);
				if (!$blank_shown && $option->checked && $option->preference == 0 && !$option->displayed) {
					$option->displayed = true; // dont display this blank option in following fields
					$blank_shown = true; // dont display any other blank option in this field
					$attributes['selected'] = true;
				}
				
                $html .= html_writer::tag('option', $groupname.' ('.$max.')', $attributes);
            }
            $html .= html_writer::end_tag('select');
            $html .= html_writer::end_tag('td');
            
            $html .= html_writer::end_tag('tr');
        }
        
        $html .= html_writer::end_tag('table');
        
        // group members
        $groupmemberno = $groupreg->groupmembers;
        if ($groupmemberno > 1) {
            $html .= html_writer::tag('h3', get_string('groupmembers2', 'groupreg'), array());
            $html .= html_writer::tag('p', get_string('groupmembers2_desc', 'groupreg'), array());
            $html .= html_writer::start_tag('table', array('class'=>'groupregs' ));
            for ($i = 0; $i < $groupmemberno; $i++) {
                $html .= html_writer::start_tag('tr', array('class'=>'option'));
                
                $html .= html_writer::tag('td', get_string('groupmember_n', 'groupreg', $i+1).':', array());
                $html .= html_writer::start_tag('td', array());
                $attributes = array('type' => 'text', 'name' => "groupmembers[$i]");
                if ($i == 0) {
                    $attributes['disabled'] = 'disabled';
                    $attributes['value'] = $USER->username;
                } else if (sizeof($options['groupmembers']) > 0) {
                    $attributes['value'] = array_pop($options['groupmembers']);
                }
                $html .= html_writer::empty_tag('input', $attributes);            
                $html .= html_writer::end_tag('td');
                $html .= html_writer::end_tag('tr');
            }
            $html .= html_writer::end_tag('table');
        } else {
            $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'groupmembers[0]', 'value' => $USER->username, 'disabled' => 'disabled'));       
        }
                
        
        // form footer
        $html .= html_writer::tag('div', '', array('class'=>'clearfloat'));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey()));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=>$coursemoduleid));

        if (!empty($options['hascapability']) && ($options['hascapability'])) {
            $html .= html_writer::empty_tag('input', array('type'=>'submit', 'value'=>get_string('savemygroupreg','groupreg'), 'class'=>'button'));
           
            if (!empty($options['allowupdate']) && ($options['allowupdate'])) {
                $url = new moodle_url('view.php', array('id'=>$coursemoduleid, 'action'=>'delgroupreg', 'sesskey'=>sesskey()));
                $html .= html_writer::link($url, get_string('removemygroupreg','groupreg'));
            }
        } else {
            $html .= html_writer::tag('div', get_string('havetologin', 'groupreg'));
        }
        
        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * Returns HTML to display groupregs result
     * @param object $response_data matrix: $response_data[optionid][preference] = array(userids)
     * @param bool $forcepublish
     * @return string
     */
    public function display_result($course, $groupreg, $response_data, $cm) {
        global $DB;
        
        // fetch all group names from this course
		$groups = array();
        $db_groups = $DB->get_records('groups', array('courseid' => $course->id));
        foreach ($db_groups as $group)
            $groups[$group->id] = $group->name;
		
		// fetch options and map option IDs to group names
		$groupnames = array();
		$db_options = $DB->get_records('groupreg_options', array('groupregid' => $groupreg->id));
		foreach($db_options as $option)
			$groupnames[$option->id] = $groups[intval($option->text)];
        
        $html = html_writer::tag('h2', get_string('responses', 'groupreg'));
        
        $html .= html_writer::start_tag('table');
        
        // header row
        $html .= html_writer::start_tag('tr');
        $html .= html_writer::tag('th', get_string('option', 'groupreg'));
        for ($i = 0; $i <= $groupreg->limitfavorites; $i++) {
            $html .= html_writer::tag('th', get_string('favorite_n', 'groupreg', $i+1), array('class' => 'groupreg-favorite'));
        }
        $html .= html_writer::tag('th', get_string('blanks', 'groupreg'), array('class' => 'groupreg-blank'));
        $html .= html_writer::empty_tag('th');
        $html .= html_writer::end_tag('tr');
        
        // data
        foreach ($response_data as $option_id => $option_data) {
            $html .= html_writer::start_tag('tr');
            $html .= html_writer::tag('td', $groupnames[$option_id]);
            for ($i = 0; $i <= $groupreg->limitfavorites; $i++) {
                $number = isset($option_data[$i+1]) ? $option_data[$i+1] : 0;
                $html .= html_writer::tag('td', $number, array('style' => 'text-align: center;', 'class' => 'groupreg-favorite'));
            }
            $number = isset($option_data[0]) ? $option_data[0] : 0;
            
            $html .= html_writer::tag('td', $number, array('style' => 'text-align: center;', 'class' => 'groupreg-blank'));
            
            $url = new moodle_url('report.php', array('id'=>$cm->id, 'action'=>'groupdetails', 'optionid'=>$option_id));
            $html .= html_writer::tag('td', html_writer::link($url, get_string('show_group_details', 'groupreg')));
            
            $html .= html_writer::end_tag('tr');
        }
        
        $html .= html_writer::end_tag('table');
        
        return $html;        
    }
    
    function display_option_result($course, $cm, $group, $groupmembers, $groupassignments) {
        global $CFG;
        
        if ($groupassignments) {
            $html .= html_writer::tag('h2', get_string('group_assignments', 'groupreg', $group->name));
            $html .= html_writer::start_tag('ul');
            foreach($groupassignments as $member) {
                $url = new moodle_url('report.php', array('id'=>$cm->id, 'action'=>'userdetails', 'userid'=>$member->id));
                $html .= html_writer::tag('li', html_writer::link($url, $member->firstname.' '.$member->lastname.' ('.$member->username.')'));
            }
            $html .= html_writer::end_tag('ul');
        }
        
        $html .= html_writer::tag('h2', get_string('group_details', 'groupreg', $group->name));
        
        $html .= html_writer::start_tag('table');
        
        $html .= html_writer::start_tag('tr');
        $html .= html_writer::tag('th', get_string('user'));
        $html .= html_writer::tag('th', get_string('preference', 'groupreg'));
        $html .= html_writer::end_tag('tr');
        
        foreach($groupmembers as $member) {
            $html .= html_writer::start_tag('tr');
            
            //$url = new moodle_url($CFG->wwwroot.'/user/view.php', array('id'=>$member->id, 'course'=>$course->id));
            $url = new moodle_url('report.php', array('id'=>$cm->id, 'action'=>'userdetails', 'userid'=>$member->id));
            $html .= html_writer::tag('td', html_writer::link($url, $member->firstname.' '.$member->lastname.' ('.$member->username.')'));
            
            if ($member->preference > 0)
                $html .= html_writer::tag('td', get_string('favorite_n', 'groupreg', $member->preference));
            else
                $html .= html_writer::tag('td', get_string('blank', 'groupreg'));
                
            $html .= html_writer::end_tag('tr');
        }
        
        $html .= html_writer::end_tag('table');
        
        return $html;
    }
    
	function display_user_result($course, $cm, $user, $choices, $assignment, $groupmembers) {
        global $CFG;
        
        $html = html_writer::start_tag('div', array('class' => 'groupreg-report-section'));
        $html .= html_writer::tag('h2', get_string('user_details', 'groupreg', $user->firstname.' '.$user->lastname));
		
        // each single answer counts as a group, so don't display these
        if ($groupmembers && count($groupmembers) > 1) {
                $html .= html_writer::tag('p', get_string('enrolled_with', 'groupreg'));
                        $html .= html_writer::start_tag('table');        
                        $html .= html_writer::start_tag('tr');
                        
                        $html .= html_writer::tag('th', get_string('groupmembers', 'groupreg'));
                        $html .= html_writer::tag('th', 'Username');
                        
                        $html .= html_writer::end_tag('tr');
                foreach($groupmembers as $member) {
                        $html .= html_writer::start_tag('tr');
                        $html .= html_writer::tag('td', $member->firstname . " " . $member->lastname);
                        $html .= html_writer::tag('td', $member->username);
                        $html .= html_writer::end_tag('tr');                        
                }
                $html .= html_writer::end_tag('table');
        }
			
        if ($assignment) {
            $html .= get_string("assignment_result", "groupreg", $assignment->name)."<br><br>";
        }
        
        $html .= html_writer::start_tag('table');
        
        $html .= html_writer::start_tag('tr');
        $html .= html_writer::tag('th', get_string('option', 'groupreg'));
        $html .= html_writer::tag('th', get_string('preference', 'groupreg'));
        $html .= html_writer::end_tag('tr');
        
        foreach($choices as $choice) {
            $html .= html_writer::start_tag('tr');
            
            $url = new moodle_url('report.php', array('id'=>$cm->id, 'action'=>'groupdetails', 'optionid'=>$choice->id));
            $html .= html_writer::tag('td', html_writer::link($url, $choice->name));
            
            if ($choice->preference > 0)
                $html .= html_writer::tag('td', get_string('favorite_n', 'groupreg', $choice->preference));
            else
                $html .= html_writer::tag('td', get_string('blank', 'groupreg'));
            
            $html .= html_writer::end_tag('tr');
        }
        
        $html .= html_writer::end_tag('table');
        
        $url = new moodle_url($CFG->wwwroot.'/user/view.php', array('id'=>$user->id, 'course'=>$course->id));
        $html .= html_writer::link($url, get_string('view_profile', 'groupreg'));
        $html .= html_writer::end_tag('div');
        
        return $html;
    }
    
    function display_user_list($course, $cm, $userlist) {
        $html = html_writer::start_tag('div', array('class' => 'groupreg-report-section'));
        $html .= html_writer::start_tag('form', array('action' => 'report.php', 'method' => 'GET'));
         
        $html .= html_writer::tag('p', get_string('report_total_users', 'groupreg', sizeof($userlist)));
        
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cm->id));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'userdetails'));
        $html .= html_writer::start_tag('select', array('name' => 'userid'));
        foreach($userlist as $user) {
            $html .= html_writer::tag('option', $user->firstname.' '.$user->lastname.' ('.$user->username.')', array('value' => $user->id));
        }
        $html .= html_writer::end_tag('select');
        $html .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('display_user_details', 'groupreg')));
        
        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_tag('div');
        
        return $html;
    }
    
    function display_missing_assignments($cm, $users_without_assignment) {
        global $CFG;
        
        $html = html_writer::start_tag('div', array('class' => 'groupreg-report-section'));
        $html .= html_writer::tag('h2', get_string('report_missing_assignments', 'groupreg'));
        $html .= html_writer::tag('p', get_string('report_missing_assignments_text', 'groupreg', sizeof($users_without_assignment)));
        
        $html .= html_writer::start_tag('ul');
        foreach($users_without_assignment as $user) {
            $url = new moodle_url('report.php', array('id'=>$cm->id, 'action'=>'userdetails', 'userid'=>$user->id));
            $html .= html_writer::tag('li', html_writer::link($url, $user->firstname.' '.$user->lastname.' ('.$user->username.')'));
        }
        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_tag('div');

            
        return $html;
    }
    
    function display_missing_votes($course, $cm, $users_without_votes, $assignment_complete) {
        global $CFG;
        
        $html = html_writer::start_tag('div', array('class' => 'groupreg-report-section'));
        $html .= html_writer::tag('h2', get_string('report_missing_votes', 'groupreg'));
        $html .= html_writer::tag('p', get_string('report_missing_votes_text', 'groupreg', sizeof($users_without_votes)));
        
        $html .= html_writer::start_tag('form', array('action' => 'report.php', 'method' => 'GET'));
         
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cm->id));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'assignuser'));
        if ($users_without_votes) {
        $html .= html_writer::start_tag('select', array('name' => 'userid'));
            foreach($users_without_votes as $user) {
                $html .= html_writer::tag('option', $user->firstname.' '.$user->lastname.' ('.$user->username.')', array('value' => $user->id));
            }
            $html .= html_writer::end_tag('select');
        }
        //$html .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('display_user_details', 'groupreg')));
        
        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_tag('div');

        
        return $html;
    }

	function display_export_assignment_form($cm) {
	global $CFG;
		
	$html = html_writer::start_tag('div', array('class' => 'groupreg-export-confirmation'));

        $html .= html_writer::tag('h2', get_string('exportassignment', 'groupreg'));
        $html .= html_writer::tag('p', get_string('exportassignment_confirm', 'groupreg'));
        $html .= html_writer::start_tag('form', array('action' => 'export.php', 'method' => 'POST'));
        
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cm->id));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'download-csv'));
		$html .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('exportassignment', 'groupreg')));
        
        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_tag('div');
        
        return $html;
	}
        
        
        function display_import_assignment_form($cm, $action) {
        global $CFG;
		
	$html = html_writer::start_tag('div', array('class' => 'groupreg-import-csv'));
        $html .= html_writer::start_tag('form', array('action' => 'import.php', 'method' => 'POST', 'enctype' => 'multipart/form-data'));
        
        $html .= html_writer::tag('h2', get_string($action, 'groupreg'));
        $html .= html_writer::tag('p', get_string("$action-confirm", 'groupreg'));

                
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cm->id));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => $action));
        $html .= html_writer::tag('input', '', array('type' => 'file', 'name' => "csvupload"));
	$html .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string("doimport", 'groupreg')));
        
        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_tag('div');
        
        return $html;
            
        }

}

