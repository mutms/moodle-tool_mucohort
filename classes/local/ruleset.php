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

use tool_mucohort\local\rule\base;
use tool_mulib\local\mulib;
use tool_mucohort\local\broken_rules_exception;

/**
 * Rules of a rule-based cohort.
 *
 * Stored as readable JSON in tool_mucohort_rules.rulesjson (active rules)
 * and tool_mucohort_rules.pendingrulesjson (draft rules):
 *
 * {
 *     "version": 1,
 *     "rules": {
 *         "<uuid>": {
 *             "sortorder": 1,
 *             "kind": "include",
 *             "type": "tool_mucohort:allusers",
 *             "config": {}
 *         }
 *     }
 * }
 *
 * Rules are always written sorted by UUID, sortorder is the display order
 * within include and exclude groups, it does not affect membership.
 *
 * This class only stores data, rule types are interpreted elsewhere.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ruleset {
    /** @var int format version */
    public const int VERSION = 1;

    /** @var string rule id regex */
    private const string UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';

    /** @var string rule type regex */
    private const string TYPE_REGEX = '/^[a-z][a-z0-9_]*:[a-z][a-z0-9_]*$/D';

    /** @var array uuid => ['sortorder' => int, 'kind' => string, 'type' => string, 'config' => array] */
    private array $rules = [];

    /**
     * Create ruleset from JSON.
     *
     * @param string $json
     * @return ruleset
     * @throws broken_rules_exception if data is not valid
     */
    public static function from_json(string $json): ruleset {
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['version'] ?? null) !== self::VERSION) {
            throw new broken_rules_exception('error_rules_invalid');
        }
        if (!is_array($data['rules'] ?? null)) {
            throw new broken_rules_exception('error_rules_invalid');
        }
        $ruleset = new ruleset();
        foreach ($data['rules'] as $uuid => $rule) {
            if (!is_string($uuid) || !preg_match(self::UUID_REGEX, $uuid) || !is_array($rule)) {
                throw new broken_rules_exception('error_rules_invalid');
            }
            if (!is_int($rule['sortorder'] ?? null) || !is_array($rule['config'] ?? null)) {
                throw new broken_rules_exception('error_rules_invalid');
            }
            if (!in_array($rule['kind'] ?? null, [base::KIND_INCLUDE, base::KIND_EXCLUDE], true)) {
                throw new broken_rules_exception('error_rules_invalid');
            }
            if (!is_string($rule['type'] ?? null) || !preg_match(self::TYPE_REGEX, $rule['type'])) {
                throw new broken_rules_exception('error_rules_invalid');
            }
            $ruleset->rules[$uuid] = self::normalise_rule($rule['sortorder'], $rule['kind'], $rule['type'], $rule['config']);
        }
        return $ruleset;
    }

    /**
     * Returns canonical readable JSON.
     *
     * @return string
     */
    public function to_json(): string {
        $rules = $this->rules;
        ksort($rules, SORT_STRING);
        foreach ($rules as $uuid => $rule) {
            if (!$rule['config']) {
                // Empty JSON object instead of empty array.
                $rule['config'] = new \stdClass();
                $rules[$uuid] = $rule;
            }
        }
        $data = [
            'version' => self::VERSION,
            'rules' => $rules ?: new \stdClass(),
        ];
        return mulib::json_encode($data, true);
    }

    /**
     * Returns hash of the whole ruleset, any change including sortorder changes it.
     *
     * @return string
     */
    public function get_hash(): string {
        return sha1($this->to_json());
    }

    /**
     * Returns hash of rule content without sortorder, used for detection of concurrent changes.
     *
     * @param array $rule
     * @return string
     */
    public static function get_rule_hash(array $rule): string {
        $data = [$rule['kind'], $rule['type'], $rule['config']];
        return sha1(mulib::json_encode($data));
    }

    /**
     * Returns rules in display order.
     *
     * @param string|null $kind null means all rules, includes first
     * @return array uuid => rule
     */
    public function get_rules(?string $kind = null): array {
        $rules = $this->rules;
        if ($kind !== null) {
            $rules = array_filter($rules, fn($rule) => $rule['kind'] === $kind);
        }
        uksort($rules, function ($a, $b) use ($rules) {
            return [$rules[$b]['kind'], $rules[$a]['sortorder'], $a] <=> [$rules[$a]['kind'], $rules[$b]['sortorder'], $b];
        });
        return $rules;
    }

    /**
     * Returns one rule.
     *
     * @param string $uuid
     * @return array|null
     */
    public function get_rule(string $uuid): ?array {
        return $this->rules[$uuid] ?? null;
    }

    /**
     * Add new rule at the end of its kind group.
     *
     * @param string $kind
     * @param string $type
     * @param array $config
     * @return string new rule UUID
     */
    public function add_rule(string $kind, string $type, array $config): string {
        if (!in_array($kind, [base::KIND_INCLUDE, base::KIND_EXCLUDE], true) || !preg_match(self::TYPE_REGEX, $type)) {
            throw new \core\exception\coding_exception('Invalid rule');
        }
        $sortorder = 0;
        foreach ($this->rules as $rule) {
            if ($rule['kind'] === $kind) {
                $sortorder = max($sortorder, $rule['sortorder']);
            }
        }
        $uuid = mulib::uuid7();
        $this->rules[$uuid] = self::normalise_rule($sortorder + 1, $kind, $type, $config);
        return $uuid;
    }

    /**
     * Update rule configuration.
     *
     * @param string $uuid
     * @param array $config
     */
    public function update_rule(string $uuid, array $config): void {
        if (!isset($this->rules[$uuid])) {
            throw new \core\exception\coding_exception('Unknown rule');
        }
        $rule = $this->rules[$uuid];
        $this->rules[$uuid] = self::normalise_rule($rule['sortorder'], $rule['kind'], $rule['type'], $config);
    }

    /**
     * Delete rule.
     *
     * @param string $uuid
     */
    public function delete_rule(string $uuid): void {
        unset($this->rules[$uuid]);
    }

    /**
     * Change display order of rules of one kind.
     *
     * @param string $kind
     * @param string[] $uuids rule UUIDs in the new order, missing rules are moved to the end
     */
    public function reorder_rules(string $kind, array $uuids): void {
        $current = array_keys($this->get_rules($kind));
        $uuids = array_values(array_intersect($uuids, $current));
        $uuids = array_merge($uuids, array_diff($current, $uuids));
        foreach ($uuids as $i => $uuid) {
            $this->rules[$uuid]['sortorder'] = $i + 1;
        }
    }

    /**
     * Normalise rule data, config keys are sorted so that the JSON is stable.
     *
     * @param int $sortorder
     * @param string $kind
     * @param string $type
     * @param array $config
     * @return array
     */
    private static function normalise_rule(int $sortorder, string $kind, string $type, array $config): array {
        ksort($config, SORT_STRING);
        return [
            'sortorder' => $sortorder,
            'kind' => $kind,
            'type' => $type,
            'config' => $config,
        ];
    }
}
