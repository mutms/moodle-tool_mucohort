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

namespace tool_mucohort\local;

use stdClass;
use tool_mucohort\local\type\rules;

/**
 * External database sources of rule-based cohorts.
 *
 * Each source is a local snapshot of users returned by an external database query
 * for one cohort. Snapshots are fetched before cohort sync when they are too old,
 * rules read only the snapshot.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class extdb_source {
    /**
     * Returns source of cohort.
     *
     * @param int $mucohortid
     * @param int $queryid
     * @return stdClass|null tool_mucohort_extdb record
     */
    public static function get_source(int $mucohortid, int $queryid): ?stdClass {
        global $DB;
        return $DB->get_record('tool_mucohort_extdb', ['mucohortid' => $mucohortid, 'queryid' => $queryid]) ?: null;
    }

    /**
     * Should the snapshot be fetched again?
     *
     * @param stdClass|null $source
     * @param int $maxage seconds
     * @return bool
     */
    public static function is_outdated(?stdClass $source, int $maxage): bool {
        if (!$source || !$source->timefirstcompleted || !$source->timefetchstarted) {
            return true;
        }
        if ((int)$source->status !== sync::STATUS_OK) {
            // Try again after failed or rejected fetch.
            return true;
        }
        return (int)$source->timefetchstarted + $maxage <= time();
    }

    /**
     * Fetch users from external database into local snapshot.
     *
     * An empty or failed result keeps the previous snapshot. Rows not received in the current fetch
     * are deleted only if no newer fetch started meanwhile.
     *
     * @param rules $cohort
     * @param int $queryid
     * @param \progress_trace $trace
     * @return stdClass tool_mucohort_extdb record
     */
    public static function fetch(rules $cohort, int $queryid, \progress_trace $trace): stdClass {
        global $DB, $USER;

        $mucohortid = (int)$cohort->get_mucohort()->id;
        $source = self::get_source($mucohortid, $queryid);
        if (!$source) {
            $source = (object)[
                'mucohortid' => $mucohortid,
                'queryid' => $queryid,
                'timecreated' => time(),
                'usercreated' => $USER->id ?: null,
            ];
            $source->id = $DB->insert_record('tool_mucohort_extdb', $source);
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_mucohort_extdb');
        $lock = $lockfactory->get_lock('source_' . $source->id, 0);
        if (!$lock) {
            $trace->output("External database source $source->id is being fetched already");
            return $DB->get_record('tool_mucohort_extdb', ['id' => $source->id], '*', MUST_EXIST);
        }

        try {
            $source = $DB->get_record('tool_mucohort_extdb', ['id' => $source->id], '*', MUST_EXIST);
            $start = max(time(), (int)$source->timefetchstarted + 1);
            $DB->update_record('tool_mucohort_extdb', (object)[
                'id' => $source->id,
                'timefetchstarted' => $start,
                'status' => sync::STATUS_RUNNING,
            ]);
            $DB->set_field('tool_mucohort_extdb_user', 'stale', 1, ['extdbid' => $source->id]);

            try {
                $count = self::receive_rows($cohort, $source, $queryid);
            } catch (\Throwable $ex) {
                // Keep the old snapshot, rules ignore the stale flag.
                $trace->output("External database source $source->id failed: " . $ex->getMessage());
                self::finish($source, $start, sync::STATUS_FAILED);
                return $DB->get_record('tool_mucohort_extdb', ['id' => $source->id], '*', MUST_EXIST);
            }

            if (!$count) {
                // Empty result is most likely a problem in external database, keep the old snapshot.
                $DB->set_field('tool_mucohort_extdb_user', 'stale', 0, ['extdbid' => $source->id]);
                $trace->output("External database source $source->id returned no rows, keeping previous data");
                self::finish($source, $start, sync::STATUS_REJECTED);
                return $DB->get_record('tool_mucohort_extdb', ['id' => $source->id], '*', MUST_EXIST);
            }

            // Only the latest fetch may delete rows, an overlapping newer fetch takes over.
            if ((int)$DB->get_field('tool_mucohort_extdb', 'timefetchstarted', ['id' => $source->id]) === $start) {
                $DB->delete_records('tool_mucohort_extdb_user', ['extdbid' => $source->id, 'stale' => 1]);
            }
            self::resolve_users($cohort, (int)$source->id);
            self::finish($source, $start, sync::STATUS_OK);
            $trace->output("External database source $source->id fetched rows: $count");
        } finally {
            $lock->release();
        }

        return $DB->get_record('tool_mucohort_extdb', ['id' => $source->id], '*', MUST_EXIST);
    }

    /**
     * Receive rows from external database.
     *
     * @param rules $cohort
     * @param stdClass $source
     * @param int $queryid
     * @return int number of received rows with user identifier
     */
    private static function receive_rows(rules $cohort, stdClass $source, int $queryid): int {
        global $DB;

        // Existing rows are kept, so that row creation time is preserved.
        $existing = [];
        $rs = $DB->get_recordset('tool_mucohort_extdb_user', ['extdbid' => $source->id], '', 'id, identifiertype, useridentifier');
        foreach ($rs as $row) {
            $existing[$row->identifiertype . ':' . $row->useridentifier] = (int)$row->id;
        }
        $rs->close();

        $query = new extdb_query($queryid, $cohort->get_cohort());
        $received = [];
        $count = 0;
        $now = time();
        $rs = $query->query();
        foreach ($rs as $row) {
            [$type, $identifier] = self::get_identifier($row);
            if ($type === null) {
                continue;
            }
            $count++;
            $key = $type . ':' . $identifier;
            if (isset($existing[$key])) {
                $received[$existing[$key]] = $existing[$key];
                continue;
            }
            $record = (object)[
                'extdbid' => $source->id,
                'identifiertype' => $type,
                'useridentifier' => $identifier,
                'userid' => null,
                'stale' => 0,
                'timecreated' => $now,
            ];
            $existing[$key] = (int)$DB->insert_record('tool_mucohort_extdb_user', $record);
        }
        $rs->close();
        $query->close();

        foreach (array_chunk($received, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk);
            $DB->set_field_select('tool_mucohort_extdb_user', 'stale', 0, "id $insql", $params);
        }
        return $count;
    }

    /**
     * Returns user identifier from external database row.
     *
     * @param array $row
     * @return array [identifier type, identifier], nulls if row does not contain valid identifier
     */
    private static function get_identifier(array $row): array {
        foreach (extdb_query::IDENTIFIERS as $type) {
            if (!isset($row[$type])) {
                continue;
            }
            $value = trim((string)$row[$type]);
            if ($value === '') {
                continue;
            }
            if ($type === 'userid' && !preg_match('/^[1-9][0-9]*$/D', $value)) {
                continue;
            }
            return [$type, \core_text::substr($value, 0, 255)];
        }
        return [null, null];
    }

    /**
     * Match snapshot rows with users, rows matching no user or several users are not used.
     *
     * Only users from the cohort tenant and users without tenant are matched.
     *
     * @param rules $cohort
     * @param int $extdbid
     */
    private static function resolve_users(rules $cohort, int $extdbid): void {
        global $DB;

        $identifier = '{tool_mucohort_extdb_user}.useridentifier';
        $matches = [
            'userid' => 'u.id = ' . $DB->sql_cast_char2int($identifier),
            'username' => "u.username = $identifier",
            'useremail' => "LOWER(u.email) = LOWER($identifier)",
            'useridnumber' => "u.idnumber = $identifier",
        ];
        $tenantwhere = '';
        $params = ['extdbid' => $extdbid];
        $tenantid = \tool_mulib\local\mulib::is_mutenancy_active() ? $cohort->get_context()->tenantid : null;
        if ($tenantid) {
            $tenantwhere = 'AND (u.tenantid IS NULL OR u.tenantid = :tenantid)';
            $params['tenantid'] = $tenantid;
        }

        foreach ($matches as $type => $match) {
            $sql = "UPDATE {tool_mucohort_extdb_user}
                       SET userid = (SELECT MIN(u.id)
                                       FROM {user} u
                                      WHERE $match AND u.deleted = 0 $tenantwhere
                                     HAVING COUNT(u.id) = 1)
                     WHERE extdbid = :extdbid AND identifiertype = :type";
            $DB->execute($sql, $params + ['type' => $type]);
        }
    }

    /**
     * Store fetch result.
     *
     * @param stdClass $source
     * @param int $start
     * @param int $status
     */
    private static function finish(stdClass $source, int $start, int $status): void {
        global $DB;
        $update = (object)[
            'id' => $source->id,
            'status' => $status,
            'fetchduration' => time() - $start,
        ];
        if ($status === sync::STATUS_OK && !$source->timefirstcompleted) {
            $update->timefirstcompleted = time();
        }
        $DB->update_record('tool_mucohort_extdb', $update);
    }

    /**
     * Returns queries used in active or draft rules of all cohorts.
     *
     * @return int[]
     */
    public static function get_used_queryids(): array {
        global $DB;

        $result = [];
        $select = 'rulesjson IS NOT NULL OR pendingrulesjson IS NOT NULL';
        $rs = $DB->get_recordset_select('tool_mucohort_rules', $select, null, 'id ASC', 'id, rulesjson, pendingrulesjson');
        foreach ($rs as $record) {
            foreach (self::get_rule_queryids([$record->rulesjson, $record->pendingrulesjson]) as $queryid) {
                $result[$queryid] = $queryid;
            }
        }
        $rs->close();
        return array_values($result);
    }

    /**
     * Returns queries used in rules.
     *
     * @param array $jsons rules JSON strings or nulls
     * @return int[]
     */
    private static function get_rule_queryids(array $jsons): array {
        $result = [];
        foreach ($jsons as $json) {
            if ($json === null) {
                continue;
            }
            try {
                $ruleset = ruleset::from_json($json);
            } catch (broken_rules_exception $ex) {
                continue;
            }
            foreach ($ruleset->get_rules() as $rule) {
                if ($rule['type'] === 'tool_mucohort:extdb' && is_int($rule['config']['queryid'] ?? null)) {
                    $result[$rule['config']['queryid']] = $rule['config']['queryid'];
                }
            }
        }
        return array_values($result);
    }

    /**
     * Delete sources of cohort that are not used in active or draft rules.
     *
     * @param rules $cohort
     */
    public static function remove_unused(rules $cohort): void {
        global $DB;

        $record = $cohort->get_rules_record();
        $used = self::get_rule_queryids([$record->rulesjson, $record->pendingrulesjson]);
        $sources = $DB->get_records('tool_mucohort_extdb', ['mucohortid' => $cohort->get_mucohort()->id]);
        foreach ($sources as $source) {
            if (!in_array((int)$source->queryid, $used, true)) {
                $DB->delete_records('tool_mucohort_extdb_user', ['extdbid' => $source->id]);
                $DB->delete_records('tool_mucohort_extdb', ['id' => $source->id]);
            }
        }
    }
}
