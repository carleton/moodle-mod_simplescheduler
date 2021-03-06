<?PHP

/**
 * Version information for mod/simplescheduler
 *
 * @package    mod
 * @subpackage simplescheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This is the development branch (master) of the simple simplescheduler module.
 */

$module->version  = 2013070400;       // The current module version (Date: YYYYMMDDXX)
$module->release  = '.9 dev';        // Human-friendly version name
$module->requires = 2012062500;       // Requires Moodle 2.3
$module->maturity = MATURITY_ALPHA;   // Alpha development code - not for production sites

$module->cron     = 60;               // Period for cron to check this module (secs)

?>
