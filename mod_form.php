<?php

/**
 * Defines the simplesscheduler module settings form.
 * 
 * @package    mod
 * @subpackage simplesscheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
//require_once($CFG->dirroot . '/mod/simplesscheduler/locallib.php');

/**
* overrides moodleform for test setup
*/
class mod_simplesscheduler_mod_form extends moodleform_mod {

	function definition() {

	    global $CFG, $COURSE, $OUTPUT;
	    $mform    =& $this->_form;
	  
	    $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
	    $mform->setType('name', PARAM_CLEANHTML);
	    $mform->addRule('name', null, 'required', null, 'client');

        // Introduction.
        $this->add_intro_editor(false, get_string('introduction', 'simplesscheduler'));

	    $mform->addElement('text', 'staffrolename', get_string('staffrolename', 'simplesscheduler'), array('size'=>'48'));
	    $mform->setType('name', PARAM_CLEANHTML);
	    $mform->addHelpButton('staffrolename', 'staffrolename', 'simplesscheduler');
	
	    //$modeoptions['onetime'] = get_string('oneatatime', 'simplesscheduler');
	    $modeoptions['oneonly'] = get_string('oneappointmentonly', 'simplesscheduler');
	    $modeoptions['multi'] = get_string('multi', 'simplesscheduler');
	    $mform->addElement('select', 'simplesschedulermode', get_string('mode', 'simplesscheduler'), $modeoptions);
	    $mform->addHelpButton('simplesschedulermode', 'appointmentmode', 'simplesscheduler');

	    $reuseguardoptions[24] = 24 . ' ' . get_string('hours');
	    $reuseguardoptions[48] = 48 . ' ' . get_string('hours');
	    $reuseguardoptions[72] = 72 . ' ' . get_string('hours');
	    $reuseguardoptions[96] = 96 . ' ' . get_string('hours');
	    $reuseguardoptions[168] = 168 . ' ' . get_string('hours');
	    $mform->addElement('select', 'reuseguardtime', get_string('reuseguardtime', 'simplesscheduler'), $reuseguardoptions);
	    $mform->addHelpButton('reuseguardtime', 'reuseguardtime', 'simplesscheduler');

	    $mform->addElement('text', 'defaultslotduration', get_string('defaultslotduration', 'simplesscheduler'), array('size'=>'2'));
	    $mform->setType('defaultslotduration', PARAM_INT);
	    $mform->addHelpButton('defaultslotduration', 'defaultslotduration', 'simplesscheduler');
        $mform->setDefault('defaultslotduration', 15);

        //$mform->addElement('modgrade', 'scale', get_string('grade'));
        $mform->addElement('hidden', 'scale');
        $mform->setDefault('scale', 0);

        //$gradingstrategy[MEAN_GRADE] = get_string('meangrade', 'simplesscheduler');
        //$gradingstrategy[MAX_GRADE] = get_string('maxgrade', 'simplesscheduler');
	    //$mform->addElement('select', 'gradingstrategy', get_string('gradingstrategy', 'simplesscheduler'), $gradingstrategy);
	    //$mform->addHelpButton('gradingstrategy', 'gradingstrategy', 'simplesscheduler');
        //$mform->disabledIf('gradingstrategy', 'scale', 'eq', 0);

        $yesno[0] = get_string('no');
        $yesno[1] = get_string('yes');
	    $mform->addElement('select', 'allownotifications', get_string('notifications', 'simplesscheduler'), $yesno);
	    $mform->addHelpButton('allownotifications', 'notifications', 'simplesscheduler');

		// Legacy. This field is still in the DB but is meaningless, meanwhile.
	    $mform->addElement('hidden', 'teacher');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

}

?>