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

use tool_mulib\muform\util\autocomplete\category_context_trait;

/**
 * Context of roles assigned to cohort members: the system context or a course category
 * where the user may assign roles, cohorts in tenants may use only categories of the same tenant.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class role_contextid extends \tool_mulib\muform\autocomplete\base {
    use category_context_trait;

    /**
     * Constructor.
     *
     * @param int $cohortid advanced cohort id
     */
    public function __construct(
        /** @var int cohort id */
        private readonly int $cohortid
    ) {
        $cohort = \tool_mucohort\local\type\base::fetch($cohortid);
        if (!($cohort instanceof \tool_mucohort\local\type\advanced) || !$cohort->can_manage_roles()) {
            throw new \core\exception\required_capability_exception(
                $cohort->get_context(),
                'tool/mucohort:manageroles',
                'nopermissions',
                ''
            );
        }
    }

    #[\Override]
    public function get_args(): array {
        return [$this->cohortid];
    }

    #[\Override]
    public function search(string $query, int $maxitems): ?array {
        $result = $this->search_category_contexts('moodle/role:assign', $query, $maxitems);
        if ($result === null) {
            return null;
        }
        return array_filter($result, fn($contextid) => $this->is_tenant_allowed((int)$contextid), ARRAY_FILTER_USE_KEY);
    }

    #[\Override]
    public function label(string $value): ?string {
        if (!$this->is_tenant_allowed((int)$value)) {
            return null;
        }
        return $this->category_context_label('moodle/role:assign', $value, null);
    }

    /**
     * Roles of tenant cohort members may be assigned only in the same tenant.
     *
     * @param int $contextid
     * @return bool
     */
    private function is_tenant_allowed(int $contextid): bool {
        if (!\tool_mulib\local\mulib::is_mutenancy_active()) {
            return true;
        }
        $tenantid = \tool_mucohort\local\type\base::fetch($this->cohortid)->get_context()->tenantid;
        if (!$tenantid) {
            return true;
        }
        $context = \core\context::instance_by_id($contextid, IGNORE_MISSING);
        return $context && $context->tenantid == $tenantid;
    }
}
