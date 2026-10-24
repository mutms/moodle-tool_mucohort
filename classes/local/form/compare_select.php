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
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;
use tool_mucohort\muform\autocompletemany\compare_cohortids;

/**
 * Select cohorts to compare with.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class compare_select extends form {
    #[\Override]
    protected function definition(): void {
        $cohortid = (int)$this->get_current_data()['cohortid'];

        $withids = new autocompletemany('withids', get_string('compare_with', 'tool_mucohort'), new compare_cohortids($cohortid));
        $withids->set_required(true);
        $this->add($withids);

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('compare', 'tool_mucohort')), 'buttons');
    }
}
