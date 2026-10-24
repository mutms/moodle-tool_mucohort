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

use stdClass;
use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\profilefield_cache;
use tool_mucohort\local\rule_not_ready_exception;
use tool_mulib\local\sql;
use tool_mulib\muform\element\select;
use tool_mulib\muform\form;

/**
 * Base for rules matching custom profile fields.
 *
 * Configuration:
 *  - fieldid: user_info_field id
 *  - operator and values: interpreted by the profile field handler
 *
 * Values are read from the cache of profile field values, the rule cannot be evaluated
 * until values of the field are cached.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class profile_field extends base {
    #[\Override]
    public static function get_name(): string {
        return get_string('rule_profilefield', 'tool_mucohort');
    }

    #[\Override]
    public static function get_type_description(): string {
        return get_string('rule_profilefield_type', 'tool_mucohort');
    }

    #[\Override]
    public static function is_available(): bool {
        return (bool)profilefield_cache::get_supported_fields();
    }

    /**
     * Returns handler of configured field.
     *
     * @param array $config
     * @return \tool_mucohort\local\profilefield\base
     * @throws broken_rules_exception
     */
    private static function get_handler(array $config): \tool_mucohort\local\profilefield\base {
        global $DB;

        $fieldid = $config['fieldid'] ?? null;
        if (!is_int($fieldid) || !is_string($config['operator'] ?? null) || !is_array($config['values'] ?? null)) {
            throw new broken_rules_exception('error_rule_invalidconfig');
        }
        $field = $DB->get_record('user_info_field', ['id' => $fieldid]);
        if (!$field) {
            throw new broken_rules_exception('error_rule_profilefieldmissing', $fieldid);
        }
        $handler = profilefield_cache::get_handler($field);
        if (!$handler) {
            throw new broken_rules_exception('error_rule_profilefieldunsupported', $field->datatype);
        }
        return $handler;
    }

    #[\Override]
    public function get_description(array $config): string {
        try {
            $handler = self::get_handler($config);
        } catch (broken_rules_exception $ex) {
            $fieldid = $config['fieldid'] ?? '';
            return get_string('rule_profilefield_missing', 'tool_mucohort', is_scalar($fieldid) ? $fieldid : '');
        }
        return $handler->get_description($config['operator'], $config['values']);
    }

    #[\Override]
    public function get_sql(array $config, ?int $userid): sql {
        $handler = self::get_handler($config);
        $condition = $handler->get_sql($config['operator'], $config['values']);
        if (!profilefield_cache::is_ready($config['fieldid'])) {
            throw new rule_not_ready_exception('error_rule_profilefieldnotready', $handler->get_field_name());
        }
        $sql = new sql(
            "EXISTS (SELECT 'x'
                       FROM {tool_mucohort_profilefield} pf
                      WHERE pf.userid = u.id AND pf.fieldid = ? /* condition */)",
            [$config['fieldid']]
        );
        return $sql->replace_comment('condition', $condition->wrap('AND (', ')'));
    }

    #[\Override]
    public function before_preview(array $config, \progress_trace $trace): void {
        self::get_handler($config);
        if (profilefield_cache::is_outdated($config['fieldid'])) {
            profilefield_cache::populate_field($config['fieldid'], $trace);
        }
    }

    #[\Override]
    public function before_sync(array $config, ?int $userid, \progress_trace $trace): void {
        self::get_handler($config);
        if ($userid) {
            // Make sure instant sync uses the current value.
            profilefield_cache::update_user($userid, $config['fieldid']);
        } else if (profilefield_cache::is_outdated($config['fieldid'])) {
            profilefield_cache::populate_field($config['fieldid'], $trace);
        }
    }

    #[\Override]
    public function has_settings(): bool {
        return true;
    }

    /**
     * Returns element name prefix of field.
     *
     * @param int $fieldid
     * @return string
     */
    private static function get_prefix(int $fieldid): string {
        return 'pf' . $fieldid . '_';
    }

    #[\Override]
    public function add_form_elements(form $form): void {
        $handlers = profilefield_cache::get_supported_fields();

        $options = ['' => ''];
        foreach ($handlers as $fieldid => $handler) {
            $options[$fieldid] = $handler->get_field_name();
        }
        $field = new select('fieldid', get_string('rule_profilefield_field', 'tool_mucohort'), $options);
        $field->set_required(true);
        $form->add($field);

        foreach ($handlers as $fieldid => $handler) {
            $prefix = self::get_prefix($fieldid);
            $before = array_keys($form->get_elements());
            $handler->add_form_elements($form, $prefix);
            foreach (array_diff(array_keys($form->get_elements()), $before) as $elname) {
                $form->get_display_manager()->hide_if($elname, 'fieldid', 'neq', $fieldid);
            }
        }
    }

    #[\Override]
    public function get_form_data(array $config): array {
        try {
            $handler = self::get_handler($config);
        } catch (broken_rules_exception $ex) {
            return [];
        }
        $fieldid = $config['fieldid'];
        $data = $handler->get_form_data(self::get_prefix($fieldid), $config['operator'], $config['values']);
        return ['fieldid' => $fieldid] + $data;
    }

    #[\Override]
    public function validate_form_data(array $data, array &$allerrors): void {
        $handler = profilefield_cache::get_supported_fields()[(int)($data['fieldid'] ?? 0)] ?? null;
        if (!$handler) {
            $allerrors['fieldid'][] = get_string('required');
            return;
        }
        $handler->validate_form_data(self::get_prefix((int)$data['fieldid']), $data, $allerrors);
    }

    #[\Override]
    public function get_config(stdClass $data): array {
        $fieldid = (int)$data->fieldid;
        $handler = profilefield_cache::get_supported_fields()[$fieldid];
        [$operator, $values] = $handler->get_config(self::get_prefix($fieldid), $data);
        return ['fieldid' => $fieldid, 'operator' => $operator, 'values' => $values];
    }
}
