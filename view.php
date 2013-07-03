<?PHP  

/**
 * This page prints a particular instance of simplesscheduler and handles
 * top level interactions
 * 
 * @package    mod
 * @subpackage simplesscheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/mod/simplesscheduler/lib.php');
require_once($CFG->dirroot.'/mod/simplesscheduler/locallib.php');

// common parameters
$id = optional_param('id', '', PARAM_INT);    // Course Module ID, or
$a = optional_param('a', '', PARAM_INT);     // simplesscheduler ID
$action = optional_param('what', 'view', PARAM_CLEAN); 
$subaction = optional_param('subaction', '', PARAM_CLEAN);
$page = optional_param('page', 'allappointments', PARAM_CLEAN);
$offset = optional_param('offset', '', PARAM_CLEAN);
$usehtmleditor = false;
$editorfields = '';

if ($id) {
    if (! $cm = get_coursemodule_from_id('simplesscheduler', $id)) {
        print_error('invalidcoursemodule');
    }
    
    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
    
    if (! $simplesscheduler = $DB->get_record('simplesscheduler', array('id' => $cm->instance))) {
        print_error('invalidcoursemodule');
    }
    
} else {
    if (! $simplesscheduler = $DB->get_record('simplesscheduler', array('id' => $a))) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record('course', array('id' => $simplesscheduler->course))) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance('simplesscheduler', $simplesscheduler->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}


require_login($course->id, false, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
// TODO require_capability('mod/simplesscheduler:view', $context);

add_to_log($course->id, 'simplesscheduler', $action, "view.php?id={$cm->id}", $simplesscheduler->id, $cm->id);

$groupmode = groupmode($course, $cm);

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/simplesscheduler/view.php', array('id' => $cm->id));


/// This is a pre-header selector for downloded documents generation

    if (has_capability('mod/simplesscheduler:manage', $context) || has_capability('mod/simplesscheduler:attend', $context)) {
        if (preg_match('/downloadexcel|^downloadcsv|downloadods/', $action)){
            include($CFG->dirroot.'/mod/simplesscheduler/downloads.php');
        }
    }

/// Print the page header

$strsimplesschedulers = get_string('modulenameplural', 'simplesscheduler');
$strsimplesscheduler  = get_string('modulename', 'simplesscheduler');
$strtime = get_string('time');
$strdate = get_string('date', 'simplesscheduler');
$strstart = get_string('start', 'simplesscheduler');
$strend = get_string('end', 'simplesscheduler');
$strname = get_string('name');
$strseen = get_string('seen', 'simplesscheduler');
$strnote = get_string('comments', 'simplesscheduler');
$strgrade = get_string('note', 'simplesscheduler');
$straction = get_string('action', 'simplesscheduler');
$strduration = get_string('duration', 'simplesscheduler');
$stremail = get_string('email');

$title = $course->shortname . ': ' . format_string($simplesscheduler->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

/// route to screen

// teacher side
if (has_capability('mod/simplesscheduler:manage', $context)) {
    if ($action == 'viewstatistics'){
        include $CFG->dirroot.'/mod/simplesscheduler/viewstatistics.php';
    }
    elseif ($action == 'viewstudent'){
        include $CFG->dirroot.'/mod/simplesscheduler/viewstudent.php';
    }
    elseif ($action == 'downloads' || $action == 'dodownloadcsv'){
        include $CFG->dirroot.'/mod/simplesscheduler/downloads.php';
    }
    elseif ($action == 'datelist'){
        include $CFG->dirroot.'/mod/simplesscheduler/datelist.php';
    }
    else{
        include $CFG->dirroot.'/mod/simplesscheduler/teacherview.php';
    }
}

// student side
elseif (has_capability('mod/simplesscheduler:appoint', $context)) { 
    include $CFG->dirroot.'/mod/simplesscheduler/studentview.php';
}
// for guests
else {
    echo $OUTPUT->box(get_string('guestscantdoanything', 'simplesscheduler'), 'center', '70%');
}    

echo $OUTPUT->footer($course);

?>