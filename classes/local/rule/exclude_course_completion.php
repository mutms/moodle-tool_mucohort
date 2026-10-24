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
 * Exclude rule matching users who completed the course.
 *
 * Configuration: courseid - course id.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exclude_course_completion extends base {
    #[\Override]
    public static function get_kind(): string {
        return self::KIND_EXCLUDE;
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('rule_exclude_coursecompletion', 'tool_mucohort');
    }

    #[\Override]
    public static function get_type_description(): string {
        return get_string('rule_exclude_coursecompletion_type', 'tool_mucohort');
    }

    #[\Override]
    public function get_description(array $config): string {
        $names = self::format_course_names([$config['courseid'] ?? null]);
        return get_string('rule_exclude_coursecompletion_desc', 'tool_mucohort', implode(', ', $names));
    }

    #[\Override]
    public function get_sql(array $config, ?int $userid): sql {
        global $DB;

        $courseid = $config['courseid'] ?? null;
        $courseids = self::validate_record_ids(is_int($courseid) ? [$courseid] : null, 'course', 'error_rule_coursemissing');
        [$insql, $params] = $DB->get_in_or_equal($courseids);
        return new sql(
            "EXISTS (SELECT 'x'
                       FROM {course_completions} cc
                      WHERE cc.userid = u.id AND cc.course $insql AND cc.timecompleted IS NOT NULL)",
            $params
        );
    }

    #[\Override]
    public function has_settings(): bool {
        return true;
    }

    #[\Override]
    public function add_form_elements(form $form): void {
        $current = $form->get_current_data()['courseid'] ?? 0;
        $source = new \tool_mucohort\muform\autocomplete\rule_courseid((int)$this->cohort->contextid, (int)$current);
        $courseid = new autocomplete('courseid', get_string('course'), $source);
        $courseid->set_required(true);
        $form->add($courseid);
    }

    #[\Override]
    public function get_form_data(array $config): array {
        return isset($config['courseid']) ? ['courseid' => (string)$config['courseid']] : [];
    }

    #[\Override]
    public function get_config(stdClass $data): array {
        return ['courseid' => (int)$data->courseid];
    }
}
