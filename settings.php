<?php  

/**
 * Global configuration settings for the simplescheduler module.
 * 
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot.'/mod/simplescheduler/lib.php');

$settings->add(new admin_setting_configcheckbox('simplescheduler_showemailplain', get_string('showemailplain', 'simplescheduler'),
    get_string('showemailplain_desc', 'simplescheduler'), 0));

$settings->add(new admin_setting_configcheckbox('simplescheduler_groupscheduling', get_string('groupscheduling', 'simplescheduler'),
    get_string('groupscheduling_desc', 'simplescheduler'), 1));

$settings->add(new admin_setting_configtext('simplescheduler_maxstudentsperslot', get_string('maxstudentsperslot', 'simplescheduler'),
    get_string('maxstudentsperslot_desc', 'simplescheduler'), 9, PARAM_INT));

?>
