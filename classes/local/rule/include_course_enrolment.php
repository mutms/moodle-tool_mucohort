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
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\form;

/**
 * Include rule matching users enrolled in any of the courses.
 *
 * Configuration: courseids - list of course ids, activeonly - ignore suspended and expired enrolments.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class include_course_enrolment extends base {
    #[\Override]
    public static function get_kind(): string {
        return self::KIND_INCLUDE;
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('rule_include_courseenrolment', 'tool_mucohort');
    }

    #[\Override]
    public static function get_type_description(): string {
        return get_string('rule_include_courseenrolment_type', 'tool_mucohort');
    }

    #[\Override]
    public function get_description(array $config): string {
        $names = implode(', ', self::format_course_names($config['courseids'] ?? []));
        if (!empty($config['activeonly'])) {
            return get_string('rule_include_courseenrolment_descactive', 'tool_mucohort', $names);
        }
        return get_string('rule_include_courseenrolment_desc', 'tool_mucohort', $names);
    }

    #[\Override]
    public function get_sql(array $config, ?int $userid): sql {
        global $DB;

        $courseids = self::validate_record_ids($config['courseids'] ?? null, 'course', 'error_rule_coursemissing');
        [$insql, $params] = $DB->get_in_or_equal($courseids);
        if (empty($config['activeonly'])) {
            return new sql(
                "EXISTS (SELECT 'x'
                           FROM {user_enrolments} ue
                           JOIN {enrol} e ON e.id = ue.enrolid
                          WHERE ue.userid = u.id AND e.courseid $insql)",
                $params
            );
        }
        $now = time();
        return new sql(
            "EXISTS (SELECT 'x'
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                      WHERE ue.userid = u.id AND e.courseid $insql
                            AND ue.status = ? AND e.status = ? AND ue.timestart <= ? AND (ue.timeend = 0 OR ue.timeend > ?))",
            array_merge($params, [ENROL_USER_ACTIVE, ENROL_INSTANCE_ENABLED, $now, $now])
        );
    }

    #[\Override]
    public function has_settings(): bool {
        return true;
    }

    #[\Override]
    public function add_form_elements(form $form): void {
        $current = $form->get_current_data()['courseids'] ?? [];
        $source = new \tool_mucohort\muform\autocompletemany\rule_courseids(
            (int)$this->cohort->contextid,
            array_map('intval', $current)
        );
        $courseids = new autocompletemany('courseids', get_string('courses'), $source);
        $courseids->set_required(true);
        $form->add($courseids);

        $activeonly = new checkbox('activeonly', '', get_string('rule_activeonly', 'tool_mucohort'));
        $activeonly->set_default(1);
        $form->add($activeonly);
    }

    #[\Override]
    public function get_form_data(array $config): array {
        $result = ['courseids' => array_map('strval', $config['courseids'] ?? [])];
        if (isset($config['activeonly'])) {
            $result['activeonly'] = (int)$config['activeonly'];
        }
        return $result;
    }

    #[\Override]
    public function get_config(stdClass $data): array {
        return [
            'courseids' => array_values(array_map('intval', $data->courseids)),
            'activeonly' => !empty($data->activeonly),
        ];
    }
}
