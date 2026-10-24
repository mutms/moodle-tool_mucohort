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
use tool_mulib\muform\element\customfields;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Update curated cohort settings.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class curated_cohort_update extends form {
    use cohort_trait;
    use curated_trait;

    #[\Override]
    protected function definition(): void {
        $current = $this->get_current_data();
        $cohortid = (int)$current['id'];

        $this->add_name_elements();
        $this->add_settings_elements();
        $this->add_curated_elements((int)$current['contextid'], $cohortid, (int)($current['candidatecohortid'] ?? 0));

        $this->add(new customfields('customfields', \core_cohort\customfield\cohort_handler::create(), $cohortid));

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('cohort_update', 'tool_mucohort')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        $this->validate_idnumber($data, $allerrors, (int)$this->get_current_data()['id']);
    }
}
