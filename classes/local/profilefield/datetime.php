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
use tool_mulib\muform\element\number;
use tool_mulib\muform\element\radios;
use tool_mulib\muform\form;

/**
 * Date and time profile field handler, the timestamp is cached, 0 means not set.
 *
 * Values: one timestamp for before and after, one number of days for lastdays, nothing for notset.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datetime extends base {
    #[\Override]
    public function get_cache_sql(string $data): array {
        global $DB;
        // Empty data means not set, stored timestamps are always numbers.
        $timestamp = $DB->sql_cast_char2int("TRIM($data)");
        return [null, "CASE WHEN TRIM($data) = '' THEN 0 ELSE $timestamp END"];
    }

    #[\Override]
    public function get_operators(): array {
        $result = [];
        foreach (['before', 'after', 'lastdays'] as $operator) {
            $result[$operator] = get_string('rule_userfield_op_' . $operator, 'tool_mucohort');
        }
        $result['notset'] = get_string('rule_profilefield_op_notset', 'tool_mucohort');
        return $result;
    }

    #[\Override]
    public function get_sql(string $operator, array $values): sql {
        $this->validate_operator($operator);
        if ($operator === 'notset') {
            if ($values) {
                throw new broken_rules_exception('error_rule_invalidconfig');
            }
            return new sql('pf.intvalue = 0');
        }
        if (count($values) !== 1 || !is_int($values[0]) || $values[0] <= 0) {
            throw new broken_rules_exception('error_rule_invalidconfig');
        }
        // Fields that are not set never match dates.
        return match ($operator) {
            'before' => new sql('pf.intvalue > 0 AND pf.intvalue < ?', [$values[0]]),
            'after' => new sql('pf.intvalue >= ?', [$values[0]]),
            'lastdays' => new sql('pf.intvalue >= ? AND pf.intvalue <= ?', [time() - $values[0] * DAYSECS, time()]),
        };
    }

    #[\Override]
    public function get_description(string $operator, array $values): string {
        $a = (object)['field' => $this->get_field_name(), 'values' => ''];
        if ($operator === 'notset') {
            return get_string('rule_profilefield_notset', 'tool_mucohort', $a->field);
        }
        $value = $values[0] ?? null;
        if (!is_int($value) || !array_key_exists($operator, $this->get_operators())) {
            return get_string('error_rule_invalidconfig', 'tool_mucohort');
        }
        if ($operator === 'lastdays') {
            $a->values = format_time($value * DAYSECS);
        } else {
            $a->values = userdate($value, get_string('strftimedatetimeshort', 'core_langconfig'));
        }
        return get_string('rule_userfield_' . $operator, 'tool_mucohort', $a);
    }

    #[\Override]
    public function add_form_elements(form $form, string $prefix): void {
        $dm = $form->get_display_manager();

        $label = get_string('rule_userfield_operator', 'tool_mucohort');
        $operator = new radios($prefix . 'operator', $label, $this->get_operators(), true);
        $operator->set_default('before');
        $form->add($operator);

        $date = new \tool_mulib\muform\element\datetime($prefix . 'date', get_string('date'));
        $date->set_required_marker(true);
        $form->add($date);
        $dm->hide_if($prefix . 'date', $prefix . 'operator', 'notin', ['before', 'after']);

        $days = new number($prefix . 'days', get_string('rule_userfield_days', 'tool_mucohort'), ['min' => 1, 'max' => 99999]);
        $days->set_required_marker(true);
        $form->add($days);
        $dm->hide_if($prefix . 'days', $prefix . 'operator', 'neq', 'lastdays');
    }

    #[\Override]
    public function get_form_data(string $prefix, string $operator, array $values): array {
        $data = [$prefix . 'operator' => $operator];
        if ($operator === 'lastdays') {
            $data[$prefix . 'days'] = $values[0] ?? null;
        } else if ($operator !== 'notset') {
            $data[$prefix . 'date'] = $values[0] ?? null;
        }
        return $data;
    }

    #[\Override]
    public function validate_form_data(string $prefix, array $data, array &$allerrors): void {
        $operator = $data[$prefix . 'operator'] ?? '';
        if ($operator === 'lastdays' && (empty($data[$prefix . 'days']) || $data[$prefix . 'days'] < 1)) {
            $allerrors[$prefix . 'days'][] = get_string('required');
        } else if (($operator === 'before' || $operator === 'after') && empty($data[$prefix . 'date'])) {
            $allerrors[$prefix . 'date'][] = get_string('required');
        }
    }

    #[\Override]
    public function get_config(string $prefix, stdClass $data): array {
        $operator = $data->{$prefix . 'operator'};
        $values = match ($operator) {
            'notset' => [],
            'lastdays' => [(int)$data->{$prefix . 'days'}],
            default => [(int)$data->{$prefix . 'date'}],
        };
        return [$operator, $values];
    }
}
