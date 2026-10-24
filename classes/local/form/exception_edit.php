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

use tool_mulib\muform\element\autocompletemany;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\radiocards;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\textarea;
use tool_mulib\muform\form;
use tool_mucohort\local\exception;
use tool_mucohort\muform\autocompletemany\exception_add_userids;

/**
 * Add or update exceptions.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exception_edit extends form {
    #[\Override]
    protected function definition(): void {
        $current = $this->get_current_data();

        if (empty($current['id'])) {
            $source = new exception_add_userids((int)$current['cohortid']);
            $userids = new autocompletemany('userids', get_string('users'), $source);
            $userids->set_required(true);
            $this->add($userids);
        } else {
            $this->add(new info('fullname', get_string('user')));
        }

        $descriptions = [
            exception::TYPE_FORCED => get_string('exception_forced_desc', 'tool_mucohort'),
            exception::TYPE_BLOCKED => get_string('exception_blocked_desc', 'tool_mucohort'),
        ];
        $type = new radiocards('type', get_string('exception_type', 'tool_mucohort'), exception::get_types(), $descriptions);
        $type->set_required(true);
        $this->add($type);

        $this->add(new textarea('reason', get_string('exception_reason', 'tool_mucohort'), ['rows' => 3]));

        $this->add(new buttons('buttons'));
        if (empty($current['id'])) {
            $submit = get_string('exception_add', 'tool_mucohort');
        } else {
            $submit = get_string('exception_update', 'tool_mucohort');
        }
        $this->add(new submit('submit', $submit), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
