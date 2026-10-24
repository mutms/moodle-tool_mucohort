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

use tool_mucertify\muform\util\autocomplete\certification_trait;

/**
 * Certification of certification rules - any certification the user may view.
 *
 * The certification already used by the rule stays valid even if the current user cannot see it,
 * because rules may be edited by several people.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule_certificationid extends \tool_mulib\muform\autocomplete\base {
    use certification_trait;

    /** @var string capability required in certification context */
    private const string CAPABILITY = 'tool/mucertify:view';

    /** @var \core\context context of the rule-based cohort */
    private \core\context $context;

    /**
     * Constructor.
     *
     * @param int $contextid context of the rule-based cohort
     * @param int $currentid certification id used by the rule
     */
    public function __construct(
        /** @var int context id */
        private readonly int $contextid,
        /** @var int current certification id */
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
        $where = self::get_certification_tenant_where($this->context, false);
        return $this->search_certifications(self::CAPABILITY, $query, $maxitems, [], $where);
    }

    #[\Override]
    public function label(string $value): ?string {
        $label = $this->current_labels([$value])[$value] ?? null;
        $where = self::get_certification_tenant_where($this->context, false);
        return $label ?? $this->certification_label(self::CAPABILITY, $value, $where);
    }

    /**
     * Labels of certifications already used by the rule, whatever changed since.
     *
     * @param string[] $ids
     * @return array [id => label html]
     */
    private function current_labels(array $ids): array {
        global $DB;
        $ids = array_intersect(array_map('intval', $ids), [$this->currentid]);
        if (!$ids) {
            return [];
        }
        $result = [];
        $certifications = $DB->get_records_list('tool_mucertify_certification', 'id', $ids, 'id ASC', 'id, fullname, contextid');
        foreach ($certifications as $certification) {
            $result[(string)$certification->id] = self::format_certification_label($certification);
        }
        return $result;
    }
}
