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

use tool_mucohort\local\type\rules;
use tool_mulib\muform\element\checkboxcards;
use tool_mulib\muform\element\duration;
use tool_mulib\muform\element\radiocards;
use tool_mulib\muform\element\select;

/**
 * Rule-based cohort settings shared by create and update forms.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait rules_trait {
    /**
     * Add sync type and schedule elements, changes apply from the next sync.
     */
    protected function add_rules_elements(): void {
        $synctype = new radiocards(
            'synctype',
            get_string('rules_synctype', 'tool_mucohort'),
            rules::get_synctypes(),
            rules::get_synctype_descriptions()
        );
        $synctype->set_default(rules::SYNCTYPE_FULL);
        $synctype->set_required(true);
        $this->add($synctype);

        $interval = new duration('syncinterval', get_string('rules_syncinterval', 'tool_mucohort'), ['w', 'd', 'h']);
        $interval->set_default(DAYSECS);
        $interval->add_help_button('rules_syncinterval', 'tool_mucohort');
        $this->add($interval);
        $this->get_display_manager()->hide_if('syncinterval', 'synctype', 'eq', rules::SYNCTYPE_NONE);

        $priority = new select('syncpriority', get_string('rules_syncpriority', 'tool_mucohort'), rules::get_syncpriorities());
        $priority->set_default(rules::SYNCPRIORITY_NORMAL);
        $priority->add_help_button('rules_syncpriority', 'tool_mucohort');
        $this->add($priority);
        $this->get_display_manager()->hide_if('syncpriority', 'synctype', 'eq', rules::SYNCTYPE_NONE);
        // Empty duration has value 0, manual sync has no priority.
        $this->get_display_manager()->hide_if('syncpriority', 'syncinterval', 'eq', 0);

        $instant = new checkboxcards(
            'instantsync',
            get_string('rules_instantsync', 'tool_mucohort'),
            rules::get_instant_sync_options(),
            rules::get_instant_sync_descriptions()
        );
        $this->add($instant);
        $this->get_display_manager()->hide_if('instantsync', 'synctype', 'eq', rules::SYNCTYPE_NONE);
    }

    /**
     * Validate sync settings.
     *
     * @param array $data
     * @param array $allerrors
     */
    protected function validate_rules_elements(array $data, array &$allerrors): void {
        $interval = (int)($data['syncinterval'] ?? 0);
        if ($interval > 0 && $interval < HOURSECS) {
            $allerrors['syncinterval'][] = get_string('error_syncinterval', 'tool_mucohort');
        }
    }
}
