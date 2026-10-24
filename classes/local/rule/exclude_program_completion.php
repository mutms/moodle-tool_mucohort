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
use tool_mulib\local\sql;
use tool_mulib\muform\element\autocomplete;
use tool_mulib\muform\form;

/**
 * Exclude rule matching users who completed the program.
 *
 * Configuration: programid - program id.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exclude_program_completion extends base {
    #[\Override]
    public static function get_kind(): string {
        return self::KIND_EXCLUDE;
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('rule_exclude_programcompletion', 'tool_mucohort');
    }

    #[\Override]
    public static function get_type_description(): string {
        return get_string('rule_exclude_programcompletion_type', 'tool_mucohort');
    }

    #[\Override]
    public function get_description(array $config): string {
        $names = self::format_program_names([$config['programid'] ?? null]);
        return get_string('rule_exclude_programcompletion_desc', 'tool_mucohort', implode(', ', $names));
    }

    #[\Override]
    public function get_sql(array $config, ?int $userid): sql {
        global $DB;

        $programid = $config['programid'] ?? null;
        $programids = self::validate_record_ids(
            is_int($programid) ? [$programid] : null,
            'tool_muprog_program',
            'error_rule_programmissing'
        );
        [$insql, $params] = $DB->get_in_or_equal($programids);
        return new sql(
            "EXISTS (SELECT 'x'
                       FROM {tool_muprog_allocation} pa
                      WHERE pa.userid = u.id AND pa.programid $insql AND pa.timecompleted IS NOT NULL)",
            $params
        );
    }

    #[\Override]
    public function has_settings(): bool {
        return true;
    }

    #[\Override]
    public function add_form_elements(form $form): void {
        $current = $form->get_current_data()['programid'] ?? 0;
        $source = new \tool_mucohort\muform\autocomplete\rule_programid((int)$this->cohort->contextid, (int)$current);
        $programid = new autocomplete('programid', get_string('program', 'tool_muprog'), $source);
        $programid->set_required(true);
        $form->add($programid);
    }

    #[\Override]
    public function get_form_data(array $config): array {
        return isset($config['programid']) ? ['programid' => (string)$config['programid']] : [];
    }

    #[\Override]
    public function get_config(stdClass $data): array {
        return ['programid' => (int)$data->programid];
    }
}
