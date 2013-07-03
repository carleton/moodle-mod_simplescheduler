<?php

/**
 * @package    mod
 * @subpackage simplesscheduler
 * @copyright  2013 Nathan White and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/mod/simplesscheduler/backup/moodle2/backup_simplesscheduler_stepslib.php'); // Because it exists (must)

/**
 * simplesscheduler backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_simplesscheduler_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Scheduler only has one structure step
        $this->add_step(new backup_simplesscheduler_activity_structure_step('simplesscheduler_structure', 'simplesscheduler.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // Link to the list of simplesschedulers
        $search="/(".$base."\/mod\/simplesscheduler\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@SCHEDULERINDEX*$2@$', $content);

        // Link to simplesscheduler view by coursemoduleid
        $search="/(".$base."\/mod\/simplesscheduler\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@SCHEDULERVIEWBYID*$2@$', $content);

        return $content;
    }
}
