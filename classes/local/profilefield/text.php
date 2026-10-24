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

namespace tool_mucohort\local\profilefield;

use stdClass;
use tool_mucohort\local\broken_rules_exception;
use tool_mulib\local\sql;
use tool_mulib\muform\element\radios;
use tool_mulib\muform\element\textarea;
use tool_mulib\muform\form;

/**
 * Text profile field handler, values are cached as text truncated to 255 characters.
 *
 * Values: list of texts, any of them matches, empty text matches empty field.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text extends base {
    #[\Override]
    public function get_cache_sql(string $data): array {
        global $DB;
        return [$DB->sql_substr("TRIM($data)", 1, 255), null];
    }

    #[\Override]
    public function get_operators(): array {
        $result = [];
        foreach (['equals', 'startswith', 'endswith', 'contains', 'empty'] as $operator) {
            $result[$operator] = get_string('rule_userfield_op_' . $operator, 'tool_mucohort');
        }
        return $result;
    }

    #[\Override]
    public function get_sql(string $operator, array $values): sql {
        global $DB;

        $this->validate_operator($operator);
        if ($operator === 'empty') {
            if ($values) {
                throw new broken_rules_exception('error_rule_invalidconfig');
            }
            return new sql("pf.charvalue = ''");
        }
        if (!$values) {
            throw new broken_rules_exception('error_rule_invalidconfig');
        }
        $conditions = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new broken_rules_exception('error_rule_invalidconfig');
            }
            $value = $DB->sql_like_escape($value);
            $value = match ($operator) {
                'equals' => $value,
                'startswith' => $value . '%',
                'endswith' => '%' . $value,
                'contains' => '%' . $value . '%',
            };
            $conditions[] = new sql($DB->sql_like('pf.charvalue', '?', false), [$value]);
        }
        return sql::join(' OR ', $conditions)->wrap('(', ')');
    }

    #[\Override]
    public function get_description(string $operator, array $values): string {
        $a = (object)['field' => $this->get_field_name(), 'values' => ''];
        if ($operator === 'empty') {
            return get_string('rule_userfield_empty', 'tool_mucohort', $a);
        }
        $formatted = [];
        foreach ($values as $value) {
            $formatted[] = get_string('rule_userfield_textvalue', 'tool_mucohort', clean_string((string)$value));
        }
        $a->values = self::join_or($formatted);
        if (!array_key_exists($operator, $this->get_operators())) {
            return get_string('error_rule_invalidconfig', 'tool_mucohort');
        }
        return get_string('rule_userfield_' . $operator, 'tool_mucohort', $a);
    }

    #[\Override]
    public function add_form_elements(form $form, string $prefix): void {
        $label = get_string('rule_userfield_operator', 'tool_mucohort');
        $operator = new radios($prefix . 'operator', $label, $this->get_operators(), true);
        $operator->set_default('equals');
        $form->add($operator);

        $values = new textarea($prefix . 'values', get_string('rule_userfield_textvalues', 'tool_mucohort'), ['rows' => 4]);
        $values->set_required_marker(true);
        $form->add($values);
        $form->get_display_manager()->hide_if($prefix . 'values', $prefix . 'operator', 'eq', 'empty');
    }

    #[\Override]
    public function get_form_data(string $prefix, string $operator, array $values): array {
        return [$prefix . 'operator' => $operator, $prefix . 'values' => implode("\n", $values)];
    }

    #[\Override]
    public function validate_form_data(string $prefix, array $data, array &$allerrors): void {
        if (($data[$prefix . 'operator'] ?? '') !== 'empty' && !self::parse_values((string)($data[$prefix . 'values'] ?? ''))) {
            $allerrors[$prefix . 'values'][] = get_string('required');
        }
    }

    #[\Override]
    public function get_config(string $prefix, stdClass $data): array {
        $operator = $data->{$prefix . 'operator'};
        $values = ($operator === 'empty') ? [] : self::parse_values((string)$data->{$prefix . 'values'});
        return [$operator, $values];
    }

    /**
     * Parse values entered one per line, blank lines and duplicates are ignored.
     *
     * @param string $text
     * @return string[]
     */
    protected static function parse_values(string $text): array {
        $values = array_map('trim', explode("\n", $text));
        return array_values(array_unique(array_filter($values, fn($value) => $value !== '')));
    }

    /**
     * Join formatted values with "or".
     *
     * @param string[] $formatted
     * @return string
     */
    final public static function join_or(array $formatted): string {
        $result = array_shift($formatted) ?? '';
        foreach ($formatted as $value) {
            $result = get_string('rule_userfield_or', 'tool_mucohort', (object)['first' => $result, 'second' => $value]);
        }
        return $result;
    }
}
