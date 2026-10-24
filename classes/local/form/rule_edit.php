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

use core\param;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\hidden;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Rule settings, used in the add rule wizard and for editing of existing rules.
 *
 * The form is storage agnostic, elements are added by the rule type.
 *
 * Extra data:
 *  - rule: \tool_mucohort\local\rule\base instance
 *  - wizard: true when used as a wizard stage
 *  - currenthash: hash of the edited rule as it is stored now, null when adding
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule_edit extends form {
    #[\Override]
    protected function definition(): void {
        $extra = $this->get_extra_data();
        /** @var \tool_mucohort\local\rule\base $rule */
        $rule = $extra['rule'];

        if (empty($extra['wizard'])) {
            $this->add(new hidden('hash', param::ALPHANUM));
        }

        $rule->add_form_elements($this);

        $this->add(new buttons('buttons'));
        if (empty($extra['wizard'])) {
            $this->add(new submit('submit', get_string('rule_update', 'tool_mucohort')), 'buttons');
        } else {
            $this->add(new submit('submit', get_string('continue')), 'buttons');
            $this->add(new reload('back', get_string('muform_back', 'tool_mulib')), 'buttons');
        }
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        $extra = $this->get_extra_data();
        if (empty($extra['wizard']) && ($data['hash'] ?? null) !== $extra['currenthash']) {
            $allerrors['hash'][] = get_string('error_rule_changed', 'tool_mucohort');
            return;
        }
        $extra['rule']->validate_form_data($data, $allerrors);
    }
}
