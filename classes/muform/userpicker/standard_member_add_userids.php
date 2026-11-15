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

namespace tool_mucohort\muform\userpicker;

use tool_mucohort\local\type\base;
use tool_mulib\local\sql;

/**
 * New members of a standard cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class standard_member_add_userids extends \tool_mulib\muform\userpicker\base {
    /** @var \core\context cohort context */
    private \core\context $context;

    /**
     * Constructor.
     *
     * @param int $cohortid
     */
    public function __construct(
        /** @var int cohort id */
        private readonly int $cohortid
    ) {
        $cohort = base::fetch($cohortid);
        $this->context = $cohort->get_context();
        if (!($cohort instanceof \tool_mucohort\local\type\standard) || !$cohort->can_manage_members()) {
            throw new \core\exception\required_capability_exception($this->context, 'moodle/cohort:assign', 'nopermissions', '');
        }
    }

    #[\Override]
    public function get_args(): array {
        return [$this->cohortid];
    }

    #[\Override]
    protected function get_context(): \core\context {
        return $this->context;
    }

    /**
     * Existing members are not candidates.
     *
     * @return sql|null
     */
    #[\Override]
    protected function get_candidates_sql(): ?sql {
        return new sql(
            "NOT EXISTS (SELECT 'x' FROM {cohort_members} cm WHERE cm.userid = u.id AND cm.cohortid = :mcohortid)",
            ['mcohortid' => $this->cohortid]
        );
    }
}
