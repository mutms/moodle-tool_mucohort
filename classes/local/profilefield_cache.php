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
use tool_mucohort\local\profilefield\base;

/**
 * Cache of custom profile field values for fast evaluation of cohort rules.
 *
 * Only fields used in rules are cached, values are synchronised by the rules before cohort sync.
 * Each non-deleted user has one row per cached field with the effective value - stored data
 * or the field default. The cache can be always rebuilt from core tables.
 *
 * The set of cached fields is stored in plugin config as JSON object
 * fieldid => time when the field was populated, 0 means the field is being populated.
 * Handlers of profile field types are in \tool_mucohort\local\profilefield namespace.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profilefield_cache {
    /** @var array|null handler classes, datatype => class */
    private static ?array $handlerclasses = null;

    /**
     * Returns registered handler classes.
     *
     * @return array datatype => handler class name
     */
    public static function get_handler_classes(): array {
        if (self::$handlerclasses === null || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            $hook = new \tool_mucohort\hook\profilefield_handlers();
            $hook->register('text', profilefield\text::class);
            $hook->register('menu', profilefield\menu::class);
            $hook->register('checkbox', profilefield\checkbox::class);
            $hook->register('datetime', profilefield\datetime::class);
            \core\di::get(\core\hook\manager::class)->dispatch($hook);
            self::$handlerclasses = $hook->get_classes();
        }
        return self::$handlerclasses;
    }

    /**
     * Returns handler of profile field.
     *
     * @param stdClass $field user_info_field record
     * @return base|null null if field type is not supported
     */
    public static function get_handler(stdClass $field): ?base {
        $classname = self::get_handler_classes()[$field->datatype] ?? null;
        return $classname ? new $classname($field) : null;
    }

    /**
     * Returns profile fields that can be used in rules.
     *
     * @return base[] fieldid => handler, in profile display order
     */
    public static function get_supported_fields(): array {
        global $DB;
        $sql = "SELECT f.*
                  FROM {user_info_field} f
                  JOIN {user_info_category} c ON c.id = f.categoryid
              ORDER BY c.sortorder ASC, f.sortorder ASC";
        $result = [];
        foreach ($DB->get_records_sql($sql) as $field) {
            $handler = self::get_handler($field);
            if ($handler) {
                $result[$field->id] = $handler;
            }
        }
        return $result;
    }

    /**
     * Returns cached fields.
     *
     * @return array fieldid => time populated, 0 means populating
     */
    public static function get_fields(): array {
        global $DB;
        // Read directly from database, the set is changed by other processes such as adhoc tasks.
        $json = $DB->get_field('config_plugins', 'value', ['plugin' => 'tool_mucohort', 'name' => 'profilefields']);
        $fields = $json ? json_decode($json, true) : [];
        return is_array($fields) ? array_map('intval', $fields) : [];
    }

    /**
     * Store cached fields.
     *
     * @param array $fields fieldid => time populated
     */
    private static function set_fields(array $fields): void {
        ksort($fields);
        set_config('profilefields', \tool_mulib\local\mulib::json_encode((object)$fields), 'tool_mucohort');
    }

    /**
     * Is the field cached and ready for use?
     *
     * @param int $fieldid
     * @return bool
     */
    public static function is_ready(int $fieldid): bool {
        return !empty(self::get_fields()[$fieldid]);
    }

    /**
     * Are cached values of the field older than allowed by site setting?
     *
     * @param int $fieldid
     * @return bool true if field should be populated again
     */
    public static function is_outdated(int $fieldid): bool {
        $populated = self::get_fields()[$fieldid] ?? 0;
        if (!$populated) {
            return true;
        }
        $lifetime = (int)get_config('tool_mucohort', 'profilefieldlifetime');
        return $populated + $lifetime <= time();
    }

    /**
     * Remove field from cache.
     *
     * @param int $fieldid
     */
    public static function remove_field(int $fieldid): void {
        global $DB;
        $fields = self::get_fields();
        unset($fields[$fieldid]);
        self::set_fields($fields);
        $DB->delete_records('tool_mucohort_profilefield', ['fieldid' => $fieldid]);
    }

    /**
     * Populate cached values of field for all users.
     *
     * @param int $fieldid
     * @param \progress_trace $trace
     */
    public static function populate_field(int $fieldid, \progress_trace $trace): void {
        global $DB;

        $field = $DB->get_record('user_info_field', ['id' => $fieldid]);
        $handler = $field ? self::get_handler($field) : null;
        if (!$handler) {
            self::remove_field($fieldid);
            return;
        }

        $start = time();
        self::sync_field($handler, null);

        // The field is ready, other fields might have changed meanwhile.
        $fields = self::get_fields();
        $fields[$fieldid] = $start;
        self::set_fields($fields);
        $trace->output("Cached profile field $fieldid values");
    }

    /**
     * Synchronise cached values of field with stored profile data.
     *
     * Values are changed in place without transactions, readers always see some value
     * and nothing else is blocked. Values of users without data follow the current field default.
     *
     * @param base $handler
     * @param int|null $userid only this user, null means all users
     */
    private static function sync_field(base $handler, ?int $userid): void {
        global $DB;

        $field = $handler->get_field();
        [$charvalue, $intvalue] = $handler->get_cache_sql('src.data');
        $userwhere = '';
        $cachewhere = '';
        $userparams = [];
        $cacheparams = [];
        if ($userid) {
            $userwhere = 'AND u.id = :userid';
            $cachewhere = 'AND userid = :cacheuserid';
            $userparams = ['userid' => $userid];
            $cacheparams = ['cacheuserid' => $userid];
        }

        // Effective values of all users, the default applies to users without data.
        $source = "SELECT u.id AS userid, COALESCE(d.data, :defaultdata) AS data
                     FROM {user} u
                LEFT JOIN {user_info_data} d ON d.userid = u.id AND d.fieldid = :datafieldid
                    WHERE u.deleted = 0 $userwhere";
        $params = ['fieldid' => $field->id, 'datafieldid' => $field->id, 'defaultdata' => (string)$field->defaultdata];
        $params = $params + $userparams;

        // Update existing values, each statement sets one column.
        foreach (['charvalue' => $charvalue, 'intvalue' => $intvalue] as $column => $value) {
            if ($value === null) {
                continue;
            }
            $sql = "UPDATE {tool_mucohort_profilefield}
                       SET $column = (SELECT $value
                                        FROM ($source) src
                                       WHERE src.userid = {tool_mucohort_profilefield}.userid)
                     WHERE fieldid = :fieldid $cachewhere";
            $DB->execute($sql, $params + $cacheparams);
        }

        // Insert values of users that are not cached yet.
        $charvalue = $charvalue ?? 'NULL';
        $intvalue = $intvalue ?? 'NULL';
        $sql = "INSERT INTO {tool_mucohort_profilefield} (userid, fieldid, charvalue, intvalue)
                SELECT src.userid, :fieldid, $charvalue, $intvalue
                  FROM ($source) src
                 WHERE NOT EXISTS (SELECT 'x'
                                     FROM {tool_mucohort_profilefield} pf
                                    WHERE pf.userid = src.userid AND pf.fieldid = :existsfieldid)";
        $DB->execute($sql, $params + ['existsfieldid' => $field->id]);

        // Remove values of deleted users.
        $sql = "DELETE FROM {tool_mucohort_profilefield}
                 WHERE fieldid = :fieldid $cachewhere
                       AND userid IN (SELECT id FROM {user} WHERE deleted = 1)";
        $DB->execute($sql, ['fieldid' => $field->id] + $cacheparams);
    }

    /**
     * Update cached value of one user, nothing is done if field values are not cached yet.
     *
     * @param int $userid
     * @param int $fieldid
     */
    public static function update_user(int $userid, int $fieldid): void {
        global $DB;

        if (!self::is_ready($fieldid)) {
            return;
        }
        $field = $DB->get_record('user_info_field', ['id' => $fieldid]);
        $handler = $field ? self::get_handler($field) : null;
        if ($handler) {
            self::sync_field($handler, $userid);
        }
    }

    /**
     * Returns fields used in active or draft rules.
     *
     * @return int[]
     */
    public static function get_used_fieldids(): array {
        global $DB;

        $result = [];
        $select = 'rulesjson IS NOT NULL OR pendingrulesjson IS NOT NULL';
        $rs = $DB->get_recordset_select('tool_mucohort_rules', $select, null, 'id ASC', 'id, rulesjson, pendingrulesjson');
        foreach ($rs as $record) {
            foreach ([$record->rulesjson, $record->pendingrulesjson] as $json) {
                if ($json === null) {
                    continue;
                }
                try {
                    $ruleset = ruleset::from_json($json);
                } catch (broken_rules_exception $ex) {
                    continue;
                }
                foreach ($ruleset->get_rules() as $rule) {
                    if ($rule['type'] === 'tool_mucohort:profilefield' && is_int($rule['config']['fieldid'] ?? null)) {
                        $result[$rule['config']['fieldid']] = $rule['config']['fieldid'];
                    }
                }
            }
        }
        $rs->close();
        return array_values($result);
    }

    /**
     * Stop caching of fields that are not used in any rules.
     *
     * @param \progress_trace $trace
     * @return int[] ids of fields that stay cached
     */
    public static function remove_unused_fields(\progress_trace $trace): array {
        $used = self::get_used_fieldids();
        $result = [];
        foreach (array_keys(self::get_fields()) as $fieldid) {
            if (in_array($fieldid, $used, true)) {
                $result[] = $fieldid;
            } else {
                self::remove_field($fieldid);
                $trace->output("Removed unused profile field $fieldid from cache");
            }
        }
        return $result;
    }
}
