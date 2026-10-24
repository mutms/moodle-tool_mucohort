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

use tool_mulib\muform\element\autocomplete;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\checkboxcards;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;
use tool_mucohort\local\type\advanced;
use tool_mucohort\muform\autocomplete\access_add_userid;

/**
 * Add or update access record.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_edit extends form {
    #[\Override]
    protected function definition(): void {
        /** @var advanced $cohort */
        $cohort = $this->get_extra_data()['cohort'];
        $current = $this->get_current_data();

        if (empty($current['id'])) {
            $userid = new autocomplete('userid', get_string('user'), new access_add_userid($cohort->get_id()));
            $userid->set_required(true);
            $this->add($userid);
        } else {
            $this->add(new info('fullname', get_string('user')));
        }

        $curator = new checkbox('iscurator', get_string('curator', 'tool_mucohort'), get_string('curator_desc', 'tool_mucohort'));
        $this->add($curator);

        $flags = new checkboxcards(
            'flags',
            get_string('access_permissions', 'tool_mucohort'),
            $cohort->get_access_flags(),
            $cohort->get_access_flag_descriptions()
        );
        $this->add($flags);

        $this->add(new buttons('buttons'));
        $submit = empty($current['id']) ? get_string('access_add', 'tool_mucohort') : get_string('access_update', 'tool_mucohort');
        $this->add(new submit('submit', $submit), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        if (!\tool_mucohort\local\access::are_flags_valid($data['flags'])) {
            $allerrors['flags'][] = get_string('error_viewmembersrequired', 'tool_mucohort');
        }
    }
}
