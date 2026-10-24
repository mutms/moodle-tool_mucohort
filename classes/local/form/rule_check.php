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
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\inforawhtml;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Check the new rule before adding it, the last stage of the add rule wizard.
 *
 * Extra data:
 *  - description: rule description html
 *  - count: number of users matched by the rule
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule_check extends form {
    #[\Override]
    protected function definition(): void {
        $extra = $this->get_extra_data();

        $this->add(new inforawhtml('description', get_string('rule', 'tool_mucohort'), $extra['description']));
        $this->add(new info('count', get_string('rule_matchcount', 'tool_mucohort'), (string)$extra['count']));
        $this->add(new info('draftinfo', '', get_string('rule_draftinfo', 'tool_mucohort')));

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('rule_add', 'tool_mucohort')), 'buttons');
        $this->add(new reload('back', get_string('muform_back', 'tool_mulib')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
