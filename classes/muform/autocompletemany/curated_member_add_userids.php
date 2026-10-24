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

namespace tool_mucohort\muform\autocompletemany;

use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use tool_mulib\muform\util\autocomplete\user_trait;

/**
 * New members of a curated cohort - candidate cohort, blocked users and tenants are respected.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class curated_member_add_userids extends \tool_mulib\muform\autocompletemany\base {
    use user_trait;

    /** @var curated */
    private curated $cohort;

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
        if (!($cohort instanceof curated) || !$cohort->can_manage_members()) {
            throw new \core\exception\required_capability_exception(
                $cohort->get_context(),
                'tool/mucohort:managemembers',
                'nopermissions',
                ''
            );
        }
        $this->cohort = $cohort;
    }

    #[\Override]
    public function get_args(): array {
        return [$this->cohortid];
    }

    #[\Override]
    public function search(string $query, int $maxitems, array $exclude): ?array {
        $where = $this->cohort->get_new_member_sql();
        return $this->search_users($this->cohort->get_context(), $query, $maxitems, $exclude, $where);
    }

    #[\Override]
    public function labels(array $values): array {
        return $this->user_labels($this->cohort->get_context(), $values, $this->cohort->get_new_member_sql());
    }

    #[\Override]
    public function validate(array $values): array {
        return $this->validate_users($values);
    }
}
