<?php

/**
 * Shows a sortable list of appointments
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

include_once $CFG->libdir.'/tablelib.php';


if (has_capability('mod/simplescheduler:canseeotherteachersbooking', $context)) {
    $teacherid = optional_param('teacherid', $USER->id, PARAM_INT);
    $select = " teacherid = $teacherid ";
    $tutor =  $DB->get_record('user', array('id' => $teacherid));
    $teachers = simplescheduler_get_attendants ($cm->id); // BUGFIX 
    
    foreach($teachers as $teacher){
        $teachermenu[$teacher->id] = fullname($teacher);
    }
    ?>
        <form name="teacherform">
        <input type="hidden" name="id" value="<?php p($cm->id) ?>" />
        <input type="hidden" name="what" value="datelist" />
        <?php echo html_writer::select($teachermenu, 'teacherid', $teacherid); ?>
        <input type="submit" value="Go" />
        </form>      
        <hr />
        <?php
}
else{
    $select = " teacherid = $USER->id ";
}

/// getting date list

$sql = "
    SELECT 
    a.id AS id,
    u1.id AS studentid,
    u1.email AS studentmail,
    u1.lastname AS studentlastname, 
    u1.firstname AS studentfirstname,
    u1.department AS studentdepartment, 
    a.appointmentnote,
    a.grade,
    sc.name,
    sc.id as simpleschedulerid,
    c.shortname as courseshort,
    c.id as courseid,
    u2.email,
    u2.lastname, 
    u2.firstname, 
    s.id as sid,
    s.starttime, 
    s.duration, 
    s.appointmentlocation, 
    s.notes 
    FROM
    {course} c,
    {simplescheduler} sc,
    {simplescheduler_appointment} a,
    {simplescheduler_slots} s,
    {user} u1,
    {user} u2
    WHERE
    c.id = sc.course AND
    sc.id = s.simpleschedulerid AND
    a.slotid = s.id AND
    u1.id = a.studentid AND
    u2.id = s.teacherid AND
    u2.id = ?";

$sqlcount = "
    SELECT
    COUNT(*)
    FROM
    {course} as c,
    {simplescheduler} as sc,
    {simplescheduler_appointment} a,
    {simplescheduler_slots} s,
    {user} u1,
    {user} u2
    WHERE
    c.id = sc.course AND
    sc.id = s.simpleschedulerid AND
    a.slotid = s.id AND
    u1.id = a.studentid AND
    u2.id = s.teacherid AND
    u2.id = ?
    ";
$numrecords = $DB->count_records_sql($sqlcount, array($teacherid));


$limit = 30;

if ($numrecords){
    
    /// make table result
    
    $coursestr = get_string('course','simplescheduler');
    $simpleschedulerstr = get_string('simplescheduler','simplescheduler');
    $whenstr = get_string('when','simplescheduler');
    $wherestr = get_string('where','simplescheduler'); 
    $whostr = get_string('who','simplescheduler');
    $wherefromstr = get_string('department','simplescheduler');
    $whatstr = get_string('what','simplescheduler');
    $whatresultedstr = get_string('whatresulted','simplescheduler');
    $whathappenedstr = get_string('whathappened','simplescheduler');
    
    
    $tablecolumns = array('courseshort', 'simpleschedulerid', 'starttime', 'appointmentlocation', 'studentfirstname', 'department', 'notes', 'appointmentnote');
    $tableheaders = array("<b>$coursestr</b>", "<b>$simpleschedulerstr</b>", "<b>$whenstr</b>", "<b>$wherestr</b>", "<b>$whostr</b>", "<b>$wherefromstr</b>", "<b>$whatstr</b>", "<b>$whathappenedstr</b>");
    
    $table = new flexible_table('mod-simplescheduler-datelist');
    $table->define_columns($tablecolumns);
    $table->define_headers($tableheaders);
    
    $table->define_baseurl($CFG->wwwroot.'/mod/simplescheduler/view.php?what=datelist&amp;id='.$cm->id);
    
    $table->sortable(true, 'when'); //sorted by date by default
    $table->collapsible(true);
    $table->initialbars(true);
    
    // allow column hiding
    $table->column_suppress('course');
    $table->column_suppress('starttime');
    
    $table->set_attribute('cellspacing', '0');
    $table->set_attribute('id', 'dates');
    $table->set_attribute('class', 'datelist');
    $table->set_attribute('width', '100%');
    
    $table->column_class('course', 'datelist_course');
    $table->column_class('simplescheduler', 'datelist_simplescheduler');
    $table->column_class('starttime', 'timelabel');
    
    $table->setup();
    
    /// get extra query parameters from flexible_table behaviour
    $where = $table->get_sql_where();
    $sort = $table->get_sql_sort();
    $table->pagesize($limit, count($numrecords));
    
    
    if (!empty($sort)){
        $sql .= " ORDER BY $sort";
    }
    
    $results = $DB->get_records_sql($sql, array($teacherid));

    
    // display implements a "same value don't appear again" filter
    $coursemem = '';
    $simpleschedulermem = '';
    $whenmem = '';
    $whomem = '';
    $whatmem = '';
    foreach($results as $id => $row){
        $coursedata = "<a href=\"{$CFG->wwwroot}/course/view.php?id={$row->courseid}\">".$row->courseshort.'</a>';
        $coursemem = $row->courseshort;
        $simpleschedulerdata = "<a href=\"{$CFG->wwwroot}/mod/simplescheduler/view.php?a={$row->simpleschedulerid}\">".$row->name.'</a>';
        $simpleschedulermem = $row->name;
        $whendata = '<strong>'.date("d M Y G:i", $row->starttime).' '.get_string('for','simplescheduler')." $row->duration ".get_string('mins', 'simplescheduler').'</strong>';
        $whenmem = "$row->starttime $row->duration";
        $whodata = "<a href=\"{$CFG->wwwroot}/mod/simplescheduler/view.php?what=viewstudent&a={$row->simpleschedulerid}&amp;studentid=$row->studentid&amp;course=$row->courseid\">".$row->studentfirstname.' '.$row->studentlastname.'</a>';
        $whomem = $row->studentmail;
        $whatdata = format_string($row->notes);
        $whatmem = $row->notes;
        $dataset = array(
            $coursedata,
            $simpleschedulerdata,
            $whendata, 
            $row->appointmentlocation, 
            $whodata, 
            $row->studentdepartment, 
            $whatdata, 
            $row->appointmentnote);
        $table->add_data($dataset);		
    }
    $table->print_html();
    print_continue($CFG->wwwroot."/mod/simplescheduler/view.php?id=".$cm->id.'&amp;page='.$page);
}
else{
    notice(get_string('noresults', 'simplescheduler'), $CFG->wwwroot."/mod/simplescheduler/view.php?id=".$cm->id);
}
?>