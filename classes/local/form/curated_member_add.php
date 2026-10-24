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
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use tool_mucohort\muform\autocompletemany\curated_member_add_userids;

/**
 * Add curated cohort members.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class curated_member_add extends form {
    #[\Override]
    protected function definition(): void {
        $cohortid = (int)$this->get_current_data()['cohortid'];
        /** @var curated $cohort */
        $cohort = base::fetch($cohortid);

        $capacity = $cohort->get_remaining_capacity();
        if ($capacity !== null) {
            $text = ($capacity > 0)
                ? get_string('maxmembers_remaining', 'tool_mucohort', $capacity)
                : get_string('maxmembers_closed', 'tool_mucohort');
            $this->add(new info('capacity', get_string('maxmembers', 'tool_mucohort'), $text));
        }

        $candidate = $cohort->get_mucohort()->candidatecohortid;
        if ($candidate) {
            $text = curated::format_candidate_cohort($cohort->get_mucohort());
            $this->add(new info('candidate', get_string('candidatecohort', 'tool_mucohort'), $text, info::HTML));
        }

        $userids = new autocompletemany('userids', get_string('users'), new curated_member_add_userids($cohortid));
        $userids->set_required(true);
        $this->add($userids);

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('member_add', 'tool_mucohort')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        /** @var curated $cohort */
        $cohort = base::fetch((int)$this->get_current_data()['cohortid']);
        $capacity = $cohort->get_remaining_capacity();
        if ($capacity !== null && count($data['userids']) > $capacity) {
            $allerrors['userids'][] = get_string('error_maxmembers', 'tool_mucohort', $capacity);
        }
    }
}
