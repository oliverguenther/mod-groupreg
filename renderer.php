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
 * @package   Choice
 * @copyright 2010 Rossiani Wijaya
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
define ('DISPLAY_HORIZONTAL_LAYOUT', 0);
define ('DISPLAY_VERTICAL_LAYOUT', 1);

class mod_groupreg_renderer extends plugin_renderer_base {

    /**
     * Returns HTML to display groupregs of option
     * @param object $options
     * @param int  $coursemoduleid
     * @param bool $vertical
     * @return string
     */
    public function display_options($course, $groupreg, $options, $coursemoduleid, $vertical = true) {
        global $DB;
        $layoutclass = 'vertical';
        $target = new moodle_url('/mod/groupreg/view.php');
        $attributes = array('method'=>'POST', 'target'=>$target, 'class'=> $layoutclass);

        $html = html_writer::start_tag('form', $attributes);
        
        // get all group names
        $groups = array();
        $db_groups = $DB->get_records('groups', array('courseid' => $course->id));
        foreach ($db_groups as $group) {
            $groups[$group->id] = $group->name;
        }
        
        // favorite choices
        $html .= html_writer::start_tag('table', array('class'=>'groupregs' ));
        for ($i = 0; $i <= $groupreg->limitfavorites; $i++) {
            $html .= html_writer::start_tag('tr', array('class'=>'option'));
            
            $html .= html_writer::tag('td', get_string('favorite_n', 'groupreg', $i+1).':', array());
            
            $html .= html_writer::start_tag('td', array());
            $html .= html_writer::start_tag('select', array('name' => "favs[$i]"));
            $html .= html_writer::tag('option', get_string('no_choice', 'groupreg'), array('value' => 0));
            foreach ($options['options'] as $option) {
                $groupname = $groups[$option->attributes->value];
                if ($option->maxanswers > 0) $max = $option->maxanswers;
                else $max = "&#8734;";
                $html .= html_writer::tag('option', $groupname.' ('.$max.')', array('value' => $option->attributes->value));
            }
            $html .= html_writer::end_tag('select');
            $html .= html_writer::end_tag('td');
            
            $html .= html_writer::end_tag('tr');
        }
        $html .= html_writer::end_tag('table');
        
        // blank choices
        $html .= html_writer::start_tag('table', array('class'=>'groupregs' ));
        for ($i = 0; $i <= $groupreg->limitblanks; $i++) {
            $html .= html_writer::start_tag('tr', array('class'=>'option'));
            
            $html .= html_writer::tag('td', get_string('blank_n', 'groupreg', $i+1).':', array());
            
            $html .= html_writer::start_tag('td', array());
            $html .= html_writer::start_tag('select', array('name' => "blanks[$i]"));
            $html .= html_writer::tag('option', get_string('no_choice', 'groupreg'), array('value' => 0));
            foreach ($options['options'] as $option) {
                $groupname = $groups[$option->attributes->value];
                if ($option->maxanswers > 0) $max = $option->maxanswers;
                else $max = "&#8734;";
                $html .= html_writer::tag('option', $groupname.' ('.$max.')', array('value' => $option->attributes->value));
            }
            $html .= html_writer::end_tag('select');
            $html .= html_writer::end_tag('td');
            
            $html .= html_writer::end_tag('tr');
        }
        
        $html .= html_writer::end_tag('table');
        
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
     * @param object $groupregs
     * @param bool $forcepublish
     * @return string
     */
    public function display_result($groupregs, $forcepublish = false) {
        if (empty($forcepublish)) { //allow the publish setting to be overridden
            $forcepublish = $groupregs->publish;
        }

        $displaylayout = $groupregs->display;

        if ($forcepublish) {  //groupreg_PUBLISH_NAMES
            return $this->display_publish_name_vertical($groupregs);
        } else { //groupreg_PUBLISH_ANONYMOUS';
            if ($displaylayout == DISPLAY_HORIZONTAL_LAYOUT) {
                return $this->display_publish_anonymous_horizontal($groupregs);
            }
            return $this->display_publish_anonymous_vertical($groupregs);
        }
    }

    /**
     * Returns HTML to display groupregs result
     * @param object $groupregs
     * @param bool $forcepublish
     * @return string
     */
    public function display_publish_name_vertical($groupregs) {
        global $PAGE;
        $html ='';
        $html .= html_writer::tag('h2',format_string(get_string("responses", "groupreg")), array('class'=>'main'));

        $attributes = array('method'=>'POST');
        $attributes['action'] = new moodle_url($PAGE->url);
        $attributes['id'] = 'attemptsform';

        if ($groupregs->viewresponsecapability) {
            $html .= html_writer::start_tag('form', $attributes);
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=> $groupregs->coursemoduleid));
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=> sesskey()));
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'mode', 'value'=>'overview'));
        }

        $table = new html_table();
        $table->cellpadding = 0;
        $table->cellspacing = 0;
        $table->attributes['class'] = 'results names ';
        $table->tablealign = 'center';
        $table->data = array();

        $count = 0;
        ksort($groupregs->options);

        $columns = array();
        foreach ($groupregs->options as $optionid => $options) {
            $coldata = '';
            if ($groupregs->showunanswered && $optionid == 0) {
                $coldata .= html_writer::tag('div', format_string(get_string('notanswered', 'groupreg')), array('class'=>'option'));
            } else if ($optionid > 0) {
                $coldata .= html_writer::tag('div', format_string($groupregs->options[$optionid]->text), array('class'=>'option'));
            }
            $numberofuser = 0;
            if (!empty($options->user) && count($options->user) > 0) {
                $numberofuser = count($options->user);
            }

            $coldata .= html_writer::tag('div', ' ('.$numberofuser. ')', array('class'=>'numberofuser', 'title' => get_string('numberofuser', 'groupreg')));
            $columns[] = $coldata;
        }

        $table->head = $columns;

        $coldata = '';
        $columns = array();
        foreach ($groupregs->options as $optionid => $options) {
            $coldata = '';
            if ($groupregs->showunanswered || $optionid > 0) {
                if (!empty($options->user)) {
                    foreach ($options->user as $user) {
                        $data = '';
                        if (empty($user->imagealt)){
                            $user->imagealt = '';
                        }

                        if ($groupregs->viewresponsecapability && $groupregs->deleterepsonsecapability  && $optionid > 0) {
                            $attemptaction = html_writer::checkbox('attemptid[]', $user->id,'');
                            $data .= html_writer::tag('div', $attemptaction, array('class'=>'attemptaction'));
                        }
                        $userimage = $this->output->user_picture($user, array('courseid'=>$groupregs->courseid));
                        $data .= html_writer::tag('div', $userimage, array('class'=>'image'));

                        $userlink = new moodle_url('/user/view.php', array('id'=>$user->id,'course'=>$groupregs->courseid));
                        $name = html_writer::tag('a', fullname($user, $groupregs->fullnamecapability), array('href'=>$userlink, 'class'=>'username'));
                        $data .= html_writer::tag('div', $name, array('class'=>'fullname'));
                        $data .= html_writer::tag('div','', array('class'=>'clearfloat'));
                        $coldata .= html_writer::tag('div', $data, array('class'=>'user'));
                    }
                }
            }

            $columns[] = $coldata;
            $count++;
        }

        $table->data[] = $columns;
        foreach ($columns as $d) {
            $table->colclasses[] = 'data';
        }
        $html .= html_writer::tag('div', html_writer::table($table), array('class'=>'response'));

        $actiondata = '';
        if ($groupregs->viewresponsecapability && $groupregs->deleterepsonsecapability) {
            $selecturl = new moodle_url('#');

            $selectallactions = new component_action('click',"select_all_in", array('div',null,'tablecontainer'));
            $selectall = new action_link($selecturl, get_string('selectall', 'quiz'), $selectallactions);
            $actiondata .= $this->output->render($selectall) . ' / ';

            $deselectallactions = new component_action('click',"deselect_all_in", array('div',null,'tablecontainer'));
            $deselectall = new action_link($selecturl, get_string('selectnone', 'quiz'), $deselectallactions);
            $actiondata .= $this->output->render($deselectall);

            $actiondata .= html_writer::tag('label', ' ' . get_string('withselected', 'quiz') . ' ', array('for'=>'menuaction'));

            $actionurl = new moodle_url($PAGE->url, array('sesskey'=>sesskey(), 'action'=>'delete_confirmation()'));
            $select = new single_select($actionurl, 'action', array('delete'=>get_string('delete')), null, array(''=>get_string('chooseaction', 'groupreg')), 'attemptsform');

            $actiondata .= $this->output->render($select);
        }
        $html .= html_writer::tag('div', $actiondata, array('class'=>'responseaction'));

        if ($groupregs->viewresponsecapability) {
            $html .= html_writer::end_tag('form');
        }

        return $html;
    }


    /**
     * Returns HTML to display groupregs result
     * @param object $groupregs
     * @return string
     */
    public function display_publish_anonymous_vertical($groupregs) {
        global $groupreg_COLUMN_HEIGHT;

        $html = '';
        $table = new html_table();
        $table->cellpadding = 5;
        $table->cellspacing = 0;
        $table->attributes['class'] = 'results anonymous ';
        $table->data = array();
        $count = 0;
        ksort($groupregs->options);
        $columns = array();
        $rows = array();

        foreach ($groupregs->options as $optionid => $options) {
            $numberofuser = 0;
            if (!empty($options->user)) {
               $numberofuser = count($options->user);
            }
            $height = 0;
            $percentageamount = 0;
            if($groupregs->numberofuser > 0) {
               $height = ($groupreg_COLUMN_HEIGHT * ((float)$numberofuser / (float)$groupregs->numberofuser));
               $percentageamount = ((float)$numberofuser/(float)$groupregs->numberofuser)*100.0;
            }

            $displaydiagram = html_writer::tag('img','', array('style'=>'height:'.$height.'px;width:49px;', 'alt'=>'', 'src'=>$this->output->pix_url('column', 'groupreg')));

            $cell = new html_table_cell();
            $cell->text = $displaydiagram;
            $cell->attributes = array('class'=>'graph vertical data');
            $columns[] = $cell;
        }
        $rowgraph = new html_table_row();
        $rowgraph->cells = $columns;
        $rows[] = $rowgraph;

        $columns = array();
        $printskiplink = true;
        foreach ($groupregs->options as $optionid => $options) {
            $columndata = '';
            $numberofuser = 0;
            if (!empty($options->user)) {
               $numberofuser = count($options->user);
            }

            if ($printskiplink) {
                $columndata .= html_writer::tag('div', '', array('class'=>'skip-block-to', 'id'=>'skipresultgraph'));
                $printskiplink = false;
            }

            if ($groupregs->showunanswered && $optionid == 0) {
                $columndata .= html_writer::tag('div', format_string(get_string('notanswered', 'groupreg')), array('class'=>'option'));
            } else if ($optionid > 0) {
                $columndata .= html_writer::tag('div', format_string($groupregs->options[$optionid]->text), array('class'=>'option'));
            }
            $columndata .= html_writer::tag('div', ' ('.$numberofuser.')', array('class'=>'numberofuser', 'title'=> get_string('numberofuser', 'groupreg')));

            if($groupregs->numberofuser > 0) {
               $percentageamount = ((float)$numberofuser/(float)$groupregs->numberofuser)*100.0;
            }
            $columndata .= html_writer::tag('div', format_float($percentageamount,1). '%', array('class'=>'percentage'));

            $cell = new html_table_cell();
            $cell->text = $columndata;
            $cell->attributes = array('class'=>'data header');
            $columns[] = $cell;
        }
        $rowdata = new html_table_row();
        $rowdata->cells = $columns;
        $rows[] = $rowdata;

        $table->data = $rows;

        $header = html_writer::tag('h2',format_string(get_string("responses", "groupreg")));
        $html .= html_writer::tag('div', $header, array('class'=>'responseheader'));
        $html .= html_writer::tag('a', get_string('skipresultgraph', 'groupreg'), array('href'=>'#skipresultgraph', 'class'=>'skip-block'));
        $html .= html_writer::tag('div', html_writer::table($table), array('class'=>'response'));

        return $html;
    }

}

