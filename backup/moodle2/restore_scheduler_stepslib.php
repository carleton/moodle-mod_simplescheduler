<?php

/**
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
 
/**
 * Define all the restore steps that will be used by the restore_simplescheduler_activity_task
 */

/**
 * Structure step to restore one simplescheduler activity
 */
class restore_simplescheduler_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $simplescheduler = new restore_path_element('simplescheduler', '/activity/simplescheduler');
        $paths[] = $simplescheduler;

        $slot = new restore_path_element('simplescheduler_slot', '/activity/simplescheduler/slots/slot');
        $paths[] = $slot;

        if ($userinfo) {
            $appointment = new restore_path_element('simplescheduler_appointment', '/activity/simplescheduler/slots/slot/appointments/appointment');
            $paths[] = $appointment;
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_simplescheduler($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if ($data->scale < 0) { // scale found, get mapping
            $data->scale = -($this->get_mappingid('scale', abs($data->scale)));
        }
        $data->teacher = $this->get_mappingid('user', $data->teacher);

        // insert the simplescheduler record
        $newitemid = $DB->insert_record('simplescheduler', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_simplescheduler_slot($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->simpleschedulerid = $this->get_new_parentid('simplescheduler');
        $data->starttime = $this->apply_date_offset($data->starttime);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->emaildate = $this->apply_date_offset($data->emaildate);
        $data->hideuntil = $this->apply_date_offset($data->hideuntil);

        $data->teacherid = $this->get_mappingid('user', $data->teacherid);

        $newitemid = $DB->insert_record('simplescheduler_slots', $data);
        $this->set_mapping('simplescheduler_slot', $oldid, $newitemid, true); 
        // Apply only once we have files in the slot
    }

    protected function process_simplescheduler_appointment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->slotid = $this->get_new_parentid('simplescheduler_slot');
        
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $data->studentid = $this->get_mappingid('user', $data->studentid);

        $newitemid = $DB->insert_record('simplescheduler_appointment', $data);
        // $this->set_mapping('simplescheduler_appointments', $oldid, $newitemid, true); 
        // Apply only once we have files in the appointment
    }

    protected function after_execute() {
        // Add simplescheduler related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_simplescheduler', 'intro', null);
    }
}
