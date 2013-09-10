<?php

/**
 * Exports the simplescheduler data in spreadsheet format.
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/************************************ ODS (OpenOffice Sheet) download generator ******************************/
if ($action == 'downloadods'){
    require_once($CFG->libdir."/odslib.class.php");
    /// Calculate file name
    $downloadfilename = clean_filename("{$course->shortname}_{$simplescheduler->name}.ods");
    /// Creating a workbook
    $workbook = new MoodleODSWorkbook("-");
}
/************************************ Excel download generator ***********************************************/
if ($action == 'downloadexcel'){
    require_once($CFG->libdir."/excellib.class.php");
    
    /// Calculate file name
    $downloadfilename = clean_filename(shorten_text("{$course->shortname}_{$simplescheduler->name}", 20).".xls");
    /// Creating a workbook
    $workbook = new MoodleExcelWorkbook("-");
}
if($action == 'downloadexcel' || $action == 'downloadods'){
    
    /// Sending HTTP headers
    $workbook->send($downloadfilename);
    
    /// Prepare data
    $sql = "
        SELECT DISTINCT
        u.id,
        u.firstname,
        u.lastname,
        u.email,
        u.department
        FROM
        {simplescheduler_slots} s,
        {user} u
        WHERE
        s.teacherid = u.id AND
        simpleschedulerid = ?
        ";
    $teachers = $DB->get_records_sql($sql, array($simplescheduler->id));
    $slots = $DB->get_records('simplescheduler_slots', array('simpleschedulerid' => $simplescheduler->id), 'starttime', 'id, starttime, duration, exclusivity, teacherid, hideuntil');
    if ($subaction == 'singlesheet'){
        /// Adding the worksheet
        $myxls['singlesheet'] =& $workbook->add_worksheet($COURSE->shortname.': '.format_string($simplescheduler->name));
        $myxls['singlesheet']->write_string(0,0,get_string('date', 'simplescheduler'));
        $myxls['singlesheet']->write_string(0,1,get_string('starttime', 'simplescheduler'));
        $myxls['singlesheet']->write_string(0,2,get_string('endtime', 'simplescheduler'));
        $myxls['singlesheet']->write_string(0,3,get_string('slottype', 'simplescheduler'));
        $myxls['singlesheet']->write_string(0,4,simplescheduler_get_teacher_name($simplescheduler));
        $myxls['singlesheet']->write_string(0,5,get_string('students', 'simplescheduler'));
        $myxls['singlesheet']->set_column(0,0,26);
        $myxls['singlesheet']->set_column(1,2,15);
        $myxls['singlesheet']->set_column(3,3,10);
        $myxls['singlesheet']->set_column(4,4,20);
        $myxls['singlesheet']->set_column(5,5,60);
        $f = $workbook->add_format(array('bold' => 1));
        $myxls['singlesheet']->set_row(0,13,$f);        
    }
    elseif ($subaction == 'byteacher') {
        /// Adding the worksheets
        if ($teachers){
            foreach($teachers as $teacher){
                $myxls[$teacher->id] =& $workbook->add_worksheet(fullname($teacher));
                /// Print names of all the fields
                $myxls[$teacher->id]->write_string(0,0,get_string('date', 'simplescheduler'));
                $myxls[$teacher->id]->write_string(0,1,get_string('starttime', 'simplescheduler'));
                $myxls[$teacher->id]->write_string(0,2,get_string('endtime', 'simplescheduler'));
                $myxls[$teacher->id]->write_string(0,3,get_string('slottype', 'simplescheduler'));
                $myxls[$teacher->id]->write_string(0,4,get_string('students', 'simplescheduler'));
                $myxls[$teacher->id]->set_column(0,0,26);
                $myxls[$teacher->id]->set_column(1,2,15);
                $myxls[$teacher->id]->set_column(3,3,10);
                $myxls[$teacher->id]->set_column(4,4,60);
                $f = $workbook->add_format(array('bold' => 1));
                $myxls[$teacher->id]->set_row(0,13,$f);        
            }
        }
    }
    
    /// Print all the lines of data.
    $i = array();    
    
    if (!empty($slots)) {
        foreach ($slots as $slot) {
            switch($subaction){
                case 'byteacher':
                    $sheetname = $slot->teacherid ;
                    break;
                default :
                    $sheetname = $subaction;
            }
            
            $appointments = $DB->get_records('simplescheduler_appointment', array('slotid' => $slot->id));
            
            /// fill slot data
            $datestart = simplescheduler_userdate($slot->starttime,1);
            $timestart = simplescheduler_usertime($slot->starttime,1);
            $timeend = simplescheduler_usertime($slot->starttime + $slot->duration * 60,1);
            $i[$sheetname] = @$i[$sheetname] + 1;
            $myxls[$sheetname]->write_string($i[$sheetname],0,$datestart);
            $myxls[$sheetname]->write_string($i[$sheetname],1,$timestart);
            $myxls[$sheetname]->write_string($i[$sheetname],2,$timeend);
            switch($slot->exclusivity){
                case 0 : 
                    $myxls[$sheetname]->write_string($i[$sheetname], 3, get_string('unlimited', 'simplescheduler'));
                    break;
                case 1 :
                    $myxls[$sheetname]->write_string($i[$sheetname], 3, get_string('exclusive', 'simplescheduler'));
                    break;
                default :
                	$remaining = ($slot->exclusivity - count($appointments));
                    $myxls[$sheetname]->write_string($i[$sheetname], 3, get_string('limited', 'simplescheduler',$remaining));
            }
            $j = 4;
            if ($subaction == 'singlesheet'){
                $myxls[$sheetname]->write_string($i[$sheetname], $j, fullname($teachers[$slot->teacherid]));
                $j++;
            }
            if (!empty($appointments)) {
                $appointedlist = '';
                foreach($appointments as $appointment){
                    $user = $DB->get_record('user', array('id' => $appointment->studentid), 'id,firstname,lastname');
                    $user->lastname = strtoupper($user->lastname);
                    $appointedlist[] = fullname($user);
                }
                $myxls[$sheetname]->write_string($i[$sheetname], $j, implode(',', $appointedlist));
            }
        }
    }
    
    /// Close the workbook
    $workbook->close();
    exit;    
}
/********************************************* csv generator : get parms ************************************/
if ($action == 'dodownloadcsv'){
    ?>
<center>
<?php 
echo $OUTPUT->heading(get_string('csvparms', 'simplescheduler'));
echo $OUTPUT->box_start() 
?>
<form name="csvparms" method="POST" action="view.php" target="_blank">
<input type="hidden" name="id" value="<?php p($cm->id) ?>" />
<input type="hidden" name="what" value="downloadcsv" />
<input type="hidden" name="page" value="<?php p($page) ?>" />
<input type="hidden" name="subaction" value="<?php p($subaction) ?>" />
<table>
    <tr>
        <td align="right" valign="top"><b><?php print_string('csvrecordseparator','simplescheduler') ?>:</b></td>
        <td align="left" valign="top">
            <select name="csvrecordseparator">
                <option value="CR">[CR] (\r) - OLD MAC</option>
                <option value="CRLF" >[CR][LF] (\r\n) - DOS/WINDOWS</option>
                <option value="LF" selected="selected">[LF] (\n) - LINUX/UNIX</option>
            </select>
        </td>
    </tr>
    <tr>
        <td align="right" valign="top"><b><?php print_string('csvfieldseparator','simplescheduler') ?>:</b></td>
        <td align="left" valign="top">
            <select name="csvfieldseparator">
                <option value="TAB">[TAB]</option>
                <option value=";">;</option>
                <option value=",">,</option>
            </select>
        </td>
    </tr>
    <tr>
        <td align="right" valign="top"><b><?php print_string('csvencoding','simplescheduler') ?>:</b></td>
        <td align="left" valign="top">
            <select name="csvencoding">
                <option value="UTF-16">UTF-16</option>
                <option value="UTF-8" selected="selected" >UTF-8</option>
                <option value="UTF-7">UTF-7</option>
                <option value="ASCII">ASCII</option>
                <option value="EUC-JP">EUC-JP</option>
                <option value="SJIS">SJIS</option>
                <option value="eucJP-win">eucJP-win</option>
                <option value="SJIS-win">SJIS-win</option>
                <option value="ISO-2022-JP">ISO-2022-JP</option>
                <option value="JIS">JIS</option>
                <option value="ISO-8859-1">ISO-8859-1</option>
                <option value="ISO-8859-2">ISO-8859-2</option>
                <option value="ISO-8859-3">ISO-8859-3</option>
                <option value="ISO-8859-4">ISO-8859-4</option>
                <option value="ISO-8859-5">ISO-8859-5</option>
                <option value="ISO-8859-6">ISO-8859-6</option>
                <option value="ISO-8859-7">ISO-8859-7</option>
                <option value="ISO-8859-8">ISO-8859-8</option>
                <option value="ISO-8859-9">ISO-8859-9</option>
                <option value="ISO-8859-10">ISO-8859-10</option>
                <option value="ISO-8859-13">ISO-8859-13</option>
                <option value="ISO-8859-14">ISO-8859-14</option>
                <option value="ISO-8859-15">ISO-8859-15</option>
                <option value="BASE64">BASE64</option>
            </select>
        </td>
    </tr>
    <tr>
        <td align="center" valign="top" colspan="2">
            <input type="submit" name="go_btn" value="<?php print_string('continue') ?>" />
        </td>
    </tr>
</table>
</form>
<?php 
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
exit;
}
/********************************************* csv generator : generate **********************************/
if ($action == 'downloadcsv'){
    
    $ENDLINES = array( 'LF' => "\n", 'CRLF' => "\r\n", 'CR' => "\r");
    $csvrecordseparator = $ENDLINES[required_param('csvrecordseparator', PARAM_TEXT)];
    $csvfieldseparator = required_param('csvfieldseparator', PARAM_TEXT);
    if ($csvfieldseparator == 'TAB'){
        $csvfieldseparator = "\t";
    }
    $csvencoding = required_param('csvencoding', PARAM_CLEAN);
    $downloadfilename = clean_filename(shorten_text("{$course->shortname}_{$simplescheduler->name}", 20).'.csv');     
    /// sending headers
    header("Content-Type:text/csv\n\n");
    header("Content-Disposition: attachment; filename=$downloadfilename");
    
    /// Prepare data
    $sql = "
        SELECT DISTINCT
        u.id,
        u.firstname,
        u.lastname,
        u.email,
        u.department
        FROM
        {simplescheduler_slots} s,
        {user} u
        WHERE
        s.teacherid = u.id AND
        simpleschedulerid = ?
        ";
    $teachers = $DB->get_records_sql($sql, array($simplescheduler->id));
    $stream = '';
    $slots = $DB->get_records('simplescheduler_slots', array('simpleschedulerid' => $simplescheduler->id), 'starttime', 'id, starttime, duration, exclusivity, teacherid, hideuntil');
    if ($subaction == 'slots'){
        /// Making title line
        $stream .= get_string('date', 'simplescheduler') . $csvfieldseparator;
        $stream .= get_string('starttime', 'simplescheduler') . $csvfieldseparator;
        $stream .= get_string('endtime', 'simplescheduler') . $csvfieldseparator;
        $stream .= get_string('slottype', 'simplescheduler') . $csvfieldseparator;
        $stream .= get_string('students', 'simplescheduler') .$csvrecordseparator;
        
        /// Print all the lines of data.
        if (!empty($slots)) {
            foreach ($slots as $slot) {
                $appointments = $DB->get_records('simplescheduler_appointment', array('slotid'=>$slot->id));
                
                /// fill slot data
                $datestart = simplescheduler_userdate($slot->starttime,1);
                $timestart = simplescheduler_usertime($slot->starttime,1);
                $timeend = simplescheduler_usertime($slot->starttime + $slot->duration * 60,1);
                $stream .= $datestart . $csvfieldseparator;
                $stream .= $timestart . $csvfieldseparator;
                $stream .= $timeend . $csvfieldseparator;
                switch($slot->exclusivity){
                    case 0 : 
                        $stream .= get_string('unlimited', 'simplescheduler') . $csvfieldseparator;
                        break;
                    case 1 :
                        $stream .= get_string('exclusive', 'simplescheduler') . $csvfieldseparator;
                        break;
                    default :
                        $stream .= get_string('limited', 'simplescheduler').' '.($slot->exclusivity - count($appointments)) . $csvfieldseparator;
                }
                if (!empty($appointments)) {
                    $appointedlist = '';
                    foreach($appointments as $appointment){
                        $user = $DB->get_record('user', array('id' => $appointment->studentid), 'id,firstname,lastname');
                        $user->lastname = strtoupper($user->lastname);
                        $appointedlist[] = fullname($user);
                    }
                    $stream .= implode(',', $appointedlist);
                }
                $stream .= $csvrecordseparator;
            }
        }
    }
    echo mb_convert_encoding($stream, $csvencoding, 'UTF-8');
    exit;    
}

/*********************************************** download selection **********************************/
else{
    $strdownloadexcelsingle = get_string('strdownloadexcelsingle', 'simplescheduler');
    $strdownloadexcelteachers = get_string('strdownloadexcelteachers', 'simplescheduler', format_string(simplescheduler_get_teacher_name($simplescheduler)));
    $strdownloadodssingle = get_string('strdownloadodssingle', 'simplescheduler');
    $strdownloadodsteachers = get_string('strdownloadodsteachers', 'simplescheduler', format_string(simplescheduler_get_teacher_name($simplescheduler)));
    $strdownloadcsvslots = get_string('strdownloadcsvslots', 'simplescheduler');
    $strdownloadcsvgrades = get_string('strdownloadcsvgrades', 'simplescheduler');
    ?>
<center>
<?php echo $OUTPUT->heading(get_string('downloads', 'simplescheduler')) ?>
<hr width="60%" class="separator"/>
<table>
    <tr>
        <td>
            <form action="view.php" method="post" name="deleteallform" target="_blank">
                <input type="hidden" name="what" value="downloadexcel" />
                <input type="hidden" name="subaction" value="singlesheet" />
                <input type="hidden" name="id" value="<?php p($cm->id) ?>" />
                <input type="submit" name="go_btn" value="<?php echo $strdownloadexcelsingle ?>" style="width:240px"/>
            </form>
        </td>
    </tr>
    <tr>
        <td>
            <form action="view.php" method="post" name="deleteallform" target="_blank">
                <input type="hidden" name="what" value="downloadexcel" />
                <input type="hidden" name="subaction" value="byteacher" />
                <input type="hidden" name="id" value="<?php p($cm->id) ?>" />
                <input type="submit" name="go_btn" value="<?php echo $strdownloadexcelteachers ?>" style="width:240px"/>
            </form>
        </td>
    </tr>
    <tr>
        <td>
            <form action="view.php" method="post" name="deleteallform" target="_blank">
                <input type="hidden" name="what" value="downloadods" />
                <input type="hidden" name="subaction" value="singlesheet" />
                <input type="hidden" name="id" value="<?php p($cm->id) ?>" />
                <input type="submit" name="go_btn" value="<?php echo $strdownloadodssingle ?>" style="width:240px"/>
            </form>
        </td>
    </tr>
    <tr>
        <td>
            <form action="view.php" method="post" name="deleteallform" target="_blank">
                <input type="hidden" name="what" value="downloadods" />
                <input type="hidden" name="subaction" value="byteacher" />
                <input type="hidden" name="id" value="<?php p($cm->id) ?>" />
                <input type="submit" name="go_btn" value="<?php echo $strdownloadodsteachers ?>" style="width:240px"/>
            </form>
        </td>
    </tr>
    <tr>
        <td>
            <form action="view.php" method="post" name="deleteallform">
                <input type="hidden" name="what" value="dodownloadcsv" />
                <input type="hidden" name="subaction" value="slots" />
                <input type="hidden" name="id" value="<?php p($cm->id) ?>" />
                <input type="submit" name="go_btn" value="<?php echo $strdownloadcsvslots ?>" style="width:240px"/>
            </form>
        </td>
    </tr>
</table>
</center>
<?php
}
?>