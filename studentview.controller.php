<?php

/**
 * Controller for student view
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/simplescheduler/mailtemplatelib.php');


/************************************************ Saving choice ************************************************/
if ($action == 'savechoice') {
   
   	$slot_id_array = NULL;
    if ($simplescheduler->simpleschedulermode == 'multi')
    {
    	$slot_id_array_raw = optional_param_array('slotid', '', PARAM_INT);
    }
    else
    {
    	$slot_id_array_raw[] = optional_param('slotid', '', PARAM_INT);
    }
    if (!empty($slot_id_array_raw))
    {
    	foreach ($slot_id_array_raw as $k=>$v)
    	{
    		if (!empty($v))
    		{
    			$slot_id_array_request[] = $v;
    		}
    	}
    }
    else $slot_id_array_request = false;
    
    $appointgroup = optional_param('appointgroup', 0, PARAM_INT);
    // $notes = optional_param('notes', '', PARAM_TEXT);
    
    //if (!$slot_id_array_request) {
    //    notice(get_string('notselected', 'simplescheduler'), "view.php?id={$cm->id}");
    //}
    
    // validate our slot ids
    if (!empty($slot_id_array_request))
    {
		foreach ($slot_id_array_request as $index => $slotid)
		{
			if (!$slot = $DB->get_record('simplescheduler_slots', array('id' => $slotid))) {
				print_error('errorinvalidslot', 'simplescheduler');
			}
		
			$available = simplescheduler_get_appointments($slotid);
			$consumed = ($available) ? count($available) : 0 ;
	
			$users_for_slot = simplescheduler_get_appointed($slotid);
			$already_signed_up = (isset($users_for_slot[$USER->id]));
		
			if (!$already_signed_up)
			{
				// if slot is already overcrowded
				if ($slot->exclusivity > 0 && ($slot->exclusivity <= $consumed)) {
					if ($updating = $DB->count_records('simplescheduler_appointment', array('slotid' => $slot->id, 'studentid' => $USER->id))) {
						$message = get_string('alreadyappointed', 'simplescheduler');
					} else {
						$message = get_string('slot_is_just_in_use', 'simplescheduler');
					}
					echo $OUTPUT->box_start('error');
					echo $message;
					echo $OUTPUT->continue_button("{$CFG->wwwroot}/mod/simplescheduler/view.php?id={$cm->id}");
					echo $OUTPUT->box_end();
					echo $OUTPUT->footer($course);
					exit();
				}
				$slot_id_array[$index] = $slotid;
			}
			$slot_id_array_validated[$index] = $slotid;
		}
    }
    
    /// If we are scheduling a full group we must discard all pending appointments of other participants of the scheduled group
    /// just add the list of other students for searching slots to delete
    if ($appointgroup){
        if (!function_exists('build_navigation')){
            // we are still in 1.8
            $oldslotownersarray = groups_get_members($appointgroup, 'student');
        } else {
            // we are still in 1.8
            $oldslotownersarray = groups_get_members($appointgroup);
        }
        // special hack for 1.8 / 1.9 compatibility for groups_get_members()
        foreach($oldslotownersarray as $oldslotownermember){
            if (is_numeric($oldslotownermember)){
                // we are in 1.8
                if (has_capability("mod/simplescheduler:appoint", $context, $oldslotownermember)){
                    $oldslotowners[] = $oldslotownermember;
                }
            } else {
                // we are in 1.9
                if (has_capability("mod/simplescheduler:appoint", $context, $oldslotownermember->id)){
                    $oldslotowners[] = $oldslotownermember->id;
                }
            }
        }
    } else {
        // single user appointment : get current user in
        $oldslotowners[] = $USER->id;
    }
    $oldslotownerlist = implode("','", $oldslotowners);
  
    // cleans up future slots that are no longer selected
    $sql = "
        SELECT 
        s.*,
        a.id as appointmentid,
        a.studentid as studentid
        FROM 
        {simplescheduler_slots} AS s,
        {simplescheduler_appointment} AS a 
        WHERE 
        s.id = a.slotid AND
        s.simpleschedulerid = '{$simplescheduler->id}' AND 
        a.studentid IN ('$oldslotownerlist')
        ";
    if (!empty($slot_id_array_validated))
    {
    	$sql .= " AND a.slotid NOT IN (" . implode(",", $slot_id_array_validated) . ")";
    }
    $sql .= " AND s.starttime > ".time(); // only mark future ones for deletion
    
    if ($oldslots = $DB->get_records_sql($sql))
    {
        foreach($oldslots as $id => $slot) {
            simplescheduler_student_revoke_appointment($id, $slot->studentid);
        }
    }
    
    if (!empty($slot_id_array))
    {
    	foreach ($slot_id_array as $slotid)
    	{
    		/// create new appointment and add it for each member of the group
    		foreach($oldslotowners as $studentid) {
    			simplescheduler_student_appoint_student($slotid, $studentid);
        	}
    	}
    }
}

// ************************************ Disengage alone from the slot ******************************* /
if ($action == 'disengage') {
    $where = 'studentid = :studentid AND ' .
             'EXISTS(SELECT 1 FROM {simplescheduler_slots} sl WHERE sl.id = slotid AND sl.simpleschedulerid = :simplescheduler )';
    $params = array('simplescheduler'=>$simplescheduler->id, 'studentid'=>$USER->id);
    $appointments = $DB->get_records_select('simplescheduler_appointment', $where, $params);
    if ($appointments) {
        foreach($appointments as $appointment) {
            $slot = $DB->get_record('simplescheduler_slots', array('id' => $appointment->slotid));
            if ($slot->starttime > time()) // only modify future slots
            {
            	simplescheduler_student_revoke_appointment($slot->id, $USER->id);
            }
        }
    }
}
?>