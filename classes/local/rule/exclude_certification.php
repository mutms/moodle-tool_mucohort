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
 * Exclude rule matching users with a valid certification.
 *
 * Configuration: certificationid - certification id.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exclude_certification extends base {
    #[\Override]
    public static function get_kind(): string {
        return self::KIND_EXCLUDE;
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('rule_exclude_certification', 'tool_mucohort');
    }

    #[\Override]
    public static function get_type_description(): string {
        return get_string('rule_exclude_certification_type', 'tool_mucohort');
    }

    #[\Override]
    public function get_description(array $config): string {
        $names = self::format_certification_names([$config['certificationid'] ?? null]);
        return get_string('rule_exclude_certification_desc', 'tool_mucohort', implode(', ', $names));
    }

    #[\Override]
    public function get_sql(array $config, ?int $userid): sql {
        global $DB;

        $certificationid = $config['certificationid'] ?? null;
        $certificationids = self::validate_record_ids(
            is_int($certificationid) ? [$certificationid] : null,
            'tool_mucertify_certification',
            'error_rule_certificationmissing'
        );
        [$insql, $params] = $DB->get_in_or_equal($certificationids);
        $now = time();
        return new sql(
            "EXISTS (SELECT 'x'
                       FROM {tool_mucertify_period} cp
                      WHERE cp.userid = u.id AND cp.certificationid $insql
                            AND cp.timecertified IS NOT NULL AND cp.timerevoked IS NULL
                            AND cp.timefrom <= ? AND (cp.timeuntil IS NULL OR cp.timeuntil > ?))",
            array_merge($params, [$now, $now])
        );
    }

    #[\Override]
    public function has_settings(): bool {
        return true;
    }

    #[\Override]
    public function add_form_elements(form $form): void {
        $current = $form->get_current_data()['certificationid'] ?? 0;
        $source = new \tool_mucohort\muform\autocomplete\rule_certificationid((int)$this->cohort->contextid, (int)$current);
        $certificationid = new autocomplete('certificationid', get_string('certification', 'tool_mucertify'), $source);
        $certificationid->set_required(true);
        $form->add($certificationid);
    }

    #[\Override]
    public function get_form_data(array $config): array {
        return isset($config['certificationid']) ? ['certificationid' => (string)$config['certificationid']] : [];
    }

    #[\Override]
    public function get_config(stdClass $data): array {
        return ['certificationid' => (int)$data->certificationid];
    }
}
