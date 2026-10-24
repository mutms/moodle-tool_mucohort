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
use tool_mucohort\local\rule_not_ready_exception;
use tool_mulib\local\sql;
use tool_mulib\muform\form;

/**
 * Base for rule types of rule-based cohorts.
 *
 * Each rule type class is either an include or an exclude rule, all include rules
 * must match and any matching exclude rule removes the user.
 *
 * Rule configuration is stored in the rules JSON, rule types only interpret it.
 * Rules are independent, duplicate or overlapping rules are allowed.
 *
 * NOTE: the cohort and mucohort records may have id === null when the cohort
 * is not saved in the database yet (clone wizard, future import), the intended
 * contextid, name and type are always known.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var string all include rules must match */
    public const string KIND_INCLUDE = 'include';
    /** @var string any matching exclude rule removes the user */
    public const string KIND_EXCLUDE = 'exclude';

    /**
     * Constructor.
     *
     * @param stdClass $cohort cohort record, id may be null
     * @param stdClass $mucohort tool_mucohort record, id may be null
     */
    final public function __construct(
        /** @var stdClass cohort record */
        protected readonly stdClass $cohort,
        /** @var stdClass tool_mucohort record */
        protected readonly stdClass $mucohort
    ) {
    }

    /**
     * Returns rule kind.
     *
     * @return string KIND_INCLUDE or KIND_EXCLUDE
     */
    abstract public static function get_kind(): string;

    /**
     * Returns rule type name.
     *
     * @return string plain text
     */
    abstract public static function get_name(): string;

    /**
     * Can this rule type be used on this site?
     *
     * For example rules that depend on database specific features may return false.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return true;
    }

    /**
     * Returns rule description as one plain sentence.
     *
     * Must not throw exceptions for invalid configuration, broken rules are described too.
     *
     * @param array $config rule configuration
     * @return string html
     */
    abstract public function get_description(array $config): string;

    /**
     * Returns SQL condition on user table alias "u" for users matched by this rule.
     *
     * Exclude rules return the users that are excluded.
     *
     * The condition must never evaluate to NULL, it is negated in removal queries.
     * Comments must not be used, the caller adds line comments with the rule id.
     *
     * @param array $config rule configuration
     * @param int|null $userid when set the result is used for one user only,
     *      the rule may use it to optimise inner queries
     * @return sql
     * @throws broken_rules_exception when the rule cannot be evaluated
     */
    abstract public function get_sql(array $config, ?int $userid): sql;

    /**
     * Returns explanation of the rule type for the rule type selection.
     *
     * @return string plain text
     */
    abstract public static function get_type_description(): string;

    /**
     * Does the rule type have any settings?
     *
     * @return bool
     */
    public function has_settings(): bool {
        return false;
    }

    /**
     * Add elements for rule settings.
     *
     * Rule forms are storage agnostic, the current data comes from get_form_data()
     * and the caller stores the result of get_config().
     *
     * @param form $form
     */
    public function add_form_elements(form $form): void {
    }

    /**
     * Convert rule configuration to current form data.
     *
     * @param array $config
     * @return array
     */
    public function get_form_data(array $config): array {
        return $config;
    }

    /**
     * Validate submitted rule settings.
     *
     * @param array $data
     * @param array $allerrors
     */
    public function validate_form_data(array $data, array &$allerrors): void {
    }

    /**
     * Prepare data before preview of rules, such as rule check in wizard or preview of draft rules.
     *
     * Problems do not need to be reported, rules are evaluated with whatever data is available.
     *
     * @param array $config
     * @param \progress_trace $trace
     */
    public function before_preview(array $config, \progress_trace $trace): void {
    }

    /**
     * Prepare data before sync of cohort, called for all syncs including updates of single users.
     *
     * For example external database rows are fetched here before full sync.
     *
     * @param array $config
     * @param int|null $userid user of single user sync, null means full sync
     * @param \progress_trace $trace
     * @throws rule_not_ready_exception to skip the sync without marking rules as broken
     * @throws broken_rules_exception if the rule cannot be evaluated
     */
    public function before_sync(array $config, ?int $userid, \progress_trace $trace): void {
    }

    /**
     * Convert submitted form data to rule configuration.
     *
     * @param stdClass $data
     * @return array
     */
    public function get_config(stdClass $data): array {
        return [];
    }

    /**
     * Validate configured ids of records used by rule, such as cohorts or courses.
     *
     * @param mixed $ids
     * @param string $table database table of records
     * @param string $missingerror lang string of error for missing records
     * @return int[] existing record ids
     * @throws broken_rules_exception for invalid configuration or missing records
     */
    final protected static function validate_record_ids(mixed $ids, string $table, string $missingerror): array {
        global $DB;

        if (!is_array($ids) || !$ids) {
            throw new broken_rules_exception('error_rule_invalidconfig');
        }
        foreach ($ids as $id) {
            if (!is_int($id) || $id <= 0) {
                throw new broken_rules_exception('error_rule_invalidconfig');
            }
        }
        $ids = array_values(array_unique($ids));
        [$insql, $params] = $DB->get_in_or_equal($ids);
        $existing = $DB->get_fieldset_select($table, 'id', "id $insql", $params);
        $missing = array_diff($ids, array_map('intval', $existing));
        if ($missing) {
            throw new broken_rules_exception($missingerror, implode(', ', $missing));
        }
        return $ids;
    }

    /**
     * Format cohort names for rule descriptions.
     *
     * @param mixed $cohortids
     * @return string[] html names, missing cohorts are included too
     */
    final protected static function format_cohort_names(mixed $cohortids): array {
        if (!is_array($cohortids)) {
            return [];
        }
        $cohortids = array_filter($cohortids, fn($id) => is_int($id) && $id > 0);
        $names = \tool_mulib\local\cohort_util::format_cohort_names($cohortids, false);
        $result = [];
        foreach ($cohortids as $cohortid) {
            $result[] = $names[$cohortid] ?? get_string('rule_cohortmissing', 'tool_mucohort', $cohortid);
        }
        return $result;
    }

    /**
     * Format course names for rule descriptions.
     *
     * @param mixed $courseids
     * @return string[] html names, missing courses are included too
     */
    final protected static function format_course_names(mixed $courseids): array {
        global $DB;

        if (!is_array($courseids)) {
            return [];
        }
        $courseids = array_values(array_filter($courseids, fn($id) => is_int($id) && $id > 0));
        $courses = $courseids ? $DB->get_records_list('course', 'id', $courseids, '', 'id, fullname') : [];
        $result = [];
        foreach ($courseids as $courseid) {
            if (isset($courses[$courseid])) {
                $context = \core\context\course::instance($courseid);
                $result[] = format_string($courses[$courseid]->fullname, true, ['context' => $context]);
            } else {
                $result[] = get_string('rule_coursemissing', 'tool_mucohort', $courseid);
            }
        }
        return $result;
    }

    /**
     * Format program names for rule descriptions.
     *
     * @param mixed $programids
     * @return string[] html names, missing programs are included too
     */
    final protected static function format_program_names(mixed $programids): array {
        global $DB;

        if (!is_array($programids)) {
            return [];
        }
        $programids = array_values(array_filter($programids, fn($id) => is_int($id) && $id > 0));
        $programs = [];
        if ($programids) {
            $programs = $DB->get_records_list('tool_muprog_program', 'id', $programids, '', 'id, fullname, contextid');
        }
        $result = [];
        foreach ($programids as $programid) {
            if (isset($programs[$programid])) {
                $context = \core\context::instance_by_id($programs[$programid]->contextid);
                $result[] = format_string($programs[$programid]->fullname, true, ['context' => $context]);
            } else {
                $result[] = get_string('rule_programmissing', 'tool_mucohort', $programid);
            }
        }
        return $result;
    }

    /**
     * Format certification names for rule descriptions.
     *
     * @param mixed $certificationids
     * @return string[] html names, missing certifications are included too
     */
    final protected static function format_certification_names(mixed $certificationids): array {
        global $DB;

        if (!is_array($certificationids)) {
            return [];
        }
        $certificationids = array_values(array_filter($certificationids, fn($id) => is_int($id) && $id > 0));
        $certifications = [];
        if ($certificationids) {
            $fields = 'id, fullname, contextid';
            $certifications = $DB->get_records_list('tool_mucertify_certification', 'id', $certificationids, '', $fields);
        }
        $result = [];
        foreach ($certificationids as $certificationid) {
            if (isset($certifications[$certificationid])) {
                $context = \core\context::instance_by_id($certifications[$certificationid]->contextid);
                $result[] = format_string($certifications[$certificationid]->fullname, true, ['context' => $context]);
            } else {
                $result[] = get_string('rule_certificationmissing', 'tool_mucohort', $certificationid);
            }
        }
        return $result;
    }
}
