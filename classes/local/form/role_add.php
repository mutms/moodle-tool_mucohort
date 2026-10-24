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

use tool_mucohort\local\role_sync;
use tool_mucohort\muform\autocomplete\role_contextid;
use tool_mulib\muform\element\autocomplete;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Add role assigned to cohort members.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class role_add extends form {
    #[\Override]
    protected function definition(): void {
        $cohortid = (int)$this->get_current_data()['cohortid'];

        // All roles that can be assigned at system or category level, assignability is validated later.
        $options = ['' => ''];
        foreach (role_fix_names(get_all_roles()) as $role) {
            $levels = get_role_contextlevels($role->id);
            if (in_array(CONTEXT_SYSTEM, $levels) || in_array(CONTEXT_COURSECAT, $levels)) {
                $options[$role->id] = $role->localname;
            }
        }
        $roleid = new select('roleid', get_string('role'), $options);
        $roleid->set_required(true);
        $this->add($roleid);

        $context = new autocomplete('contextid', get_string('role_context', 'tool_mucohort'), new role_contextid($cohortid));
        $this->add($context);
        $this->add(new info('contextinfo', '', get_string('role_context_desc', 'tool_mucohort')));

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('role_add', 'tool_mucohort')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        if (empty($data['roleid'])) {
            return;
        }
        /** @var \tool_mucohort\local\type\advanced $cohort */
        $cohort = \tool_mucohort\local\type\base::fetch((int)$this->get_current_data()['cohortid']);
        // No context means the cohort context.
        $contextid = empty($data['contextid']) ? null : (int)$data['contextid'];
        $context = $contextid ? \core\context::instance_by_id($contextid) : $cohort->get_context();
        if (!isset(role_sync::get_assignable_roles($context)[(int)$data['roleid']])) {
            $allerrors['roleid'][] = get_string('error_role_notassignable', 'tool_mucohort');
            return;
        }
        if (role_sync::get_record($cohort, (int)$data['roleid'], $contextid)) {
            $allerrors['roleid'][] = get_string('error_role_exists', 'tool_mucohort');
        }
    }
}
