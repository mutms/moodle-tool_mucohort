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

use tool_mulib\muform\util\autocomplete\cohort_trait;

/**
 * Cohorts of cohort membership rules - any cohort the user may see.
 *
 * Cohorts already used by the rule stay valid even if the current user cannot see them,
 * because rules may be edited by several people.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule_cohortids extends \tool_mulib\muform\autocompletemany\base {
    use cohort_trait;

    /** @var \core\context context of the rule-based cohort */
    private \core\context $context;

    /**
     * Constructor.
     *
     * @param int $contextid context of the rule-based cohort
     * @param int $cohortid rule-based cohort id, 0 when cohort is not saved yet
     * @param int[] $currentids cohort ids used by the rule
     */
    public function __construct(
        /** @var int context id */
        private readonly int $contextid,
        /** @var int rule-based cohort id */
        private readonly int $cohortid,
        /** @var int[] current cohort ids */
        private readonly array $currentids
    ) {
        $this->context = \core\context::instance_by_id($contextid);
        require_capability('tool/mucohort:manage', $this->context);
    }

    #[\Override]
    public function get_args(): array {
        return [$this->contextid, $this->cohortid, $this->currentids];
    }

    #[\Override]
    public function search(string $query, int $maxitems, array $exclude): ?array {
        if ($this->cohortid) {
            $exclude[] = (string)$this->cohortid;
        }
        return $this->search_cohorts($this->context, $query, $maxitems, $exclude);
    }

    #[\Override]
    public function labels(array $values): array {
        $values = array_filter($values, fn($id) => $id !== (string)$this->cohortid);
        return $this->cohort_labels($this->context, $values, array_map('intval', $this->currentids));
    }
}
