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
use tool_mulib\muform\util\autocomplete\cohort_trait;

/**
 * Cohorts to compare with - only cohorts with members visible to the current user.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class compare_cohortids extends \tool_mulib\muform\autocompletemany\base {
    use cohort_trait;

    /** @var base */
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
    public function search(string $query, int $maxitems, array $exclude): ?array {
        $exclude[] = (string)$this->cohortid;
        $result = $this->search_cohorts($this->cohort->get_context(), $query, $maxitems, $exclude);
        if ($result === null) {
            return null;
        }
        return array_filter($result, fn($id) => self::can_compare((int)$id), ARRAY_FILTER_USE_KEY);
    }

    #[\Override]
    public function labels(array $values): array {
        $values = array_filter($values, fn($id) => $id !== (string)$this->cohortid);
        $labels = $this->cohort_labels($this->cohort->get_context(), $values);
        return array_filter($labels, fn($id) => self::can_compare((int)$id), ARRAY_FILTER_USE_KEY);
    }

    /**
     * May the current user see members of the cohort?
     *
     * @param int $cohortid
     * @return bool
     */
    private static function can_compare(int $cohortid): bool {
        $cohort = base::fetch($cohortid, IGNORE_MISSING);
        return $cohort && $cohort->can_view_members();
    }
}
