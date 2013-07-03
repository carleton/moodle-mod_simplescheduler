<?php

/**
 * Defines the simplescheduler module settings form.
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
//require_once($CFG->dirroot . '/mod/simplescheduler/locallib.php');

/**
* overrides moodleform for test setup
*/
class mod_simplescheduler_mod_form extends moodleform_mod {

	function definition() {

	    global $CFG, $COURSE, $OUTPUT;
	    $mform    =& $this->_form;
	  
	    $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
	    $mform->setType('name', PARAM_CLEANHTML);
	    $mform->addRule('name', null, 'required', null, 'client');

        // Introduction.
        $this->add_intro_editor(false, get_string('introduction', 'simplescheduler'));

	    $mform->addElement('text', 'staffrolename', get_string('staffrolename', 'simplescheduler'), array('size'=>'48'));
	    $mform->setType('name', PARAM_CLEANHTML);
	    $mform->addHelpButton('staffrolename', 'staffrolename', 'simplescheduler');
	
	    //$modeoptions['onetime'] = get_string('oneatatime', 'simplescheduler');
	    $modeoptions['oneonly'] = get_string('oneappointmentonly', 'simplescheduler');
	    $modeoptions['multi'] = get_string('multi', 'simplescheduler');
	    $mform->addElement('select', 'simpleschedulermode', get_string('mode', 'simplescheduler'), $modeoptions);
	    $mform->addHelpButton('simpleschedulermode', 'appointmentmode', 'simplescheduler');

	    $reuseguardoptions[24] = 24 . ' ' . get_string('hours');
	    $reuseguardoptions[48] = 48 . ' ' . get_string('hours');
	    $reuseguardoptions[72] = 72 . ' ' . get_string('hours');
	    $reuseguardoptions[96] = 96 . ' ' . get_string('hours');
	    $reuseguardoptions[168] = 168 . ' ' . get_string('hours');
	    $mform->addElement('select', 'reuseguardtime', get_string('reuseguardtime', 'simplescheduler'), $reuseguardoptions);
	    $mform->addHelpButton('reuseguardtime', 'reuseguardtime', 'simplescheduler');

	    $mform->addElement('text', 'defaultslotduration', get_string('defaultslotduration', 'simplescheduler'), array('size'=>'2'));
	    $mform->setType('defaultslotduration', PARAM_INT);
	    $mform->addHelpButton('defaultslotduration', 'defaultslotduration', 'simplescheduler');
        $mform->setDefault('defaultslotduration', 15);

        //$mform->addElement('modgrade', 'scale', get_string('grade'));
        $mform->addElement('hidden', 'scale');
        $mform->setDefault('scale', 0);

        //$gradingstrategy[MEAN_GRADE] = get_string('meangrade', 'simplescheduler');
        //$gradingstrategy[MAX_GRADE] = get_string('maxgrade', 'simplescheduler');
	    //$mform->addElement('select', 'gradingstrategy', get_string('gradingstrategy', 'simplescheduler'), $gradingstrategy);
	    //$mform->addHelpButton('gradingstrategy', 'gradingstrategy', 'simplescheduler');
        //$mform->disabledIf('gradingstrategy', 'scale', 'eq', 0);

        $yesno[0] = get_string('no');
        $yesno[1] = get_string('yes');
	    $mform->addElement('select', 'allownotifications', get_string('notifications', 'simplescheduler'), $yesno);
	    $mform->addHelpButton('allownotifications', 'notifications', 'simplescheduler');

		// Legacy. This field is still in the DB but is meaningless, meanwhile.
	    $mform->addElement('hidden', 'teacher');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

}

?>