Simple Appointment Scheduler for Moodle 2.x

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details:

http://www.gnu.org/copyleft/gpl.html


=== Description ===

The Simple Scheduler module helps you to schedule appointments with your students. 
Teachers specify time slots for meetings, students then choose one of them on Moodle.
Teacher can record comments on the meeting within the module.

simplescheduler is a fork of scheduler that aims for the following:

* support for single and multiple slot signup
* simplified UI that supports just core scheduling functionality

=== Installation instructions ===

Place the code of the module into the mod/simplescheduler directory of your Moodle
directory root. That is, the present file should be located at:
mod/simplescheduler/README.txt

For further installation instructions please see:
    http://docs.moodle.org/en/Installing_contributed_modules_or_plugins

This module is intended for Moodle 2.3 and above.


=== Authors ===

The fork is maintained by Nathan White, Carleton College <nwhite@carleton.edu>

Based on previous work on scheduler by:

* Henning Bostelmann, University of York <henning.bostelmann@york.ac.uk>
* Gustav Delius <gustav.delius@york.ac.uk> (until Moodle 1.7)
* Valery Fremaux <valery.fremaux@club-internet.fr> (Moodle 1.8 - Moodle 1.9)

With further contributions taken from:

* Vivek Arora (independent migration of the module to 2.0)
* Andriy Semenets (Russian and Ukrainian localization)
* Gaël Mifsud (French localization)
* Various authors of the core Moodle code

=== Release notes ===

This is experimental. It is a highly changed version of the scheduler code base.

There are many areas of the original code base that have not been altered, and
many other areas that have been totally removed.

Here is a list of overall @todos - there is probably more. This is alpha code.

1. Remove JavaScript hacks that are relied upon for saving teacher comments.
2. Make the "Students who still need to make an appointment" section a separate tab.
3. Verify that capabilities are properly setup and checked at the correct point in the code.
4. Test group functionality - remove it or make it work.
5. Add warning message on slot delete action.
6. Make integration with calendar smarter / more efficient.