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
use tool_mucohort\local\type\base;
use tool_mulib\local\cohort_util;
use tool_mulib\local\sql;

/**
 * Cohorts to compare with - only cohorts with members visible to the current user.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class compare_cohortids extends \tool_mulib\muform\cohortpicker\base {
    /** @var base target cohort */
    private base $cohort;

    /**
     * Constructor.
     *
     * @param int $cohortid target cohort
     */
    public function __construct(
        /** @var int target cohort id */
        private readonly int $cohortid
    ) {
        $cohort = base::fetch($cohortid);
        if (!$cohort->can_view_members()) {
            throw new \core\exception\required_capability_exception(
                $cohort->get_context(),
                'tool/mucohort:viewmembers',
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
    protected function get_context(): context {
        return $this->cohort->get_context();
    }

    /**
     * The target cohort cannot be compared with itself.
     *
     * @return int[]
     */
    #[\Override]
    protected function get_excluded_cohortids(): array {
        return [$this->cohortid];
    }

    #[\Override]
    protected function get_restriction_sql(): ?sql {
        global $USER;
        return cohort_util::get_view_members_sql('ch', (int)$USER->id);
    }
}
