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
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\form;

/**
 * Exclude rule matching users enrolled in the course.
 *
 * Configuration: courseid - course id, activeonly - ignore suspended and expired enrolments.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exclude_course_enrolment extends base {
    #[\Override]
    public static function get_kind(): string {
        return self::KIND_EXCLUDE;
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('rule_exclude_courseenrolment', 'tool_mucohort');
    }

    #[\Override]
    public static function get_type_description(): string {
        return get_string('rule_exclude_courseenrolment_type', 'tool_mucohort');
    }

    #[\Override]
    public function get_description(array $config): string {
        $courseid = $config['courseid'] ?? null;
        $names = implode(', ', self::format_course_names([$courseid]));
        if (!empty($config['activeonly'])) {
            return get_string('rule_exclude_courseenrolment_descactive', 'tool_mucohort', $names);
        }
        return get_string('rule_exclude_courseenrolment_desc', 'tool_mucohort', $names);
    }

    #[\Override]
    public function get_sql(array $config, ?int $userid): sql {
        global $DB;

        $courseid = $config['courseid'] ?? null;
        $courseids = self::validate_record_ids(is_int($courseid) ? [$courseid] : null, 'course', 'error_rule_coursemissing');
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
        $current = $form->get_current_data()['courseid'] ?? 0;
        $source = new \tool_mucohort\muform\autocomplete\rule_courseid((int)$this->cohort->contextid, (int)$current);
        $courseid = new autocomplete('courseid', get_string('course'), $source);
        $courseid->set_required(true);
        $form->add($courseid);

        $activeonly = new checkbox('activeonly', '', get_string('rule_activeonly', 'tool_mucohort'));
        $activeonly->set_default(1);
        $form->add($activeonly);
    }

    #[\Override]
    public function get_form_data(array $config): array {
        $result = isset($config['courseid']) ? ['courseid' => (string)$config['courseid']] : [];
        if (isset($config['activeonly'])) {
            $result['activeonly'] = (int)$config['activeonly'];
        }
        return $result;
    }

    #[\Override]
    public function get_config(stdClass $data): array {
        return ['courseid' => (int)$data->courseid, 'activeonly' => !empty($data->activeonly)];
    }
}
