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

namespace tool_mucohort\local\wizard\rule_add;

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\wizardback;
use tool_mulib\muform\element\wizardcontinue;
use tool_mulib\muform\form;

/**
 * Rule settings, the second stage of the add rule wizard, elements are added by the rule type.
 *
 * Extra data:
 *  - rule: \tool_mucohort\local\rule\base instance
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class settings extends form {
    #[\Override]
    protected function definition(): void {
        /** @var \tool_mucohort\local\rule\base $rule */
        $rule = $this->get_extra_data()['rule'];

        $rule->add_form_elements($this);

        $this->add(new buttons('buttons'));
        $this->add(new wizardcontinue(), 'buttons');
        $this->add(new wizardback(), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        $this->get_extra_data()['rule']->validate_form_data($data, $allerrors);
    }
}
