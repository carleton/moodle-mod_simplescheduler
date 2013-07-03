<?PHP 

/**
 * Library (public API) of the simplesscheduler module
 * 
 * @package    mod
 * @subpackage simplesscheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/// Library of functions and constants for module simplesscheduler
include_once $CFG->dirroot.'/mod/simplesscheduler/locallib.php';
include_once $CFG->dirroot.'/mod/simplesscheduler/mailtemplatelib.php';

define('SCHEDULER_SELF', 0); // Used for setting conflict search scope 
define('SCHEDULER_OTHERS', 1); // Used for setting conflict search scope 
define('SCHEDULER_ALL', 2); // Used for setting conflict search scope 

/**
 * Given an object containing all the necessary data,
 * will create a new instance and return the id number
 * of the new instance.
 * @param object $simplesscheduler the current instance
 * @return int the new instance id
 * @uses $DB
 */
function simplesscheduler_add_instance($simplesscheduler) {
    global $DB;    
    $simplesscheduler->timemodified = time();
    $id = $DB->insert_record('simplesscheduler', $simplesscheduler);
    $simplesscheduler->id = $id;
    return $id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 * @param object $simplesscheduler the current instance
 * @return object the updated instance
 * @uses $DB
 */
function simplesscheduler_update_instance($simplesscheduler) {
    global $DB;
    $simplesscheduler->timemodified = time();
    $simplesscheduler->id = $simplesscheduler->instance;    
    $DB->update_record('simplesscheduler', $simplesscheduler);
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
function simplesscheduler_delete_instance($id) {
    global $CFG, $DB;
    if (! $simplesscheduler = $DB->get_record('simplesscheduler', array('id' => $id))) {
        return false;
    }
    $result = $DB->delete_records('simplesscheduler', array('id' => $simplesscheduler->id));
    $oldslots = $DB->get_records('simplesscheduler_slots', array('simplesschedulerid' => $simplesscheduler->id), '', 'id, id');
    if ($oldslots) {
        foreach(array_keys($oldslots) as $slotid){
            // will delete appointments and remaining related events - we suppress notifications here.
            simplesscheduler_delete_slot($slotid, null);
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
 * @param object $simplesscheduler the activity module behind the course module instance
 * @return object an information object as defined above
 */
function simplesscheduler_user_outline($course, $user, $mod, $simplesscheduler) {
    $return = NULL;
    return $return;
}

/**
 * Prints a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 * @param object $course the course instance
 * @param object $user the concerned user instance
 * @param object $mod the current course module instance
 * @param object $simplesscheduler the activity module behind the course module instance
 * @param boolean true if the user completed activity, false otherwise
 */
function simplesscheduler_user_complete($course, $user, $mod, $simplesscheduler) {
    
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in simplesscheduler activities and print it out.
 * Return true if there was output, or false is there was none.
 * @param object $course the course instance
 * @param boolean $isteacher true tells a teacher uses the function
 * @param int $timestart a time start timestamp
 * @return boolean true if anything was printed, otherwise false
 */
function simplesscheduler_print_recent_activity($course, $isteacher, $timestart) {
    
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
function simplesscheduler_cron () {
    global $CFG, $DB;
    
    $date = make_timestamp(date('Y'), date('m'), date('d'), date('H'), date('i'));
    
    // for every appointment in all simplesschedulers
    $select = 'emaildate > 0 AND emaildate <= ? AND starttime > ?';
    $slots = $DB->get_records_select('simplesscheduler_slots', $select, array($date, $date), 'starttime');
    
    foreach ($slots as $slot) {
        // get teacher
        $teacher = $DB->get_record('user', array('id' => $slot->teacherid));
        
        // get course
        $simplesscheduler = $DB->get_record('simplesscheduler', array('id'=>$slot->simplesschedulerid));
        $course = $DB->get_record('course', array('id'=>$simplesscheduler->course));
        
        // get appointed student list
        $appointments = $DB->get_records('simplesscheduler_appointment', array('slotid'=>$slot->id), '', 'id, studentid');
        
        //if no email previously sent and one is required
        foreach ($appointments as $appointment) {
            $student = $DB->get_record('user', array('id'=>$appointment->studentid));
            $vars = simplesscheduler_get_mail_variables ($simplesscheduler, $slot, $teacher, $student);
            simplesscheduler_send_email_from_template ($student, $teacher, $course, 'remindtitle', 'reminder', $vars, 'simplesscheduler');                
        }
        // mark as sent
        $slot->emaildate = -1;
        $DB->update_record('simplesscheduler_slots', $slot);
    }
    return true;
}

/**
 * Returns the users with data in one simplesscheduler
 * (users with records in journal_entries, students and teachers)
 * @param int $simplesschedulerid the id of the activity module
 * @uses $CFG
 * @uses $DB
 */
function simplesscheduler_get_participants($simplesschedulerid) {
    global $CFG, $DB;
    
    //Get students using slots they have
    $sql = '
        SELECT DISTINCT
        u.*
        FROM
        {user} u,
        {simplesscheduler_slots} s,
        {simplesscheduler_appointment} a
        WHERE
        s.simplesschedulerid = ? AND
        s.id = a.slotid AND
        u.id = a.studentid
        ';
    $students = $DB->get_records_sql($sql, array($simplesschedulerid));
    
    //Get teachers using slots they have
    $sql = '
        SELECT DISTINCT
        u.*
        FROM
        {user} u,
        {simplesscheduler_slots} s
        WHERE
        s.simplesschedulerid = ? AND
        u.id = s.teacherid
        ';
    $teachers = $DB->get_records_sql($sql, array($simplesschedulerid));
    
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
function simplesscheduler_reset_course_form_definition(&$mform) {
    global $COURSE, $DB;
    
    $mform->addElement('header', 'simplesschedulerheader', get_string('modulenameplural', 'simplesscheduler'));
    
    if($DB->record_exists('simplesscheduler', array('course'=>$COURSE->id))){
        
        $mform->addElement('checkbox', 'reset_simplesscheduler_slots', get_string('resetslots', 'simplesscheduler'));
        $mform->addElement('checkbox', 'reset_simplesscheduler_appointments', get_string('resetappointments', 'simplesscheduler'));
        $mform->disabledIf('reset_simplesscheduler_appointments', 'reset_simplesscheduler_slots', 'checked');
    }
}

/**
 * Default values for the reset form
 */
function simplesscheduler_reset_course_form_defaults($course) {
    return array('reset_simplesscheduler_slots'=>1, 'reset_simplesscheduler_appointments'=>1);
}


/**
 * This function is used by the remove_course_userdata function in moodlelib.
 * If this function exists, remove_course_userdata will execute it.
 * This function will remove all posts from the specified forum.
 * @param data the reset options
 * @return void
 */
function simplesscheduler_reset_userdata($data) {
    global $CFG, $DB;
    
    $status = array();
    $componentstr = get_string('modulenameplural', 'simplesscheduler');
    
    $sqlfromslots = 'FROM {simplesscheduler_slots} WHERE simplesschedulerid IN '.
        '(SELECT sc.id FROM {simplesscheduler} sc '.
        ' WHERE sc.course = :course)';
    
    $params = array('course'=>$data->courseid);
    
    $strreset = get_string('reset');
    
    
    if (!empty($data->reset_simplesscheduler_appointments) || !empty($data->reset_simplesscheduler_slots)) {
        
        $slots = $DB->get_recordset_sql('SELECT * '.$sqlfromslots, $params);
        $success = true;
        foreach ($slots as $slot) { 
            // delete calendar events
            $success = $success && simplesscheduler_delete_calendar_events($slot);
            
            // delete appointments
            $success = $success && $DB->delete_records('simplesscheduler_appointment', array('slotid'=>$slot->id));			    		    	
        }
        $slots->close();
        $status[] = array('component' => $componentstr, 'item' => get_string('resetappointments','simplesscheduler'), 'error' => !$success);
    }
    if (!empty($data->reset_simplesscheduler_slots)) {
        if ($DB->execute('DELETE '.$sqlfromslots, $params)) {
            $status[] = array('component' => $componentstr, 'item' => get_string('resetslots','simplesscheduler'), 'error' => false);
        }
    }
    return $status;
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function simplesscheduler_supports($feature) {
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
