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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_groupreg_activity_task
 */

/**
 * Define the complete groupreg structure for backup, with file and id annotations
 */
class backup_groupreg_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $groupreg = new backup_nested_element('groupreg', array('id'), array(
            'name', 'intro', 'introformat', 'publish',
            'showresults', 'display', 'allowupdate', 'showunanswered',
            'limitanswers', 'limitfavorites', 'limitblanks', 'timeopen', 'timeclose', 'timemodified',
            'completionsubmit', 'assigned', 'groupmembers'));

        $options = new backup_nested_element('options');

        $option = new backup_nested_element('option', array('id'), array(
            'text', 'maxanswers', 'timemodified'));

        $answers = new backup_nested_element('answers');

        $answer = new backup_nested_element('answer', array('id'), array(
            'userid', 'optionid', 'timemodified', 'preference', 'usergroup'));
			
		$assignments = new backup_nested_element('assignments');

        $assigned = new backup_nested_element('assigned', array('id'), array(
            'userid', 'optionid', 'timeassigned'));

        // Build the tree
        $groupreg->add_child($options);
        $options->add_child($option);

        $groupreg->add_child($answers);
        $answers->add_child($answer);
		
		$groupreg->add_child($assignments);
		$assignments->add_child($assigned);

        // Define sources
        $groupreg->set_source_table('groupreg', array('id' => backup::VAR_ACTIVITYID));

        $option->set_source_sql('
            SELECT *
              FROM {groupreg_options}
             WHERE groupregid = ?',
            array(backup::VAR_PARENTID));

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $answer->set_source_table('groupreg_answers', array('groupregid' => '../../id'));
			$assigned->set_source_table('groupreg_assigned', array('groupregid' => '../../id'));
        }

        // Define id annotations
        $answer->annotate_ids('user', 'userid');
		$assigned->annotate_ids('user', 'userid');
		$option->annotate_ids('group', 'text');

        // Define file annotations
        $groupreg->annotate_files('mod_groupreg', 'intro', null); // This file area hasn't itemid

        // Return the root element (groupreg), wrapped into standard activity structure
        return $this->prepare_activity_structure($groupreg);
    }
}
