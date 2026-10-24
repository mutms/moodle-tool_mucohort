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

use tool_mulib\muform\element\number;

/**
 * Curated cohort settings shared by create and update forms.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait curated_trait {
    /**
     * Add maximum members and candidate cohort elements.
     *
     * @param int $contextid
     * @param int $cohortid 0 for new cohort
     * @param int $candidatecohortid current candidate cohort
     */
    protected function add_curated_elements(int $contextid, int $cohortid, int $candidatecohortid): void {
        $maxmembers = new number('maxmembers', get_string('maxmembers', 'tool_mucohort'), ['min' => 0, 'max' => 9999999]);
        $maxmembers->add_help_button('maxmembers', 'tool_mucohort');
        $this->add($maxmembers);

        $this->add_candidate_element($contextid, $cohortid, $candidatecohortid);
    }
}
