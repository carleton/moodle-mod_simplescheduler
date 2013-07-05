<?php

/**
 * Contains various sub-screens that a teacher can see.
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @todo consider "past slots" toggle
 * @todo have my appointments highlighted by default
 * @todo review to make sure capabilities are checked as appropriate
 * @todo revamp revokeone to utilize existing/new methods from locallib.php
 * @todo notify needs to use strings from lang file
 * @todo fix conflict handling in "add slots" view - right now slots seem to get deleted
 * @todo get database insert / update / delete into locallib and out of the this file
 * @todo use moodle form classes properly
 */
defined('MOODLE_INTERNAL') || die();

function get_slot_data(&$form){
	global $USER;
	$form = new stdClass();
    if (!$form->hideuntil = optional_param('hideuntil', '', PARAM_INT)){
        $form->displayyear = required_param('displayyear', PARAM_INT);
        $form->displaymonth = required_param('displaymonth', PARAM_INT);
        $form->displayday = required_param('displayday', PARAM_INT);
        $form->hideuntil = make_timestamp($form->displayyear, $form->displaymonth, $form->displayday);
    }
    if (!$form->starttime = optional_param('starttime', '', PARAM_INT)){
        $form->year = required_param('year', PARAM_INT);
        $form->month = required_param('month', PARAM_INT);
        $form->day = required_param('day', PARAM_INT);
        $form->hour = required_param('hour', PARAM_INT);
        $form->minute = required_param('minute', PARAM_INT);
        $form->starttime = make_timestamp($form->year, $form->month, $form->day, $form->hour, $form->minute);
    }
    $form->exclusivity = required_param('exclusivity', PARAM_INT);
    $form->duration = required_param('duration', PARAM_INT);
    $form->notes = required_param('notes', PARAM_TEXT);
    // if no teacher specified, the current user (who edits the slot) is assumed to be the teacher
    $form->teacherid = optional_param('teacherid', $USER->id, PARAM_INT);
    $form->appointmentlocation = required_param('appointmentlocation', PARAM_CLEAN);
}

/**
 *
 */
function get_session_data(&$form){
	global $USER;
	$form = new stdClass();
    if (!$form->rangestart = optional_param('rangestart', '', PARAM_INT)){
        $year = required_param('startyear', PARAM_INT);
        $month = required_param('startmonth', PARAM_INT);
        $day = required_param('startday', PARAM_INT);
        $form->rangestart = make_timestamp($year, $month, $day);
        $form->starthour = required_param('starthour', PARAM_INT);
        $form->startminute = required_param('startminute', PARAM_INT);
        $form->timestart = make_timestamp($year, $month, $day, $form->starthour, $form->startminute);
    }
    if (!$form->rangeend = optional_param('rangeend', '', PARAM_INT)){
        $year = required_param('endyear', PARAM_INT);
        $month = required_param('endmonth', PARAM_INT);
        $day = required_param('endday', PARAM_INT);
        $form->rangeend = make_timestamp($year, $month, $day);
        $form->endhour = required_param('endhour', PARAM_INT);
        $form->endminute = required_param('endminute', PARAM_INT);
        $form->timeend = make_timestamp($year, $month, $day, $form->endhour, $form->endminute);
    }
    $form->monday = optional_param('monday', 0, PARAM_INT);
    $form->tuesday = optional_param('tuesday', 0, PARAM_INT);
    $form->wednesday = optional_param('wednesday', 0, PARAM_INT);
    $form->thursday = optional_param('thursday', 0, PARAM_INT);
    $form->friday = optional_param('friday', 0, PARAM_INT);
    $form->saturday = optional_param('saturday', 0, PARAM_INT);
    $form->sunday = optional_param('sunday', 0, PARAM_INT);
    $form->forcewhenoverlap = required_param('forcewhenoverlap', PARAM_INT);
    $form->exclusivity = required_param('exclusivity', PARAM_INT);
    $form->divide = optional_param('divide', 0, PARAM_INT);
    $form->duration = optional_param('duration', 15, PARAM_INT);
    // if no teacher specified, the current user (who edits the slot) is assumed to be the teacher
    $form->teacherid = optional_param('teacherid', $USER->id, PARAM_INT);
    $form->appointmentlocation = optional_param('appointmentlocation', '', PARAM_CLEAN);
    $form->emailfrom = required_param('emailfrom', PARAM_CLEAN);
    $form->displayfrom = required_param('displayfrom', PARAM_CLEAN);
}

// load group restrictions
$modinfo = get_fast_modinfo($course);

$usergroups = '';
if ($cm->groupmode > 0) {
	$groups = groups_get_all_groups($COURSE->id, 0, $cm->groupingid);
	$usergroups = array_keys($groups);
}

if ($action) {
	switch ($action) {
		/************************************** creates or updates a slot ***********************************************
		 *
		 * If fails, should reenter within the form signalling error cause
		 */
		case 'doaddupdateslot':{
			// get expected parameters
			$slotid = optional_param('slotid', '', PARAM_INT);
		
			// get standard slot parms
			$data = new stdClass();
			get_slot_data($data);
			$appointments = unserialize(stripslashes(optional_param('appointments', '', PARAM_RAW)));
		
			$errors = array();
		
			//  in the "schedule as seen" workflow, do not check for conflicting slots etc.
			$force = optional_param('seen', 0, PARAM_BOOL);
			if (!$force) {
			
				// Avoid slots starting in the past (too far)
				if ($data->starttime < (time() - DAYSECS * 10)) {
					$erroritem = new stdClass();
					$erroritem->message = get_string('startpast', 'simplescheduler');
					$erroritem->on = 'rangestart';
					$errors[] = $erroritem;
				}
			
				if ($data->exclusivity > 0 and count($appointments) > $data->exclusivity){
					$erroritem = new stdClass();
					$erroritem->message = get_string('exclusivityoverload', 'simplescheduler');
					$erroritem->on = 'exclusivity';
					$errors[] = $erroritem;
				}
			
				if ($data->teacherid == 0){
					$erroritem = new stdClass();
					$erroritem->message = get_string('noteacherforslot', 'simplescheduler');
					$erroritem->on = 'teacherid';
					$errors[] = $erroritem;
				}
			
				if (count($errors)){
					$action = 'addslot';
					return;
				}
			
				// Avoid overlapping slots, by asking the user if they'd like to overwrite the existing ones...
				// for other simplescheduler, we check independently of exclusivity. Any slot here conflicts
				// for this simplescheduler, we check against exclusivity. Any complete slot here conflicts
				$conflictsRemote = simplescheduler_get_conflicts($simplescheduler->id, $data->starttime, $data->starttime + $data->duration * 60, $data->teacherid, 0, SIMPLESCHEDULER_OTHERS, false);
				$conflictsLocal = simplescheduler_get_conflicts($simplescheduler->id, $data->starttime, $data->starttime + $data->duration * 60, $data->teacherid, 0, SIMPLESCHEDULER_SELF, true);
				if (!$conflictsRemote) $conflictsRemote = array();
				if (!$conflictsLocal) $conflictsLocal = array();
				$conflicts = $conflictsRemote + $conflictsLocal;
			
				// remove itself from conflicts when updating
				if (!empty($slotid) and array_key_exists($slotid, $conflicts)){
					unset($conflicts[$slotid]);
				}
		
				if (count($conflicts)) {
					if ($subaction == 'confirmdelete' && confirm_sesskey()) {
						foreach ($conflicts as $conflict) {
							if ($conflict->id != @$slotid) {
								$DB->delete_records('simplescheduler_slots', array('id' => $conflict->id));
								$DB->delete_records('simplescheduler_appointment', array('slotid' => $conflict->id));
								simplescheduler_delete_calendar_events($conflict);
							}
						}
					} 
					else { 
						echo "<br/><br/>";
						echo $OUTPUT->box_start('center', '', '');
						echo get_string('slotwarning', 'simplescheduler').'<br/><br/>';
						foreach ($conflicts as $conflict) {
							$students = simplescheduler_get_appointed($conflict->id);
					
						echo (!empty($students)) ? '<b>' : '' ;
						echo userdate($conflict->starttime);
						echo ' [';
						echo $conflict->duration.' '.get_string('minutes');
						echo ']<br/>';
						
						if ($students){
								$appointed = array();
								foreach($students as $aStudent){
									$appointed[] = fullname($aStudent);
								}
								if (count ($appointed)){
									echo '<span style="font-size : smaller">';
									echo implode(', ', $appointed);
									echo '</span>';
								}
								unset ($appointed);
								echo '<br/>';
							}
							echo (!empty($students)) ? '</b>' : '' ;
						}
					
						$options = array();
						$options['what'] = 'addslot';
						$options['id'] = $cm->id;
						$options['page'] = $page;
						$options['slotid'] = $slotid;
						echo $OUTPUT->single_button(new moodle_url('view.php',$options), get_string('cancel'));
					
						$options['what'] = 'doaddupdateslot';
						$options['subaction'] = 'confirmdelete';
						$options['sesskey'] = sesskey();
						$options['year'] = $data->year;
						$options['month'] = $data->month;
						$options['day'] = $data->day;
						$options['hour'] = $data->hour;
						$options['minute'] = $data->minute;
						$options['displayyear'] = $data->displayyear;
						$options['displaymonth'] = $data->displaymonth;
						$options['displayday'] = $data->displayday;
						$options['duration'] = $data->duration;
						$options['teacherid'] = $data->teacherid;
						$options['exclusivity'] = $data->exclusivity;
						$options['appointments'] = serialize($appointments);
						$options['notes'] = $data->notes;
						$options['appointmentlocation'] = $data->appointmentlocation;
						echo $OUTPUT->single_button(new moodle_url('view.php',$options), get_string('deletetheseslots', 'simplescheduler'));
						echo $OUTPUT->box_end(); 
						echo $OUTPUT->footer($course);
						die();  
					}
				}
			}
	
			// make new slot record
			$slot = new stdClass();
			$slot->simpleschedulerid = $simplescheduler->id;
			$slot->starttime = $data->starttime;
			$slot->duration = $data->duration;
			if (!empty($data->slotid)){
				$appointed = count(simplescheduler_get_appointments($data->slotid));
				if ($data->exclusivity > 0 and $appointed > $data->exclusivity){
					unset($erroritem);
					$erroritem->message = get_string('exclusivityoverload', 'simplescheduler');
					$erroritem->on = 'exclusivity';
					$errors[] = $erroritem;
					return;
				}
				$slot->exclusivity = max($data->exclusivity, $appointed);
			}
			else{
				$slot->exclusivity = $data->exclusivity;
			}
			$slot->timemodified = time();
			if (!empty($data->teacherid)) $slot->teacherid = $data->teacherid;
			$slot->notes = $data->notes;
			$slot->appointmentlocation = $data->appointmentlocation;
			$slot->hideuntil = $data->hideuntil;
			$slot->emaildate = 0;
			if (!$slotid){ // add it
				$slot->id = $DB->insert_record('simplescheduler_slots', $slot);
				echo $OUTPUT->heading(get_string('oneslotadded','simplescheduler'));
			}
			else{ // update it
				$slot->id = $slotid;
				$DB->update_record('simplescheduler_slots', $slot);
				echo $OUTPUT->heading(get_string('slotupdated','simplescheduler'));
			}
	
			$DB->delete_records('simplescheduler_appointment', array('slotid'=>$slot->id)); // cleanup old appointments
			if($appointments){
				foreach ($appointments as $appointment){ // insert updated
					$appointment->slotid = $slot->id; // now we know !!
					$DB->insert_record('simplescheduler_appointment', $appointment);
				}
			}
	
			simplescheduler_events_update($slot, $course);
			break;
		}
		/************************************ Saving a session with slots *************************************/
		case 'doaddsession':{
			// This creates sessions using the data submitted by the user via the form on add.html
			get_session_data($data);
	
			$fordays = (($data->rangeend - $data->rangestart) / DAYSECS);
	
			$errors = array();
	
			/// range is negative
			if ($fordays < 0){
				$erroritem->message = get_string('negativerange', 'simplescheduler');
				$erroritem->on = 'rangeend';
				$errors[] = $erroritem;
			}
	
			if ($data->teacherid == 0){
				unset($erroritem);
				$erroritem->message = get_string('noteacherforslot', 'simplescheduler');
				$erroritem->on = 'teacherid';
				$errors[] = $erroritem;
			}
	
			/// first slot is in the past
			if ($data->rangestart < time() - DAYSECS) {
				unset($erroritem);
				$erroritem->message = get_string('startpast', 'simplescheduler');
				$erroritem->on = 'rangestart';
				$errors[] = $erroritem;
			}
	
			// first error trap. Ask to correct that first
			if (count($errors)){
				$action = 'addsession';
				break;
			}
	
	
			/// make a base slot for generating
			$slot = new stdClass();
			$slot->appointmentlocation = $data->appointmentlocation;
			$slot->exclusivity = $data->exclusivity;
			$slot->duration = $data->duration;
			$slot->simpleschedulerid = $simplescheduler->id;
			$slot->timemodified = time();
			$slot->teacherid = $data->teacherid;
	
			/// check if overlaps. Check also if some slots are in allowed day range
			$startfrom = $data->rangestart;
			$noslotsallowed = true;
			for ($d = 0; $d <= $fordays; $d ++){
				$starttime = $startfrom + ($d * DAYSECS);
				$eventdate = usergetdate($starttime);
				$dayofweek = $eventdate['wday'];
				if ((($dayofweek == 1) && ($data->monday == 1)) ||
						(($dayofweek == 2) && ($data->tuesday == 1)) || 
						(($dayofweek == 3) && ($data->wednesday == 1)) ||
						(($dayofweek == 4) && ($data->thursday == 1)) || 
						(($dayofweek == 5) && ($data->friday == 1)) ||
						(($dayofweek == 6) && ($data->saturday == 1)) ||
						(($dayofweek == 0) && ($data->sunday == 1))){
					$noslotsallowed = false;
					$data->starttime = make_timestamp($eventdate['year'], $eventdate['mon'], $eventdate['mday'], $data->starthour, $data->startminute);
					$conflicts = simplescheduler_get_conflicts($simplescheduler->id, $data->starttime, $data->starttime + $data->duration * 60, $data->teacherid, 0, SIMPLESCHEDULER_ALL, false);
					if (!$data->forcewhenoverlap && $conflicts) {
						$hasconflict = true;
					}
				}
			}
	
			if (isset($hasconflict))
			{
				$erroritem->message = get_string('error_overlappings', 'simplescheduler');
				$erroritem->on = 'range';
				$errors[] = $erroritem;
			}
	
			/// Finally check if some slots are allowed (an error is thrown to ask care to this situation)
			if ($noslotsallowed){
				unset($erroritem);
				$erroritem->message = get_string('allslotsincloseddays', 'simplescheduler');
				$erroritem->on = 'days';
				$errors[] = $erroritem;
			}
	
			// second error trap. For last error cases.
			if (count($errors)){
				$action = 'addsession';
				break;
			}
	
			/// Now create as many slots of $duration as will fit between $starttime and $endtime and that do not conflicts
			$countslots = 0;
			$couldnotcreateslots = '';
			$startfrom = $data->timestart;
			for ($d = 0; $d <= $fordays; $d ++){
				$starttime = $startfrom + ($d * DAYSECS);
				$eventdate = usergetdate($starttime);
				$dayofweek = $eventdate['wday'];
				if ((($dayofweek == 1) && ($data->monday == 1)) ||
						(($dayofweek == 2) && ($data->tuesday == 1)) ||
						(($dayofweek == 3) && ($data->wednesday == 1)) || 
						(($dayofweek == 4) && ($data->thursday == 1)) ||
						(($dayofweek == 5) && ($data->friday == 1)) ||
						(($dayofweek == 6) && ($data->saturday == 1)) ||
						(($dayofweek == 0) && ($data->sunday == 1))){
					$slot->starttime = make_timestamp($eventdate['year'], $eventdate['mon'], $eventdate['mday'], $data->starthour, $data->startminute);
					$data->timestart = $slot->starttime;
					$data->timeend = make_timestamp($eventdate['year'], $eventdate['mon'], $eventdate['mday'], $data->endhour, $data->endminute);
			
					// this corrects around midnight bug
					if ($data->timestart > $data->timeend){
						$data->timeend += DAYSECS;
					}
					if ($data->displayfrom == 'now'){
						$slot->hideuntil = time();
					} 
					else {
						$slot->hideuntil = make_timestamp($eventdate['year'], $eventdate['mon'], $eventdate['mday'], 6, 0) - $data->displayfrom;
					}
					if ($data->emailfrom == 'never'){
						$slot->emaildate = 0;
					} 
					else {
						$slot->emaildate = make_timestamp($eventdate['year'], $eventdate['mon'], $eventdate['mday'], 0, 0) - $data->emailfrom;
					}
					while ($slot->starttime <= $data->timeend - $data->duration * 60) {
						$conflicts = simplescheduler_get_conflicts($simplescheduler->id, $data->timestart, $data->timestart + $data->duration * 60, $data->teacherid, 0, SIMPLESCHEDULER_ALL, false);
						if ($conflicts) {
							if (!$data->forcewhenoverlap){
								print_string('conflictingslots', 'simplescheduler');
								echo '<ul>';
								foreach ($conflicts as $aConflict){
									$sql = "
										SELECT
										c.fullname,
										c.shortname,
										sl.starttime
										FROM
										{course} c,
										{simplescheduler} s,
										{simplescheduler_slots} sl
										WHERE
										s.course = c.id AND
										sl.simpleschedulerid = s.id AND
										sl.id = {$aConflict->id}
										";
									$conflictinfo = $DB->get_record_sql($sql);
									echo '<li> ' . userdate($conflictinfo->starttime) . ' ' . usertime($conflictinfo->starttime) . ' ' . get_string('incourse', 'simplescheduler') . ': ' . $conflictinfo->shortname . ' - ' . $conflictinfo->fullname . "</li>\n";
								}
								echo '</ul><br/>';
							}
							else{ // we force, so delete all conflicting before inserting .. where is the insert?
								foreach($conflicts as $conflict){
									simplescheduler_delete_slot($conflict->id);
								}
							}
						}
						else {
							$DB->insert_record('simplescheduler_slots', $slot, false);
							$countslots++;
						}
						$slot->starttime += $data->duration * 60;
						$data->timestart += $data->duration * 60;
					}
				}
			}
			echo $OUTPUT->heading(get_string('slotsadded', 'simplescheduler', $countslots));
			break;
		}
		/************************************ Deleting a slot ***********************************************/
		case 'deleteslot': {
			$slotid = required_param('slotid', PARAM_INT);
			simplescheduler_delete_slot($slotid, $simplescheduler);
			break;
		}
		/************************************ Deleting multiple slots ***********************************************/
		case 'deleteslots': {
			$slotids = required_param('items', PARAM_RAW);
			$slots = explode(",", $slotids);
			foreach($slots as $aSlotId){
				simplescheduler_delete_slot($aSlotId, $simplescheduler);
			}
			break;
		}
		/************************************ Revoking one appointment from a slot ***************************************
		 * @todo deleting and creating the calendar event is not efficient - we should add support for a student id.
		 */
		case 'revokeone': {
			$slotid = required_param('slotid', PARAM_INT);
			$studentid = required_param('studentid', PARAM_INT);
			if (!empty($slotid) && !empty($studentid)) {
				$result = simplescheduler_teacher_revoke_appointment($slotid, $studentid);
				notify(get_string($result, 'simplescheduler'));
			}
			break;
		}

		/************************************ Toggling to unlimited group ***************************************/
		case 'allowgroup':{
			$slotid = required_param('slotid', PARAM_INT);
			$slot = new stdClass();
			$slot->id = $slotid;
			$slot->exclusivity = 0;
			$DB->update_record('simplescheduler_slots', $slot);
			break;
		}

		/************************************ Toggling to single student ******************************************/
		case 'forbidgroup':{
			$slotid = required_param('slotid', PARAM_INT);
			$slot = new stdClass();
			$slot->id = $slotid;
			$slot->exclusivity = 1;
			$DB->update_record('simplescheduler_slots', $slot);
			break;
		}

		/************************************ Deleting all slots ***************************************************/
		case 'deleteall':{
			if (has_capability('mod/simplescheduler:manageallappointments', $context)){
				if ($slots = $DB->get_records('simplescheduler_slots', array('simpleschedulerid' => $cm->instance))){
					foreach($slots as $aSlot){
						simplescheduler_delete_slot($aSlot->id, $simplescheduler);
					}           
				}
			}      
			break;
		}
		/************************************ Deleting unused slots *************************************************/
		// MUST STAY HERE, JUST BEFORE deleteallunused
		case 'deleteunused':{
			$teacherClause = " AND s.teacherid = {$USER->id} ";
		}
		/************************************ Deleting unused slots (all teachers) ************************************/
		case 'deleteallunused': {
			if (!isset($teacherClause)) $teacherClause = '';
			if (has_capability('mod/simplescheduler:manageallappointments', $context)){
				$sql = "
					SELECT
					s.id,
					s.simpleschedulerid
					FROM
					{simplescheduler_slots} s
					LEFT JOIN
					{simplescheduler_appointment} a
					ON
					s.id = a.slotid
					WHERE
					s.simpleschedulerid = ? AND a.studentid IS NULL
					{$teacherClause}
					";
				if ($unappointed = $DB->get_records_sql($sql, array($simplescheduler->id))) {
					foreach ($unappointed as $aSlot) {
						simplescheduler_delete_slot($aSlot->id, $simplescheduler);
					}
				}
			}
			break;
		}
		/************************************ Deleting current teacher's slots ***************************************/
		case 'deleteonlymine': {
			if ($slots = $DB->get_records_select('simplescheduler_slots', "simpleschedulerid = {$cm->instance} AND teacherid = {$USER->id}", null, '', 'id')) {
				foreach($slots as $aSlot) {
					simplescheduler_delete_slot($aSlot->id, $simplescheduler);
				}
			}
			break;
		}
		/************************************ Sign up a student for a slot ******************************************/
		case 'addstudent': {
			// get expected parameters
			$slotid = optional_param('slotid', '', PARAM_INT);
			$studentid = optional_param('studentid', '', PARAM_INT);
		
			if (!empty($studentid) && !empty($slotid))
			{
				$result = simplescheduler_teacher_appoint_student($slotid, $studentid);
				notify(get_string($result, 'simplescheduler'));
				break;
			}
		}
	}
}

/************************************ View : New single slot form ****************************************/
if ($action == 'addslot'){
	echo $OUTPUT->heading(get_string('addsingleslot', 'simplescheduler'));
    $form = new stdClass();
    if (!empty($errors)) {
        get_slot_data($form);
        $form->what = 'doaddupdateslot';
        $form->appointments = $appointments;
    } else {
        $form->what = 'doaddupdateslot';
        // blank appointment data
        if (empty($form->appointments)) $form->appointments = array();
        $form->starttime = time();
        $form->duration = 15;
        $form->exclusivity = 1;
        $form->hideuntil = $simplescheduler->timemodified; // supposed being in the past so slot is visible
        $form->notes = '';
        $form->teacherid = $USER->id;
        $form->appointmentlocation = simplescheduler_get_last_location($simplescheduler);
    }
    
    /// print errors
    if (!empty($errors)){
        $errorstr = '';
        foreach($errors as $anError){
            $errorstr .= $anError->message;
        }
        echo $OUTPUT->box($errorstr, 'errorbox');
    }
    
    /// print form
    echo $OUTPUT->box_start('boxaligncenter');
    include('oneslotform.html');
    echo $OUTPUT->box_end();
    echo '<br />';
    
    // return code for include ... what is this for?
    return -1;
}
/************************************ View: Update single appointment form *********************************/
if ($action == 'updateappointment') {
	include_once('appointmentform.php');
	$appointment_id = required_param('appointmentid', PARAM_INT);
	
	$mform = new appointment_form();
	if ($mform->is_cancelled()) {
    	// was cancelled
	} else if ($fromform = $mform->get_data()) {
		// do something with validated data.
	} else {
	$mform->set_data($toform);
	}
	//displays the form
	echo $OUTPUT->box_start('boxaligncenter');
	$mform->display();
	echo $OUTPUT->box_end();
	return -1;
}
/************************************ View: Update single slot form ****************************************/
if ($action == 'updateslot') {
    $slotid = required_param('slotid', PARAM_INT);
    
    echo $OUTPUT->heading(get_string('updatesingleslot', 'simplescheduler'));
    $form = new stdClass();
    
    if(!empty($errors)){ // if some errors, get data from client side
    	get_slot_data($form);
    	$form->appointments = unserialize(stripslashes(required_param('appointments', PARAM_RAW)));
    } else {
    	/// get data from the last inserted
    	$slot = $DB->get_record('simplescheduler_slots', array('id'=>$slotid));
    	$form = &$slot;
		// get all appointments for this slot
		$form->appointments = array();
		$appointments = $DB->get_records('simplescheduler_appointment', array('slotid'=>$slotid));
		// convert appointement keys to studentid
		if ($appointments){
			foreach($appointments as $appointment){
				$form->appointments[$appointment->studentid] = $appointment;
			}
		}
	}
    
    // print errors and notices
    if (!empty($errors)){
        $errorstr = '';
        foreach($errors as $anError){
            $errorstr .= $anError->message;
        }
        echo $OUTPUT->box($errorstr, 'errorbox');
    }
    
    /// print form
    $form->what = 'doaddupdateslot';
    
    echo $OUTPUT->box_start('boxaligncenter');
    include('oneslotform.html');
    echo $OUTPUT->box_end();
    echo '<br />';
    
    // return code for include
    return -1;
}
/************************************ Add session multiple slots form ****************************************/
if ($action == 'addsession') {
    // if there is some error from controller, display it
    if (!empty($errors)){
        $errorstr = '';
        foreach($errors as $anError){
            $errorstr .= $anError->message;
        }
        echo $OUTPUT->box($errorstr, 'errorbox');
    }
    
    $form = new stdClass();
    if (!empty($errors)){
        get_session_data($data);
        $form = &$data;
    } else {
        $form->rangestart = time();
        $form->rangeend = time();
        $form->timestart = time();
        $form->timeend = time() + HOURSECS;
        $form->hideuntil = $simplescheduler->timemodified;
        $form->duration = $simplescheduler->defaultslotduration;
        $form->forcewhenoverlap = 0;
        $form->teacherid = $USER->id;
        $form->exclusivity = 1;
        $form->duration = $simplescheduler->defaultslotduration;
        $form->monday = 1;
        $form->tuesday = 1;
        $form->wednesday = 1;
        $form->thursday = 1;
        $form->friday = 1;
        $form->saturday = 0;
        $form->sunday = 0;
    }
    
    echo $OUTPUT->heading(get_string('addsession', 'simplescheduler'));
    echo $OUTPUT->box_start('boxaligncenter');
    include_once('addslotsform.html');
    echo $OUTPUT->box_end();
    echo '<br />';
    
    // return code for include
    return -1;
}

//****************** Standard view ***********************************************//
/// print top tabs
$tabrows = array();
$row  = array();

switch ($action){
    case 'datelist':{
        $currenttab = get_string('datelist', 'simplescheduler');
        break;
    }
    case 'viewstudent':{
        $currenttab = get_string('studentdetails', 'simplescheduler');
        $row[] = new tabobject($currenttab, '', $currenttab);
        break;
    }
    case 'downloads':{
        $currenttab = get_string('downloads', 'simplescheduler');
        break;
    }
    default: {
        $currenttab = get_string($page, 'simplescheduler');
    }
}

$tabname = get_string('myappointments', 'simplescheduler');
$row[] = new tabobject($tabname, "view.php?id={$cm->id}&amp;page=myappointments", $tabname);
if ($DB->count_records('simplescheduler_slots', array('simpleschedulerid'=>$simplescheduler->id)) > $DB->count_records('simplescheduler_slots', array('simpleschedulerid'=>$simplescheduler->id, 'teacherid'=>$USER->id))) {
    $tabname = get_string('allappointments', 'simplescheduler');
    $row[] = new tabobject($tabname, "view.php?id={$cm->id}&amp;page=allappointments", $tabname);
} else {
    // we are alone in this simplescheduler
    if ($page == 'allappointements') {
        $currenttab = get_string('myappointments', 'simplescheduler');
    }
}
$tabname = get_string('datelist', 'simplescheduler');
$row[] = new tabobject($tabname, "view.php?id={$cm->id}&amp;what=datelist", $tabname);
$tabname = get_string('downloads', 'simplescheduler');
$row[] = new tabobject($tabname, "view.php?what=downloads&amp;id={$cm->id}&amp;course={$simplescheduler->course}", $tabname);
$tabrows[] = $row;
print_tabs($tabrows, $currenttab);

/// print heading
echo $OUTPUT->heading($simplescheduler->name);

/// print page
if (trim(strip_tags($simplescheduler->intro))) {
    echo $OUTPUT->box_start('mod_introbox');
    echo format_module_intro('simplescheduler', $simplescheduler, $cm->id);
    echo $OUTPUT->box_end();
}

if ($page == 'allappointments'){
    $select = "simpleschedulerid = '". $simplescheduler->id ."'";
} else {
    $select = "simpleschedulerid = '". $simplescheduler->id ."' AND teacherid = '{$USER->id}'";
    $page = 'myappointments';
}
$sqlcount = $DB->count_records_select('simplescheduler_slots',$select);

if (($offset == '') && ($sqlcount > 25)){
    $offsetcount = $DB->count_records_select('simplescheduler_slots', $select." AND starttime < '".strtotime('now')."'");
    $offset = floor($offsetcount/25);
}


$slots = $DB->get_records_select('simplescheduler_slots', $select, null, 'starttime', '*', $offset * 25, 25);
if ($slots){
    foreach(array_keys($slots) as $slotid){
        $slots[$slotid]->isappointed = $DB->count_records('simplescheduler_appointment', array('slotid'=>$slotid));
    }
}

$straddsession = get_string('addsession', 'simplescheduler');
$straddsingleslot = get_string('addsingleslot', 'simplescheduler');
$strdownloadexcel = get_string('downloadexcel', 'simplescheduler');

// get possible attendees
$students = simplescheduler_get_possible_attendees($cm, $usergroups); 

/// some slots already exist
if ($slots){
    // print instructions and button for creating slots
    echo $OUTPUT->box_start('boxaligncenter');
    
    // these instructions are too redundant and in prime real estate - the buttons themselves are quite explanatory
    //print_string('addslot', 'simplescheduler');
    
    // print add session button
    $strdeleteallslots = get_string('deleteallslots', 'simplescheduler');
    $strdeleteallunusedslots = get_string('deleteallunusedslots', 'simplescheduler');
    $strdeleteunusedslots = get_string('deleteunusedslots', 'simplescheduler');
    $strdeletemyslots = get_string('deletemyslots', 'simplescheduler');
    $strstudents = get_string('students', 'simplescheduler');
    $displaydeletebuttons = 1;
    include $CFG->dirroot.'/mod/simplescheduler/commands.html';
    echo $OUTPUT->box_end();

    // prepare slots table
    $table = new html_table();
    if ($page == 'myappointments'){
        $table->head  = array ($strdate, $strstart, $strend, $strstudents, $straction);
        $table->align = array ('LEFT', 'LEFT', 'LEFT', 'LEFT', 'LEFT');
    } else {
        $table->head  = array ($strdate, $strstart, $strend, $strstudents, s(simplescheduler_get_teacher_name($simplescheduler)), $straction);
        $table->align = array ('LEFT', 'LEFT', 'LEFT', 'LEFT', 'LEFT', 'LEFT');
    }
    $table->width = '90%';
    $table->attributes = array('class' => 'generaltable boxaligncenter');
    $offsetdatemem = '';
    $appointedstudentids = '';
    $has_appointment = array();
    foreach($slots as $slot) {
        
        //if (!$slot->isappointed && $slot->starttime + (60 * $slot->duration) < time()) {
            // This slot is in the past and has not been chosen by any student, so delete
         //   $DB->delete_records('simplescheduler_slots', array('id'=>$slot->id));
          //  continue;
        //}
        
        /// Parameter $local in simplescheduler_userdate and simplescheduler_usertime added by power-web.at
        /// When local Time or Date is needed the $local Param must be set to 1
        $offsetdate = simplescheduler_userdate($slot->starttime,1);
        $offsettime = simplescheduler_usertime($slot->starttime,1);
        $endtime = simplescheduler_usertime($slot->starttime + ($slot->duration * 60),1);
        
        // slot is appointed
        $studentArray = array();
        $slotappointedstudentids = array();
        if ($slot->isappointed) {
        	$strrevoke = get_string('revoke', 'simplescheduler');
        	$studentcolumn = '';
            $appointedstudents = $DB->get_records('simplescheduler_appointment', array('slotid'=>$slot->id));
            foreach($appointedstudents as $appstudent){
                $student = $DB->get_record('user', array('id'=>$appstudent->studentid));
                $slotappointedstudentids[$appstudent->studentid] = $appstudent->studentid;
                $appointedstudentids[$appstudent->studentid] = $appstudent->studentid;
                if ($student) {
                    $name = "<a href=\"view.php?what=viewstudent&amp;id={$cm->id}&amp;studentid={$student->id}&amp;course={$simplescheduler->course}&amp;order=DESC\">".fullname($student).'</a>';
                }
                $studentcolumn .= "<p>$name";
                $studentcolumn .= "<span style=\"font-size: x-small;\"><a href=\"view.php?what=revokeone&amp;id={$cm->id}&amp;slotid={$slot->id}&amp;studentid={$student->id}&amp;page={$page}\" title=\"{$strrevoke}\"><img align=\"right\" src=\"{$CFG->wwwroot}/pix/t/delete.gif\" alt=\"{$strrevoke}\" /></a></span></p>";
        
            }
        } else {
            // slot is free
            $studentcolumn = "";
            $slotappointedstudentids = array();
        }

		$eligible_to_add = array();
		// lets find out if we have eligible students to add to this slot
		foreach ($students as $studentid => $student)
        {
        	if (!isset($slotappointedstudentids[$studentid]))
        	{
        		if ($simplescheduler->simpleschedulermode == 'oneonly')
        		{
        			if (!isset($has_appointment[$studentid]))
        			{
        				$has_appointment[$studentid] = simplescheduler_student_has_appointment($studentid, $simplescheduler->id);
        			}
        			if ($has_appointment[$studentid]) continue; // student can only have one and already has one.
        		}
        		$eligible_to_add[$studentid] = $student;
        	}
        }
		
		if (!empty($eligible_to_add))
		{
			// lets make a form here that lets us add an eligible student
			$form = '<div class="addStudent">';
			// lets add add student form for this slot to actions (if available)
			$form .= '<form name="addtoslotform" method="post" action="view.php?id=2">';
			$form .= '<input type="hidden" value="addstudent" name="what"></input>';
			$form .= '<input type="hidden" value="'.$cm->id.'" name="id"></input>';
			$form .= '<input type="hidden" value="'.$slot->id.'" name="slotid"></input>';
			$form .= '<input type="hidden" value="allappointments" name="page"></input>';
			$form .= '<select name="studentid">';
			$form .= '<option value="">'.get_string('add_a_student_pulldown', 'simplescheduler').'</option>';
			foreach ($eligible_to_add as $studentid => $student)
			{
				$form .= '<option value="'.$studentid.'">'.fullname($student).'</option>';
			}
			$form .= '<input type="submit" value="Add" name="go_btn"></input>';
			$form .= '</form>';
			$form .= '</div>';
			$studentcolumn .= $form;
		}
		
        $studentArray[] = (!empty($studentcolumn)) ? $studentcolumn : get_string('empty_slot_no_availability', 'simplescheduler');
        
        $actions = '<span style="font-size: x-small;">';
        if ($USER->id == $slot->teacherid || has_capability('mod/simplescheduler:manageallappointments', $context)){
            
            $strdelete = get_string('delete');
            $stredit = get_string('move','simplescheduler');
            $strnonexclusive = get_string('isnonexclusive', 'simplescheduler');
            $strallowgroup = get_string('allowgroup', 'simplescheduler');
            $strforbidgroup = get_string('forbidgroup', 'simplescheduler');
            
            $actions .= "<a href=\"view.php?what=deleteslot&amp;id={$cm->id}&amp;slotid={$slot->id}&amp;page={$page}\" title=\"{$strdelete}\"><img src=\"{$CFG->wwwroot}/pix/t/delete.gif\" alt=\"{$strdelete}\" /></a>";
            $actions .= "&nbsp;<a href=\"view.php?what=updateslot&amp;id={$cm->id}&amp;slotid={$slot->id}&amp;page={$page}\" title=\"{$stredit}\"><img src=\"{$CFG->wwwroot}/pix/t/edit.gif\" alt=\"{$stredit}\" /></a>";
            if ($slot->isappointed > 1){
                    $actions .= "&nbsp;<img src=\"{$CFG->wwwroot}/pix/c/group.gif\" title=\"{$strnonexclusive}\" />";
                } else {
                if ($slot->exclusivity == 1){
                    $actions .= "&nbsp;<a href=\"view.php?what=allowgroup&amp;id={$cm->id}&amp;slotid={$slot->id}&amp;page={$page}\" title=\"{$strallowgroup}\"><img src=\"{$CFG->wwwroot}/pix/t/groupn.gif\" alt=\"{$strallowgroup}\" /></a>";
                } else {
                	$actions .= "&nbsp;<a href=\"view.php?what=forbidgroup&amp;id={$cm->id}&amp;slotid={$slot->id}&amp;page={$page}\" title=\"{$strforbidgroup}\"><img src=\"{$CFG->wwwroot}/pix/t/groupv.gif\" alt=\"{$strforbidgroup}\" /></a>";
                }
            }
            
        } else {
            // just signal group status
            if ($slot->isappointed > 1)  {
                $actions .= "&nbsp;<img src=\"{$CFG->wwwroot}/pix/c/group.gif\" title=\"{$strnonexclusive}\" />";
            } else {
                if ($slot->exclusivity == 1){
                    $actions .= "&nbsp;<img src=\"{$CFG->wwwroot}/pix/t/groupn.gif\" title=\"{$strallowgroup}\" />";
                } else {
                    $actions .= "&nbsp;<img src=\"{$CFG->wwwroot}/pix/t/groupv.gif\" title=\"{$strforbidgroup}\" />";
                }
            }
        }
        
        if ($slot->exclusivity > 1){
            $actions .= ' ('.$slot->exclusivity.')';
        }
        $actions .= '</span>';
                
        if($page == 'myappointments'){
            $table->data[] = array (($offsetdate == $offsetdatemem) ? '' : $offsetdate, $offsettime, $endtime, implode("\n",$studentArray), $actions);
        } else {
            $teacherlink = "<a href=\"$CFG->wwwroot/user/view.php?id={$slot->teacherid}\">".fullname($DB->get_record('user', array('id'=> $slot->teacherid)))."</a>";
            $table->data[] = array (($offsetdate == $offsetdatemem) ? '' : $offsetdate, $offsettime, $endtime, implode("\n",$studentArray), $teacherlink, $actions);
        }
        $offsetdatemem = $offsetdate;
    }
    
    // print slots table
    echo $OUTPUT->heading(get_string('slots' ,'simplescheduler'));
    echo html_writer::table($table);
    ?>


<?php
if ($sqlcount > 25) {
	$table = new html_table();
    $str = "Page : ";
    $pagescount = ceil($sqlcount/25);
    for ($n = 0; $n < $pagescount; $n ++){
        if ($n == $offset){
            $str .= ($n+1).' ';
        } else {
            $str .= "<a href=view.php?id={$cm->id}&amp;page={$page}&amp;offset={$n}>".($n+1)."</a> ";
        }
    }
    $table->data[] = array($str);
    $table->attributes = array('class' => 'generaltable boxaligncenter');
    $table->width = '90%';
    echo html_writer::table($table);
}


// Instruction for teacher to click Seen box after appointment
//echo '<br /><center>' . get_string('markseen', 'simplescheduler') . '</center>';

} else if ($action != 'addsession') {
    /// There are no slots, should the teacher be asked to make some
    echo $OUTPUT->box_start('boxaligncenter', '', '');
    
    // these instructions are too redundant - the buttons themselves are quite explanatory
    //print_string('welcomenewteacher', 'simplescheduler');
    $displaydeletebuttons = 0;
    include $CFG->dirroot.'/mod/simplescheduler/commands.html';
    echo $OUTPUT->box_end();
}

/// print table of outstanding appointer (students)
?>
<table width="90%" class="boxaligncenter">
    <tr valign="top">
        <td width="50%">
<?php

//echo $OUTPUT->heading(get_string('schedulestudents', 'simplescheduler'));

if (!$students) {
    $nostudentstr = get_string('noexistingstudents','simplescheduler');
    if ($COURSE->id == SITEID){
        $nostudentstr .= '<br/>'.get_string('howtoaddstudents','simplescheduler');
    }
    echo $OUTPUT->notification($nostudentstr);
} else {
    $mtable = new html_table();
    
    // build table header
    $mtable->head  = array ('', $strname);
    $mtable->align = array ('CENTER','LEFT');
    $extrafields = simplescheduler_get_user_fields(null);
	foreach ($extrafields as $field) {
    	$mtable->head[] = $field->title;
    	$mtable->align[] = 'LEFT';	    
	}    
    // end table header
    
    $mtable->data = array();
    // In $mailto the mailing list for reminder emails is built up
    $mailto = '<a href="mailto:';
    // $maillist will hold a list of email addresses for people who prefer to cut
    // and paste into their To field rather than using the mailto link
    $maillist = array();
    $date = usergetdate(time());
    foreach ($students as $student) {
        if (!simplescheduler_has_slot($student->id, $simplescheduler, true)) {
            $picture = $OUTPUT->user_picture($student);
            $name = "<a href=\"../../user/view.php?id={$student->id}&amp;course={$simplescheduler->course}\">";
            $name .= fullname($student);
            $name .= '</a>';
            if (simplescheduler_has_slot($student->id, $simplescheduler, true, false) == 0){
                // student has never scheduled
                $mailto .= $student->email.', ';
                $maillist[] = $student->email; // constructing list of email addresses to be shown later
            }
            
            
            $args['what'] = 'schedule';
            $args['id'] = $cm->id;
            $args['studentid'] = $student->id;
            $args['page'] = $page;
            $url = new moodle_url('view.php',$args);
            
            $starttimenow = time();
            $appointment = new stdClass();
            $appointment->slotid = -1;
            $appointment->studentid = $student->id;
            $appointment->appointmentnote = '';
            $appointment->attended = 1;
            $appointment->notes = '';
            $appointment->timecreated = time();
            $appointment->timemodified = time();
            $appointmentarr = array($appointment);
            $appointmentser = serialize($appointmentarr);
            
            $args['what'] = 'doaddupdateslot';
            $args['id'] = $cm->id;
            $args['teacherid'] = $USER->id;
            $args['seen'] = 1;
            $args['appointments'] = $appointmentser;
            $args['starttime'] = $starttimenow;
            $args['duration'] = $simplescheduler->defaultslotduration;
            $args['hideuntil'] = $simplescheduler->timemodified;
            $args['appointmentlocation'] = '';
            $args['exclusivity'] = '1';
            $args['notes'] = '';
            $url = new moodle_url('view.php',$args);
            
            $newdata = array($picture, $name);
            $extrafields = simplescheduler_get_user_fields($student);
            foreach ($extrafields as $field) {
                $newdata[] = $field->value;
            }                
            $mtable->data[] = $newdata;
        }
    }
    
    // dont print if allowed to book multiple appointments
    // There are students who still have to make appointments
    if (($num = count($mtable->data)) > 0) {
        
        // Print number of students who still have to make an appointment
        echo $OUTPUT->heading(get_string('missingstudents', 'simplescheduler', $num), 3);
        
        // Print links to print invitation or reminder emails
        $strinvitation = get_string('invitation', 'simplescheduler');
        $strreminder = get_string('reminder', 'simplescheduler');
        $mailto = rtrim($mailto, ', ');
        
        $subject = $strinvitation . ': ' . $simplescheduler->name;
        $body = $strinvitation . ': ' . $simplescheduler->name . "\n\n";
        $body .= get_string('invitationtext', 'simplescheduler');
        $body .= "{$CFG->wwwroot}/mod/simplescheduler/view.php?id={$cm->id}";
        $maildisplay = '';
        if ($CFG->simplescheduler_showemailplain) {
        	$maildisplay .= '<p>'.implode(', ', $maillist).'</p>';
        }
        $maildisplay .= get_string('composeemail', 'simplescheduler'). 
            $mailto.'?subject='.htmlentities(rawurlencode($subject)).
            '&amp;body='.htmlentities(rawurlencode($body)).
            '"> '.$strinvitation.'</a> ';
        $maildisplay .= ' &mdash; ';
        
        $subject = $strreminder . ': ' . $simplescheduler->name;
        $body = $strreminder . ': ' . $simplescheduler->name . "\n\n";
        $body .= get_string('remindertext', 'simplescheduler');
        $body .= "{$CFG->wwwroot}/mod/simplescheduler/view.php?id={$cm->id}";
        $maildisplay .= $mailto.'?subject='.htmlentities(rawurlencode($subject)). 
            '&amp;body='.htmlentities(rawurlencode($body)).
            '"> '.$strreminder.'</a>';
        echo $OUTPUT->box($maildisplay); 
        
        // print table of students who still have to make appointments
        echo html_writer::table($mtable);
    } else {
        echo $OUTPUT->notification(get_string('allappointed', 'simplescheduler'));
    }
}
?>
        </td>
<?php
if (simplescheduler_group_scheduling_enabled($course, $cm)){
    ?>
        <td width="50%">
<?php

/// print table of outstanding appointer (groups)

echo $OUTPUT->heading(get_string('schedulegroups', 'simplescheduler'));

if (empty($groups)){
    echo $OUTPUT->notification(get_string('nogroups', 'simplescheduler'));
} else {
	$mtable = new html_table();
    $mtable->head  = array ('', $strname, $straction);
    $mtable->align = array ('CENTER', 'LEFT', 'CENTER');
    foreach($groups as $group) {
        $members = groups_get_members($group->id, 'u.id, lastname, firstname, email, picture', 'lastname, firstname');
        if (empty($members)) continue;
        if (!simplescheduler_has_slot(implode(',', array_keys($members)), $simplescheduler, true)) {
            $actions = '';
            $actions .= "<a href=\"view.php?what=schedulegroup&amp;id={$cm->id}&amp;groupid={$group->id}&amp;page={$page}\">";
            $actions .= get_string('schedule', 'simplescheduler');
            $actions .= '</a>';
            $groupmembers = array();
            foreach($members as $member){
                $groupmembers[] = fullname($member);
            }
            $groupcrew = '['. implode(", ", $groupmembers) . ']';
            $mtable->data[] = array('', $groups[$group->id]->name.' '.$groupcrew, $actions);
        }
    }
    // print table of students who still have to make appointments
    if (!empty($mtable->data)){
        echo html_writer::table($mtable);
    } else {
        echo $OUTPUT->notification(get_string('nogroups', 'simplescheduler'));
    }
}
?>
        </td>
<?php
}
?>
    </tr>
</table>

<form action="<?php echo "{$CFG->wwwroot}/course/view.php" ?>" method="get">
    <input type="hidden" name="id" value="<?php p($course->id) ?>" />
    <input type="submit" name="go_btn" value="<?php print_string('return', 'simplescheduler') ?>" />
</form>