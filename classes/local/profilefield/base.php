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
use tool_mulib\muform\form;

/**
 * Base for handlers of custom profile field types used in cohort rules.
 *
 * Handlers provide SQL expressions mapping stored profile field data to cached values in tool_mucohort_profilefield
 * and provide conditions on the cache. Every non-deleted user has one cached row per cached
 * field with the effective value, that is the stored data or the field default.
 *
 * Rule configuration: operator and values, values are interpreted by the handler.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Constructor.
     *
     * @param stdClass $field user_info_field record
     */
    final public function __construct(
        /** @var stdClass user_info_field record */
        protected readonly stdClass $field
    ) {
    }

    /**
     * Returns profile field record.
     *
     * @return stdClass
     */
    final public function get_field(): stdClass {
        return clone($this->field);
    }

    /**
     * Returns formatted field name.
     *
     * @return string
     */
    final public function get_field_name(): string {
        return format_string($this->field->name, true, ['context' => \core\context\system::instance()]);
    }

    /**
     * Returns SQL expressions of cached values.
     *
     * @param string $data expression of stored data without parameters,
     *      the field default is already used when user has no data
     * @return array [charvalue, intvalue] SQL expressions, null for column that is not used
     */
    abstract public function get_cache_sql(string $data): array;

    /**
     * Returns supported operators.
     *
     * @return array operator => name
     */
    abstract public function get_operators(): array;

    /**
     * Returns condition on cache table alias "pf" for matching rows.
     *
     * @param string $operator
     * @param array $values
     * @return sql
     * @throws broken_rules_exception if configuration is not valid
     */
    abstract public function get_sql(string $operator, array $values): sql;

    /**
     * Returns rule description, must not throw exceptions for invalid configuration.
     *
     * @param string $operator
     * @param array $values
     * @return string html
     */
    abstract public function get_description(string $operator, array $values): string;

    /**
     * Add form elements for operator and values, element names must start with the prefix.
     *
     * @param form $form
     * @param string $prefix
     */
    abstract public function add_form_elements(form $form, string $prefix): void;

    /**
     * Convert operator and values to current form data.
     *
     * @param string $prefix
     * @param string $operator
     * @param array $values
     * @return array
     */
    abstract public function get_form_data(string $prefix, string $operator, array $values): array;

    /**
     * Validate submitted operator and values.
     *
     * @param string $prefix
     * @param array $data
     * @param array $allerrors
     */
    abstract public function validate_form_data(string $prefix, array $data, array &$allerrors): void;

    /**
     * Convert submitted data to operator and values.
     *
     * @param string $prefix
     * @param stdClass $data
     * @return array [operator, values]
     */
    abstract public function get_config(string $prefix, stdClass $data): array;

    /**
     * Validate operator.
     *
     * @param string $operator
     * @throws broken_rules_exception
     */
    final protected function validate_operator(string $operator): void {
        if (!array_key_exists($operator, $this->get_operators())) {
            throw new broken_rules_exception('error_rule_invalidconfig');
        }
    }
}
