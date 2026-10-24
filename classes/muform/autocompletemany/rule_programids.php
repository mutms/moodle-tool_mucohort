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

use tool_muprog\muform\util\autocomplete\program_trait;

/**
 * Programs of program rules - any program the user may view.
 *
 * Programs already used by the rule stay valid even if the current user cannot see them,
 * because rules may be edited by several people.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule_programids extends \tool_mulib\muform\autocompletemany\base {
    use program_trait;

    /** @var string capability required in program context */
    private const string CAPABILITY = 'tool/muprog:view';

    /** @var \core\context context of the rule-based cohort */
    private \core\context $context;

    /**
     * Constructor.
     *
     * @param int $contextid context of the rule-based cohort
     * @param int[] $currentids program ids used by the rule
     */
    public function __construct(
        /** @var int context id */
        private readonly int $contextid,
        /** @var int[] current program ids */
        private readonly array $currentids
    ) {
        $this->context = \core\context::instance_by_id($contextid);
        require_capability('tool/mucohort:manage', $this->context);
    }

    #[\Override]
    public function get_args(): array {
        return [$this->contextid, $this->currentids];
    }

    #[\Override]
    public function search(string $query, int $maxitems, array $exclude): ?array {
        $where = self::get_program_tenant_where($this->context, false);
        return $this->search_programs(self::CAPABILITY, $query, $maxitems, $exclude, $where);
    }

    #[\Override]
    public function labels(array $values): array {
        $where = self::get_program_tenant_where($this->context, false);
        return $this->current_labels($values) + $this->program_labels(self::CAPABILITY, $values, $where);
    }

    /**
     * Labels of programs already used by the rule, whatever changed since.
     *
     * @param string[] $ids
     * @return array [id => label html]
     */
    private function current_labels(array $ids): array {
        global $DB;
        $ids = array_intersect(array_map('intval', $ids), $this->currentids);
        if (!$ids) {
            return [];
        }
        $result = [];
        foreach ($DB->get_records_list('tool_muprog_program', 'id', $ids, 'id ASC', 'id, fullname, contextid') as $program) {
            $result[(string)$program->id] = self::format_program_label($program);
        }
        return $result;
    }
}
