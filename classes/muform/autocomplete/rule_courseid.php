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

use tool_mulib\muform\util\autocomplete\course_trait;

/**
 * Course of course rules - any course where the user may view participants.
 *
 * The course already used by the rule stays valid even if the current user cannot see it,
 * because rules may be edited by several people.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule_courseid extends \tool_mulib\muform\autocomplete\base {
    use course_trait;

    /** @var string capability required in course context */
    private const string CAPABILITY = 'moodle/course:viewparticipants';

    /** @var \core\context context of the rule-based cohort */
    private \core\context $context;

    /**
     * Constructor.
     *
     * @param int $contextid context of the rule-based cohort
     * @param int $currentid course id used by the rule
     */
    public function __construct(
        /** @var int context id */
        private readonly int $contextid,
        /** @var int current course id */
        private readonly int $currentid
    ) {
        $this->context = \core\context::instance_by_id($contextid);
        require_capability('tool/mucohort:manage', $this->context);
    }

    #[\Override]
    public function get_args(): array {
        return [$this->contextid, $this->currentid];
    }

    #[\Override]
    public function search(string $query, int $maxitems): ?array {
        return $this->search_courses($this->context, self::CAPABILITY, $query, $maxitems);
    }

    #[\Override]
    public function label(string $value): ?string {
        $current = $this->currentid ? [$this->currentid] : [];
        return $this->course_labels($this->context, self::CAPABILITY, [$value], $current)[$value] ?? null;
    }
}
