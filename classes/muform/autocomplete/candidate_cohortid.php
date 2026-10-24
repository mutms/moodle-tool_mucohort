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

use tool_mulib\muform\util\autocomplete\cohort_trait;

/**
 * Candidate cohort of advanced cohort - any cohort the user may see.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class candidate_cohortid extends \tool_mulib\muform\autocomplete\base {
    use cohort_trait;

    /** @var \core\context context of the advanced cohort */
    private \core\context $context;

    /**
     * Constructor.
     *
     * @param int $contextid context of the advanced cohort
     * @param int $cohortid advanced cohort id, 0 when creating new cohort
     * @param int $currentid current candidate cohort, it stays selectable even if not visible any more
     */
    public function __construct(
        /** @var int context id */
        private readonly int $contextid,
        /** @var int advanced cohort id */
        private readonly int $cohortid = 0,
        /** @var int current candidate cohort id */
        private readonly int $currentid = 0
    ) {
        $this->context = \core\context::instance_by_id($contextid);
        require_capability('tool/mucohort:manage', $this->context);
    }

    #[\Override]
    public function get_args(): array {
        return [$this->contextid, $this->cohortid, $this->currentid];
    }

    #[\Override]
    public function search(string $query, int $maxitems): ?array {
        $exclude = $this->cohortid ? [(string)$this->cohortid] : [];
        return $this->search_cohorts($this->context, $query, $maxitems, $exclude);
    }

    #[\Override]
    public function label(string $value): ?string {
        if ($this->cohortid && $value === (string)$this->cohortid) {
            return null;
        }
        $current = $this->currentid ? [$this->currentid] : [];
        return $this->cohort_labels($this->context, [$value], $current)[$value] ?? null;
    }
}
