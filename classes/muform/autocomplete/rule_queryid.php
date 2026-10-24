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

use tool_mucohort\local\extdb_query;
use tool_mulib\local\context_map;
use tool_mulib\local\mulib;
use tool_mulib\local\search_util;
use tool_mulib\local\sql;

/**
 * External database query of cohort rule.
 *
 * Only queries the user may use are offered, the query already used by the rule
 * stays valid for other rule editors.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule_queryid extends \tool_mulib\muform\autocomplete\base {
    /** @var string capability required in the query context */
    private const string CAPABILITY = 'tool/mulib:useextdb';

    /** @var \core\context context of the rule-based cohort */
    private \core\context $context;

    /**
     * Constructor.
     *
     * @param int $contextid context of the rule-based cohort
     * @param int $cohortid rule-based cohort id, 0 when cohort is not saved yet
     * @param int $currentid query id used by the rule
     */
    public function __construct(
        /** @var int context id */
        private readonly int $contextid,
        /** @var int rule-based cohort id */
        private readonly int $cohortid,
        /** @var int current query id */
        private readonly int $currentid
    ) {
        $this->context = \core\context::instance_by_id($contextid);
        require_capability('tool/mucohort:manageextdb', $this->context);
    }

    #[\Override]
    public function get_args(): array {
        return [$this->contextid, $this->cohortid, $this->currentid];
    }

    #[\Override]
    public function search(string $query, int $maxitems): ?array {
        global $DB, $USER;

        [$insql, $inparams] = $DB->get_in_or_equal($this->context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'qctx');
        $sql = new sql(
            "SELECT q.id, q.name
               FROM {tool_mulib_extdb_query} q
               /* capsubquery */
               /* tenantjoin */
              WHERE q.contextid $insql AND q.component = :component AND q.type = :type /* search */
           ORDER BY q.name ASC, q.id ASC",
            array_merge($inparams, ['component' => extdb_query::get_component(), 'type' => extdb_query::get_type()])
        );
        $capsubquery = context_map::get_contexts_by_capability_query(
            self::CAPABILITY,
            $USER->id,
            new sql("(ctx.contextlevel = ? OR ctx.contextlevel = ?)", [CONTEXT_SYSTEM, CONTEXT_COURSECAT])
        );
        $sql = $sql->replace_comment('capsubquery', $capsubquery->wrap('JOIN (', ') capctx ON capctx.id = q.contextid'));
        $search = search_util::get_search_query(trim($query), ['name', 'note'], 'q');
        $sql = $sql->replace_comment('search', $search->wrap('AND ', ''));
        $tenantjoin = '';
        if (mulib::is_mutenancy_active() && $this->context->tenantid) {
            $tenantjoin = new sql(
                "JOIN {context} tctx ON tctx.id = q.contextid AND (tctx.tenantid = ? OR tctx.tenantid IS NULL)",
                [$this->context->tenantid]
            );
        }
        $sql = $sql->replace_comment('tenantjoin', $tenantjoin);

        $queries = $DB->get_records_sql_menu($sql->sql, $sql->params, 0, $maxitems + 1);
        if (count($queries) > $maxitems) {
            return null;
        }
        $result = [];
        foreach ($queries as $id => $name) {
            $result[(string)$id] = format_string($name, true, ['context' => $this->context]);
        }
        return $result;
    }

    #[\Override]
    public function label(string $value): ?string {
        global $DB;

        if (!preg_match('/^[1-9][0-9]*$/D', $value)) {
            return null;
        }
        $params = ['id' => (int)$value, 'component' => extdb_query::get_component(), 'type' => extdb_query::get_type()];
        $query = $DB->get_record('tool_mulib_extdb_query', $params);
        if (!$query) {
            return null;
        }
        $qcontext = \core\context::instance_by_id($query->contextid, IGNORE_MISSING);
        if (!$qcontext) {
            return null;
        }
        if (mulib::is_mutenancy_active()) {
            if ($qcontext->tenantid && $this->context->tenantid && $qcontext->tenantid != $this->context->tenantid) {
                return null;
            }
        }
        $label = format_string($query->name, true, ['context' => $this->context]);
        if ((int)$query->id === $this->currentid) {
            // Current value is always ok.
            return $label;
        }
        if (!in_array($qcontext->id, $this->context->get_parent_context_ids(true))) {
            return null;
        }
        if (!has_capability(self::CAPABILITY, $qcontext)) {
            return null;
        }
        return $label;
    }
}
