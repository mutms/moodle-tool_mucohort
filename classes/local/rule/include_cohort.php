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
use tool_mulib\muform\element\autocompletemany;
use tool_mulib\muform\form;

/**
 * Include rule matching members of any of the cohorts.
 *
 * Configuration: cohortids - list of cohort ids.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class include_cohort extends base {
    #[\Override]
    public static function get_kind(): string {
        return self::KIND_INCLUDE;
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('rule_include_cohort', 'tool_mucohort');
    }

    #[\Override]
    public static function get_type_description(): string {
        return get_string('rule_include_cohort_type', 'tool_mucohort');
    }

    #[\Override]
    public function get_description(array $config): string {
        $names = self::format_cohort_names($config['cohortids'] ?? []);
        return get_string('rule_include_cohort_desc', 'tool_mucohort', implode(', ', $names));
    }

    #[\Override]
    public function get_sql(array $config, ?int $userid): sql {
        global $DB;

        $cohortids = self::validate_record_ids($config['cohortids'] ?? null, 'cohort', 'error_rule_cohortmissing');
        [$insql, $params] = $DB->get_in_or_equal($cohortids);
        return new sql(
            "EXISTS (SELECT 'x' FROM {cohort_members} cm WHERE cm.userid = u.id AND cm.cohortid $insql)",
            $params
        );
    }

    #[\Override]
    public function has_settings(): bool {
        return true;
    }

    #[\Override]
    public function add_form_elements(form $form): void {
        $current = $form->get_current_data()['cohortids'] ?? [];
        $source = new \tool_mucohort\muform\autocompletemany\rule_cohortids(
            (int)$this->cohort->contextid,
            (int)$this->cohort->id,
            array_map('intval', $current)
        );
        $cohortids = new autocompletemany('cohortids', get_string('cohorts', 'core_cohort'), $source);
        $cohortids->set_required(true);
        $form->add($cohortids);
    }

    #[\Override]
    public function get_form_data(array $config): array {
        return ['cohortids' => array_map('strval', $config['cohortids'] ?? [])];
    }

    #[\Override]
    public function get_config(stdClass $data): array {
        return ['cohortids' => array_values(array_map('intval', $data->cohortids))];
    }
}
