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
use tool_mulib\muform\element\multiselect;
use tool_mulib\muform\form;

/**
 * Menu profile field handler, the selected option text is cached.
 *
 * Values: list of option texts, any of them matches.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class menu extends base {
    /**
     * Returns menu options.
     *
     * @return array option text => formatted name
     */
    public function get_options(): array {
        $result = [];
        foreach (explode("\n", (string)$this->field->param1) as $option) {
            $option = trim($option);
            if ($option !== '') {
                $result[$option] = format_string($option, true, ['context' => \core\context\system::instance()]);
            }
        }
        return $result;
    }

    #[\Override]
    public function get_cache_sql(string $data): array {
        global $DB;
        return [$DB->sql_substr("TRIM($data)", 1, 255), null];
    }

    #[\Override]
    public function get_operators(): array {
        return ['equals' => get_string('rule_userfield_op_equals', 'tool_mucohort')];
    }

    #[\Override]
    public function get_sql(string $operator, array $values): sql {
        global $DB;

        $this->validate_operator($operator);
        if (!$values) {
            throw new broken_rules_exception('error_rule_invalidconfig');
        }
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new broken_rules_exception('error_rule_invalidconfig');
            }
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values(array_unique($values)));
        return new sql("pf.charvalue $insql", $params);
    }

    #[\Override]
    public function get_description(string $operator, array $values): string {
        $options = $this->get_options();
        $formatted = [];
        foreach ($values as $value) {
            $formatted[] = $options[$value] ?? clean_string((string)$value);
        }
        $a = (object)['field' => $this->get_field_name(), 'values' => text::join_or($formatted)];
        return get_string('rule_userfield_menu', 'tool_mucohort', $a);
    }

    #[\Override]
    public function add_form_elements(form $form, string $prefix): void {
        $label = get_string('rule_userfield_menuvalues', 'tool_mucohort');
        $values = new multiselect($prefix . 'values', $label, $this->get_options());
        $values->set_required_marker(true);
        $form->add($values);
    }

    #[\Override]
    public function get_form_data(string $prefix, string $operator, array $values): array {
        return [$prefix . 'values' => $values];
    }

    #[\Override]
    public function validate_form_data(string $prefix, array $data, array &$allerrors): void {
        if (empty($data[$prefix . 'values'])) {
            $allerrors[$prefix . 'values'][] = get_string('required');
        }
    }

    #[\Override]
    public function get_config(string $prefix, stdClass $data): array {
        return ['equals', array_values((array)$data->{$prefix . 'values'})];
    }
}
