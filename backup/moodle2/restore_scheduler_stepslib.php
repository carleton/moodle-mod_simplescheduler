<?php

/**
 * @package    mod
 * @subpackage simplesscheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
 
/**
 * Define all the restore steps that will be used by the restore_simplesscheduler_activity_task
 */

/**
 * Structure step to restore one simplesscheduler activity
 */
class restore_simplesscheduler_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $simplesscheduler = new restore_path_element('simplesscheduler', '/activity/simplesscheduler');
        $paths[] = $simplesscheduler;

        $slot = new restore_path_element('simplesscheduler_slot', '/activity/simplesscheduler/slots/slot');
        $paths[] = $slot;

        if ($userinfo) {
            $appointment = new restore_path_element('simplesscheduler_appointment', '/activity/simplesscheduler/slots/slot/appointments/appointment');
            $paths[] = $appointment;
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_simplesscheduler($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if ($data->scale < 0) { // scale found, get mapping
            $data->scale = -($this->get_mappingid('scale', abs($data->scale)));
        }
        $data->teacher = $this->get_mappingid('user', $data->teacher);

        // insert the simplesscheduler record
        $newitemid = $DB->insert_record('simplesscheduler', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_simplesscheduler_slot($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->simplesschedulerid = $this->get_new_parentid('simplesscheduler');
        $data->starttime = $this->apply_date_offset($data->starttime);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->emaildate = $this->apply_date_offset($data->emaildate);
        $data->hideuntil = $this->apply_date_offset($data->hideuntil);

        $data->teacherid = $this->get_mappingid('user', $data->teacherid);

        $newitemid = $DB->insert_record('simplesscheduler_slots', $data);
        $this->set_mapping('simplesscheduler_slot', $oldid, $newitemid, true); 
        // Apply only once we have files in the slot
    }

    protected function process_simplesscheduler_appointment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->slotid = $this->get_new_parentid('simplesscheduler_slot');
        
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $data->studentid = $this->get_mappingid('user', $data->studentid);

        $newitemid = $DB->insert_record('simplesscheduler_appointment', $data);
        // $this->set_mapping('simplesscheduler_appointments', $oldid, $newitemid, true); 
        // Apply only once we have files in the appointment
    }

    protected function after_execute() {
        // Add simplesscheduler related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_simplesscheduler', 'intro', null);
    }
}
