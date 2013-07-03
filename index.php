<?PHP 

/**
 * Main file of the simplesscheduler package.
 * It lists all the instances of simplesscheduler in a particular course.
 * 
 * @package    mod
 * @subpackage simplesscheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = required_param('id', PARAM_INT);   // course

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}
$PAGE->set_url('/mod/simplesscheduler/index.php', array('id'=>$id));
$PAGE->set_pagelayout('incourse');

$coursecontext = get_context_instance(CONTEXT_COURSE, $id);
require_login($course->id);

add_to_log($course->id, 'simplesscheduler', 'view all', "index.php?id=$course->id", '');

/// Get all required strings

$strsimplesschedulers = get_string('modulenameplural', 'simplesscheduler');
$strsimplesscheduler  = get_string('modulename', 'simplesscheduler');

/// Print the header

$navlinks = array();
$navlinks[] = array('name' => $strsimplesscheduler, 'link' => '', 'type' => 'title');    
$navigation = build_navigation($navlinks);
print_header_simple($strsimplesschedulers, '', $navigation, '', '', true, '', navmenu($course));

/// Get all the appropriate data

if (!$simplesschedulers = get_all_instances_in_course('simplesscheduler', $course)) {
    print_error('nosimplesschedulers', 'simplesscheduler', "../../course/view.php?id=$course->id");
}

/// Print the list of instances 

$timenow = time();
$strname  = get_string('name');
$strweek  = get_string('week');
$strtopic  = get_string('topic');

$table = new html_table();

if ($course->format == 'weeks') {
    $table->head  = array ($strweek, $strname);
    $table->align = array ('CENTER', 'LEFT');
} else if ($course->format == 'topics') {
    $table->head  = array ($strtopic, $strname);
    $table->align = array ('CENTER', 'LEFT', 'LEFT', 'LEFT');
} else {
    $table->head  = array ($strname);
    $table->align = array ('LEFT', 'LEFT', 'LEFT');
}

foreach ($simplesschedulers as $simplesscheduler) {
    if (!$simplesscheduler->visible) {
        //Show dimmed if the mod is hidden
        $link = "<a class=\"dimmed\" href=\"view.php?id={$simplesscheduler->coursemodule}\">$simplesscheduler->name</a>";
    } else {
        //Show normal if the mod is visible
        $link = "<a href=\"view.php?id={$simplesscheduler->coursemodule}\">$simplesscheduler->name</a>";
    }
    if ($simplesscheduler->visible or has_capability('moodle/course:viewhiddenactivities', $coursecontext)) {
        if ($course->format == 'weeks' or $course->format == 'topics') {
            $table->data[] = array ($simplesscheduler->section, $link);
        } else {
            $table->data[] = array ($link);
        }
    }
}

echo html_writer::table($table);

/// Finish the page

echo $OUTPUT->footer($course);

?>
