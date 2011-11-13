<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_groupreg_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $groupreg_SHOWRESULTS, $groupreg_PUBLISH, $groupreg_DISPLAY, $DB, $COURSE;

        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('groupregname', 'groupreg'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor(true, get_string('chatintro', 'chat'));

        $mform->addElement('hidden', 'assigned', '', '0');
        
//-------------------------------------------------------------------------------
        $groups = array('' => get_string('choosegroup', 'groupreg'));
        $db_groups = $DB->get_records('groups', array('courseid' => $COURSE->id));
        foreach ($db_groups as $group) {
            $groups[$group->id] = $group->name;
        }
        
        $repeatarray = array();
        $repeatarray[] = &MoodleQuickForm::createElement('header', '', get_string('option','groupreg').' {no}');
        $repeatarray[] = &MoodleQuickForm::createElement('select', 'option', get_string('option','groupreg'), $groups);
        $repeatarray[] = &MoodleQuickForm::createElement('text', 'limit', get_string('limit','groupreg'));
        $repeatarray[] = &MoodleQuickForm::createElement('text', 'grouping', get_string('grouping','groupreg'));
        $repeatarray[] = &MoodleQuickForm::createElement('hidden', 'optionid', 0);

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'miscellaneoussettingshdr', get_string('miscellaneoussettings', 'form'));

        $mform->addElement('selectyesno', 'allowupdate', get_string("allowupdate", "groupreg"));
        $mform->disabledIf('allowupdate', 'assigned', 'eq', '1');
        
        //$mform->addElement('select', 'limitanswers', get_string('limitanswers', 'groupreg'), $menuoptions);
        //$mform->disabledIf('limitanswers', 'assigned', 'eq', '1');
        //$mform->addHelpButton('limitanswers', 'limitanswers', 'groupreg');
        $mform->addElement('hidden', 'limitanswers', 1);

        $favoptions = array(1,2,3,4,5,6,7,8,9,10);
        $mform->addElement('select', 'limitfavorites', get_string('limitfavorites', 'groupreg'), $favoptions);
        $mform->disabledIf('limitfavorites', 'assigned', 'eq', '1');
        
        $blankoptions = array(1,2,3,4,5,6,7,8,9,10);
        $mform->addElement('select', 'limitblanks', get_string('limitblanks', 'groupreg'), $blankoptions);
        $mform->disabledIf('limitblanks', 'assigned', 'eq', '1');
        
        $mform->addElement('text', 'groupmembers', get_string('groupmembers2', 'groupreg'), 4);
        $mform->addHelpButton('groupmembers', 'groupmembers2', 'groupreg');
        
        if ($this->_instance){
            $repeatno = $DB->count_records('groupreg_options', array('groupregid'=>$this->_instance)) + 2;
        } else {
            $repeatno = 5;
        }
//-------------------------------------------------------------------------------
        $mform->addElement('header', 'importfromcsv', get_string('importfromcsv', 'groupreg'));
        $mform->addElement('html', '<p>' . get_string('csvimport', 'groupreg') . '</p>');
        $mform->addElement('filepicker', 'csvfile', get_string('csvfile', 'groupreg'), null, array('accepted_types' => 'text/csv'));
        $mform->addHelpButton('csvfile', 'csvfile', 'groupreg');
        

        $repeateloptions = array();
        $repeateloptions['limit']['default'] = 1;
        $repeateloptions['limit']['disabledif'] = array('limitanswers', 'eq', 0);
        $mform->setType('limit', PARAM_INT);

        $repeateloptions['option']['helpbutton'] = array('groupregoptions', 'groupreg');
        $mform->setType('option', PARAM_CLEAN);
        $mform->setType('grouping', PARAM_CLEAN);

        $mform->setType('optionid', PARAM_INT);

        $this->repeat_elements($repeatarray, $repeatno,
                    $repeateloptions, 'option_repeats', 'option_add_fields', 3);

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'timerestricthdr', get_string('timerestrict', 'groupreg'));
        $mform->addElement('checkbox', 'timerestrict', get_string('timerestrict', 'groupreg'));

        $mform->addElement('date_time_selector', 'timeopen', get_string("groupregopen", "groupreg"));
        $mform->disabledIf('timeopen', 'timerestrict');

        $mform->addElement('date_time_selector', 'timeclose', get_string("groupregclose", "groupreg"));
        $mform->disabledIf('timeclose', 'timerestrict');
        $mform->disabledIf('timeclose', 'assigned', 'eq', '1');

//-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        $this->add_action_buttons();
    }

    function data_preprocessing(&$default_values){
        global $DB;
        if (!empty($this->_instance) 
                && ($options = $DB->get_records_menu('groupreg_options',array('groupregid'=>$this->_instance), 'id', 'id,text'))
                && ($options3 = $DB->get_records_menu('groupreg_options',array('groupregid'=>$this->_instance), 'id', 'id,grouping'))
                && ($options2 = $DB->get_records_menu('groupreg_options', array('groupregid'=>$this->_instance), 'id', 'id,maxanswers')) ) {
            $groupregids=array_keys($options);
            $options=array_values($options);
            $options2=array_values($options2);
            $options3=array_values($options3);
            
            foreach (array_keys($options) as $key){
                $default_values['option['.$key.']'] = $options[$key];
                if ($options2[$key] <= 0)
                    $options2[$key] = 1;
                $default_values['limit['.$key.']'] = $options2[$key];
                $default_values['grouping['.$key.']'] = $options3[$key];
                $default_values['optionid['.$key.']'] = $groupregids[$key];
            }

        }
        if (empty($default_values['timeopen'])) {
            $default_values['timerestrict'] = 0;
        } else {
            $default_values['timerestrict'] = 1;
        }

    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        
        // TODO check if CSV file uploaded

        // ensure at least one choice is made
        $choices = 0;
        foreach ($data['option'] as $option){
            if (trim($option) != ''){
                $choices++;
            }
        }

        if ($choices < 1) {
           $errors['option[0]'] = get_string('fillinatleastoneoption', 'groupreg');
        }

        if ($choices < 2) {
           $errors['option[1]'] = get_string('fillinatleastoneoption', 'groupreg');
        }
		
		// check for double choices        
        $groups_selected = array();
        $opt_id = 0;
        foreach ($data['option'] as $option){
            if (in_array($option, $groups_selected)) {
                $errors['option['.$opt_id.']'] = get_string('samegroupused', 'groupreg');
            }
            elseif ($option) {
                $groups_selected[] = $option;
            }
            $opt_id++;
        }

        return $errors;
    }

    function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }
        // Set up completion section even if checkbox is not ticked
        if (empty($data->completionsection)) {
            $data->completionsection=0;
        }
        return $data;
    }

    function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox', 'completionsubmit', '', get_string('completionsubmit', 'groupreg'));
        return array('completionsubmit');
    }

    function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }
}

