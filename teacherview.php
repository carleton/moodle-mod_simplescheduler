<?php

/**
 * Contains various sub-screens that a teacher can see.
 * 
 * @package    mod
 * @subpackage scheduler
 * @copyright  2011 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @todo add interface for assigning students to slots
 * @todo add "show past slots" toggle
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

if ($action){
    include($CFG->dirroot.'/mod/scheduler/teacherview.controller.php');
}


/************************************ View : New single slot form ****************************************/
if ($action == 'addslot'){
	echo $OUTPUT->heading(get_string('addsingleslot', 'scheduler'));
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
        $form->hideuntil = $scheduler->timemodified; // supposed being in the past so slot is visible
        $form->notes = '';
        $form->teacherid = $USER->id;
        $form->appointmentlocation = scheduler_get_last_location($scheduler);
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
    
    // return code for include
    return -1;
}
/************************************ View : Update single slot form ****************************************/
if ($action == 'updateslot') {
    $slotid = required_param('slotid', PARAM_INT);
    
    echo $OUTPUT->heading(get_string('updatesingleslot', 'scheduler'));
    $form = new stdClass();
    
    if(!empty($errors)){ // if some errors, get data from client side
    	get_slot_data($form);
    	$form->appointments = unserialize(stripslashes(required_param('appointments', PARAM_RAW)));
    } else {
    	/// get data from the last inserted
    	$slot = $DB->get_record('scheduler_slots', array('id'=>$slotid));
    	$form = &$slot;
		// get all appointments for this slot
		$form->appointments = array();
		$appointments = $DB->get_records('scheduler_appointment', array('slotid'=>$slotid));
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
        $form->hideuntil = $scheduler->timemodified;
        $form->duration = $scheduler->defaultslotduration;
        $form->forcewhenoverlap = 0;
        $form->teacherid = $USER->id;
        $form->exclusivity = 1;
        $form->duration = $scheduler->defaultslotduration;
        $form->monday = 1;
        $form->tuesday = 1;
        $form->wednesday = 1;
        $form->thursday = 1;
        $form->friday = 1;
        $form->saturday = 0;
        $form->sunday = 0;
    }
    
    echo $OUTPUT->heading(get_string('addsession', 'scheduler'));
    echo $OUTPUT->box_start('boxaligncenter');
    include_once('addslotsform.html');
    echo $OUTPUT->box_end();
    echo '<br />';
    
    // return code for include
    return -1;
}
/************************************ Schedule a student form ***********************************************/
if ($action == 'schedule') {
    $form = new stdClass();
    if($subaction == 'cancel') {
        get_slot_data($form);
        $form->appointments = unserialize(stripslashes(required_param('appointments', PARAM_RAW)));
        $form->studentid = required_param('studentid', PARAM_INT);
        $form->slotid = required_param('slotid', PARAM_INT);
        $form->availableslots = scheduler_get_available_slots($form->studentid, $scheduler->id);
        $form->what = 'doaddupdateslot';
    } elseif(empty($subaction)) {
        if (!empty($errors)){
            get_slot_data($form);
            $form->availableslots = scheduler_get_available_slots($form->studentid, $scheduler->id);
            $form->studentid = required_param('studentid', PARAM_INT);
            $form->slotid = optional_param('slotid', -1, PARAM_INT);
        } else {
            $form->studentid = required_param('studentid', PARAM_INT);
            
            /// getting available slots
            $form->availableslots = scheduler_get_available_slots($form->studentid, $scheduler->id);
            $form->what = 'doaddupdateslot' ;
            $form->starttime = time();
            $form->duration = $scheduler->defaultslotduration;
            $form->exclusivity = 1;
            $form->hideuntil = $scheduler->timemodified; // supposed being in the past so slot is visible
            $form->notes = '';
            $form->teacherid = $USER->id;
            $form->appointmentlocation = scheduler_get_last_location($scheduler);
            $form->slotid = 0;
            $appointment = new stdClass();
            $appointment->slotid = -1;
            $appointment->studentid = $form->studentid;
            $appointment->appointmentnote = '';
            $appointment->timecreated = time();
            $appointment->timemodified = time();
            $form->appointments[$form->studentid] = $appointment;
        }
    }
    
    // display error or advices
    if (!empty($errors)){
        $errorstr = '';
        foreach($errors as $anError){
            $errorstr .= $anError->message;
        }
        echo $OUTPUT->box($errorstr, 'errorbox');
    }
    
    // diplay form
    $form->student = $DB->get_record('user', array('id'=>$form->studentid));
    $studentname = fullname($form->student, true);
    echo $OUTPUT->heading(get_string('scheduleappointment', 'scheduler', $studentname));
    echo $OUTPUT->box_start('boxaligncenter');
    include($CFG->dirroot.'/mod/scheduler/oneslotform.html');
    echo $OUTPUT->box_end();
    
    // return code for include
    return -1;
}

//****************** Standard view ***********************************************//
/// print top tabs
$tabrows = array();
$row  = array();

switch ($action){
    case 'viewstatistics':{
        $currenttab = get_string('statistics', 'scheduler');
        break;
    }
    case 'datelist':{
        $currenttab = get_string('datelist', 'scheduler');
        break;
    }
    case 'viewstudent':{
        $currenttab = get_string('studentdetails', 'scheduler');
        $row[] = new tabobject($currenttab, '', $currenttab);
        break;
    }
    case 'downloads':{
        $currenttab = get_string('downloads', 'scheduler');
        break;
    }
    default: {
        $currenttab = get_string($page, 'scheduler');
    }
}

$tabname = get_string('myappointments', 'scheduler');
$row[] = new tabobject($tabname, "view.php?id={$cm->id}&amp;page=myappointments", $tabname);
if ($DB->count_records('scheduler_slots', array('schedulerid'=>$scheduler->id)) > $DB->count_records('scheduler_slots', array('schedulerid'=>$scheduler->id, 'teacherid'=>$USER->id))) {
    $tabname = get_string('allappointments', 'scheduler');
    $row[] = new tabobject($tabname, "view.php?id={$cm->id}&amp;page=allappointments", $tabname);
} else {
    // we are alone in this scheduler
    if ($page == 'allappointements') {
        $currenttab = get_string('myappointments', 'scheduler');
    }
}
$tabname = get_string('datelist', 'scheduler');
$row[] = new tabobject($tabname, "view.php?id={$cm->id}&amp;what=datelist", $tabname);
$tabname = get_string('statistics', 'scheduler');
$row[] = new tabobject($tabname, "view.php?what=viewstatistics&amp;id={$cm->id}&amp;course={$scheduler->course}&amp;page=overall", $tabname);
$tabname = get_string('downloads', 'scheduler');
$row[] = new tabobject($tabname, "view.php?what=downloads&amp;id={$cm->id}&amp;course={$scheduler->course}", $tabname);
$tabrows[] = $row;
print_tabs($tabrows, $currenttab);

/// print heading
echo $OUTPUT->heading($scheduler->name);

/// print page
if (trim(strip_tags($scheduler->intro))) {
    echo $OUTPUT->box_start('mod_introbox');
    echo format_module_intro('scheduler', $scheduler, $cm->id);
    echo $OUTPUT->box_end();
}

if ($page == 'allappointments'){
    $select = "schedulerid = '". $scheduler->id ."'";
} else {
    $select = "schedulerid = '". $scheduler->id ."' AND teacherid = '{$USER->id}'";
    $page = 'myappointments';
}
$sqlcount = $DB->count_records_select('scheduler_slots',$select);

if (($offset == '') && ($sqlcount > 25)){
    $offsetcount = $DB->count_records_select('scheduler_slots', $select." AND starttime < '".strtotime('now')."'");
    $offset = floor($offsetcount/25);
}


$slots = $DB->get_records_select('scheduler_slots', $select, null, 'starttime', '*', $offset * 25, 25);
if ($slots){
    foreach(array_keys($slots) as $slotid){
        $slots[$slotid]->isappointed = $DB->count_records('scheduler_appointment', array('slotid'=>$slotid));
        $slots[$slotid]->isattended = $DB->record_exists('scheduler_appointment', array('slotid'=>$slotid, 'attended'=> 1));
    }
}

$straddsession = get_string('addsession', 'scheduler');
$straddsingleslot = get_string('addsingleslot', 'scheduler');
$strdownloadexcel = get_string('downloadexcel', 'scheduler');

// get possible attendees
$students = scheduler_get_possible_attendees($cm, $usergroups); 

/// some slots already exist
if ($slots){
    // print instructions and button for creating slots
    echo $OUTPUT->box_start('boxaligncenter');
    
    // these instructions are too redundant and in prime real estate - the buttons themselves are quite explanatory
    //print_string('addslot', 'scheduler');
    
    // print add session button
    $strdeleteallslots = get_string('deleteallslots', 'scheduler');
    $strdeleteallunusedslots = get_string('deleteallunusedslots', 'scheduler');
    $strdeleteunusedslots = get_string('deleteunusedslots', 'scheduler');
    $strdeletemyslots = get_string('deletemyslots', 'scheduler');
    $strstudents = get_string('students', 'scheduler');
    $displaydeletebuttons = 1;
    include $CFG->dirroot.'/mod/scheduler/commands.html';
    echo $OUTPUT->box_end();

    // prepare slots table
    $table = new html_table();
    if ($page == 'myappointments'){
        $table->head  = array ($strdate, $strstart, $strend, $strstudents, $straction);
        $table->align = array ('LEFT', 'LEFT', 'LEFT', 'LEFT', 'LEFT');
    } else {
        $table->head  = array ($strdate, $strstart, $strend, $strstudents, s(scheduler_get_teacher_name($scheduler)), $straction);
        $table->align = array ('LEFT', 'LEFT', 'LEFT', 'LEFT', 'LEFT', 'LEFT');
    }
    $table->width = '90%';
    $table->attributes = array('class' => 'generaltable boxaligncenter');
    $offsetdatemem = '';
    $appointedstudentids = '';
    foreach($slots as $slot) {
        
        //if (!$slot->isappointed && $slot->starttime + (60 * $slot->duration) < time()) {
            // This slot is in the past and has not been chosen by any student, so delete
         //   $DB->delete_records('scheduler_slots', array('id'=>$slot->id));
          //  continue;
        //}
        
        /// Parameter $local in scheduler_userdate and scheduler_usertime added by power-web.at
        /// When local Time or Date is needed the $local Param must be set to 1
        $offsetdate = scheduler_userdate($slot->starttime,1);
        $offsettime = scheduler_usertime($slot->starttime,1);
        $endtime = scheduler_usertime($slot->starttime + ($slot->duration * 60),1);
        
        // slot is appointed
        $studentArray = array();
        $slotappointedstudentids = array();
        if ($slot->isappointed) {
        	$strrevoke = get_string('revoke', 'scheduler');
        	$studentcolumn = '';
            $appointedstudents = $DB->get_records('scheduler_appointment', array('slotid'=>$slot->id));
            foreach($appointedstudents as $appstudent){
                $student = $DB->get_record('user', array('id'=>$appstudent->studentid));
                $slotappointedstudentids[$appstudent->studentid] = $appstudent->studentid;
                $appointedstudentids[$appstudent->studentid] = $appstudent->studentid;
                if ($student) {
                    //$picture = $OUTPUT->user_picture($student);
                    $name = "<a href=\"view.php?what=viewstudent&amp;id={$cm->id}&amp;studentid={$student->id}&amp;course={$scheduler->course}&amp;order=DESC\">".fullname($student).'</a>';
                }
                //$studentcolumn .= "$picture $name"; // need to work on formatting and link if we want pictures.
                $studentcolumn .= "<p>$name";
                $studentcolumn .= "<span style=\"font-size: x-small;\"><a href=\"view.php?what=revokeone&amp;id={$cm->id}&amp;slotid={$slot->id}&amp;studentid={$student->id}&amp;page={$page}\" title=\"{$strrevoke}\"><img align=\"right\" src=\"{$CFG->wwwroot}/pix/t/delete.gif\" alt=\"{$strrevoke}\" /></a></span></p>";
        
            }
        } else {
            // slot is free
            $studentcolumn = "";
            $slotappointedstudentids = array();
        }
        
        // lets make a form here that lets us add an eligible student
        $form = '<div class="addStudent">';

        // lets add add student form for this slot to actions (if available)
        $form .= '<form name="addtoslotform" method="post" action="view.php?id=2">';
        $form .= '<input type="hidden" value="addstudent" name="what"></input>';
        $form .= '<input type="hidden" value="'.$cm->id.'" name="id"></input>';
        $form .= '<input type="hidden" value="'.$slot->id.'" name="slotid"></input>';
        $form .= '<input type="hidden" value="allappointments" name="page"></input>';
        $form .= '<select name="studentid">';
        $form .= '<option value="">'.get_string('add_a_student_pulldown', 'scheduler').'</option>';
        foreach ($students as $studentid => $student)
        {
        	if (!isset($slotappointedstudentids[$studentid]))
        	{
        		if ($scheduler->schedulermode == 'oneonly')
        		{
        			if (scheduler_student_has_appointment($studentid, $scheduler->id))
        			{
        				continue; // student can only have one and already has one.
        			}
        		}
        		$form .= '<option value="'.$studentid.'">'.fullname($student).'</option>';
        	}
        }
        $form .= '<input type="submit" value="Add" name="go_btn"></input>';
        $form .= '</form>';
        $form .= '</div>';
        
        $studentcolumn .= $form;

        $studentArray[] = $studentcolumn;
        
        $actions = '<span style="font-size: x-small;">';
        if ($USER->id == $slot->teacherid || has_capability('mod/scheduler:manageallappointments', $context)){
            
            $strdelete = get_string('delete');
            $stredit = get_string('move','scheduler');
            $strattended = get_string('attended','scheduler');
            $strnonexclusive = get_string('isnonexclusive', 'scheduler');
            $strallowgroup = get_string('allowgroup', 'scheduler');
            $strforbidgroup = get_string('forbidgroup', 'scheduler');
            
            $actions .= "<a href=\"view.php?what=deleteslot&amp;id={$cm->id}&amp;slotid={$slot->id}&amp;page={$page}\" title=\"{$strdelete}\"><img src=\"{$CFG->wwwroot}/pix/t/delete.gif\" alt=\"{$strdelete}\" /></a>";
            $actions .= "&nbsp;<a href=\"view.php?what=updateslot&amp;id={$cm->id}&amp;slotid={$slot->id}&amp;page={$page}\" title=\"{$stredit}\"><img src=\"{$CFG->wwwroot}/pix/t/edit.gif\" alt=\"{$stredit}\" /></a>";
            if ($slot->isattended){
                $actions .= "&nbsp;<img src=\"{$CFG->wwwroot}/pix/c/group.gif\" title=\"{$strattended}\" />";
            } else {
                if ($slot->isappointed > 1){
                    $actions .= "&nbsp;<img src=\"{$CFG->wwwroot}/pix/c/group.gif\" title=\"{$strnonexclusive}\" />";
                } else {
                    if ($slot->exclusivity == 1){
                        $actions .= "&nbsp;<a href=\"view.php?what=allowgroup&amp;id={$cm->id}&amp;slotid={$slot->id}&amp;page={$page}\" title=\"{$strallowgroup}\"><img src=\"{$CFG->wwwroot}/pix/t/groupn.gif\" alt=\"{$strallowgroup}\" /></a>";
                    } else {
                        $actions .= "&nbsp;<a href=\"view.php?what=forbidgroup&amp;id={$cm->id}&amp;slotid={$slot->id}&amp;page={$page}\" title=\"{$strforbidgroup}\"><img src=\"{$CFG->wwwroot}/pix/t/groupv.gif\" alt=\"{$strforbidgroup}\" /></a>";
                    }
                }
            }
        } else {
            // just signal group status
            if ($slot->isattended){
                $actions .= "&nbsp;<img src=\"{$CFG->wwwroot}/pix/c/group.gif\" title=\"{$strattended}\" />";
            } else {
                if ($slot->isappointed > 1){
                    $actions .= "&nbsp;<img src=\"{$CFG->wwwroot}/pix/c/group.gif\" title=\"{$strnonexclusive}\" />";
                } else {
                    if ($slot->exclusivity == 1){
                        $actions .= "&nbsp;<img src=\"{$CFG->wwwroot}/pix/t/groupn.gif\" title=\"{$strallowgroup}\" />";
                    } else {
                        $actions .= "&nbsp;<img src=\"{$CFG->wwwroot}/pix/t/groupv.gif\" title=\"{$strforbidgroup}\" />";
                    }
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
    echo $OUTPUT->heading(get_string('slots' ,'scheduler'));
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
//echo '<br /><center>' . get_string('markseen', 'scheduler') . '</center>';

} else if ($action != 'addsession') {
    /// There are no slots, should the teacher be asked to make some
    echo $OUTPUT->box_start('boxaligncenter', '', '');
    
    // these instructions are too redundant - the buttons themselves are quite explanatory
    //print_string('welcomenewteacher', 'scheduler');
    $displaydeletebuttons = 0;
    include $CFG->dirroot.'/mod/scheduler/commands.html';
    echo $OUTPUT->box_end();
}

/// print table of outstanding appointer (students)
?>
<table width="90%" class="boxaligncenter">
    <tr valign="top">
        <td width="50%">
<?php
echo $OUTPUT->heading(get_string('schedulestudents', 'scheduler'));

if (!$students) {
    $nostudentstr = get_string('noexistingstudents','scheduler');
    if ($COURSE->id == SITEID){
        $nostudentstr .= '<br/>'.get_string('howtoaddstudents','scheduler');
    }
    echo $OUTPUT->notification($nostudentstr);
} else {
    $mtable = new html_table();
    
    // build table header
    $mtable->head  = array ('', $strname);
    $mtable->align = array ('CENTER','LEFT');
    $extrafields = scheduler_get_user_fields(null);
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
        if (!scheduler_has_slot($student->id, $scheduler, true, $scheduler->schedulermode == 'onetime')) {
            $picture = $OUTPUT->user_picture($student);
            $name = "<a href=\"../../user/view.php?id={$student->id}&amp;course={$scheduler->course}\">";
            $name .= fullname($student);
            $name .= '</a>';
            if (scheduler_has_slot($student->id, $scheduler, true, false) == 0){
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
            $args['duration'] = $scheduler->defaultslotduration;
            $args['hideuntil'] = $scheduler->timemodified;
            $args['appointmentlocation'] = '';
            $args['exclusivity'] = '1';
            $args['notes'] = '';
            $url = new moodle_url('view.php',$args);
            
            $newdata = array($picture, $name);
            $extrafields = scheduler_get_user_fields($student);
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
        echo $OUTPUT->heading(get_string('missingstudents', 'scheduler', $num), 3);
        
        // Print links to print invitation or reminder emails
        $strinvitation = get_string('invitation', 'scheduler');
        $strreminder = get_string('reminder', 'scheduler');
        $mailto = rtrim($mailto, ', ');
        
        $subject = $strinvitation . ': ' . $scheduler->name;
        $body = $strinvitation . ': ' . $scheduler->name . "\n\n";
        $body .= get_string('invitationtext', 'scheduler');
        $body .= "{$CFG->wwwroot}/mod/scheduler/view.php?id={$cm->id}";
        $maildisplay = '';
        if ($CFG->scheduler_showemailplain) {
        	$maildisplay .= '<p>'.implode(', ', $maillist).'</p>';
        }
        $maildisplay .= get_string('composeemail', 'scheduler'). 
            $mailto.'?subject='.htmlentities(rawurlencode($subject)).
            '&amp;body='.htmlentities(rawurlencode($body)).
            '"> '.$strinvitation.'</a> ';
        $maildisplay .= ' &mdash; ';
        
        $subject = $strreminder . ': ' . $scheduler->name;
        $body = $strreminder . ': ' . $scheduler->name . "\n\n";
        $body .= get_string('remindertext', 'scheduler');
        $body .= "{$CFG->wwwroot}/mod/scheduler/view.php?id={$cm->id}";
        $maildisplay .= $mailto.'?subject='.htmlentities(rawurlencode($subject)). 
            '&amp;body='.htmlentities(rawurlencode($body)).
            '"> '.$strreminder.'</a>';
        echo $OUTPUT->box($maildisplay); 
        
        // print table of students who still have to make appointments
        echo html_writer::table($mtable);
    } else {
        echo $OUTPUT->notification(get_string('nostudents', 'scheduler'));
    }
}
?>
        </td>
<?php
if (scheduler_group_scheduling_enabled($course, $cm)){
    ?>
        <td width="50%">
<?php

/// print table of outstanding appointer (groups)

echo $OUTPUT->heading(get_string('schedulegroups', 'scheduler'));

if (empty($groups)){
    echo $OUTPUT->notification(get_string('nogroups', 'scheduler'));
} else {
	$mtable = new html_table();
    $mtable->head  = array ('', $strname, $straction);
    $mtable->align = array ('CENTER', 'LEFT', 'CENTER');
    foreach($groups as $group) {
        $members = groups_get_members($group->id, 'u.id, lastname, firstname, email, picture', 'lastname, firstname');
        if (empty($members)) continue;
        if (!scheduler_has_slot(implode(',', array_keys($members)), $scheduler, true, $scheduler->schedulermode == 'onetime')) {
            $actions = '';
            $actions .= "<a href=\"view.php?what=schedulegroup&amp;id={$cm->id}&amp;groupid={$group->id}&amp;page={$page}\">";
            $actions .= get_string('schedule', 'scheduler');
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
        echo $OUTPUT->notification(get_string('nogroups', 'scheduler'));
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
    <input type="submit" name="go_btn" value="<?php print_string('return', 'scheduler') ?>" />
</form>