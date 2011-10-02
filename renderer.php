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
		$html .= "<h3>".get_string("yourselection", "groupreg")."</h3><ul>";
		
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
        
        $html .= "<h3>".get_string("groupmembers2", "groupreg")."</h3><ul>";
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
        
        // get all users who registered together with the user
        $usernames = array();
        if (isset($options['usergroup'])) {
            $dbanswers = $DB->get_records('groupreg_answers', array('usergroup' => $options['usergroup']));
            if ($dbanswers) foreach($dbanswers as $answer) {
                if ($answer->userid == $USER->id)
                    continue;
                    
                $otheruser = $DB->get_record('user', array('id' => $answer->userid));
                if ($otheruser && !in_array($otheruser->username, $usernames))
                    $usernames[] = $otheruser->username;
            }
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
        $groupmemberno = $groupreg->groupmembers; // change to be dynamic, if required
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
                } else if (sizeof($usernames) > 0) {
                    $attributes['value'] = array_pop($usernames);
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
    public function display_result($course, $groupreg, $response_data) {
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
        
        $html = html_writer::tag('h3', get_string('responses', 'groupreg'));
        
        $html .= html_writer::start_tag('table');
        
        // header row
        $html .= html_writer::start_tag('tr');
        $html .= html_writer::tag('th', get_string('option', 'groupreg'));
        for ($i = 0; $i <= $groupreg->limitfavorites; $i++) {
            $html .= html_writer::tag('th', get_string('favorite_n', 'groupreg', $i+1), array('class' => 'groupreg-favorite'));
        }
        $html .= html_writer::tag('th', get_string('blanks', 'groupreg'), array('class' => 'groupreg-blank'));
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
            $html .= html_writer::end_tag('tr');
        }
        
        $html .= html_writer::end_tag('table');
        
        return $html;        
    }

}

