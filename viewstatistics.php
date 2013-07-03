<?php

/**
 * Statistics report for the simplesscheduler
 * 
 * @package    mod
 * @subpackage simplesscheduler
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
    $a = ($tab == 'staffbreakdown') ? format_string(simplesscheduler_get_teacher_name($simplesscheduler)) : '';
    $tabname = get_string($tab, 'simplesscheduler', strtolower($a));
    $row[] = new tabobject($tabname, "view.php?what=viewstatistics&amp;id=$cm->id&amp;course=$simplesscheduler->course&amp;page=".$tab, $tabname);
}
$tabrows[] = $row;

print_tabs($tabrows, get_string($page, 'simplesscheduler'));

//display correct type of statistics by request

$attendees = simplesscheduler_get_possible_attendees ($cm, $usergroups); 

switch ($page) {
    case 'overall':
        $sql = '
            SELECT
            COUNT(DISTINCT(a.studentid))
            FROM
            {simplesscheduler_slots} s,
            {simplesscheduler_appointment} a
            WHERE
            s.id = a.slotid AND
            s.simplesschedulerid = ? AND
            a.attended = 1
            ';
        $attended = $DB->count_records_sql($sql, array($simplesscheduler->id));
        
        $sql = '
            SELECT
            COUNT(DISTINCT(a.studentid))
            FROM
            {simplesscheduler_slots} s,
            {simplesscheduler_appointment} a
            WHERE
            s.id = a.slotid AND
            s.simplesschedulerid = ? AND
            a.attended = 0
            ';
        $registered = $DB->count_records_sql($sql, array($simplesscheduler->id));
        
        $sql = '
            SELECT
            COUNT(DISTINCT(s.id))
            FROM
            {simplesscheduler_slots} s
            LEFT JOIN
            {simplesscheduler_appointment} a
            ON
            s.id = a.slotid
            WHERE
            s.simplesschedulerid = ? AND
            s.teacherid = ? AND
            a.attended IS NULL
            ';
        $freeowned = $DB->count_records_sql($sql, array($simplesscheduler->id, $USER->id));
        
        $sql = '
            SELECT
            COUNT(DISTINCT(s.id))
            FROM
            {simplesscheduler_slots} s
            LEFT JOIN
            {simplesscheduler_appointment} a
            ON
            s.id = a.slotid
            WHERE
            s.simplesschedulerid = ? AND
            s.teacherid != ? AND
            a.attended IS NULL
            ';
        $freenotowned = $DB->count_records_sql($sql, array($simplesscheduler->id, $USER->id));
        
        $allattendees = ($attendees) ? count($attendees) : 0 ;
        
        $str = '<h3>'.get_string('attendable', 'simplesscheduler').'</h3>';
        $str .= '<b>'.get_string('attendablelbl', 'simplesscheduler').'</b>: ' . $allattendees . '<br/>';
        $str .= '<h3>'.get_string('attended', 'simplesscheduler').'</h3>';
        $str .= '<b>'.get_string('attendedlbl', 'simplesscheduler').'</b>: ' . $attended . '<br/><br/>';
        $str .= '<h3>'.get_string('unattended', 'simplesscheduler').'</h3>';
        $str .= '<b>'.get_string('registeredlbl', 'simplesscheduler').'</b>: ' . $registered . '<br/>';
        $str .= '<b>'.get_string('unregisteredlbl', 'simplesscheduler').'</b>: ' . ($allattendees - $registered - $attended) . '<br/>'; //BUGFIX
        $str .= '<h3>'.get_string('availableslots', 'simplesscheduler').'</h3>';
        $str .= '<b>'.get_string('availableslotsowned', 'simplesscheduler').'</b>: ' . $freeowned . '<br/>';
        $str .= '<b>'.get_string('availableslotsnotowned', 'simplesscheduler').'</b>: ' . $freenotowned . '<br/>';
        $str .= '<b>'.get_string('availableslotsall', 'simplesscheduler').'</b>: ' . ($freeowned + $freenotowned) . '<br/>';
        
        echo $OUTPUT->box($str);
        
        break;
    case 'studentbreakdown':
        //display the amount of time each student has received
        
        if (!empty($attendees)) {
        	$table = new html_table();
            $table->head  = array (get_string('student', 'simplesscheduler'), get_string('duration', 'simplesscheduler'));
            $table->align = array ('LEFT', 'CENTER');
            $table->width = '70%';
            $table->data = array();
            $sql = '
                SELECT
                a.studentid,
                SUM(s.duration) as totaltime
                FROM
                {simplesscheduler_slots} s,
                {simplesscheduler_appointment} a
                WHERE
                s.id = a.slotid AND
                a.studentid > 0 AND
                s.simplesschedulerid = ?
                GROUP BY
                a.studentid
                ';
            if ($statrecords = $DB->get_records_sql($sql, array($simplesscheduler->id))) {
                foreach($statrecords as $aRecord){
                    $table->data[] = array (fullname($attendees[$aRecord->studentid]), $aRecord->totaltime); // BUGFIX
                }
                
                uasort($table->data, 'byName');
            }
            echo html_writer::table($table);
        }
        else{
            echo $OUTPUT->box(get_string('nostudents', 'simplesscheduler'), 'center', '70%');
        }
        break;
    case 'staffbreakdown':
        //display break down by member of staff
        $sql = '
            SELECT
            s.teacherid,
            SUM(s.duration) as totaltime
            FROM
            {simplesscheduler_slots} s
            LEFT JOIN
            {simplesscheduler_appointment} a
            ON
            a.slotid = s.id
            WHERE
            s.simplesschedulerid = ? AND
            
            a.studentid IS NOT NULL
            GROUP BY
            s.teacherid
            ';
        if ($statrecords = $DB->get_records_sql($sql, array($simplesscheduler->id))) {
        	$table = new html_table();
            $table->width = '70%';
            $table->head  = array (s(simplesscheduler_get_teacher_name($simplesscheduler)), get_string('cumulatedduration', 'simplesscheduler'));
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
            {simplesscheduler_slots} s
            LEFT JOIN
            {simplesscheduler_appointment} a
            ON
            a.slotid = s.id
            WHERE
            a.studentid IS NOT NULL AND
            simplesschedulerid = ?
            GROUP BY
            s.starttime
            ORDER BY
            groupsize DESC
            ';
        if ($groupslots = $DB->get_records_sql($sql, array($simplesscheduler->id))){
        	$table = new html_table();
            $table->head  = array (get_string('duration', 'simplesscheduler'), get_string('appointments', 'simplesscheduler'));
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
            {simplesscheduler_slots} s
            LEFT JOIN
            {simplesscheduler_appointment} a
            ON 
            a.slotid = s.id
            WHERE 
            a.studentid IS NOT NULL AND
            simplesschedulerid = '{$simplesscheduler->id}'
            GROUP BY
            s.starttime
            ORDER BY
            groupsize DESC
            ";
        if ($groupslots = $DB->get_records_sql($sql)){
        	$table = new html_table();
            $table->head  = array (get_string('groupsize', 'simplesscheduler'), get_string('occurrences', 'simplesscheduler'), get_string('cumulatedduration', 'simplesscheduler'));
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
print_continue("$CFG->wwwroot/mod/simplesscheduler/view.php?id=".$cm->id);
/// Finish the page
echo $OUTPUT->footer($course);
exit;
?>