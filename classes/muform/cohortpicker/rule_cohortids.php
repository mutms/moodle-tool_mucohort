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

namespace tool_mucohort\muform\cohortpicker;

use core\context;

/**
 * Cohorts of cohort membership rules - any cohort the user may use.
 *
 * Cohorts already used by the rule stay valid even if the current user cannot use them,
 * because rules may be edited by several people.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule_cohortids extends \tool_mulib\muform\cohortpicker\base {
    /** @var context context of the advanced cohort */
    private context $context;

    /**
     * Constructor.
     *
     * @param int $contextid context of the advanced cohort
     * @param int $cohortid advanced cohort id, 0 when cohort is not saved yet
     * @param string $currentids cohort ids used by the rule separated with commas
     */
    public function __construct(
        /** @var int context id */
        private readonly int $contextid,
        /** @var int advanced cohort id */
        private readonly int $cohortid = 0,
        /** @var string current cohort ids separated with commas */
        private readonly string $currentids = ''
    ) {
        $this->context = context::instance_by_id($contextid);
        require_capability('tool/mucohort:manage', $this->context);
    }

    #[\Override]
    public function get_args(): array {
        return [$this->contextid, $this->cohortid, $this->currentids];
    }

    #[\Override]
    protected function get_context(): context {
        return $this->context;
    }

    /**
     * Cohorts already used stay valid.
     *
     * @return int[]
     */
    #[\Override]
    protected function get_current_cohortids(): array {
        return array_values(array_filter(array_map('intval', explode(',', $this->currentids))));
    }

    /**
     * The advanced cohort itself cannot be picked.
     *
     * @return int[]
     */
    #[\Override]
    protected function get_excluded_cohortids(): array {
        return $this->cohortid ? [$this->cohortid] : [];
    }
}
