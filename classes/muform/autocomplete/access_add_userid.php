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

namespace tool_mucohort\muform\autocomplete;

use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;
use tool_mulib\local\sql;
use tool_mulib\muform\util\autocomplete\user_trait;

/**
 * New access record user of an advanced cohort.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_add_userid extends \tool_mulib\muform\autocomplete\base {
    use user_trait;

    /** @var advanced */
    private advanced $cohort;

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
        if (!($cohort instanceof advanced) || !$cohort->can_manage_access()) {
            throw new \core\exception\required_capability_exception(
                $cohort->get_context(),
                'tool/mucohort:manageaccess',
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
    public function search(string $query, int $maxitems): ?array {
        return $this->search_users($this->cohort->get_context(), $query, $maxitems, [], $this->get_where());
    }

    #[\Override]
    public function label(string $value): ?string {
        return $this->user_labels($this->cohort->get_context(), [$value], $this->get_where())[$value] ?? null;
    }

    /**
     * Users with access record are not candidates.
     *
     * @return sql
     */
    private function get_where(): sql {
        return new sql(
            "NOT EXISTS (SELECT 'x' FROM {tool_mucohort_access} a WHERE a.userid = u.id AND a.mucohortid = :amucohortid)",
            ['amucohortid' => $this->cohort->get_mucohort()->id]
        );
    }
}
