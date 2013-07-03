<?PHP  

/**
 * This page prints a particular instance of simplescheduler and handles
 * top level interactions
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/mod/simplescheduler/lib.php');
require_once($CFG->dirroot.'/mod/simplescheduler/locallib.php');

// common parameters
$id = optional_param('id', '', PARAM_INT);    // Course Module ID, or
$a = optional_param('a', '', PARAM_INT);     // simplescheduler ID
$action = optional_param('what', 'view', PARAM_CLEAN); 
$subaction = optional_param('subaction', '', PARAM_CLEAN);
$page = optional_param('page', 'allappointments', PARAM_CLEAN);
$offset = optional_param('offset', '', PARAM_CLEAN);
$usehtmleditor = false;
$editorfields = '';

if ($id) {
    if (! $cm = get_coursemodule_from_id('simplescheduler', $id)) {
        print_error('invalidcoursemodule');
    }
    
    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
    
    if (! $simplescheduler = $DB->get_record('simplescheduler', array('id' => $cm->instance))) {
        print_error('invalidcoursemodule');
    }
    
} else {
    if (! $simplescheduler = $DB->get_record('simplescheduler', array('id' => $a))) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record('course', array('id' => $simplescheduler->course))) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance('simplescheduler', $simplescheduler->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}


require_login($course->id, false, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
// TODO require_capability('mod/simplescheduler:view', $context);

add_to_log($course->id, 'simplescheduler', $action, "view.php?id={$cm->id}", $simplescheduler->id, $cm->id);

$groupmode = groupmode($course, $cm);

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/simplescheduler/view.php', array('id' => $cm->id));


/// This is a pre-header selector for downloded documents generation

    if (has_capability('mod/simplescheduler:manage', $context) || has_capability('mod/simplescheduler:attend', $context)) {
        if (preg_match('/downloadexcel|^downloadcsv|downloadods/', $action)){
            include($CFG->dirroot.'/mod/simplescheduler/downloads.php');
        }
    }

/// Print the page header

$strsimpleschedulers = get_string('modulenameplural', 'simplescheduler');
$strsimplescheduler  = get_string('modulename', 'simplescheduler');
$strtime = get_string('time');
$strdate = get_string('date', 'simplescheduler');
$strstart = get_string('start', 'simplescheduler');
$strend = get_string('end', 'simplescheduler');
$strname = get_string('name');
$strseen = get_string('seen', 'simplescheduler');
$strnote = get_string('comments', 'simplescheduler');
$strgrade = get_string('note', 'simplescheduler');
$straction = get_string('action', 'simplescheduler');
$strduration = get_string('duration', 'simplescheduler');
$stremail = get_string('email');

$title = $course->shortname . ': ' . format_string($simplescheduler->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

/// route to screen

// teacher side
if (has_capability('mod/simplescheduler:manage', $context)) {
    if ($action == 'viewstudent'){
        include $CFG->dirroot.'/mod/simplescheduler/viewstudent.php';
    }
    elseif ($action == 'downloads' || $action == 'dodownloadcsv'){
        include $CFG->dirroot.'/mod/simplescheduler/downloads.php';
    }
    elseif ($action == 'datelist'){
        include $CFG->dirroot.'/mod/simplescheduler/datelist.php';
    }
    else{
        include $CFG->dirroot.'/mod/simplescheduler/teacherview.php';
    }
}

// student side
elseif (has_capability('mod/simplescheduler:appoint', $context)) { 
    include $CFG->dirroot.'/mod/simplescheduler/studentview.php';
}
// for guests
else {
    echo $OUTPUT->box(get_string('guestscantdoanything', 'simplescheduler'), 'center', '70%');
}    

echo $OUTPUT->footer($course);

?>