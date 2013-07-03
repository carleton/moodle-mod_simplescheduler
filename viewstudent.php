<?php

/**
 * Prints the screen that displays a single student to a teacher.
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @todo eliminate save comment javascript method in place of a save button at the bottom.
 */

defined('MOODLE_INTERNAL') || die();
require_once $CFG->dirroot.'/mod/simplescheduler/locallib.php';


$studentid = required_param('studentid', PARAM_INT);
$order = optional_param('order','ASC',PARAM_ALPHA);
if (!in_array($order,array('ASC','DESC'))) {
    $order='ASC';
}

$usehtmleditor = can_use_html_editor();

if ($subaction != ''){
    include $CFG->dirroot.'/mod/simplescheduler/viewstudent.controller.php'; 
}
 
simplescheduler_print_user($DB->get_record('user', array('id' => $studentid)), $course);

//print tabs
$tabrows = array();
$row  = array();
if($page == 'appointments'){
    $currenttab = get_string('appointments', 'simplescheduler');
} else {
    $currenttab = get_string('notes', 'simplescheduler');
}
$tabname = get_string('appointments', 'simplescheduler');
$row[] = new tabobject($tabname, "view.php?what=viewstudent&amp;id={$cm->id}&amp;studentid={$studentid}&amp;course={$simplescheduler->course}&amp;order={$order}&amp;page=appointments", $tabname);
$tabname = get_string('comments', 'simplescheduler');
$row[] = new tabobject($tabname, "view.php?what=viewstudent&amp;id={$cm->id}&amp;studentid={$studentid}&amp;course={$simplescheduler->course}&amp;order={$order}&amp;page=notes", $tabname);
$tabrows[] = $row;
print_tabs($tabrows, $currenttab);

/// if slots have been booked
$sql = "
    SELECT
    s.*,
    a.id as appid,
    a.studentid,
    a.attended,
    a.appointmentnote,
    a.timemodified as apptimemodified
    FROM
    {simplescheduler_slots} s,
    {simplescheduler_appointment} a
    WHERE
    s.id = a.slotid AND
    simpleschedulerid = ? AND
    studentid = ?
    ORDER BY
    starttime $order
    ";
if ($slots = $DB->get_records_sql($sql, array($simplescheduler->id, $studentid, $order))) {
    /// provide link to sort in the opposite direction
    if($order == 'DESC'){
        $orderlink = "<a href=\"view.php?what=viewstudent&amp;id=$cm->id&amp;studentid=".$studentid."&amp;course=$simplescheduler->course&amp;order=ASC&amp;page=$page\">";
    } else {
        $orderlink = "<a href=\"view.php?what=viewstudent&amp;id=$cm->id&amp;studentid=".$studentid."&amp;course=$simplescheduler->course&amp;order=DESC&amp;page=$page\">";
    }
    
    $table = new html_table();
    /// print page header and prepare table headers
    if ($page == 'appointments'){
        echo $OUTPUT->heading(get_string('slots' ,'simplescheduler'));
        $table->head  = array ($strdate, $strstart, $strend, $strnote, s(simplescheduler_get_teacher_name($simplescheduler)));
        $table->align = array ('LEFT', 'LEFT', 'LEFT', 'LEFT', 'LEFT');
        $table->width = '80%';
    } else {
        echo $OUTPUT->heading(get_string('comments' ,'simplescheduler'));
        $table->head  = array (get_string('studentcomments', 'simplescheduler'), get_string('comments', 'simplescheduler'), $straction);
        $table->align = array ('LEFT', 'LEFT');
        $table->width = '80%';
    }
    foreach($slots as $slot) {
        $startdate = simplescheduler_userdate($slot->starttime,1);
        $starttime = simplescheduler_usertime($slot->starttime,1);
        $endtime = simplescheduler_usertime($slot->starttime + ($slot->duration * 60),1);
        $distributecheck = '';
        if ($page == 'appointments'){
            if ($DB->count_records('simplescheduler_appointment', array('slotid' => $slot->id)) > 1){
                $distributecheck = "<br/><input type=\"checkbox\" name=\"distribute{$slot->appid}\" value=\"1\" /> ".get_string('distributetoslot', 'simplescheduler')."\n";
            }
            //display appointments
            if ($slot->attended == 0){
            	$teacher = $DB->get_record('user', array('id'=>$slot->teacherid));
                $table->data[] = array ($startdate, $starttime, $endtime, $slot->appointmentnote, fullname($teacher));
            }
            else {
                $slot->appointmentnote .= "<br/><span class=\"timelabel\">[".userdate($slot->apptimemodified)."]</span>";
                $teacher = $DB->get_record('user', array('id'=>$slot->teacherid));
                $table->data[] = array ($startdate, $starttime, $endtime, $slot->appointmentnote, fullname($teacher));
            }
        } else {
            if ($DB->count_records('simplescheduler_appointment', array('slotid' => $slot->id)) > 1){
                $distributecheck = "<input type=\"checkbox\" name=\"distribute\" value=\"1\" /> ".get_string('distributetoslot', 'simplescheduler')."\n";
            }
            //display notes
            $actions = "<a href=\"javascript:document.forms['updatenote{$slot->id}'].submit()\">".get_string('savecomment', 'simplescheduler').'</a>';
            $commenteditor = "<form name=\"updatenote{$slot->id}\" action=\"view.php\" method=\"post\">\n";
            $commenteditor .= "<input type=\"hidden\" name=\"what\" value=\"viewstudent\" />\n";
            $commenteditor .= "<input type=\"hidden\" name=\"subaction\" value=\"updatenote\" />\n";
            $commenteditor .= "<input type=\"hidden\" name=\"page\" value=\"appointments\" />\n";
            $commenteditor .= "<input type=\"hidden\" name=\"id\" value=\"{$cm->id}\" />\n";
            $commenteditor .= "<input type=\"hidden\" name=\"studentid\" value=\"{$studentid}\" />\n";
            $commenteditor .= "<input type=\"hidden\" name=\"appid\" value=\"{$slot->appid}\" />\n";
            $commenteditor .= print_textarea($usehtmleditor, 20, 60, 400, 200, 'appointmentnote_'.$slot->id, $slot->appointmentnote, $COURSE->id, true);
            if ($usehtmleditor) {
                $commenteditor .= "<input type=\"hidden\" name=\"format\" value=\"FORMAT_HTML\" />\n";
            } 
            else {
                $commenteditor .= '<p align="right">';
                $commenteditor .= $OUTPUT->help_icon('textformat', get_string('formattexttype'), 'moodle', true, false, '', true);
                $commenteditor .= get_string('formattexttype');
                $commenteditor .= ':&nbsp;';
                if (!$form->format) {
                    $form->format = 'MOODLE';
                }
                $commenteditor .= html_writer::select(format_text_menu(), 'format', $form->format); 
                $commenteditor .= '</p>';
            }
            $commenteditor .= $distributecheck;
            $commenteditor .= "</form>";
            $table->data[] = array ($slot->notes.'<br/><font size=-2>'.$startdate.' '.$starttime.' to '.$endtime.'</font>', $commenteditor, $actions);
        }
    }
    // print slots table
    if ($page == 'appointments'){
        echo '<form name="studentform" action="view.php" method="post">';
        echo "<input type=\"hidden\" name=\"id\" value=\"{$cm->id}\" />\n";
        echo "<input type=\"hidden\" name=\"subaction\" value=\"updategrades\" />\n";
        echo "<input type=\"hidden\" name=\"what\" value=\"viewstudent\" />\n";
        echo "<input type=\"hidden\" name=\"page\" value=\"appointments\" />\n";
        echo "<input type=\"hidden\" name=\"studentid\" value=\"{$studentid}\" />\n";
    }
    echo html_writer::table($table);
    if ($page == 'appointments'){
        echo '</form>';
    }
}
echo $OUTPUT->continue_button($CFG->wwwroot.'/mod/simplescheduler/view.php?id='.$cm->id);

return;
/// Finish the page
echo $OUTPUT->footer($course);
exit;
?>