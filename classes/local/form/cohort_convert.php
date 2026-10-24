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
use tool_mucohort\local\type\rules;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkboxcards;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\radiocards;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Convert cohort to a different type.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_convert extends form {
    #[\Override]
    protected function definition(): void {
        $this->add(new info('name', get_string('name', 'core_cohort')));
        $this->add(new info('fromtype', get_string('convert_from', 'tool_mucohort')));
        $extra = $this->get_extra_data();
        $type = new radiocards('type', get_string('convert_to', 'tool_mucohort'), $extra['types'], $extra['descriptions']);
        $type->set_required(true);
        $this->add($type);

        if (isset($extra['types'][rules::TYPE])) {
            // Safe defaults for migration, members are not removed until people decide so.
            $synctype = new radiocards(
                'synctype',
                get_string('rules_synctype', 'tool_mucohort'),
                rules::get_synctypes(),
                rules::get_synctype_descriptions()
            );
            $synctype->set_default(rules::SYNCTYPE_ADDONLY);
            $this->add($synctype);
            $this->get_display_manager()->hide_if('synctype', 'type', 'neq', rules::TYPE);

            $seedforced = new checkboxcards(
                'seedforced',
                '',
                ['seedforced' => get_string('convert_seedforced', 'tool_mucohort')],
                ['seedforced' => get_string('convert_seedforced_desc', 'tool_mucohort')]
            );
            $this->add($seedforced);
            $this->get_display_manager()->hide_if('seedforced', 'type', 'neq', rules::TYPE);
        }

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('convert', 'tool_mucohort')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
