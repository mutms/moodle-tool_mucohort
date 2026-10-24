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

namespace tool_mucohort\local\rule;

use stdClass;
use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\extdb_query;
use tool_mucohort\local\extdb_source;
use tool_mucohort\local\rule_not_ready_exception;
use tool_mulib\local\sql;
use tool_mulib\muform\element\autocomplete;
use tool_mulib\muform\element\duration;
use tool_mulib\muform\form;

/**
 * Base for rules matching users returned by external database query.
 *
 * Configuration:
 *  - queryid: tool_mulib_extdb_query id of cohort rule query type
 *  - maxage: maximum age of fetched data in seconds, data is fetched again before cohort sync when older
 *
 * Rules read only the local snapshot, the external database is not contacted
 * in previews or single user updates.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class extdb extends base {
    #[\Override]
    public static function get_name(): string {
        return get_string('rule_extdb', 'tool_mucohort');
    }

    #[\Override]
    public static function get_type_description(): string {
        return get_string('rule_extdb_type', 'tool_mucohort');
    }

    #[\Override]
    public static function is_available(): bool {
        global $DB;
        $params = ['component' => extdb_query::get_component(), 'type' => extdb_query::get_type()];
        return $DB->record_exists('tool_mulib_extdb_query', $params);
    }

    /**
     * Returns configured query.
     *
     * @param array $config
     * @return stdClass tool_mulib_extdb_query record
     * @throws broken_rules_exception
     */
    private static function get_query(array $config): stdClass {
        global $DB;

        $queryid = $config['queryid'] ?? null;
        $maxage = $config['maxage'] ?? null;
        if (!is_int($queryid) || !is_int($maxage) || $maxage <= 0) {
            throw new broken_rules_exception('error_rule_invalidconfig');
        }
        $params = ['id' => $queryid, 'component' => extdb_query::get_component(), 'type' => extdb_query::get_type()];
        $query = $DB->get_record('tool_mulib_extdb_query', $params);
        if (!$query) {
            throw new broken_rules_exception('error_rule_extdbmissing', $queryid);
        }
        return $query;
    }

    /**
     * Returns formatted query name.
     *
     * @param stdClass $query
     * @return string
     */
    private static function format_query_name(stdClass $query): string {
        return format_string($query->name, true, ['context' => \core\context\system::instance()]);
    }

    #[\Override]
    public function get_description(array $config): string {
        try {
            $query = self::get_query($config);
        } catch (broken_rules_exception $ex) {
            $queryid = $config['queryid'] ?? '';
            return get_string('rule_extdb_missing', 'tool_mucohort', is_scalar($queryid) ? $queryid : '');
        }
        return get_string('rule_extdb_desc', 'tool_mucohort', self::format_query_name($query));
    }

    #[\Override]
    public function get_sql(array $config, ?int $userid): sql {
        $query = self::get_query($config);
        $source = $this->mucohort->id ? extdb_source::get_source((int)$this->mucohort->id, $config['queryid']) : null;
        if (!$source || !$source->timefirstcompleted) {
            throw new rule_not_ready_exception('error_rule_extdbnotready', self::format_query_name($query));
        }
        return new sql(
            "EXISTS (SELECT 'x'
                       FROM {tool_mucohort_extdb_user} xu
                      WHERE xu.extdbid = ? AND xu.userid = u.id)",
            [$source->id]
        );
    }

    #[\Override]
    public function before_preview(array $config, \progress_trace $trace): void {
        self::get_query($config);
        if (!$this->mucohort->id) {
            return;
        }
        $source = extdb_source::get_source((int)$this->mucohort->id, $config['queryid']);
        if (extdb_source::is_outdated($source, $config['maxage'])) {
            /** @var \tool_mucohort\local\type\rules $cohort */
            $cohort = \tool_mucohort\local\type\base::fetch((int)$this->cohort->id);
            extdb_source::fetch($cohort, $config['queryid'], $trace);
        }
    }

    #[\Override]
    public function before_sync(array $config, ?int $userid, \progress_trace $trace): void {
        if ($userid) {
            // Single user updates use the snapshot only.
            return;
        }
        $query = self::get_query($config);
        $source = extdb_source::get_source((int)$this->mucohort->id, $config['queryid']);
        if (!extdb_source::is_outdated($source, $config['maxage'])) {
            return;
        }
        /** @var \tool_mucohort\local\type\rules $cohort */
        $cohort = \tool_mucohort\local\type\base::fetch((int)$this->cohort->id);
        $source = extdb_source::fetch($cohort, $config['queryid'], $trace);
        if ((int)$source->status !== \tool_mucohort\local\sync::STATUS_OK) {
            // Do not sync with outdated or missing data.
            throw new rule_not_ready_exception('error_rule_extdbfetchfailed', self::format_query_name($query));
        }
    }

    #[\Override]
    public function has_settings(): bool {
        return true;
    }

    #[\Override]
    public function add_form_elements(form $form): void {
        $current = (int)($form->get_current_data()['queryid'] ?? 0);
        $source = new \tool_mucohort\muform\autocomplete\rule_queryid(
            (int)$this->cohort->contextid,
            (int)$this->cohort->id,
            $current
        );
        $queryid = new autocomplete('queryid', get_string('rule_extdb_query', 'tool_mucohort'), $source);
        $queryid->set_required(true);
        $form->add($queryid);

        $maxage = new duration('maxage', get_string('rule_extdb_maxage', 'tool_mucohort'), ['d', 'h']);
        $maxage->set_default(DAYSECS);
        $maxage->set_required(true);
        $maxage->add_help_button('rule_extdb_maxage', 'tool_mucohort');
        $form->add($maxage);
    }

    #[\Override]
    public function get_form_data(array $config): array {
        $data = [];
        if (isset($config['queryid'])) {
            $data['queryid'] = (string)$config['queryid'];
        }
        if (isset($config['maxage'])) {
            $data['maxage'] = $config['maxage'];
        }
        return $data;
    }

    #[\Override]
    public function get_config(stdClass $data): array {
        return ['maxage' => (int)$data->maxage, 'queryid' => (int)$data->queryid];
    }
}
