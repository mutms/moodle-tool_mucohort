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
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\hidden;
use tool_mulib\muform\element\inforawhtml;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Approve draft rules.
 *
 * Extra data:
 *  - message: html text explaining the change
 *  - canapprove: false when draft rules cannot be approved
 *  - cansync: true when sync can be queued after approval
 *  - currenthash: hash of draft rules as they are stored now
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rules_approve extends form {
    #[\Override]
    protected function definition(): void {
        $extra = $this->get_extra_data();

        $this->add(new hidden('hash', param::ALPHANUM));
        $this->add(new inforawhtml('message', '', $extra['message']));

        if ($extra['canapprove'] && $extra['cansync']) {
            $syncnow = new checkbox('syncnow', '', get_string('rules_approve_syncnow', 'tool_mucohort'));
            $syncnow->set_default(1);
            $this->add($syncnow);
        }

        $this->add(new buttons('buttons'));
        if ($extra['canapprove']) {
            $this->add(new submit('submit', get_string('rules_approve', 'tool_mucohort')), 'buttons');
        }
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        if (($data['hash'] ?? null) !== $this->get_extra_data()['currenthash']) {
            $allerrors['hash'][] = get_string('error_rule_changed', 'tool_mucohort');
        }
    }
}
