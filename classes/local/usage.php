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

namespace tool_mucohort\local;

use core\url;
use tool_mucohort\hook\cohort_usage;
use tool_mucohort\local\type\base;
use stdClass;

/**
 * Usage of cohorts in Moodle core and in advanced cohorts.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class usage {
    /**
     * Returns cohort usage collected from all plugins.
     *
     * @param base $cohort
     * @return cohort_usage
     */
    public static function get_usage(base $cohort): cohort_usage {
        $hook = new cohort_usage($cohort->get_cohort());
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        return $hook;
    }

    /**
     * Hook callback adding usages in Moodle core and advanced cohorts.
     *
     * @param cohort_usage $hook
     */
    public static function callback_cohort_usage(cohort_usage $hook): void {
        $cohort = $hook->get_cohort();
        self::add_candidate_usage($hook, $cohort);
        self::add_enrol_usage($hook, $cohort);
        self::add_badge_usage($hook, $cohort);
        self::add_cohortroles_usage($hook, $cohort);
        self::add_audience_usage($hook, $cohort);
    }

    /**
     * Advanced cohorts using the cohort as candidate cohort.
     *
     * @param cohort_usage $hook
     * @param stdClass $cohort
     */
    private static function add_candidate_usage(cohort_usage $hook, stdClass $cohort): void {
        global $DB;

        $sql = "SELECT c.*
                  FROM {cohort} c
                  JOIN {tool_mucohort} mc ON mc.cohortid = c.id
                 WHERE mc.candidatecohortid = ?
              ORDER BY c.name ASC, c.id ASC";
        $section = get_string('usage_candidate', 'tool_mucohort');
        foreach ($DB->get_records_sql($sql, [$cohort->id]) as $record) {
            $url = management::get_cohort_url($record);
            $hook->add_item($section, self::format_name($record->name), $url, null, $url !== null);
        }
    }

    /**
     * Cohort sync enrolments and self enrolments restricted to cohort members.
     *
     * @param cohort_usage $hook
     * @param stdClass $cohort
     */
    private static function add_enrol_usage(cohort_usage $hook, stdClass $cohort): void {
        global $DB;

        $types = [
            'cohort' => ['field' => 'customint1', 'section' => get_string('usage_enrolcohort', 'tool_mucohort')],
            'self' => ['field' => 'customint5', 'section' => get_string('usage_enrolself', 'tool_mucohort')],
        ];
        $roles = role_get_names(null, ROLENAME_ALIAS, true);
        foreach ($types as $enrol => $type) {
            if (!enrol_is_enabled($enrol)) {
                continue;
            }
            $sql = "SELECT e.id, e.status, e.roleid, e.courseid, c.fullname
                      FROM {enrol} e
                      JOIN {course} c ON c.id = e.courseid
                     WHERE e.enrol = :enrol AND e.{$type['field']} = :cohortid
                  ORDER BY c.fullname ASC, e.id ASC";
            $instances = $DB->get_records_sql($sql, ['enrol' => $enrol, 'cohortid' => $cohort->id]);
            foreach ($instances as $instance) {
                $context = \core\context\course::instance($instance->courseid, IGNORE_MISSING);
                $visible = $context && has_capability('moodle/course:enrolreview', $context);
                $url = $visible ? new url('/enrol/instances.php', ['id' => $instance->courseid]) : null;
                $details = [];
                if ($enrol === 'cohort' && isset($roles[$instance->roleid])) {
                    $details[] = $roles[$instance->roleid];
                }
                if ($instance->status == ENROL_INSTANCE_DISABLED) {
                    $details[] = get_string('disabled', 'admin');
                }
                $name = self::format_name($instance->fullname, $context ?: null);
                $hook->add_item($type['section'], $name, $url, $details ? implode(', ', $details) : null, $visible);
            }
        }
    }

    /**
     * Badges awarded to cohort members.
     *
     * @param cohort_usage $hook
     * @param stdClass $cohort
     */
    private static function add_badge_usage(cohort_usage $hook, stdClass $cohort): void {
        global $CFG, $DB;

        if (empty($CFG->enablebadges)) {
            return;
        }
        $sql = "SELECT DISTINCT b.id, b.name, b.type, b.courseid
                  FROM {badge} b
                  JOIN {badge_criteria} bc ON bc.badgeid = b.id
                  JOIN {badge_criteria_param} bcp ON bcp.critid = bc.id
                 WHERE bcp.name = :name
              ORDER BY b.name ASC, b.id ASC";
        $section = get_string('usage_badges', 'tool_mucohort');
        foreach ($DB->get_records_sql($sql, ['name' => 'cohort_' . $cohort->id]) as $badge) {
            if ($badge->courseid) {
                $context = \core\context\course::instance($badge->courseid, IGNORE_MISSING);
            } else {
                $context = \core\context\system::instance();
            }
            $visible = $context && has_capability('moodle/badges:configurecriteria', $context);
            $url = $visible ? new url('/badges/criteria.php', ['id' => $badge->id]) : null;
            $hook->add_item($section, self::format_name($badge->name, $context ?: null), $url, null, $visible);
        }
    }

    /**
     * Role assignments of cohort members in user contexts.
     *
     * @param cohort_usage $hook
     * @param stdClass $cohort
     */
    private static function add_cohortroles_usage(cohort_usage $hook, stdClass $cohort): void {
        global $DB;

        if (\core_component::get_component_directory('tool_cohortroles') === null) {
            return;
        }
        $sql = "SELECT roleid, COUNT('x') AS usercount
                  FROM {tool_cohortroles}
                 WHERE cohortid = ?
              GROUP BY roleid";
        $records = $DB->get_records_sql($sql, [$cohort->id]);
        if (!$records) {
            return;
        }
        $syscontext = \core\context\system::instance();
        $visible = has_capability('moodle/role:manage', $syscontext);
        $url = $visible ? new url('/admin/tool/cohortroles/index.php') : null;
        $roles = role_get_names($syscontext, ROLENAME_ALIAS, true);
        $section = get_string('usage_cohortroles', 'tool_mucohort');
        foreach ($records as $record) {
            $name = $roles[$record->roleid] ?? (string)$record->roleid;
            $details = get_string('usage_users', 'tool_mucohort', $record->usercount);
            $hook->add_item($section, $name, $url, $details, $visible);
        }
    }

    /**
     * Custom report audiences of cohort members.
     *
     * @param cohort_usage $hook
     * @param stdClass $cohort
     */
    private static function add_audience_usage(cohort_usage $hook, stdClass $cohort): void {
        global $DB;

        $classname = \core_cohort\reportbuilder\audience\cohortmember::class;
        $sql = "SELECT a.id, a.configdata, r.id AS reportid
                  FROM {reportbuilder_audience} a
                  JOIN {reportbuilder_report} r ON r.id = a.reportid
                 WHERE a.classname = :classname1 OR a.classname = :classname2
              ORDER BY r.id ASC";
        $params = ['classname1' => $classname, 'classname2' => '\\' . $classname];
        $reportids = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $audience) {
            $config = json_decode((string)$audience->configdata, true);
            $cohortids = array_map('intval', (array)($config['cohorts'] ?? []));
            if (in_array((int)$cohort->id, $cohortids, true)) {
                $reportids[$audience->reportid] = $audience->reportid;
            }
        }
        $rs->close();

        $section = get_string('usage_audiences', 'tool_mucohort');
        foreach ($DB->get_records_list('reportbuilder_report', 'id', $reportids, 'name ASC, id ASC') as $record) {
            $report = new \core_reportbuilder\local\models\report(0, $record);
            $visible = \core_reportbuilder\permission::can_edit_report($report);
            $url = $visible ? new url('/reportbuilder/edit.php', ['id' => $record->id]) : null;
            $context = \core\context::instance_by_id($record->contextid, IGNORE_MISSING);
            $hook->add_item($section, self::format_name((string)$record->name, $context ?: null), $url, null, $visible);
        }
    }

    /**
     * Format name as plain text.
     *
     * @param string $name
     * @param \core\context|null $context
     * @return string
     */
    private static function format_name(string $name, ?\core\context $context = null): string {
        $context = $context ?? \core\context\system::instance();
        return format_string($name, true, ['context' => $context, 'escape' => false]);
    }
}
