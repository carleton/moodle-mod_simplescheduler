<?php

/**
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Define all the backup steps that will be used by the backup_simplescheduler_activity_task
 */

/**
 * Define the complete simplescheduler structure for backup, with file and id annotations
 */
class backup_simplescheduler_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $simplescheduler = new backup_nested_element('simplescheduler', array('id'), array(
            'name', 'intro', 'introformat', 'simpleschedulermode',
            'defaultslotduration', 'allownotifications', 'staffrolename',
            'teacher', 'timemodified'));

        $slots = new backup_nested_element('slots');

        $slot = new backup_nested_element('slot', array('id'), array(
            'starttime', 'duration', 'teacherid', 'appointmentlocation',
            'timemodified', 'notes', 'exclusivity',
            'appointmentnote', 'emaildate', 'hideuntil'));

        $appointments = new backup_nested_element('appointments');

        $appointment = new backup_nested_element('appointment', array('id'), array(
            'studentid', 'appointmentnote',
            'timecreated', 'timemodified'));

        // Build the tree

        $simplescheduler->add_child($slots);
        $slots->add_child($slot);

        $slot->add_child($appointments);
        $appointments->add_child($appointment);


        // Define sources
        $simplescheduler->set_source_table('simplescheduler', array('id' => backup::VAR_ACTIVITYID));
        $slot->set_source_table('simplescheduler_slots', array('simpleschedulerid' => backup::VAR_PARENTID));

        // Include appointments only if we back up user information
        if ($userinfo) {
            $appointment->set_source_table('simplescheduler_appointment', array('slotid' => backup::VAR_PARENTID));
        }

        // Define id annotations
        $simplescheduler->annotate_ids('scale', 'scale');
        $simplescheduler->annotate_ids('user', 'teacher');
        
        $slot->annotate_ids('user', 'teacherid');
        
        $appointment->annotate_ids('user', 'studentid');

        // Define file annotations
        $simplescheduler->annotate_files('mod_simplescheduler', 'intro', null); // This file area has no itemid


        // Return the root element (simplescheduler), wrapped into standard activity structure
        return $this->prepare_activity_structure($simplescheduler);
    }
}
