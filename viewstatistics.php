<?php

/**
 * Statistics report for the simplescheduler
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

// a function utility for sorting stat results
function byName($a, $b){
    return strcasecmp($a[0],$b[0]);
}

// precompute groups in case partial popuation is considered by grouping 

$groups = groups_get_all_groups($COURSE->id, 0, $cm->groupingid);
$usergroups = array_keys($groups);

//display statistics tabs

$tabs = array('overall', 'studentbreakdown', 'staffbreakdown','lengthbreakdown','groupbreakdown');
$tabrows = array();
$row  = array();
$currenttab = '';
foreach ($tabs as $tab) {
    $a = ($tab == 'staffbreakdown') ? format_string(simplescheduler_get_teacher_name($simplescheduler)) : '';
    $tabname = get_string($tab, 'simplescheduler', strtolower($a));
    $row[] = new tabobject($tabname, "view.php?what=viewstatistics&amp;id=$cm->id&amp;course=$simplescheduler->course&amp;page=".$tab, $tabname);
}
$tabrows[] = $row;

print_tabs($tabrows, get_string($page, 'simplescheduler'));

//display correct type of statistics by request

$attendees = simplescheduler_get_possible_attendees ($cm, $usergroups); 

switch ($page) {
    case 'overall':
        $sql = '
            SELECT
            COUNT(DISTINCT(a.studentid))
            FROM
            {simplescheduler_slots} s,
            {simplescheduler_appointment} a
            WHERE
            s.id = a.slotid AND
            s.simpleschedulerid = ? AND
            a.attended = 1
            ';
        $attended = $DB->count_records_sql($sql, array($simplescheduler->id));
        
        $sql = '
            SELECT
            COUNT(DISTINCT(a.studentid))
            FROM
            {simplescheduler_slots} s,
            {simplescheduler_appointment} a
            WHERE
            s.id = a.slotid AND
            s.simpleschedulerid = ? AND
            a.attended = 0
            ';
        $registered = $DB->count_records_sql($sql, array($simplescheduler->id));
        
        $sql = '
            SELECT
            COUNT(DISTINCT(s.id))
            FROM
            {simplescheduler_slots} s
            LEFT JOIN
            {simplescheduler_appointment} a
            ON
            s.id = a.slotid
            WHERE
            s.simpleschedulerid = ? AND
            s.teacherid = ? AND
            a.attended IS NULL
            ';
        $freeowned = $DB->count_records_sql($sql, array($simplescheduler->id, $USER->id));
        
        $sql = '
            SELECT
            COUNT(DISTINCT(s.id))
            FROM
            {simplescheduler_slots} s
            LEFT JOIN
            {simplescheduler_appointment} a
            ON
            s.id = a.slotid
            WHERE
            s.simpleschedulerid = ? AND
            s.teacherid != ? AND
            a.attended IS NULL
            ';
        $freenotowned = $DB->count_records_sql($sql, array($simplescheduler->id, $USER->id));
        
        $allattendees = ($attendees) ? count($attendees) : 0 ;
        
        $str = '<h3>'.get_string('attendable', 'simplescheduler').'</h3>';
        $str .= '<b>'.get_string('attendablelbl', 'simplescheduler').'</b>: ' . $allattendees . '<br/>';
        $str .= '<h3>'.get_string('attended', 'simplescheduler').'</h3>';
        $str .= '<b>'.get_string('attendedlbl', 'simplescheduler').'</b>: ' . $attended . '<br/><br/>';
        $str .= '<h3>'.get_string('unattended', 'simplescheduler').'</h3>';
        $str .= '<b>'.get_string('registeredlbl', 'simplescheduler').'</b>: ' . $registered . '<br/>';
        $str .= '<b>'.get_string('unregisteredlbl', 'simplescheduler').'</b>: ' . ($allattendees - $registered - $attended) . '<br/>'; //BUGFIX
        $str .= '<h3>'.get_string('availableslots', 'simplescheduler').'</h3>';
        $str .= '<b>'.get_string('availableslotsowned', 'simplescheduler').'</b>: ' . $freeowned . '<br/>';
        $str .= '<b>'.get_string('availableslotsnotowned', 'simplescheduler').'</b>: ' . $freenotowned . '<br/>';
        $str .= '<b>'.get_string('availableslotsall', 'simplescheduler').'</b>: ' . ($freeowned + $freenotowned) . '<br/>';
        
        echo $OUTPUT->box($str);
        
        break;
    case 'studentbreakdown':
        //display the amount of time each student has received
        
        if (!empty($attendees)) {
        	$table = new html_table();
            $table->head  = array (get_string('student', 'simplescheduler'), get_string('duration', 'simplescheduler'));
            $table->align = array ('LEFT', 'CENTER');
            $table->width = '70%';
            $table->data = array();
            $sql = '
                SELECT
                a.studentid,
                SUM(s.duration) as totaltime
                FROM
                {simplescheduler_slots} s,
                {simplescheduler_appointment} a
                WHERE
                s.id = a.slotid AND
                a.studentid > 0 AND
                s.simpleschedulerid = ?
                GROUP BY
                a.studentid
                ';
            if ($statrecords = $DB->get_records_sql($sql, array($simplescheduler->id))) {
                foreach($statrecords as $aRecord){
                    $table->data[] = array (fullname($attendees[$aRecord->studentid]), $aRecord->totaltime); // BUGFIX
                }
                
                uasort($table->data, 'byName');
            }
            echo html_writer::table($table);
        }
        else{
            echo $OUTPUT->box(get_string('nostudents', 'simplescheduler'), 'center', '70%');
        }
        break;
    case 'staffbreakdown':
        //display break down by member of staff
        $sql = '
            SELECT
            s.teacherid,
            SUM(s.duration) as totaltime
            FROM
            {simplescheduler_slots} s
            LEFT JOIN
            {simplescheduler_appointment} a
            ON
            a.slotid = s.id
            WHERE
            s.simpleschedulerid = ? AND
            
            a.studentid IS NOT NULL
            GROUP BY
            s.teacherid
            ';
        if ($statrecords = $DB->get_records_sql($sql, array($simplescheduler->id))) {
        	$table = new html_table();
            $table->width = '70%';
            $table->head  = array (s(simplescheduler_get_teacher_name($simplescheduler)), get_string('cumulatedduration', 'simplescheduler'));
            $table->align = array ('LEFT', 'CENTER');
            foreach($statrecords as $aRecord){
                $aTeacher = $DB->get_record('user', array('id'=>$aRecord->teacherid));
                $table->data[] = array (fullname($aTeacher), $aRecord->totaltime);
            }
            uasort($table->data, 'byName');
            echo html_writer::table($table);
        }
        break;
    case 'lengthbreakdown':
        //display by number of atendees to one member of staff
        $sql = '
            SELECT
            s.starttime,
            COUNT(*) as groupsize,
            MAX(s.duration) as duration
            FROM
            {simplescheduler_slots} s
            LEFT JOIN
            {simplescheduler_appointment} a
            ON
            a.slotid = s.id
            WHERE
            a.studentid IS NOT NULL AND
            simpleschedulerid = ?
            GROUP BY
            s.starttime
            ORDER BY
            groupsize DESC
            ';
        if ($groupslots = $DB->get_records_sql($sql, array($simplescheduler->id))){
        	$table = new html_table();
            $table->head  = array (get_string('duration', 'simplescheduler'), get_string('appointments', 'simplescheduler'));
            $table->align = array ('LEFT', 'CENTER');
            $table->width = '70%';
            
            $durationcount = array();
            foreach($groupslots as $slot) {
                if (array_key_exists($slot->duration, $durationcount)) {
                    $durationcount[$slot->duration] ++;
                } else {
                    $durationcount[$slot->duration] = 1;
                }
            }
            foreach ($durationcount as $key => $duration) {
                $table->data[] = array ($key, $duration);
            }        
            echo html_writer::table($table);
        }         
        break;
    case 'groupbreakdown':
        //display by number of atendees to one member of staff
        $sql = "
            SELECT
            s.starttime,
            COUNT(*) as groupsize,
            MAX(s.duration) as duration
            FROM 
            {simplescheduler_slots} s
            LEFT JOIN
            {simplescheduler_appointment} a
            ON 
            a.slotid = s.id
            WHERE 
            a.studentid IS NOT NULL AND
            simpleschedulerid = '{$simplescheduler->id}'
            GROUP BY
            s.starttime
            ORDER BY
            groupsize DESC
            ";
        if ($groupslots = $DB->get_records_sql($sql)){
        	$table = new html_table();
            $table->head  = array (get_string('groupsize', 'simplescheduler'), get_string('occurrences', 'simplescheduler'), get_string('cumulatedduration', 'simplescheduler'));
            $table->align = array ('LEFT', 'CENTER', 'CENTER');
            $table->width = '70%';
            $grouprows = array();
            foreach($groupslots as $aGroup){
                if (!array_key_exists($aGroup->groupsize, $grouprows)){
                    $grouprows[$aGroup->groupsize]->occurrences = 0;
                    $grouprows[$aGroup->groupsize]->duration = 0;
                }                
                $grouprows[$aGroup->groupsize]->occurrences++;
                $grouprows[$aGroup->groupsize]->duration += $aGroup->duration;
            }
            foreach(array_keys($grouprows) as $aGroupSize){
                $table->data[] = array ($aGroupSize,$grouprows[$aGroupSize]->occurrences, $grouprows[$aGroupSize]->duration);
            }
            echo html_writer::table($table);
        }
}
echo '<br/>';
print_continue("$CFG->wwwroot/mod/simplescheduler/view.php?id=".$cm->id);
/// Finish the page
echo $OUTPUT->footer($course);
exit;
?>