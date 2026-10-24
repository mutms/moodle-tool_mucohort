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
use tool_mulib\muform\form;

/**
 * Checkbox profile field handler, 1 or 0 is cached.
 *
 * Values: not used.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkbox extends base {
    #[\Override]
    public function get_cache_sql(string $data): array {
        // Empty data and '0' mean not checked.
        return [null, "CASE WHEN TRIM($data) IN ('', '0') THEN 0 ELSE 1 END"];
    }

    #[\Override]
    public function get_operators(): array {
        return [
            'checked' => get_string('rule_profilefield_op_checked', 'tool_mucohort'),
            'notchecked' => get_string('rule_profilefield_op_notchecked', 'tool_mucohort'),
        ];
    }

    #[\Override]
    public function get_sql(string $operator, array $values): sql {
        $this->validate_operator($operator);
        if ($values) {
            throw new broken_rules_exception('error_rule_invalidconfig');
        }
        return new sql('pf.intvalue = ?', [$operator === 'checked' ? 1 : 0]);
    }

    #[\Override]
    public function get_description(string $operator, array $values): string {
        $identifier = ($operator === 'checked') ? 'rule_profilefield_checked' : 'rule_profilefield_notchecked';
        return get_string($identifier, 'tool_mucohort', $this->get_field_name());
    }

    #[\Override]
    public function add_form_elements(form $form, string $prefix): void {
        $label = get_string('rule_userfield_operator', 'tool_mucohort');
        $operator = new radios($prefix . 'operator', $label, $this->get_operators(), true);
        $operator->set_default('checked');
        $form->add($operator);
    }

    #[\Override]
    public function get_form_data(string $prefix, string $operator, array $values): array {
        return [$prefix . 'operator' => $operator];
    }

    #[\Override]
    public function validate_form_data(string $prefix, array $data, array &$allerrors): void {
    }

    #[\Override]
    public function get_config(string $prefix, stdClass $data): array {
        return [$data->{$prefix . 'operator'}, []];
    }
}
