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
 * Cohort context: the system context or a course category where the user may manage cohorts of given type.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_contextid extends \tool_mulib\muform\autocomplete\base {
    use category_context_trait;

    /** @var string capability required in the selected context */
    private string $capability;

    /**
     * Constructor.
     *
     * @param int $currentcontextid current cohort context or the context of new cohort,
     *      it stays selectable without the capability
     * @param string $type cohort type
     */
    public function __construct(
        /** @var int current context id */
        private readonly int $currentcontextid,
        /** @var string cohort type */
        private readonly string $type = \tool_mucohort\local\type\standard::TYPE
    ) {
        $classes = \tool_mucohort\local\type\base::get_classes();
        if (!isset($classes[$type])) {
            throw new \core\exception\invalid_parameter_exception('Invalid cohort type');
        }
        $this->capability = $classes[$type]::get_manage_capability();
        require_capability($this->capability, \core\context::instance_by_id($currentcontextid));
    }

    #[\Override]
    public function get_args(): array {
        return [$this->currentcontextid, $this->type];
    }

    #[\Override]
    public function search(string $query, int $maxitems): ?array {
        return $this->search_category_contexts($this->capability, $query, $maxitems);
    }

    #[\Override]
    public function label(string $value): ?string {
        return $this->category_context_label($this->capability, $value, $this->currentcontextid);
    }
}
