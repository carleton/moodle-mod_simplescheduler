<?php

/**
 * Controller for all teacher-related views.
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @todo review to make sure capabilities are checked as appropriate
 * @todo revamp revokeone to utilize existing/new methods from locallib.php
 * @todo notify needs to use strings from lang file
 * @todo fix conflict handling in "add slots" view
 */

defined('MOODLE_INTERNAL') || die();


// We first have to check whether some action needs to be performed
switch ($action) {
/************************************ creates or updates a slot ***********************************************/
 /*
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
                        else{ // we force, so delete all conflicting before inserting
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

/*************************************************************************************************************/
?>