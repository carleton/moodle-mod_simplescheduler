<?php  

/**
 * Global configuration settings for the simplesscheduler module.
 * 
 * @package    mod
 * @subpackage simplesscheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot.'/mod/simplesscheduler/lib.php');

$settings->add(new admin_setting_configcheckbox('simplesscheduler_showemailplain', get_string('showemailplain', 'simplesscheduler'),
    get_string('showemailplain_desc', 'simplesscheduler'), 0));

$settings->add(new admin_setting_configcheckbox('simplesscheduler_groupscheduling', get_string('groupscheduling', 'simplesscheduler'),
    get_string('groupscheduling_desc', 'simplesscheduler'), 1));

$settings->add(new admin_setting_configtext('simplesscheduler_maxstudentsperslot', get_string('maxstudentsperslot', 'simplesscheduler'),
    get_string('maxstudentsperslot_desc', 'simplesscheduler'), 9, PARAM_INT));

?>
