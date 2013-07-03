<?PHP 

/**
 * Library (public API) of the simplescheduler module
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/// Library of functions and constants for module simplescheduler
include_once $CFG->dirroot.'/mod/simplescheduler/locallib.php';
include_once $CFG->dirroot.'/mod/simplescheduler/mailtemplatelib.php';

define('SIMPLESCHEDULER_SELF', 0); // Used for setting conflict search scope 
define('SIMPLESCHEDULER_OTHERS', 1); // Used for setting conflict search scope 
define('SIMPLESCHEDULER_ALL', 2); // Used for setting conflict search scope 

/**
 * Given an object containing all the necessary data,
 * will create a new instance and return the id number
 * of the new instance.
 * @param object $simplescheduler the current instance
 * @return int the new instance id
 * @uses $DB
 */
function simplescheduler_add_instance($simplescheduler) {
    global $DB;    
    $simplescheduler->timemodified = time();
    $id = $DB->insert_record('simplescheduler', $simplescheduler);
    $simplescheduler->id = $id;
    return $id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 * @param object $simplescheduler the current instance
 * @return object the updated instance
 * @uses $DB
 */
function simplescheduler_update_instance($simplescheduler) {
    global $DB;
    $simplescheduler->timemodified = time();
    $simplescheduler->id = $simplescheduler->instance;    
    $DB->update_record('simplescheduler', $simplescheduler);
    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 * @param int $id the instance to be deleted
 * @return boolean true if success, false otherwise
 * @uses $DB
 */
function simplescheduler_delete_instance($id) {
    global $CFG, $DB;
    if (! $simplescheduler = $DB->get_record('simplescheduler', array('id' => $id))) {
        return false;
    }
    $result = $DB->delete_records('simplescheduler', array('id' => $simplescheduler->id));
    $oldslots = $DB->get_records('simplescheduler_slots', array('simpleschedulerid' => $simplescheduler->id), '', 'id, id');
    if ($oldslots) {
        foreach(array_keys($oldslots) as $slotid){
            // will delete appointments and remaining related events - we suppress notifications here.
            simplescheduler_delete_slot($slotid, null);
        }
    }
    return $result;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 * @param object $course the course instance
 * @param object $user the concerned user instance
 * @param object $mod the current course module instance
 * @param object $simplescheduler the activity module behind the course module instance
 * @return object an information object as defined above
 */
function simplescheduler_user_outline($course, $user, $mod, $simplescheduler) {
    $return = NULL;
    return $return;
}

/**
 * Prints a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 * @param object $course the course instance
 * @param object $user the concerned user instance
 * @param object $mod the current course module instance
 * @param object $simplescheduler the activity module behind the course module instance
 * @param boolean true if the user completed activity, false otherwise
 */
function simplescheduler_user_complete($course, $user, $mod, $simplescheduler) {
    
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in simplescheduler activities and print it out.
 * Return true if there was output, or false is there was none.
 * @param object $course the course instance
 * @param boolean $isteacher true tells a teacher uses the function
 * @param int $timestart a time start timestamp
 * @return boolean true if anything was printed, otherwise false
 */
function simplescheduler_print_recent_activity($course, $isteacher, $timestart) {
    
    return false;
}

/**
 * Function to be run periodically according to the moodle
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 * @return boolean always true
 * @uses $CFG
 * @uses $DB
 */
function simplescheduler_cron () {
    global $CFG, $DB;
    
    $date = make_timestamp(date('Y'), date('m'), date('d'), date('H'), date('i'));
    
    // for every appointment in all simpleschedulers
    $select = 'emaildate > 0 AND emaildate <= ? AND starttime > ?';
    $slots = $DB->get_records_select('simplescheduler_slots', $select, array($date, $date), 'starttime');
    
    foreach ($slots as $slot) {
        // get teacher
        $teacher = $DB->get_record('user', array('id' => $slot->teacherid));
        
        // get course
        $simplescheduler = $DB->get_record('simplescheduler', array('id'=>$slot->simpleschedulerid));
        $course = $DB->get_record('course', array('id'=>$simplescheduler->course));
        
        // get appointed student list
        $appointments = $DB->get_records('simplescheduler_appointment', array('slotid'=>$slot->id), '', 'id, studentid');
        
        //if no email previously sent and one is required
        foreach ($appointments as $appointment) {
            $student = $DB->get_record('user', array('id'=>$appointment->studentid));
            $vars = simplescheduler_get_mail_variables ($simplescheduler, $slot, $teacher, $student);
            simplescheduler_send_email_from_template ($student, $teacher, $course, 'remindtitle', 'reminder', $vars, 'simplescheduler');                
        }
        // mark as sent
        $slot->emaildate = -1;
        $DB->update_record('simplescheduler_slots', $slot);
    }
    return true;
}

/**
 * Returns the users with data in one simplescheduler
 * (users with records in journal_entries, students and teachers)
 * @param int $simpleschedulerid the id of the activity module
 * @uses $CFG
 * @uses $DB
 */
function simplescheduler_get_participants($simpleschedulerid) {
    global $CFG, $DB;
    
    //Get students using slots they have
    $sql = '
        SELECT DISTINCT
        u.*
        FROM
        {user} u,
        {simplescheduler_slots} s,
        {simplescheduler_appointment} a
        WHERE
        s.simpleschedulerid = ? AND
        s.id = a.slotid AND
        u.id = a.studentid
        ';
    $students = $DB->get_records_sql($sql, array($simpleschedulerid));
    
    //Get teachers using slots they have
    $sql = '
        SELECT DISTINCT
        u.*
        FROM
        {user} u,
        {simplescheduler_slots} s
        WHERE
        s.simpleschedulerid = ? AND
        u.id = s.teacherid
        ';
    $teachers = $DB->get_records_sql($sql, array($simpleschedulerid));
    
    if ($students and $teachers){
        $participants = array_merge(array_values($students), array_values($teachers));
    }
    elseif ($students) {
        $participants = array_values($students);
    }
    elseif ($teachers){
        $participants = array_values($teachers);
    }
    else{
        $participants = array();
    }
    
    //Return students array (it contains an array of unique users)
    return ($participants);
}

/*
 * Course resetting API
 *
 */

/**
 * Called by course/reset.php
 * @param $mform form passed by reference
 */
function simplescheduler_reset_course_form_definition(&$mform) {
    global $COURSE, $DB;
    
    $mform->addElement('header', 'simpleschedulerheader', get_string('modulenameplural', 'simplescheduler'));
    
    if($DB->record_exists('simplescheduler', array('course'=>$COURSE->id))){
        
        $mform->addElement('checkbox', 'reset_simplescheduler_slots', get_string('resetslots', 'simplescheduler'));
        $mform->addElement('checkbox', 'reset_simplescheduler_appointments', get_string('resetappointments', 'simplescheduler'));
        $mform->disabledIf('reset_simplescheduler_appointments', 'reset_simplescheduler_slots', 'checked');
    }
}

/**
 * Default values for the reset form
 */
function simplescheduler_reset_course_form_defaults($course) {
    return array('reset_simplescheduler_slots'=>1, 'reset_simplescheduler_appointments'=>1);
}


/**
 * This function is used by the remove_course_userdata function in moodlelib.
 * If this function exists, remove_course_userdata will execute it.
 * This function will remove all posts from the specified forum.
 * @param data the reset options
 * @return void
 */
function simplescheduler_reset_userdata($data) {
    global $CFG, $DB;
    
    $status = array();
    $componentstr = get_string('modulenameplural', 'simplescheduler');
    
    $sqlfromslots = 'FROM {simplescheduler_slots} WHERE simpleschedulerid IN '.
        '(SELECT sc.id FROM {simplescheduler} sc '.
        ' WHERE sc.course = :course)';
    
    $params = array('course'=>$data->courseid);
    
    $strreset = get_string('reset');
    
    
    if (!empty($data->reset_simplescheduler_appointments) || !empty($data->reset_simplescheduler_slots)) {
        
        $slots = $DB->get_recordset_sql('SELECT * '.$sqlfromslots, $params);
        $success = true;
        foreach ($slots as $slot) { 
            // delete calendar events
            $success = $success && simplescheduler_delete_calendar_events($slot);
            
            // delete appointments
            $success = $success && $DB->delete_records('simplescheduler_appointment', array('slotid'=>$slot->id));			    		    	
        }
        $slots->close();
        $status[] = array('component' => $componentstr, 'item' => get_string('resetappointments','simplescheduler'), 'error' => !$success);
    }
    if (!empty($data->reset_simplescheduler_slots)) {
        if ($DB->execute('DELETE '.$sqlfromslots, $params)) {
            $status[] = array('component' => $componentstr, 'item' => get_string('resetslots','simplescheduler'), 'error' => false);
        }
    }
    return $status;
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function simplescheduler_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        
        default: return null;
    }
}
?>
