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
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;
use tool_mucohort\muform\autocomplete\cohort_contextid;

/**
 * Move cohort to a different context.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_move extends form {
    #[\Override]
    protected function definition(): void {
        $cohort = $this->get_current_data();

        $this->add(new info('name', get_string('name', 'core_cohort')));

        $source = new cohort_contextid((int)$cohort['contextid'], $cohort['type']);
        $contextid = new autocomplete('contextid', get_string('category'), $source);
        $contextid->set_required(true);
        $this->add($contextid);

        $tenantmembers = self::get_tenant_members((int)$cohort['id']);
        if ($tenantmembers) {
            $text = \html_writer::alist($tenantmembers) . get_string('cohort_move_tenantmembers_desc', 'tool_mucohort');
            $this->add(new info('tenantmembers', get_string('cohort_move_tenantmembers', 'tool_mucohort'), $text, info::HTML));
        }

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('cohort_move', 'tool_mucohort')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    /**
     * Returns numbers of members from tenants.
     *
     * @param int $cohortid
     * @return string[] tenant name with number of members
     */
    private static function get_tenant_members(int $cohortid): array {
        global $DB;

        if (!\tool_mulib\local\mulib::is_mutenancy_active()) {
            return [];
        }
        $sql = "SELECT t.id, t.name, COUNT(u.id) AS memberscount
                  FROM {cohort_members} cm
                  JOIN {user} u ON u.id = cm.userid
                  JOIN {tool_mutenancy_tenant} t ON t.id = u.tenantid
                 WHERE cm.cohortid = :cohortid
              GROUP BY t.id, t.name
              ORDER BY t.name ASC";
        $result = [];
        foreach ($DB->get_records_sql($sql, ['cohortid' => $cohortid]) as $tenant) {
            $result[] = format_string($tenant->name) . ': ' . $tenant->memberscount;
        }
        return $result;
    }
}
