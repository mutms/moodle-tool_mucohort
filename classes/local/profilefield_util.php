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
use tool_mulib\local\profilefield_cache;

/**
 * Custom profile fields in cohort rules.
 *
 * Values are cached in tool_mulib, rules record their fields as references
 * of component "tool_mucohort", area "rules" and item mucohortid.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profilefield_util {
    /** @var string rule type using profile fields */
    public const string RULE_TYPE = 'tool_mucohort:profilefield';

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
     * @return base|null null if field type is not supported in rules or not cached by tool_mulib
     */
    public static function get_handler(stdClass $field): ?base {
        $classname = self::get_handler_classes()[$field->datatype] ?? null;
        if (!$classname || !profilefield_cache::get_handler($field)) {
            return null;
        }
        return new $classname($field);
    }

    /**
     * Returns profile fields that can be used in rules.
     *
     * @param bool $readyonly only fields with values ready in cache
     * @return base[] fieldid => handler, in profile display order
     */
    public static function get_supported_fields(bool $readyonly = false): array {
        global $DB;

        $sql = "SELECT f.*
                  FROM {user_info_field} f
                  JOIN {user_info_category} c ON c.id = f.categoryid
              ORDER BY c.sortorder ASC, f.sortorder ASC";
        $result = [];
        foreach ($DB->get_records_sql($sql) as $field) {
            if ($readyonly && !profilefield_cache::is_ready((int)$field->id)) {
                continue;
            }
            $handler = self::get_handler($field);
            if ($handler) {
                $result[$field->id] = $handler;
            }
        }
        return $result;
    }

    /**
     * Returns profile fields used in rules.
     *
     * @param array $jsons rules JSON strings or nulls, invalid rules are ignored
     * @return int[]
     */
    public static function get_rules_fieldids(array $jsons): array {
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
                $fieldid = $rule['config']['fieldid'] ?? null;
                if ($rule['type'] === self::RULE_TYPE && is_int($fieldid)) {
                    $result[$fieldid] = $fieldid;
                }
            }
        }
        sort($result);
        return $result;
    }

    /**
     * Record profile fields used in active and draft rules of cohort as tool_mulib references.
     *
     * @param int $mucohortid
     */
    public static function update_references(int $mucohortid): void {
        global $DB;

        $record = $DB->get_record('tool_mucohort_rules', ['mucohortid' => $mucohortid], 'id, rulesjson, pendingrulesjson');
        $fieldids = $record ? self::get_rules_fieldids([$record->rulesjson, $record->pendingrulesjson]) : [];
        profilefield_cache::set_references('tool_mucohort', 'rules', $mucohortid, $fieldids);
    }
}
