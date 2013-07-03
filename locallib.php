<?php

/**
 * General library for the simplescheduler module.
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @todo should we remove and stop calling simplescheduler_free_late_unused_slots?
 * @todo notifications are send indiscriminately right now should we skip them for appointments that have passed?
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/customlib.php');

/**
 * Parameter $local added by power-web.at
 * When local Date is needed the $local Param must be set to 1 
 * @param int $date a timestamp
 * @param int $local
 * @todo check consistence
 * @return string printable date
 */
function simplescheduler_userdate($date, $local=0) {
    if ($date == 0) {
        return '';
    } else {
        return userdate($date, get_string('strftimedaydate'));
    }
}

/**
 * Parameter $local added by power-web.at 
 * When local Time is needed the $local Param must be set to 1 
 * @param int $date a timestamp
 * @param int $local
 * @todo check consistence
 * @return string printable time
 */
function simplescheduler_usertime($date, $local=0) {
    if ($date == 0) {
        return '';
    } else {
        $timeformat = get_user_preferences('calendar_timeformat');//get user config
        if(empty($timeformat)){
            $timeformat = get_config(NULL,'calendar_site_timeformat');//get calendar config	if above not exist
        }
        if(empty($timeformat)){
            $timeformat = get_string('strftimetime');//get locale default format if both above not exist
        }
        return userdate($date, $timeformat);    
    }
}

/**
 * get list of attendants for slot form
 * @param int $cmid the course module
 * @return array of moodle user records
 */
function simplescheduler_get_attendants($cmid){
    $context = get_context_instance(CONTEXT_MODULE, $cmid);
    $attendants = get_users_by_capability ($context, 'mod/simplescheduler:attend', 'u.id,lastname,firstname,email,picture', 'lastname, firstname', '', '', '', '', false, false, false);
    return $attendants;
}

/**
 * get list of possible attendees (i.e., users that can make an appointment)
 * @param int $cm the course module
 * @param $groups - single group or array of groups - only return
 *                  users who are in one of these group(s).
 * @return array of moodle user records
 */
function simplescheduler_get_possible_attendees($cm, $groups=''){
		
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $attendees = get_users_by_capability($context, 'mod/simplescheduler:appoint', '', 'lastname, firstname', '', '', $groups, '', false, false, false);
    return $attendees;
}

/**
 * Returns an array of slots that would overlap with this one.
 * @param int $simpleschedulerid the current activity module id
 * @param int $starttimethe start of time slot as a timestamp
 * @param int $endtime end of time slot as a timestamp
 * @param int $teacher if not null, the id of the teacher constraint, 0 otherwise standas for "all teachers"
 * @param int $others selects where to search for conflicts, [SCHEDULER_SELF, SCHEDULER_OTHERS, SCHEDULER_ALL]
 * @param boolean $careexclusive if false, conflict will consider all slots wether exlusive or not. Use it for testing if user is appointed in the given scope.
 * @uses $CFG
 * @uses $DB
 * @return array array of conflicting slots
 */
function simplescheduler_get_conflicts($simpleschedulerid, $starttime, $endtime, $teacher=0, $student=0, $others=SCHEDULER_SELF, $careexclusive=true) {
    global $CFG, $DB;
    
    switch ($others){
        case SCHEDULER_SELF:
            $simpleschedulerScope = "s.simpleschedulerid = {$simpleschedulerid} AND ";
            break;
        case SCHEDULER_OTHERS:
            $simpleschedulerScope = "s.simpleschedulerid != {$simpleschedulerid} AND ";
            break;
        default:
            $simpleschedulerScope = '';
    }
    $teacherScope = ($teacher != 0) ? "s.teacherid = {$teacher} AND " : '' ;
    $studentJoin = ($student != 0) ? "JOIN {simplescheduler_appointment} a ON a.slotid = s.id AND a.studentid = {$student} " : '' ;
    $exclusiveClause = ($careexclusive) ? "exclusivity != 0 AND " : '' ;
	$timeClause = "( (s.starttime <= {$starttime} AND s.starttime + s.duration * 60 > {$starttime}) OR ".
        		  "  (s.starttime < {$endtime} AND s.starttime + s.duration * 60 >= {$endtime}) OR ".
        		  "  (s.starttime >= {$starttime} AND s.starttime + s.duration * 60 <= {$endtime}) ) ";

    $sql = 'SELECT s.* from {simplescheduler_slots} s '.$studentJoin.' WHERE '.
    		 $simpleschedulerScope.$teacherScope.$exclusiveClause.$timeClause;
        
    $conflicting = $DB->get_records_sql($sql);
    
    return $conflicting;
}

/**
 * retreives the unappointed slots
 * @param int $simpleschedulerid
 * @uses $CFG
 * @uses $DB
 */
function simplescheduler_get_unappointed_slots($simpleschedulerid){
    global $CFG, $DB;
    
    $sql = '
        SELECT
        s.*,
        MAX(a.studentid) AS appointed
        FROM
        {simplescheduler_slots} s
        LEFT JOIN
        {simplescheduler_appointment} a
        ON
        a.slotid = s.id
        WHERE
        s.simpleschedulerid = ?
        GROUP BY
        s.id
        HAVING
        appointed = 0 OR appointed IS NULL
        ORDER BY
        s.starttime ASC
        ';
    $recs = $DB->get_records_sql($sql, array($simpleschedulerid));
    return $recs;
}

/**
 * retreives the available slots in several situations with a complex query
 * @param int $studentid
 * @param int $simpleschedulerid
 * @param boolean $studentside changes query if we are getting slots in student context
 * @uses $CFG
 * @uses $DB
 *
 * @todo do we need to retain this studentside appointedbyme thing?
 */
function simplescheduler_get_available_slots($studentid, $simpleschedulerid, $studentside=false){
    global $CFG, $DB;
    
    // more compatible tryout
    $slots = $DB->get_records('simplescheduler_slots', array('simpleschedulerid' => $simpleschedulerid), 'starttime');
    $retainedslots = array();
    if ($slots){
        foreach($slots as $slot){
            $slot->population = $DB->count_records('simplescheduler_appointment', array('slotid' => $slot->id));
            $slot->appointed = ($slot->population > 0);
            $slot->attended = $DB->record_exists('simplescheduler_appointment', array('slotid' => $slot->id, 'attended' => 1));
            if ($studentside){
                $slot->appointedbyme = $DB->record_exists('simplescheduler_appointment', array('slotid' => $slot->id, 'studentid' => $studentid));
                if ($slot->appointedbyme) {
                    $retainedslots[] = $slot;
                    continue;
                }
            }
            // both side, slot is not complete
            if ($slot->exclusivity == 0 or ($slot->exclusivity > 0 and $slot->population < $slot->exclusivity)){
                $retainedslots[] = $slot;
                continue;
            }
        }
    }
    
    return $retainedslots;
}

/**
 * Returns true if a student has an appointment in a particular simplescheduler.
 *
 * @param int $studentid
 * @param int $simpleschedulerid
 * @return boolean
 */
function simplescheduler_student_has_appointment($studentid, $simpleschedulerid)
{
	global $DB;
	$sql = '
			SELECT
			COUNT(*)
			FROM
			{simplescheduler_slots} s,
			{simplescheduler_appointment} a
			WHERE
			s.id = a.slotid AND
			a.studentid = ? AND
			s.simpleschedulerid = ?
    	';
    return ($DB->count_records_sql($sql, array($studentid, $simpleschedulerid)));
}

/**
 * checks if user has an appointment in this simplescheduler
 * @param object $userlist
 * @param object $simplescheduler
 * @param boolean $student, if true, is a student, a teacher otherwise
 * @param boolean $unattended, if true, only checks for unattended slots
 * @param string $otherthan giving a slotid, excludes this slot from the search
 * @uses $CFG
 * @uses $DB
 * @return the count of records
 */
function simplescheduler_has_slot($userlist, &$simplescheduler, $student=true, $unattended = false, $otherthan = 0){
    global $CFG, $DB;
    
    $userlist = str_replace(',', "','", $userlist);
    
    $unattendedClause = ($unattended) ? ' AND a.attended = 0 ' : '' ;
    $otherthanClause = ($otherthan) ? " AND a.slotid != $otherthan " : '' ;
    
    if ($student){
        $sql = "
            SELECT
            COUNT(*)
            FROM
            {simplescheduler_slots} s,
            {simplescheduler_appointment} a
            WHERE
            a.slotid = s.id AND
            s.simpleschedulerid = ? AND
            a.studentid IN ('{$userlist}')
            $unattendedClause
            $otherthanClause
            ";
        return $DB->count_records_sql($sql, array($simplescheduler->id));
    } else {
        return $DB->count_records('simplescheduler_slots', array('teacherid' => $userlist, 'simpleschedulerid' => $simplescheduler->id));
    }
}

/**
 * returns an array of appointed user records for a certain slot.
 * @param int $slotid
 * @uses $CFG
 * @uses $DB
 * @return an array of users
 */
function simplescheduler_get_appointed($slotid){
    global $CFG, $DB;
    
    $sql = "
        SELECT
        u.*
        FROM
        {user} u,
        {simplescheduler_appointment} a
        WHERE
        u.id = a.studentid AND
        a.slotid = ?
        ";
    return $DB->get_records_sql($sql, array($slotid));
}

/**
 * Fully deletes a slot with all dependencies
 *
 * - deletes calendar events
 * - conditionally sends notifications to students signed up for the slot
 * - deletes the schedules slot
 * - deletes all appointments
 *
 * If param notify is not explicitly set, deleting triggers notification on modifications to upcoming slots
 * provided that notification is enabled for the simplescheduler as a whole.
 *
 * @param int slotid
 * @param stdClass $simplescheduler (optional)
 * @param boolean notify (if true, will send, if false, will not, if null uses logic)
 * @uses $DB
 */
function simplescheduler_delete_slot($slotid, $simplescheduler=null, $notify=NULL){
    global $CFG, $DB;
    
    if ($slot = $DB->get_record('simplescheduler_slots', array('id' => $slotid))) {
        simplescheduler_delete_calendar_events($slot);
        
        if (!$simplescheduler){ // fetch optimization
	        $simplescheduler = $DB->get_record('simplescheduler', array('id' => $slot->simpleschedulerid));
    	}
        
        if (is_null($notify)) // if our slot is a future slot and 
        {
        	$notify = ($simplescheduler->allownotifications && (time() < $slot->starttime));
        }
        
        // do notifications before we delete the slot
        if ($notify)
    	{
    		// find students needing notification if any
    		$students = $DB->get_records('simplescheduler_appointment', array('slotid' => $slot->id), '', 'id,studentid');
    		if (!empty($students))
    		{
    			include_once($CFG->dirroot.'/mod/simplescheduler/mailtemplatelib.php');
    			foreach ($students as $student)
    			{
    				$course = $DB->get_record('course', array('id' => $simplescheduler->course));
    				$student = $DB->get_record('user', array('id'=>$student->studentid));
                    $teacher = $DB->get_record('user', array('id'=>$slot->teacherid));
                    $vars = simplescheduler_get_mail_variables($simplescheduler,$slot,$teacher,$student);
                    simplescheduler_send_email_from_template($student, $teacher, $course, 'cancelledbyteacher', 'teachercancelled', $vars, 'simplescheduler');
    			}
    		}
    	}
    	
    	// delete the slot
        $DB->delete_records('simplescheduler_slots', array('id' => $slotid));
    	$DB->delete_records('simplescheduler_appointment', array('slotid' => $slotid));
    }
}


/**
 * get appointment records for a slot
 * @param int $slotid
 * @return an array of appointments
 * @uses $CFG
 * @uses $DB
 */
function simplescheduler_get_appointments($slotid){
    global $CFG, $DB;
    $apps = $DB->get_records('simplescheduler_appointment', array('slotid' => $slotid));
    return $apps;
}

/**
 * Deletes records from simplescheduler_appointment table
 *
 * @param int $appointmentid
 * @param object $slot
 * @uses $DB
 */
function simplescheduler_delete_appointment($appointmentid) {
    global $DB;
    
    if (!$oldrecord = $DB->get_record('simplescheduler_appointment', array('id' => $appointmentid))) return ;
    if (!$DB->delete_records('simplescheduler_appointment', array('id' => $appointmentid))) {
            print_error('Couldn\'t delete old choice from database');
    }
}

/**
 * get the last considered location in this simplescheduler
 * @param reference $simplescheduler
 * @uses $USER
 * @uses $DB
 * @return the last known location for the current user (teacher)
 */
function simplescheduler_get_last_location(&$simplescheduler){
    global $USER, $DB;
    
    // we could have made an embedded query in Mysql 5.0
    $lastlocation = '';
    $select = 'simpleschedulerid = ? AND teacherid = ? GROUP BY timemodified';
    $maxtime = $DB->get_field_select('simplescheduler_slots', 'MAX(timemodified)', $select, array($simplescheduler->id, $USER->id), IGNORE_MULTIPLE);
    if ($maxtime){
        $select = "
            simpleschedulerid = :simpleschedulerid AND 
            timemodified = :maxtime AND 
            teacherid = :userid 
            GROUP BY timemodified
            ";
        $maxid = $DB->get_field_select('simplescheduler_slots', 'MAX(timemodified)', $select, array('simpleschedulerid' => $simplescheduler->id, 'maxtime' => $maxtime, 'userid' => $USER->id), IGNORE_MULTIPLE );
        $lastlocation = $DB->get_field('simplescheduler_slots', 'appointmentlocation', array('id' => $maxid));
    }
    return $lastlocation;
}

/**
 * frees all slots unapppointed that are in the past
 * @param int $simpleschedulerid
 * @param int $now give a date reference for measuring the "past" ! If 0, uses current time
 * @uses $CFG
 * @uses $DB
 * @return void
 */
function simplescheduler_free_late_unused_slots($simpleschedulerid, $now=0){
    global $CFG, $DB;
    
    if(!$now) {
        $now = time();
    }
    $sql = '
        SELECT DISTINCT
        s.id
        FROM
        {simplescheduler_slots} s
        LEFT JOIN
        {simplescheduler_appointment} a
        ON
        s.id = a.slotid
        WHERE
        a.studentid IS NULL AND
        s.simpleschedulerid = ? AND
        starttime < ?
        ';
    $to_delete = $DB->get_records_sql($sql, array($simpleschedulerid, $now));
    if ($to_delete){
        list($usql, $params) = $DB->get_in_or_equal(array_keys($to_delete));
        $DB->delete_records_select('simplescheduler_slots', " id $usql ", $params);
    }
}


/// Events related functions


// TODO: The following is not yet converted
 /**
  * Updates events in the calendar to the information provided.
  * If the events do not yet exist it creates them.
  * The only argument this function requires is the complete database record of a simplescheduler slot.
  * The course parameter should be the full record of the course for this simplescheduler so the 
  * teacher-title and student-title can be determined.
  * @param object $slot the slot instance
  * @param object $course the actual course
  */
function simplescheduler_add_update_calendar_events($slot, $course) {    
    
    global $DB;
    
    //firstly, collect up the information we'll need no matter what.
    $eventDuration = ($slot->duration) * 60;
    $eventStartTime = $slot->starttime;
    
    // get all students attached to that slot
    $appointments = $DB->get_records('simplescheduler_appointment', array('slotid'=>$slot->id), '', 'studentid');
    
    // nothing to do
    if (!$appointments) return;
    
    $studentids = array_keys($appointments);
    
    $teacher = $DB->get_record('user', array('id'=>$slot->teacherid));
    $students = $DB->get_records_list('user', 'id', $studentids);
    
    $simpleschedulerDescription = $DB->get_field('simplescheduler', 'intro', array('id' => $slot->simpleschedulerid));
    $simpleschedulerName = $DB->get_field('simplescheduler', 'name', array('id' => $slot->simpleschedulerid));
    $teacherEventDescription = "$simpleschedulerName<br/><br/>$simpleschedulerDescription";
    
    $studentEventDescription = $teacherEventDescription;
    
    //the eventtype field stores a code that is used to relate calendar events with the slots that 'own' them.
    //the code is SSstu (for a student event) or SSsup (for a teacher event).
    //then, the id of the simplescheduler slot that it belongs to.
    //finally, the courseID. I can't remember why, TODO: remember the good reason.
    //all in a colon delimited string. This will run into problems when the IDs of slots and courses are bigger than 7 digits in length...    
    $teacherEventType = "SSsup:{$slot->id}:{$course->id}";
    $studentEventType = "SSstu:{$slot->id}:{$course->id}";
    
    $studentNames = array();
    
    foreach($students as $student){
        $studentNames[] = fullname($student);
        $studentEventName = get_string('meetingwith', 'simplescheduler').' '.get_string('teacher','simplescheduler').', '.fullname($teacher);
        $studentEventName = shorten_text($studentEventName, 200);
        
        //firstly, deal with the student's event
        //if it exists, update it, else create a new one.

		$studentEvent = simplescheduler_get_student_event($slot, $student->id);
        
        if ($studentEvent) {
            $studentEvent->name = $studentEventName;
            $studentEvent->description = $studentEventDescription;
            $studentEvent->format = 1;
            $studentEvent->userid = $student->id;
            $studentEvent->timemodified = time();
            // $studentEvent->modulename = 'simplescheduler'; // Issue on delete/edit link
            $studentEvent->instance = $slot->simpleschedulerid;
            $studentEvent->timestart = $eventStartTime;
            $studentEvent->timeduration = $eventDuration;
            $studentEvent->visible = 1;
            $studentEvent->eventtype = $studentEventType;
            $DB->update_record('event', $studentEvent);
        } else {
            $studentEvent = new stdClass();
            $studentEvent->name = $studentEventName;
            $studentEvent->description = $studentEventDescription;
            $studentEvent->format = 1;
            $studentEvent->userid = $student->id;
            $studentEvent->timemodified = time();
            // $studentEvent->modulename = 'simplescheduler';
            $studentEvent->instance = $slot->simpleschedulerid;
            $studentEvent->timestart = $eventStartTime;
            $studentEvent->timeduration = $eventDuration;
            $studentEvent->visible = 1;
            $studentEvent->id = null;
            $studentEvent->eventtype = $studentEventType;
            // This should be changed to use add_event()
            $DB->insert_record('event', $studentEvent);
        }
        
    }
    
    if (count($studentNames) > 1){
        $teacherEventName = get_string('meetingwithplural', 'simplescheduler').' '.get_string('students', 'simplescheduler').', '.implode(', ', $studentNames);
    } else {
        $teacherEventName = get_string('meetingwith', 'simplescheduler').' '.get_string('student', 'simplescheduler').', '.$studentNames[0];
    }
    $teacherEventName = shorten_text($teacherEventName, 200);
	$teacherEvent = simplescheduler_get_teacher_event($slot);
    if ($teacherEvent) {
        $teacherEvent->name = $teacherEventName;
        $teacherEvent->description = $teacherEventDescription;
        $teacherEvent->format = 1;
        $teacherEvent->userid = $slot->teacherid;
        $teacherEvent->timemodified = time();
        // $teacherEvent->modulename = 'simplescheduler';
        $teacherEvent->instance = $slot->simpleschedulerid;
        $teacherEvent->timestart = $eventStartTime;
        $teacherEvent->timeduration = $eventDuration;
        $teacherEvent->visible = 1;
        $teacherEvent->eventtype = $teacherEventType;
        $DB->update_record('event', $teacherEvent);
    } else {
        $teacherEvent = new stdClass();
        $teacherEvent->name = $teacherEventName;
        $teacherEvent->description = $teacherEventDescription;
        $teacherEvent->format = 1;
        $teacherEvent->userid = $slot->teacherid;
        $teacherEvent->instance = $slot->simpleschedulerid;
        $teacherEvent->timemodified = time();
        // $teacherEvent->modulename = 'simplescheduler';
        $teacherEvent->timestart = $eventStartTime;
        $teacherEvent->timeduration = $eventDuration;
        $teacherEvent->visible = 1;
        $teacherEvent->id = null;
        $teacherEvent->eventtype = $teacherEventType;
        $DB->insert_record('event', $teacherEvent);
    }
}


/**
 * Will delete calendar events for a given simplescheduler slot, and not complain if the record does not exist.
 * The only argument this function requires is the complete database record of a simplescheduler slot.
 * @param object $slot the slot instance
 * @uses $DB 
 * @return boolean true if success, false otherwise
 */
function simplescheduler_delete_calendar_events($slot) {
    global $DB;
    
    $simplescheduler = $DB->get_record('simplescheduler', array('id'=>$slot->simpleschedulerid));
    
    if (!$simplescheduler) return false ;
    
    $teacherEventType = "SSsup:{$slot->id}:{$simplescheduler->course}";
    $studentEventType = "SSstu:{$slot->id}:{$simplescheduler->course}";
    
    $teacherDeletionSuccess = $DB->delete_records('event', array('eventtype'=>$teacherEventType));
    $studentDeletionSuccess = $DB->delete_records('event', array('eventtype'=>$studentEventType));
    
    return ($teacherDeletionSuccess && $studentDeletionSuccess);
    //this return may not be meaningful if the delete records functions do not return anything meaningful.
}

/**
 * This function decides if a slot should have calendar events associated with it,
 * and calls the update/delete functions if neccessary.
 * it must be passed the complete simplescheduler_slots record to function correctly.
 * The course parameter should be the record that belongs to the course for this simplescheduler.
 * @param object $slot the slot instance
 * @param object $course the actual course
 * @uses $DB
 */
function simplescheduler_events_update($slot, $course) {
    global $DB;
    
    $slotDoesntHaveAStudent = !$DB->count_records('simplescheduler_appointment', array('slotid' => $slot->id));
    $slotWasAttended = $DB->count_records('simplescheduler_appointment', array('slotid' => $slot->id, 'attended' => 1));
    
    if ($slotDoesntHaveAStudent || $slotWasAttended) {
        simplescheduler_delete_calendar_events($slot);
    }
    else {
        simplescheduler_add_update_calendar_events($slot, $course);
    }
}

/**
 * This function gets the calendar entry of the teacher relating to a slot.
 * If none is found, the return value is false.
 * 
 * @param object $slot the slot instance
 * @uses $DB
 * @return stdClass the calendar event of the teacher
 */
function simplescheduler_get_teacher_event($slot) {
    global $DB;
    
    //first we need to know the course that the simplescheduler belongs to...
    $courseid = $DB->get_field('simplescheduler', 'course', array('id' => $slot->simpleschedulerid), MUST_EXIST);
    
    //now try to fetch the event records...
    $teacherEventType = "SSsup:{$slot->id}:{$courseid}";
    
    $event = $DB->get_record('event', array('eventtype' => $teacherEventType), '*', IGNORE_MISSING);
    
	return $event; 
}

/**
 * This function gets the calendar entry of a student relating to a slot.
 * If none is found, the return value is false.
 * 
 * @param object $slot the slot instance
 * @param int $studentid the id number of the student record
 * @uses $DB
 * @return stdClass the calendar event of the student
 */
function simplescheduler_get_student_event($slot, $studentid) {
    global $DB;
    
    //first we need to know the course that the simplescheduler belongs to...
    $courseid = $DB->get_field('simplescheduler', 'course', array('id' => $slot->simpleschedulerid), MUST_EXIST);
    
    //now try to fetch the event records...
    $studentEventType = "SSstu:{$slot->id}:{$courseid}";
    
    $event = $DB->get_record('event', array('eventtype' => $studentEventType, 'userid'=>$studentid), '*', IGNORE_MISSING);
	return $event; 
}

/**
 * Construct an array with subtitution rules for mail templates, relating to 
 * a single appointment. Any of the parameters can be null.
 * @param object $simplescheduler The simplescheduler instance
 * @param object $slot The slot data, obtained with get_record().
 * @param user $attendant A {@link $USER} object describing the attendant (teacher)
 * @param user $attendee A {@link $USER} object describing the attendee (student)
 * @return array A hash with mail template substitutions 
 */
function simplescheduler_get_mail_variables ($simplescheduler, $slot, $attendant, $attendee) {
    
    global $CFG;
    
    $vars = array();
    
    if ($simplescheduler) {
        $vars['MODULE']     = $simplescheduler->name;
        $vars['STAFFROLE']  = simplescheduler_get_teacher_name($simplescheduler);
    }
    if ($slot) {
        $vars ['DATE']     = userdate($slot->starttime,get_string('strftimedate'));
        $vars ['TIME']     = userdate($slot->starttime,get_string('strftimetime'));
        $vars ['ENDTIME']  = userdate($slot->starttime+$slot->duration*60, get_string('strftimetime'));
        $vars ['LOCATION'] = $slot->appointmentlocation;
    }
    if ($attendant) {
        $vars['ATTENDANT']     = fullname($attendant);
        $vars['ATTENDANT_URL'] = $CFG->wwwroot.'/user/view.php?id='.$attendant->id;
    }
    if ($attendee) {
        $vars['ATTENDEE']     = fullname($attendee);
        $vars['ATTENDEE_URL'] = $CFG->wwwroot.'/user/view.php?id='.$attendee->id;
    }
    
    return $vars;
    
}

/**
 * Prints a summary of a user in a nice little box.
 *
 * @uses $CFG
 * @uses $USER
 * @param user $user A {@link $USER} object representing a user
 * @param course $course A {@link $COURSE} object representing a course
 */
function simplescheduler_print_user($user, $course, $messageselect=false, $return=false) {
    
    global $CFG, $USER, $OUTPUT ;
    
    $output = '';
    
    static $string;
    static $datestring;
    static $countries;
    
    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    if (isset($user->context->id)) {
        $usercontext = $user->context;
    } else {
        $usercontext = get_context_instance(CONTEXT_USER, $user->id);
    }
    
    if (empty($string)) {     // Cache all the strings for the rest of the page

        $string = new stdClass();        
        $string->email       = get_string('email');
        $string->lastaccess  = get_string('lastaccess');
        $string->activity    = get_string('activity');
        $string->loginas     = get_string('loginas');
        $string->fullprofile = get_string('fullprofile');
        $string->role        = get_string('role');
        $string->name        = get_string('name');
        $string->never       = get_string('never');
        
        $datestring = new stdClass();        
        $datestring->day     = get_string('day');
        $datestring->days    = get_string('days');
        $datestring->hour    = get_string('hour');
        $datestring->hours   = get_string('hours');
        $datestring->min     = get_string('min');
        $datestring->mins    = get_string('mins');
        $datestring->sec     = get_string('sec');
        $datestring->secs    = get_string('secs');
        $datestring->year    = get_string('year');
        $datestring->years   = get_string('years');
        
    }
    
    /// Get the hidden field list
    if (has_capability('moodle/course:viewhiddenuserfields', $context)) {
        $hiddenfields = array();
    } else {
        $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
    }
    
    $output .= '<table class="userinfobox">';
    $output .= '<tr>';
    $output .= '<td class="left side">';
    $output .= $OUTPUT->user_picture($user, array('size'=>100));
    $output .= '</td>';
    $output .= '<td class="content">';
    $output .= '<div class="username">'.fullname($user, has_capability('moodle/site:viewfullnames', $context)).'</div>';
    $output .= '<div class="info">';
    if (!empty($user->role) and ($user->role <> $course->teacher)) {
        $output .= $string->role .': '. $user->role .'<br />';
    }

	$extrafields = simplescheduler_get_user_fields($user);
	foreach ($extrafields as $field) {
        $output .= $field->title . ': ' . $field->value . '<br />';	    
	}
	
    
    if (!isset($hiddenfields['lastaccess'])) {
        if ($user->lastaccess) {
            $output .= $string->lastaccess .': '. userdate($user->lastaccess);
            $output .= '&nbsp; ('. format_time(time() - $user->lastaccess, $datestring) .')';
        } else {
            $output .= $string->lastaccess .': '. $string->never;
        }
    }
    $output .= '</div></td><td class="links">';
    //link to blogs
    if ($CFG->bloglevel > 0) {
        $output .= '<a href="'.$CFG->wwwroot.'/blog/index.php?userid='.$user->id.'">'.get_string('blogs','blog').'</a><br />';
    }
    //link to notes
    if (!empty($CFG->enablenotes) and (has_capability('moodle/notes:manage', $context) || has_capability('moodle/notes:view', $context))) {
        $output .= '<a href="'.$CFG->wwwroot.'/notes/index.php?course=' . $course->id. '&amp;user='.$user->id.'">'.get_string('notes','notes').'</a><br />';
    }
    
    if (has_capability('moodle/site:viewreports', $context) or has_capability('moodle/user:viewuseractivitiesreport', $usercontext)) {
        $output .= '<a href="'. $CFG->wwwroot .'/course/user.php?id='. $course->id .'&amp;user='. $user->id .'">'. $string->activity .'</a><br />';
    }
    $output .= '<a href="'. $CFG->wwwroot .'/user/profile.php?id='. $user->id .'">'. $string->fullprofile .'...</a>';
    
    if (!empty($messageselect)) {
        $output .= '<br /><input type="checkbox" name="user'.$user->id.'" /> ';
    }
    
    $output .= '</td></tr></table>';
    
    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

function simplescheduler_get_teacher_name($simplescheduler) {
    $name = $simplescheduler->staffrolename;
    if (empty($name)) {
        $name = get_string('teacher', 'simplescheduler');
    }
    return $name;
}

function simplescheduler_group_scheduling_enabled($course, $cm) {
	global $CFG;
    $globalenable = (bool) $CFG->simplescheduler_groupscheduling;
    $localenable = (groupmode($course, $cm) > 0);
    return $globalenable && $localenable;
}

/**
 * Appoint student to a slot - handle notifications and calendar updates.
 * @param int $slotid
 * @param int $studentid
 * @return string language string describing result
 */ 
function simplescheduler_teacher_appoint_student($slotid, $studentid) {
	global $DB;
	
	// load necessary objects
	$slot = $DB->get_record('simplescheduler_slots', array('id' => $slotid));
	$simplescheduler = $DB->get_record('simplescheduler', array('id' => $slot->simpleschedulerid));
	$course = $DB->get_record('course', array('id' => $simplescheduler->course));
	
	if ($slot && $simplescheduler && $course)
	{
		// make sure we do not already have an appointment
		if ( ($simplescheduler->simpleschedulermode == 'oneonly') && simplescheduler_student_has_appointment($studentid, $simplescheduler->id) ) {
			return 'teacher_appoint_student_has_appointment';
		}
		elseif ($DB->get_records('simplescheduler_appointment', array('slotid'=>$slotid,'studentid'=>$studentid))) {
			return 'teacher_appoint_student_already_appointed';
		}
		else {
			// create appointment
			$appointment = new stdClass();
			$appointment->slotid = $slotid;
			$appointment->studentid = $studentid;
			$appointment->attended = 0;
			$appointment->timecreated = time();
			$appointment->timemodified = time();
			$DB->insert_record('simplescheduler_appointment', $appointment);
			
			// update calendar
			simplescheduler_events_update($slot, $course);
	
			// notify student if this is a future slot
			if ($simplescheduler->allownotifications && (time() < $slot->starttime)) {
				$student = $DB->get_record('user', array('id' => $studentid));
				$teacher = $DB->get_record('user', array('id' => $slot->teacherid));
				$vars = simplescheduler_get_mail_variables($simplescheduler,$slot,$teacher,$student);
				simplescheduler_send_email_from_template($student, $teacher, $course, 'newappointment', 'assigned', $vars, 'simplescheduler');
			}
			return 'teacher_appoint_student_success';
		}
    }
    return false;
}

/**
 * Revoke appointment to a slot (teacher view) - handle notifications and calendar updates.
 * @param int $slotid
 * @param int $studentid
 * @return string language string describing result
 */ 
function simplescheduler_teacher_revoke_appointment($slotid, $studentid)
{
	global $DB;
	
	// load necessary objects
	$slot = $DB->get_record('simplescheduler_slots', array('id' => $slotid));
	$simplescheduler = $DB->get_record('simplescheduler', array('id' => $slot->simpleschedulerid));
	$course = $DB->get_record('course', array('id' => $simplescheduler->course));
	if ($appointments = $DB->get_records('simplescheduler_appointment', array('slotid' => $slot->id, 'studentid' => $studentid), '', 'id,studentid')) {
		$appointment = reset($appointments);
    	simplescheduler_delete_appointment($appointment->id);
    		
    	// delete and recreate events for the slot
        simplescheduler_delete_calendar_events($slot);
        simplescheduler_add_update_calendar_events($slot, $course);
    	
        // notify student if this is a future slot
        if ($simplescheduler->allownotifications && (time() < $slot->starttime)) {
            $student = $DB->get_record('user', array('id'=>$appointment->studentid));
            $teacher = $DB->get_record('user', array('id'=>$slot->teacherid));        
            $vars = simplescheduler_get_mail_variables($simplescheduler,$slot,$teacher,$student);
            simplescheduler_send_email_from_template($student, $teacher, $course, 'cancelledbyteacher', 'teachercancelled', $vars, 'simplescheduler');
        }
        return 'teacher_revoke_appointment_success';
    }
    return 'teacher_revoke_appointment_already_revoked';
}

/**
 * Revoke appointment to a slot (student view) - handle notifications and calendar updates.
 * @param int $slotid
 * @param int $studentid
 * @return boolean success or failure
 */      
function simplescheduler_student_revoke_appointment($slotid, $studentid)
{
	global $DB;
	
	// load necessary objects
	$slot = $DB->get_record('simplescheduler_slots', array('id' => $slotid));
	$simplescheduler = $DB->get_record('simplescheduler', array('id' => $slot->simpleschedulerid));
	$course = $DB->get_record('course', array('id' => $simplescheduler->course));
	if ($appointments = $DB->get_records('simplescheduler_appointment', array('slotid' => $slot->id, 'studentid' => $studentid), '', 'id,studentid')) {
		$appointment = reset($appointments);
    	simplescheduler_delete_appointment($appointment->id);
    	
    	// delete and recreate events for the slot
        simplescheduler_delete_calendar_events($slot);
        simplescheduler_add_update_calendar_events($slot, $course);
    	
        // notify student if this is a future slot
        if ($simplescheduler->allownotifications && (time() < $slot->starttime)) {
            $student = $DB->get_record('user', array('id'=>$appointment->studentid));
            $teacher = $DB->get_record('user', array('id'=>$slot->teacherid));        
            $vars = simplescheduler_get_mail_variables($simplescheduler,$slot,$teacher,$student);
            //simplescheduler_send_email_from_template($student, $teacher, $course, 'cancelledbyteacher', 'teachercancelled', $vars, 'simplescheduler');
            simplescheduler_send_email_from_template($teacher, $student, $course, 'cancelledbystudent', 'cancelled', $vars, 'simplescheduler');
        }
        
        return true;
    }
    return false;
}

/**
 * Appoint student to a slot (from student view) - handle notifications and calendar updates.
 *
 * @todo implement me - user in studentview.controller.php
 */
function simplescheduler_student_appoint_student($slotid, $studentid)
{
	global $DB;
	
	//load necessary objects
	$slot = $DB->get_record('simplescheduler_slots', array('id'=>$slotid));
	$simplescheduler = $DB->get_record('simplescheduler', array('id' => $slot->simpleschedulerid));
	$course = $DB->get_record('course', array('id' => $simplescheduler->course));
	
	if ($slot && $simplescheduler && $course)
	{
		$appointment = new stdClass();
		$appointment->slotid = $slotid;
		$appointment->studentid = $studentid;
		$appointment->attended = 0;
		$appointment->timecreated = time();
		$appointment->timemodified = time();
		$DB->insert_record('simplescheduler_appointment', $appointment);
    	
		// update calendar	
		simplescheduler_events_update($slot, $course);
			
		// notify teacher
		if ($simplescheduler->allownotifications) {
			$student = $DB->get_record('user', array('id' => $appointment->studentid));
			$teacher = $DB->get_record('user', array('id' => $slot->teacherid));
			$vars = simplescheduler_get_mail_variables($simplescheduler,$slot,$teacher,$student);
			simplescheduler_send_email_from_template($teacher, $student, $course, 'newappointment', 'applied', $vars, 'simplescheduler');
		}
		return true;
    }
    return false;
}
        
/**
 * adds an error css marker in case of matching error
 * @param array $errors the current error set
 * @param string $errorkey 
 */
if (!function_exists('print_error_class')){
    function print_error_class($errors, $errorkeylist){
        if ($errors){
            foreach($errors as $anError){
                if ($anError->on == '') continue;
                if (preg_match("/\\b{$anError->on}\\b/" ,$errorkeylist)){
                    echo " class=\"formerror\" ";
                    return;
                }
            }        
        }
    }
}
?>