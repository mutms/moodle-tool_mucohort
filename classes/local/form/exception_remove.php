<?php
// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon

namespace tool_mucohort\local\form;

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Remove exception.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exception_remove extends form {
    #[\Override]
    protected function definition(): void {
        $this->add(new info('fullname', get_string('user')));
        $this->add(new info('exceptiontype', get_string('exception_type', 'tool_mucohort')));
        if (!empty($this->get_extra_data()['canremovemember'])) {
            // Forced users of curated cohorts may be removed from the cohort at the same time.
            $removemember = new checkbox(
                'removemember',
                get_string('exception_removemember', 'tool_mucohort'),
                get_string('exception_removemember_desc', 'tool_mucohort')
            );
            $this->add($removemember);
            $keep = get_string('exception_remove_forced', 'tool_mucohort');
            $this->add(new info('consequencekeep', get_string('exception_consequence', 'tool_mucohort'), $keep));
            $remove = get_string('exception_remove_forced_member', 'tool_mucohort');
            $this->add(new info('consequenceremove', get_string('exception_consequence', 'tool_mucohort'), $remove));
            $this->get_display_manager()->hide_if('consequencekeep', 'removemember', 'checked');
            $this->get_display_manager()->hide_if('consequenceremove', 'removemember', 'notchecked');
        } else {
            $this->add(new info('consequence', get_string('exception_consequence', 'tool_mucohort')));
        }

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('exception_remove', 'tool_mucohort')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
