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

use tool_mucohort\local\type\advanced;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use tool_mucohort\local\type\rules;
use tool_mucohort\local\type\standard;
use stdClass;

/**
 * Conversion of cohorts between types.
 *
 * Conversions never change cohort membership.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class conversion {
    /**
     * Returns types the cohort can be converted to by the current user.
     *
     * @param base $cohort
     * @return array type => class name
     */
    public static function get_target_types(base $cohort): array {
        $result = [];
        foreach (base::get_classes() as $type => $class) {
            if (self::can_convert($cohort, $type)) {
                $result[$type] = $class;
            }
        }
        return $result;
    }

    /**
     * Can the current user convert the cohort to the type?
     *
     * @param base $cohort
     * @param string $type target type
     * @return bool
     */
    public static function can_convert(base $cohort, string $type): bool {
        if (!self::is_supported($cohort->get_type(), $type)) {
            return false;
        }
        /** @var class-string<base> $class */
        $class = base::get_classes()[$type];
        if (!$class::is_enabled()) {
            return false;
        }
        if (!$cohort->can_manage()) {
            return false;
        }
        if ($cohort instanceof rules && sync::get_current($cohort)) {
            return false;
        }
        return has_capability($class::get_manage_capability(), $cohort->get_context());
    }

    /**
     * Is the conversion supported?
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function is_supported(string $from, string $to): bool {
        $types = [standard::TYPE, curated::TYPE, rules::TYPE];
        return $from !== $to && in_array($from, $types, true) && in_array($to, $types, true);
    }

    /**
     * Returns data that would be lost by the conversion.
     *
     * @param base $cohort
     * @param string $type
     * @return array lang string identifier => count, only non-zero counts
     */
    public static function get_losses(base $cohort, string $type): array {
        global $DB;

        $mucohort = $cohort->get_mucohort();
        if (!$mucohort) {
            return [];
        }
        $counts = [];
        if ($type === standard::TYPE) {
            $counts['convert_loss_access'] = $DB->count_records('tool_mucohort_access', ['mucohortid' => $mucohort->id]);
            $counts['convert_loss_exceptions'] = $DB->count_records('tool_mucohort_exception', ['mucohortid' => $mucohort->id]);
            $counts['convert_loss_roles'] = $DB->count_records('tool_mucohort_role', ['mucohortid' => $mucohort->id]);
        }
        if ($cohort instanceof curated) {
            $counts['convert_loss_maxmembers'] = (int)$cohort->get_curated()->maxmembers;
        }
        if ($cohort instanceof rules) {
            $record = $cohort->get_rules_record();
            $rules = 0;
            foreach ([$record->rulesjson, $record->pendingrulesjson] as $json) {
                if ($json !== null) {
                    try {
                        $rules += count(ruleset::from_json($json)->get_rules());
                    } catch (broken_rules_exception $ex) {
                        $rules++;
                    }
                }
            }
            $counts['convert_loss_rules'] = $rules;
            $counts['convert_loss_synchistory'] = $DB->count_records('tool_mucohort_sync', ['mucohortid' => $mucohort->id]);
        }
        return array_filter($counts);
    }

    /**
     * Convert cohort to a different type, membership is not changed.
     *
     * @param base $cohort
     * @param string $type
     * @param stdClass|null $options settings of the new type, such as rule-based synctype,
     *      seedforced = 1 adds current members of a cohort converted to rule-based as forced exceptions
     * @return base converted cohort
     */
    public static function convert(base $cohort, string $type, ?stdClass $options = null): base {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $from = $cohort->get_type();
        if (!self::is_supported($from, $type)) {
            throw new \core\exception\coding_exception("Unsupported cohort conversion from '$from' to '$type'");
        }
        if ($cohort instanceof rules && sync::get_current($cohort)) {
            throw new \core\exception\moodle_exception('sync_already', 'tool_mucohort');
        }
        $options = $options ?? new stdClass();

        $trans = $DB->start_delegated_transaction();

        $now = time();
        if ($type === standard::TYPE) {
            base::delete_mucohort_data((int)$cohort->get_mucohort()->id);
            $component = '';
        } else {
            /** @var class-string<advanced> $class */
            $class = base::get_classes()[$type];
            $mucohort = $cohort->get_mucohort();
            if ($mucohort) {
                /** @var class-string<advanced> $fromclass */
                $fromclass = base::get_classes()[$from];
                $fromclass::delete_type_records((int)$mucohort->id);
                $mucohort->type = $type;
                $mucohort->timemodified = $now;
                $DB->update_record('tool_mucohort', $mucohort);
            } else {
                $mucohort = new stdClass();
                $mucohort->cohortid = $cohort->get_id();
                $mucohort->type = $type;
                $mucohort->candidatecohortid = null;
                $mucohort->timecreated = $now;
                $mucohort->timemodified = $now;
                $mucohort->id = $DB->insert_record('tool_mucohort', $mucohort);
            }
            $class::create_type_records($mucohort, $options);
            $component = 'tool_mucohort';
        }

        if ($cohort->get_cohort()->component !== $component) {
            $record = (object)[
                'id' => $cohort->get_id(),
                'contextid' => $cohort->get_cohort()->contextid,
                'component' => $component,
            ];
            cohort_update_cohort($record);
        }

        $trans->allow_commit();

        $converted = base::fetch($cohort->get_id());
        \tool_mucohort\event\cohort_converted::create_from_cohort($converted, $from)->trigger();

        if ($converted instanceof rules && !empty($options->seedforced)) {
            // Existing members stay members whatever the rules are.
            $userids = $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $converted->get_id()]);
            foreach ($userids as $userid) {
                if (!exception::get_record($converted, (int)$userid)) {
                    exception::set($converted, (int)$userid, exception::TYPE_FORCED, null);
                }
            }
        }

        return $converted;
    }
}
