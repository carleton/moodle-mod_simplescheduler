<?php  

/**
 * Global configuration settings for the scheduler module.
 * 
 * @package    mod
 * @subpackage scheduler
 * @copyright  2011 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot.'/mod/scheduler/lib.php');

$settings->add(new admin_setting_configcheckbox('scheduler_showemailplain', get_string('showemailplain', 'scheduler'),
    get_string('showemailplain_desc', 'scheduler'), 0));

$settings->add(new admin_setting_configcheckbox('scheduler_groupscheduling', get_string('groupscheduling', 'scheduler'),
    get_string('groupscheduling_desc', 'scheduler'), 1));

$settings->add(new admin_setting_configtext('scheduler_maxstudentsperslot', get_string('maxstudentsperslot', 'scheduler'),
    get_string('maxstudentsperslot_desc', 'scheduler'), 9, PARAM_INT));

?>
