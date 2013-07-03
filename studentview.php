<?php

/**
 * Student simplescheduler screen (where students choose appointments).
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
    
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/simplescheduler/studentview.controller.php');

$mygroups = groups_get_all_groups($course->id, $USER->id, $cm->groupingid, 'g.id, g.name');

/// printing head information

echo $OUTPUT->heading($simplescheduler->name);
if (trim(strip_tags($simplescheduler->intro))) {
    echo $OUTPUT->box_start('mod_introbox');
    echo format_module_intro('simplescheduler', $simplescheduler, $cm->id);
    echo $OUTPUT->box_end();
}

// clean all late slots (for every body, anyway, they are passed !!)
simplescheduler_free_late_unused_slots($simplescheduler->id);

/// does this student have an appointment?    
$hasappointment = simplescheduler_student_has_appointment($USER->id, $simplescheduler->id);

/// get available slots
$haveunattendedappointments = false;

// this grabs all slots that are available or already appointed for the user.
if ($slots = simplescheduler_get_available_slots($USER->id, $simplescheduler->id, true)) {
	$minhidedate = 0; // very far in the past
    $studentSlots = array();
    $studentPastAppointedSlots = array();
    foreach($slots as $slot) {
        /// check if other appointement is not "on the way". Student could not apply to it.
        if (simplescheduler_get_conflicts($simplescheduler->id, $slot->starttime, $slot->starttime + $slot->duration * 60, 0, $USER->id, SCHEDULER_OTHERS)){
        	continue;
        }
        
        /// check if not mine and late, don't care
        if (!$slot->appointedbyme and $slot->starttime < time()) {
            continue;
        }
        elseif ($slot->appointedbyme and $slot->starttime < time()) {
    		$studentPastAppointedSlots[$slot->starttime.'_'.$slot->teacherid] = $slot;
    		continue;    	
        }
        /// check what to print in groupsession indication
        if ($slot->exclusivity == 0){
            $slot->groupsession = get_string('yes');
        } else {
            if ($slot->exclusivity > $slot->population){
                $remaining = ($slot->exclusivity - $slot->population).'/'.$slot->exclusivity;
                $slot->groupsession = get_string('limited', 'simplescheduler', $remaining);
            } else { // should not be visible to students
                $slot->groupsession = get_string('complete', 'simplescheduler');
            }
        }
        
        /// examine slot situations and elects those which have sense for the current student
        
        // I am in slot, unconditionnally
        if ($slot->appointedbyme) {
            $studentSlots[$slot->starttime.'_'.$slot->teacherid] = $slot;
            $haveunattendedappointments = true;
        } else {
        	// slot is free
            if (!$slot->appointed) {
                //if student is only allowed one appointment and this student has already had their then skip this record
                if ($slot->hideuntil <= time()){
                    $studentSlots[$slot->starttime.'_'.$slot->teacherid] = $slot;
                }
                $minhidedate = ($slot->hideuntil < $minhidedate || $minhidedate == 0) ? $slot->hideuntil : $minhidedate ;
            } 
            // slot is booked by another student, group booking is allowed and there is still room
            elseif ($slot->appointed and (($slot->exclusivity == 0) || ($slot->exclusivity > $slot->population))) {
                // there is already a record fot this time/teacher : sure its our's
                if (array_key_exists($slot->starttime.'_'.$slot->teacherid, $studentSlots)) continue;
                // else record the slot with this user (not me).
                $studentSlots[$slot->starttime.'_'.$slot->teacherid] = $slot;
            }
        }
    }

	/// prepare appointed slot table
    
    if (count($studentPastAppointedSlots)){
        echo $OUTPUT->heading(get_string('attendedslots' ,'simplescheduler'));
        
        $table = new html_table();
        
        $table->head  = array ($strdate, s(simplescheduler_get_teacher_name($simplescheduler)), $strnote);
        $table->align = array ('left', 'center', 'left', 'left');
        $table->size = array ('', '', '40%', '150');
        $table->width = '90%'; 
        $table->data = array();
        $previousdate = '';
        $previoustime = 0;
        $previousendtime = 0;
        
        foreach($studentPastAppointedSlots as $key => $aSlot){
            /// preparing data
            $startdate = simplescheduler_userdate($aSlot->starttime,1);
            $starttime = simplescheduler_usertime($aSlot->starttime,1);
            $endtime = simplescheduler_usertime($aSlot->starttime + ($aSlot->duration * 60),1);
            $startdatestr = ($startdate == $previousdate) ? '' : $startdate ;
            $starttimestr = ($starttime == $previoustime) ? '' : $starttime ;
            $endtimestr = ($endtime == $previousendtime) ? '' : $endtime ;
            $studentappointment = $DB->get_record('simplescheduler_appointment', array('slotid' => $aSlot->id, 'studentid' => $USER->id));
            $studentnotes1 = '';
            $studentnotes2 = '';
            if ($aSlot->notes != ''){
                $studentnotes1 = '<div class="slotnotes">';
                $studentnotes1 .= '<b>'.get_string('yourslotnotes', 'simplescheduler').'</b><br/>';
                $studentnotes1 .= format_string($aSlot->notes).'</div>';
            }
            if ($studentappointment->appointmentnote != ''){
                $studentnotes2 .= '<div class="appointmentnote">';
                $studentnotes2 .= '<b>'.get_string('yourappointmentnote', 'simplescheduler').'</b><br/>';
                $studentnotes2 .= format_string($studentappointment->appointmentnote).'</div>';
            }
            $studentnotes = "{$studentnotes1}{$studentnotes2}";
            
            // recording data into table
            $teacher = $DB->get_record('user', array('id'=>$aSlot->teacherid));
            $table->data[] = array ("<span class=\"attended\">$startdatestr</span><br/><div class=\"timelabel\">[$starttimestr - $endtimestr]</div>", "<a href=\"../../user/view.php?id={$aSlot->teacherid}&amp;course={$simplescheduler->course}\">".fullname($teacher).'</a>',$studentnotes);
            
            $previoustime = $starttime;
            $previousendtime = $endtime;
            $previousdate = $startdate;
        }
        
        echo html_writer::table($table);
    }

	$OUTPUT->box_start('center', '80%');
	if ($simplescheduler->simpleschedulermode == 'oneonly' && !empty($studentPastAppointedSlots)) {
		print_string('welcomealreadyappointed', 'simplescheduler');
		$closed = true;
	}
	elseif (empty($studentSlots))
	{
		print_string('welcomestudentnothingavailable', 'simplescheduler');
		$closed = true;
	}
	elseif (simplescheduler_has_slot($USER->id, $simplescheduler, true)) {
		if ($simplescheduler->simpleschedulermode == 'oneonly') {
			print_string('welcomebackstudent', 'simplescheduler');
		}
		else print_string('welcomebackstudentmulti', 'simplescheduler');
		$closed = false;
	}
	else {
		if ($simplescheduler->simpleschedulermode == 'oneonly') {
			print_string('welcomenewstudent', 'simplescheduler');
		}
		else print_string('welcomenewstudentmulti', 'simplescheduler');
		$closed = false;
	}
	$OUTPUT->box_end();
	
	if (!$closed) {
    	// prepare appointable slot table
    	echo $OUTPUT->heading(get_string('slots' ,'simplescheduler'));
    	$slottable = new html_table;
		$slottable->head  = array ($strdate, $strstart, $strend, get_string('location', 'simplescheduler'), get_string('choice', 'simplescheduler'), s(simplescheduler_get_teacher_name($simplescheduler)), get_string('groupsession', 'simplescheduler'));
		$slottable->align = array ('left', 'left', 'left', 'left', 'center', 'left', 'left');
		$slottable->data = array();
		$previousdate = '';
		$previoustime = 0;
		$previousendtime = 0;
		$canappoint = false;
		foreach($studentSlots as $key => $aSlot){
			$startdate = simplescheduler_userdate($aSlot->starttime,1);
			$starttime = simplescheduler_usertime($aSlot->starttime,1);
			$endtime = simplescheduler_usertime($aSlot->starttime + ($aSlot->duration * 60),1);
			$startdatestr = ($startdate == $previousdate) ? '' : $startdate ;
			$starttimestr = ($starttime == $previoustime) ? '' : $starttime ;
			$endtimestr = ($endtime == $previousendtime) ? '' : $endtime ;
			$location = s($aSlot->appointmentlocation);
		
		   if ($aSlot->appointedbyme){
				$teacher = $DB->get_record('user', array('id'=>$aSlot->teacherid));
				if ($simplescheduler->simpleschedulermode == 'multi') {
					$radio = "<input type=\"checkbox\" name=\"slotid[{$aSlot->id}]\" value=\"{$aSlot->id}\" checked=\"checked\" />\n";
				} else {
					$radio = "<input type=\"radio\" name=\"slotid\" value=\"{$aSlot->id}\" checked=\"checked\" />\n";
				}
				$slottable->data[] = array ("<b>$startdatestr</b>", "<b>$starttime</b>", "<b>$endtime</b>", "<b>$location</b>",
					$radio, "<b>"."<a href=\"../../user/view.php?id={$aSlot->teacherid}&amp;course=$simplescheduler->course\">".fullname($teacher).'</a></b>','<b>'.$aSlot->groupsession.'</b>');
			} else {
				if ($aSlot->appointed and has_capability('mod/simplescheduler:seeotherstudentsbooking', $context)){
					$appointments = simplescheduler_get_appointments($aSlot->id);
					$collegues = "<div style=\"visibility:hidden; display:none\" id=\"collegues{$aSlot->id}\"><br/>";
					foreach($appointments as $appstudent){
						$student = $DB->get_record('user', array('id'=>$appstudent->studentid));
						$picture = $OUTPUT->user_picture($student, array('courseid'=>$course->id));
						$name = "<a href=\"view.php?id={$cm->id}&amp;what=viewstudent&amp;studentid={$student->id}&amp;course={$simplescheduler->course}&amp;order=DESC\">".fullname($student).'</a>';
						$collegues .= " $picture $name<br/>";
					}
					$collegues .= '</div>';
					$aSlot->groupsession .= " <a href=\"javascript:toggleVisibility('{$aSlot->id}')\"><img name=\"group<?php p($aSlot->id) ?>\" src=\"{$CFG->wwwroot}/pix/t/switch_plus.gif\" border=\"0\" title=\"".get_string('whosthere', 'simplescheduler')."\"></a> {$collegues}";
				}
				$canappoint = true;
				$canusegroup = ($aSlot->appointed) ? 0 : 1;
				if ($simplescheduler->simpleschedulermode == 'multi') {
					$radio = "<input type=\"checkbox\" name=\"slotid[{$aSlot->id}]\" value=\"{$aSlot->id}\" onclick=\"checkGroupAppointment($canusegroup)\" />\n";
				} else {
					$radio = "<input type=\"radio\" name=\"slotid\" value=\"{$aSlot->id}\" onclick=\"checkGroupAppointment($canusegroup)\" />\n";
				}
				$teacher = $DB->get_record('user', array('id'=>$aSlot->teacherid));
				$slottable->data[] = array ($startdatestr, $starttimestr, $endtimestr, $location,
					$radio, "<a href=\"../../user/view.php?id={$aSlot->teacherid}&amp;course={$simplescheduler->course}\">".fullname($teacher).'</a>', $aSlot->groupsession);
			}
			$previoustime = $starttime;
			$previousendtime = $endtime;
			$previousdate = $startdate;
		}
    
    	/// print slot table
    	if (count($slottable->data)) {
        	echo '<form name="appoint" action="view.php" method="get">';
        	echo '<input type="hidden" name="id" value="'. $cm->id . '" />';
        	echo '<input type="hidden" name="what" value="savechoice" />';
        	echo '<script type="text/javascript">';
        	echo 'function checkGroupAppointment(enable) {';
            echo "var numgroups = '". count($mygroups) ."'";
            echo 'if (!enable){';
            echo '    if (numgroups > 1){ // we have a select. we must force "appointsolo".';
            echo "        document.forms['appoint'].elements['appointgroup'].options[0].selected = true;";
            echo '    }';
            echo '}';
            echo "document.forms['appoint'].elements['appointgroup'].disabled = !enable;";
        	echo '}';  
        	echo '</script>';
			echo html_writer::table($slottable);
		}
/// add some global script        

?>
                     <script type="text/javascript">
                        function toggleVisibility(id){
                            obj = document.getElementById('collegues' + id);
                            if (obj.style.visibility == "hidden"){
                                obj.style.visibility = "visible";
                                obj.style.display = "block";
                                document.images["group"+id].src='<?php echo $CFG->wwwroot."/pix/t/switch_minus.gif" ?>';
                            } else {
                                obj.style.visibility = "hidden";
                                obj.style.display = "none";
                                document.images["group"+id].src='<?php echo $CFG->wwwroot."/pix/t/switch_plus.gif" ?>';
                            }
                        }
                     </script>
    <?php

    /*
     Should add a note from the teacher to the student. 
     TODO : addfield into appointments
     echo $OUTPUT->heading(get_string('savechoice', 'simplescheduler'), 3);
     echo '<table><tr><td valign="top" align="right"><b>';
     print_string('studentnotes', 'simplescheduler');
     echo ' :</b></td><td valign="top" align="left"><textarea name="notes" cols="60" rows="20"></textarea></td></tr></table>';
     */
    echo '<br /><input type="submit" value="'.get_string('savechoice', 'simplescheduler').'" /> ';
    if (simplescheduler_group_scheduling_enabled($course, $cm)){
        if (count($mygroups) == 1){
            $groups = array_values($mygroups);
            echo ' <input type="checkbox" name="appointgroup" value="'.$groups[0]->id.'" /> '.get_string('appointformygroup', 'simplescheduler').': '.$groups[0]->name;                    
            echo $OUTPUT->help_icon('appointagroup', 'simplescheduler');
        }
        if (count($mygroups) > 1){
            print_string('appointfor', 'simplescheduler');
            foreach($mygroups as $group){
                $groupchoice[0] = get_string('appointsolo','simplescheduler');
                $groupchoice[$group->id] = $group->name;
            }
            echo html_writer::select($groupchoice, 'appointgroup', '', '');
            echo $OUTPUT->help_icon('appointagroup', 'simplescheduler');
        }
    }

echo '</form>';

if ($haveunattendedappointments and has_capability('mod/simplescheduler:disengage', $context)){
    echo "<br/><a href=\"view.php?id={$cm->id}&amp;what=disengage\">".get_string('disengage','simplescheduler').'</a>';
}
}
else {
    if ($minhidedate > time()){
        $noslots = get_string('noslotsopennow', 'simplescheduler') .'<br/><br/>';
        $noslots .= get_string('firstslotavailable', 'simplescheduler') . '<span style="color:#C00000"><b>'.userdate($minhidedate).'</b></span>';
    } else {
        $noslots = get_string('noslotsavailable', 'simplescheduler') .'<br/><br/>';
    }
    $OUTPUT->box($noslots, 'center', '70%');
}
}
else {
    notify(get_string('noslots', 'simplescheduler'));
}
?>