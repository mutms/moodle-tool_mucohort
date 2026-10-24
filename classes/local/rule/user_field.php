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

use tool_mucohort\local\broken_rules_exception;
use stdClass;
use tool_mulib\local\sql;
use tool_mulib\muform\element\datetime;
use tool_mulib\muform\element\multiselect;
use tool_mulib\muform\element\number;
use tool_mulib\muform\element\radios;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\textarea;
use tool_mulib\muform\form;

/**
 * Base for rules matching standard user table fields.
 *
 * Configuration:
 *  - field: user table column
 *  - operator: text fields equals, startswith, endswith, contains;
 *              menu fields equals; date fields before, after, lastdays, never
 *  - values: list of values, any of them matches; dates have one timestamp,
 *            lastdays has one number of days and never has no values
 *
 * Text matching is case-insensitive and accent-sensitive, it uses only standard DML helpers.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class user_field extends base {
    /** @var string free text field */
    public const string FIELDTYPE_TEXT = 'text';
    /** @var string field with a list of options */
    public const string FIELDTYPE_MENU = 'menu';
    /** @var string timestamp field, 0 means never */
    public const string FIELDTYPE_DATE = 'date';

    /**
     * Returns supported user fields.
     *
     * @return array field => field type
     */
    public static function get_fields(): array {
        return [
            'username' => self::FIELDTYPE_TEXT,
            'email' => self::FIELDTYPE_TEXT,
            'idnumber' => self::FIELDTYPE_TEXT,
            'institution' => self::FIELDTYPE_TEXT,
            'department' => self::FIELDTYPE_TEXT,
            'city' => self::FIELDTYPE_TEXT,
            'country' => self::FIELDTYPE_MENU,
            'lang' => self::FIELDTYPE_MENU,
            'auth' => self::FIELDTYPE_MENU,
            'timecreated' => self::FIELDTYPE_DATE,
            'firstaccess' => self::FIELDTYPE_DATE,
            'lastaccess' => self::FIELDTYPE_DATE,
        ];
    }

    /**
     * Returns operators supported by field type.
     *
     * @param string $fieldtype
     * @return string[]
     */
    public static function get_operators(string $fieldtype): array {
        return match ($fieldtype) {
            self::FIELDTYPE_TEXT => ['equals', 'startswith', 'endswith', 'contains'],
            self::FIELDTYPE_MENU => ['equals'],
            self::FIELDTYPE_DATE => ['before', 'after', 'lastdays', 'never'],
        };
    }

    /**
     * Returns user field name.
     *
     * @param string $field
     * @return string
     */
    public static function get_field_name(string $field): string {
        return match ($field) {
            'lang' => get_string('language'),
            'auth' => get_string('authentication'),
            default => get_string($field),
        };
    }

    /**
     * Returns options of menu fields.
     *
     * @param string $field
     * @return array value => name
     */
    public static function get_menu_options(string $field): array {
        $sm = get_string_manager();
        if ($field === 'country') {
            return $sm->get_list_of_countries(true);
        }
        if ($field === 'lang') {
            return $sm->get_list_of_translations(true);
        }
        if ($field === 'auth') {
            $result = [];
            foreach (\core_component::get_plugin_list('auth') as $auth => $unused) {
                $result[$auth] = get_string('pluginname', 'auth_' . $auth);
            }
            return $result;
        }
        throw new \core\exception\coding_exception('Invalid menu field: ' . $field);
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('rule_userfield', 'tool_mucohort');
    }

    #[\Override]
    public static function get_type_description(): string {
        return get_string('rule_userfield_type', 'tool_mucohort');
    }

    #[\Override]
    public function get_description(array $config): string {
        $field = $config['field'] ?? null;
        $fieldtype = self::get_fields()[$field] ?? null;
        $operator = $config['operator'] ?? null;
        if (!$fieldtype || !in_array($operator, self::get_operators($fieldtype), true)) {
            return get_string('error_rule_invalidconfig', 'tool_mucohort');
        }
        $values = is_array($config['values'] ?? null) ? $config['values'] : [];

        $options = ($fieldtype === self::FIELDTYPE_MENU) ? self::get_menu_options($field) : [];
        $formatted = [];
        foreach ($values as $value) {
            if ($fieldtype === self::FIELDTYPE_DATE) {
                if (!is_int($value)) {
                    continue;
                }
                if ($operator === 'lastdays') {
                    $formatted[] = format_time($value * DAYSECS);
                } else {
                    $formatted[] = userdate($value, get_string('strftimedatetimeshort', 'core_langconfig'));
                }
            } else if ($fieldtype === self::FIELDTYPE_MENU) {
                $formatted[] = clean_string((string)($options[$value] ?? $value));
            } else {
                if ($value === '') {
                    $formatted[] = get_string('rule_userfield_emptyvalue', 'tool_mucohort');
                } else {
                    // Typed values are quoted, so that spaces and other characters are clearly visible.
                    $formatted[] = get_string('rule_userfield_textvalue', 'tool_mucohort', clean_string((string)$value));
                }
            }
        }
        $first = array_shift($formatted);
        foreach ($formatted as $value) {
            $first = get_string('rule_userfield_or', 'tool_mucohort', (object)['first' => $first, 'second' => $value]);
        }

        $a = (object)[
            'field' => self::get_field_name($field),
            'values' => $first ?? '',
        ];
        if ($fieldtype === self::FIELDTYPE_MENU) {
            // Selected options are names, they are not quoted.
            return get_string('rule_userfield_menu', 'tool_mucohort', $a);
        }
        return get_string('rule_userfield_' . $operator, 'tool_mucohort', $a);
    }

    #[\Override]
    public function get_sql(array $config, ?int $userid): sql {
        global $DB;

        $field = $config['field'] ?? null;
        $fieldtype = self::get_fields()[$field] ?? null;
        $operator = $config['operator'] ?? null;
        $values = $config['values'] ?? null;
        if (!$fieldtype || !in_array($operator, self::get_operators($fieldtype), true) || !is_array($values)) {
            throw new broken_rules_exception('error_rule_invalidconfig');
        }

        if ($fieldtype === self::FIELDTYPE_DATE) {
            if ($operator === 'never') {
                if ($values) {
                    throw new broken_rules_exception('error_rule_invalidconfig');
                }
                return new sql("u.$field = 0");
            }
            if (count($values) !== 1 || !is_int($values[0]) || $values[0] <= 0) {
                throw new broken_rules_exception('error_rule_invalidconfig');
            }
            return match ($operator) {
                'before' => new sql("u.$field < ?", [$values[0]]),
                'after' => new sql("u.$field >= ?", [$values[0]]),
                'lastdays' => new sql("u.$field >= ?", [time() - $values[0] * DAYSECS]),
            };
        }

        if (!$values) {
            throw new broken_rules_exception('error_rule_invalidconfig');
        }
        foreach ($values as $value) {
            if (!is_string($value) || ($value === '' && $operator !== 'equals')) {
                throw new broken_rules_exception('error_rule_invalidconfig');
            }
        }

        if ($fieldtype === self::FIELDTYPE_MENU) {
            [$insql, $params] = $DB->get_in_or_equal(array_values(array_unique($values)));
            return new sql("u.$field $insql", $params);
        }

        $conditions = [];
        foreach ($values as $value) {
            $value = $DB->sql_like_escape($value);
            $value = match ($operator) {
                'equals' => $value,
                'startswith' => $value . '%',
                'endswith' => '%' . $value,
                'contains' => '%' . $value . '%',
            };
            $conditions[] = new sql($DB->sql_like("u.$field", '?', false), [$value]);
        }
        return sql::join(' OR ', $conditions)->wrap('(', ')');
    }

    #[\Override]
    public function has_settings(): bool {
        return true;
    }

    /**
     * Returns fields of given type.
     *
     * @param string $fieldtype
     * @return string[]
     */
    private static function get_fields_of_type(string $fieldtype): array {
        return array_keys(array_filter(self::get_fields(), fn($type) => $type === $fieldtype));
    }

    #[\Override]
    public function add_form_elements(form $form): void {
        $dm = $form->get_display_manager();
        $textfields = self::get_fields_of_type(self::FIELDTYPE_TEXT);
        $datefields = self::get_fields_of_type(self::FIELDTYPE_DATE);

        $options = [];
        foreach (array_keys(self::get_fields()) as $field) {
            $options[$field] = self::get_field_name($field);
        }
        $field = new select('field', get_string('rule_userfield_field', 'tool_mucohort'), ['' => ''] + $options);
        $field->set_required(true);
        $form->add($field);

        $operators = [];
        foreach (self::get_operators(self::FIELDTYPE_TEXT) as $operator) {
            $operators[$operator] = get_string('rule_userfield_op_' . $operator, 'tool_mucohort');
        }
        $textoperator = new radios('textoperator', get_string('rule_userfield_operator', 'tool_mucohort'), $operators, true);
        $textoperator->set_default('equals');
        $form->add($textoperator);
        $dm->hide_if('textoperator', 'field', 'notin', $textfields);

        $textvalues = new textarea('textvalues', get_string('rule_userfield_textvalues', 'tool_mucohort'), ['rows' => 4]);
        $textvalues->set_required_marker(true);
        $form->add($textvalues);
        $dm->hide_if('textvalues', 'field', 'notin', $textfields);

        foreach (self::get_fields_of_type(self::FIELDTYPE_MENU) as $menufield) {
            $values = new multiselect(
                'values_' . $menufield,
                get_string('rule_userfield_menuvalues', 'tool_mucohort'),
                self::get_menu_options($menufield)
            );
            $values->set_required_marker(true);
            $form->add($values);
            $dm->hide_if('values_' . $menufield, 'field', 'neq', $menufield);
        }

        $operators = [];
        foreach (self::get_operators(self::FIELDTYPE_DATE) as $operator) {
            $operators[$operator] = get_string('rule_userfield_op_' . $operator, 'tool_mucohort');
        }
        $dateoperator = new radios('dateoperator', get_string('rule_userfield_operator', 'tool_mucohort'), $operators, true);
        $dateoperator->set_default('before');
        $form->add($dateoperator);
        $dm->hide_if('dateoperator', 'field', 'notin', $datefields);

        $date = new datetime('date', get_string('date'));
        $date->set_required_marker(true);
        $form->add($date);
        $dm->hide_if('date', 'field', 'notin', $datefields);
        $dm->hide_if('date', 'dateoperator', 'notin', ['before', 'after']);

        $days = new number('days', get_string('rule_userfield_days', 'tool_mucohort'), ['min' => 1, 'max' => 99999]);
        $days->set_required_marker(true);
        $form->add($days);
        $dm->hide_if('days', 'field', 'notin', $datefields);
        $dm->hide_if('days', 'dateoperator', 'neq', 'lastdays');
    }

    #[\Override]
    public function get_form_data(array $config): array {
        $field = $config['field'] ?? null;
        $fieldtype = self::get_fields()[$field] ?? null;
        if (!$fieldtype) {
            return [];
        }
        $values = is_array($config['values'] ?? null) ? $config['values'] : [];
        $data = ['field' => $field];
        if ($fieldtype === self::FIELDTYPE_TEXT) {
            $data['textoperator'] = $config['operator'] ?? 'equals';
            $data['textvalues'] = implode("\n", $values);
        } else if ($fieldtype === self::FIELDTYPE_MENU) {
            $data['values_' . $field] = $values;
        } else {
            $data['dateoperator'] = $config['operator'] ?? 'before';
            if ($data['dateoperator'] === 'lastdays') {
                $data['days'] = $values[0] ?? null;
            } else if ($data['dateoperator'] !== 'never') {
                $data['date'] = $values[0] ?? null;
            }
        }
        return $data;
    }

    #[\Override]
    public function validate_form_data(array $data, array &$allerrors): void {
        $fieldtype = self::get_fields()[$data['field'] ?? ''] ?? null;
        $required = get_string('required');
        if ($fieldtype === self::FIELDTYPE_TEXT) {
            if (!self::parse_text_values((string)$data['textvalues'])) {
                $allerrors['textvalues'][] = $required;
            }
        } else if ($fieldtype === self::FIELDTYPE_MENU) {
            if (empty($data['values_' . $data['field']])) {
                $allerrors['values_' . $data['field']][] = $required;
            }
        } else if ($fieldtype === self::FIELDTYPE_DATE) {
            if ($data['dateoperator'] === 'lastdays') {
                if (empty($data['days']) || $data['days'] < 1) {
                    $allerrors['days'][] = $required;
                }
            } else if ($data['dateoperator'] !== 'never') {
                if (empty($data['date'])) {
                    $allerrors['date'][] = $required;
                }
            }
        }
    }

    #[\Override]
    public function get_config(stdClass $data): array {
        $fieldtype = self::get_fields()[$data->field];
        if ($fieldtype === self::FIELDTYPE_TEXT) {
            $values = self::parse_text_values($data->textvalues);
            return ['field' => $data->field, 'operator' => $data->textoperator, 'values' => $values];
        }
        if ($fieldtype === self::FIELDTYPE_MENU) {
            return ['field' => $data->field, 'operator' => 'equals', 'values' => array_values($data->{'values_' . $data->field})];
        }
        if ($data->dateoperator === 'never') {
            $values = [];
        } else if ($data->dateoperator === 'lastdays') {
            $values = [(int)$data->days];
        } else {
            $values = [(int)$data->date];
        }
        return ['field' => $data->field, 'operator' => $data->dateoperator, 'values' => $values];
    }

    /**
     * Parse values entered one per line, blank lines and duplicates are ignored.
     *
     * @param string $text
     * @return string[]
     */
    private static function parse_text_values(string $text): array {
        $values = array_map('trim', explode("\n", $text));
        return array_values(array_unique(array_filter($values, fn($value) => $value !== '')));
    }
}
