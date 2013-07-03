<?php

/**
 * @package    mod
 * @subpackage simplesscheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Define all the backup steps that will be used by the backup_simplesscheduler_activity_task
 */

/**
 * Define the complete simplesscheduler structure for backup, with file and id annotations
 */
class backup_simplesscheduler_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $simplesscheduler = new backup_nested_element('simplesscheduler', array('id'), array(
            'name', 'intro', 'introformat', 'simplesschedulermode',
            'reuseguardtime', 'defaultslotduration', 'allownotifications', 'staffrolename',
            'teacher', 'scale', 'gradingstrategy', 'timemodified'));

        $slots = new backup_nested_element('slots');

        $slot = new backup_nested_element('slot', array('id'), array(
            'starttime', 'duration', 'teacherid', 'appointmentlocation',
            'reuse', 'timemodified', 'notes', 'exclusivity',
            'appointmentnote', 'emaildate', 'hideuntil'));

        $appointments = new backup_nested_element('appointments');

        $appointment = new backup_nested_element('appointment', array('id'), array(
            'studentid', 'attended', 'grade', 'appointmentnote',
            'timecreated', 'timemodified'));

        // Build the tree

        $simplesscheduler->add_child($slots);
        $slots->add_child($slot);

        $slot->add_child($appointments);
        $appointments->add_child($appointment);


        // Define sources
        $simplesscheduler->set_source_table('simplesscheduler', array('id' => backup::VAR_ACTIVITYID));
        $slot->set_source_table('simplesscheduler_slots', array('simplesschedulerid' => backup::VAR_PARENTID));

        // Include appointments only if we back up user information
        if ($userinfo) {
            $appointment->set_source_table('simplesscheduler_appointment', array('slotid' => backup::VAR_PARENTID));
        }

        // Define id annotations
        $simplesscheduler->annotate_ids('scale', 'scale');
        $simplesscheduler->annotate_ids('user', 'teacher');
        
        $slot->annotate_ids('user', 'teacherid');
        
        $appointment->annotate_ids('user', 'studentid');

        // Define file annotations
        $simplesscheduler->annotate_files('mod_simplesscheduler', 'intro', null); // This file area has no itemid


        // Return the root element (simplesscheduler), wrapped into standard activity structure
        return $this->prepare_activity_structure($simplesscheduler);
    }
}
