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
use tool_mulib\muform\element\editor;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\text;
use tool_mulib\muform\element\yesno;
use tool_mucohort\local\type\base;
use tool_mucohort\muform\autocomplete\candidate_cohortid;

/**
 * Core cohort settings shared by create and update forms of all types.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait cohort_trait {
    /**
     * Add name and idnumber elements.
     */
    protected function add_name_elements(): void {
        $name = new text('name', get_string('name', 'core_cohort'), ['maxlength' => 254, 'width' => 'full']);
        $name->set_required(true);
        $this->add($name);

        $idnumber = new text('idnumber', get_string('idnumber', 'core_cohort'), ['type' => 'rawtext', 'maxlength' => 100]);
        $this->add($idnumber);
    }

    /**
     * Add visibility, theme and description elements.
     */
    protected function add_settings_elements(): void {
        global $CFG;

        $visible = new yesno('visible', get_string('visible', 'core_cohort'));
        $visible->add_help_button('visible', 'core_cohort');
        $this->add($visible);

        if (!empty($CFG->allowcohortthemes)) {
            require_once($CFG->dirroot . '/cohort/lib.php');
            $themes = array_merge(['' => get_string('forceno')], cohort_get_list_of_themes());
            $this->add(new select('theme', get_string('forcetheme'), array_map('strval', $themes)));
        }

        $this->add(new editor('description', get_string('description'), -1));
    }

    /**
     * Add candidate cohort element of advanced cohorts.
     *
     * @param int $contextid
     * @param int $cohortid 0 for new cohort
     * @param int $candidatecohortid current candidate cohort
     */
    protected function add_candidate_element(int $contextid, int $cohortid, int $candidatecohortid): void {
        $source = new candidate_cohortid($contextid, $cohortid, $candidatecohortid);
        $candidate = new autocomplete('candidatecohortid', get_string('candidatecohort', 'tool_mucohort'), $source);
        $candidate->add_help_button('candidatecohort', 'tool_mucohort');
        $this->add($candidate);
    }

    /**
     * Validate idnumber.
     *
     * @param array $data
     * @param array $allerrors
     * @param int|null $cohortid
     */
    protected function validate_idnumber(array $data, array &$allerrors, ?int $cohortid): void {
        $idnumber = $data['idnumber'] ?? '';
        if ($idnumber === '') {
            return;
        }
        if (trim($idnumber) !== $idnumber) {
            $allerrors['idnumber'][] = get_string('error_idnumberspaces', 'tool_mucohort');
            return;
        }
        if (base::is_idnumber_used($idnumber, $cohortid)) {
            $allerrors['idnumber'][] = get_string('duplicateidnumber', 'core_cohort');
        }
    }
}
