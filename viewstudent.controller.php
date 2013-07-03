<?php

/**
 * Controller for student-related use cases.
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$app = new stdClass();

if ($subaction == 'updatenote' and (has_capability('mod/simplescheduler:manage', $context) or has_capability('mod/simplescheduler:manageallappointments', $context))){
    $app->id = required_param('appid', PARAM_INT);
    $distribute = optional_param('distribute', 0, PARAM_INT);
    
   if ($app->id){
    	$slotid = $DB->get_field('simplescheduler_appointment', 'slotid', array('id' => $app->id));
        if ($distribute){
            echo "distributing";
            $allapps = simplescheduler_get_appointments($slotid);
            foreach($allapps as $anapp){
                $anapp->appointmentnote = required_param('appointmentnote_'.$slotid, PARAM_CLEANHTML);
                $anapp->timemodified = time();
                $DB->update_record('simplescheduler_appointment', $anapp);
            }
        }
        else{
            $app->appointmentnote = required_param('appointmentnote_'.$slotid, PARAM_CLEANHTML);
            $DB->update_record('simplescheduler_appointment', $app);
        }
    }
}
/******************************* Update grades when concerned teacher ************************/
if ($subaction == 'updategrades' and (has_capability('mod/simplescheduler:manage', $context) or has_capability('mod/simplescheduler:manageallappointments', $context))){
    $keys = preg_grep("/^gr(.*)/", array_keys($_POST));
    foreach($keys as $key){
        preg_match("/^gr(.*)/", $key, $matches);
        $app->id = $matches[1];
        $app->grade = required_param($key, PARAM_INT);
        $app->timemodified = time();
        
        $distribute = optional_param('distribute'.$app->id, 0, PARAM_INT);
        if ($distribute){ // distribute to all members
            $slotid = $DB->get_field('simplescheduler_appointment', 'slotid', array('id' => $app->id));
            $allapps = simplescheduler_get_appointments($slotid);
            foreach($allapps as $anapp){
                $anapp->grade = $app->grade;
                $anapp->timemodified = $app->timemodified;
                $DB->update_record('simplescheduler_appointment', $anapp);
                $studentid = $DB->get_field('simplescheduler_appointment', 'studentid', array('id'=>$anapp->id));
                simplescheduler_update_grades($simplescheduler, $studentid);
            }
        }
        else{ // set to current members
            $DB->update_record('simplescheduler_appointment', $app);
            $studentid = $DB->get_field('simplescheduler_appointment', 'studentid', array('id'=>$app->id));
            simplescheduler_update_grades($simplescheduler, $studentid);
        }
    }
}

?>