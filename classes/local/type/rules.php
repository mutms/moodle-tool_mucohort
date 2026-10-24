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

namespace tool_mucohort\local\type;

use stdClass;
use tool_mucohort\hook\rule_types;
use tool_mucohort\local\exception;
use tool_mucohort\local\rule;
use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\rule_not_ready_exception;
use tool_mucohort\local\ruleset;
use tool_mulib\local\sql;

/**
 * Rule-based cohort - advanced cohort with membership calculated from rules.
 *
 * Target members = ((all include rules AND NOT any exclude rule) OR forced) AND NOT blocked
 * AND NOT deleted, unconfirmed or guest AND NOT user of another tenant.
 *
 * The candidate cohort limits only additions, it never causes removals.
 * In add-only mode only blocked users and users of other tenants are removed,
 * the mode is a cohort setting, it is not part of rules.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rules extends advanced {
    /** @var string type identifier */
    public const string TYPE = 'rules';

    /** @var stdClass|null tool_mucohort_rules record */
    private ?stdClass $rules = null;

    /** @var rule_types|null available rule types */
    private ?rule_types $ruletypes = null;

    /** @var int sync does not change membership */
    public const int SYNCTYPE_NONE = 0;
    /** @var int sync adds and removes members */
    public const int SYNCTYPE_FULL = 1;
    /** @var int sync only adds members, blocked users and users of other tenants are still removed */
    public const int SYNCTYPE_ADDONLY = 2;

    /** @var string[] tool_mucohort_rules fields enabling instant sync of one user */
    public const array INSTANT_SYNC_FLAGS = ['synconusercreated', 'synconfirstlogin', 'synconlogin'];

    /** @var int high sync priority, synchronised first */
    public const int SYNCPRIORITY_HIGH = 0;
    /** @var int normal sync priority */
    public const int SYNCPRIORITY_NORMAL = 1;
    /** @var int low sync priority, synchronised last */
    public const int SYNCPRIORITY_LOW = 2;

    /** @var string no rules were approved yet, sync is inactive */
    public const string STATE_INACTIVE = 'inactive';
    /** @var string active rules cannot be evaluated, sync is skipped */
    public const string STATE_BROKEN = 'broken';
    /** @var string active rules are valid, but sync does not change membership */
    public const string STATE_DISABLED = 'disabled';
    /** @var string active rules are used for sync */
    public const string STATE_ACTIVE = 'active';

    #[\Override]
    public static function get_create_form_class(): ?string {
        return \tool_mucohort\local\form\rules_cohort_create::class;
    }

    #[\Override]
    public function get_update_form_class(): ?string {
        return \tool_mucohort\local\form\rules_cohort_update::class;
    }

    #[\Override]
    public static function create_type_records(stdClass $mucohort, stdClass $data): void {
        global $DB;
        $record = self::get_default_rules_record((int)$mucohort->id);
        $record->synctype = self::normalise_synctype($data->synctype ?? self::SYNCTYPE_FULL);
        $record->syncinterval = self::normalise_syncinterval($data->syncinterval ?? null);
        $record->syncpriority = self::normalise_syncpriority($data->syncpriority ?? self::SYNCPRIORITY_NORMAL);
        foreach (self::INSTANT_SYNC_FLAGS as $flag) {
            $record->$flag = (int)in_array($flag, (array)($data->instantsync ?? []), true);
        }
        $DB->insert_record('tool_mucohort_rules', $record);
    }

    #[\Override]
    public static function delete_type_records(int $mucohortid): void {
        global $DB;
        $extdbids = $DB->get_fieldset('tool_mucohort_extdb', 'id', ['mucohortid' => $mucohortid]);
        foreach ($extdbids as $extdbid) {
            $DB->delete_records('tool_mucohort_extdb_user', ['extdbid' => $extdbid]);
        }
        $DB->delete_records('tool_mucohort_extdb', ['mucohortid' => $mucohortid]);
        $DB->delete_records('tool_mucohort_sync', ['mucohortid' => $mucohortid]);
        $DB->delete_records('tool_mucohort_rules', ['mucohortid' => $mucohortid]);
    }

    #[\Override]
    public function get_update_form_data(): array {
        $current = parent::get_update_form_data();
        $current['synctype'] = $this->get_synctype();
        $record = $this->get_rules_record();
        $current['syncinterval'] = (int)$record->syncinterval;
        $current['syncpriority'] = (int)$record->syncpriority;
        $current['instantsync'] = array_values(array_filter(self::INSTANT_SYNC_FLAGS, fn($flag) => (bool)$record->$flag));
        return $current;
    }

    #[\Override]
    public function update(stdClass $data): void {
        global $DB;

        $trans = $DB->start_delegated_transaction();

        parent::update($data);

        $record = $this->get_rules_record();
        if (property_exists($data, 'synctype')) {
            $DB->set_field('tool_mucohort_rules', 'synctype', self::normalise_synctype($data->synctype), ['id' => $record->id]);
        }
        if (property_exists($data, 'syncinterval')) {
            $interval = self::normalise_syncinterval($data->syncinterval);
            $DB->set_field('tool_mucohort_rules', 'syncinterval', $interval, ['id' => $record->id]);
        }
        if (property_exists($data, 'syncpriority')) {
            $priority = self::normalise_syncpriority($data->syncpriority);
            $DB->set_field('tool_mucohort_rules', 'syncpriority', $priority, ['id' => $record->id]);
        }
        if (property_exists($data, 'instantsync')) {
            foreach (self::INSTANT_SYNC_FLAGS as $flag) {
                $value = (int)in_array($flag, (array)$data->instantsync, true);
                $DB->set_field('tool_mucohort_rules', $flag, $value, ['id' => $record->id]);
            }
        }

        $trans->allow_commit();

        $this->refresh();
        $this->update_schedule();
    }

    /**
     * Returns how sync changes membership, changes apply from the next sync.
     *
     * @return int SYNCTYPE_NONE, SYNCTYPE_FULL or SYNCTYPE_ADDONLY
     */
    public function get_synctype(): int {
        return self::normalise_synctype($this->get_rules_record()->synctype);
    }

    /**
     * Describe automatic sync schedule.
     *
     * @return string
     */
    public function format_schedule(): string {
        $record = $this->get_rules_record();
        if (!$record->syncinterval) {
            return get_string('rules_syncinterval_manual', 'tool_mucohort');
        }
        $interval = get_string('rules_syncinterval_every', 'tool_mucohort', format_time((int)$record->syncinterval));
        if ($record->timenextsync === null) {
            return $interval;
        }
        $a = (object)[
            'interval' => $interval,
            'next' => userdate((int)$record->timenextsync, get_string('strftimedatetimeshort', 'core_langconfig')),
        ];
        return get_string('rules_syncinterval_next', 'tool_mucohort', $a);
    }

    /**
     * Returns sync type names.
     *
     * @return array synctype => name
     */
    public static function get_synctypes(): array {
        return [
            self::SYNCTYPE_FULL => get_string('rules_synctype_1', 'tool_mucohort'),
            self::SYNCTYPE_ADDONLY => get_string('rules_synctype_2', 'tool_mucohort'),
            self::SYNCTYPE_NONE => get_string('rules_synctype_0', 'tool_mucohort'),
        ];
    }

    /**
     * Returns sync type descriptions.
     *
     * @return array synctype => plain text description
     */
    public static function get_synctype_descriptions(): array {
        $result = [];
        foreach (array_keys(self::get_synctypes()) as $synctype) {
            $result[$synctype] = get_string('rules_synctype_' . $synctype . '_desc', 'tool_mucohort');
        }
        return $result;
    }

    /**
     * Normalise sync type, unknown values mean no membership changes.
     *
     * @param int|string|null $synctype
     * @return int
     */
    private static function normalise_synctype(int|string|null $synctype): int {
        $synctype = (int)$synctype;
        if ($synctype === self::SYNCTYPE_FULL || $synctype === self::SYNCTYPE_ADDONLY) {
            return $synctype;
        }
        return self::SYNCTYPE_NONE;
    }

    /**
     * Returns names of instant sync options.
     *
     * @return array flag => name
     */
    public static function get_instant_sync_options(): array {
        $result = [];
        foreach (self::INSTANT_SYNC_FLAGS as $flag) {
            $result[$flag] = get_string('rules_' . $flag, 'tool_mucohort');
        }
        return $result;
    }

    /**
     * Returns descriptions of instant sync options.
     *
     * @return array flag => plain text description
     */
    public static function get_instant_sync_descriptions(): array {
        $result = [];
        foreach (self::INSTANT_SYNC_FLAGS as $flag) {
            $result[$flag] = get_string('rules_' . $flag . '_desc', 'tool_mucohort');
        }
        return $result;
    }

    /**
     * Returns sync priority names.
     *
     * @return array priority => name
     */
    public static function get_syncpriorities(): array {
        return [
            self::SYNCPRIORITY_HIGH => get_string('rules_syncpriority_0', 'tool_mucohort'),
            self::SYNCPRIORITY_NORMAL => get_string('rules_syncpriority_1', 'tool_mucohort'),
            self::SYNCPRIORITY_LOW => get_string('rules_syncpriority_2', 'tool_mucohort'),
        ];
    }

    /**
     * Normalise automatic sync interval.
     *
     * @param int|string|null $interval seconds, empty or 0 means manual sync only
     * @return int|null null means manual sync only
     */
    private static function normalise_syncinterval(int|string|null $interval): ?int {
        if ($interval === null || $interval === '' || (int)$interval <= 0) {
            return null;
        }
        // Syncs are queued by a scheduled task, shorter intervals would not work.
        return max(HOURSECS, (int)$interval);
    }

    /**
     * Normalise sync priority.
     *
     * @param int|string|null $priority
     * @return int
     */
    private static function normalise_syncpriority(int|string|null $priority): int {
        $priority = (int)$priority;
        return array_key_exists($priority, self::get_syncpriorities()) ? $priority : self::SYNCPRIORITY_NORMAL;
    }

    /**
     * Recalculate when the next automatic sync is due.
     *
     * Automatic sync is scheduled only for valid approved rules that change membership
     * and have a sync interval, the next sync is due one interval after the latest sync.
     */
    public function update_schedule(): void {
        global $DB;

        $this->rules = null;
        $record = $this->get_rules_record();
        $next = null;
        $scheduled = $record->rulesjson !== null && !$record->broken && $record->syncinterval
            && $this->get_synctype() !== self::SYNCTYPE_NONE;
        if ($scheduled) {
            $last = \tool_mucohort\local\sync::get_last($this);
            $next = $last ? (int)$last->timequeued + (int)$record->syncinterval : time();
        }
        if ($record->timenextsync === null ? $next !== null : (int)$record->timenextsync !== $next) {
            $DB->set_field('tool_mucohort_rules', 'timenextsync', $next, ['id' => $record->id]);
            $this->rules = null;
        }
    }

    /**
     * Returns tool_mucohort_rules record.
     *
     * @return stdClass
     */
    public function get_rules_record(): stdClass {
        global $DB;
        if (!$this->rules) {
            $this->rules = $DB->get_record('tool_mucohort_rules', ['mucohortid' => $this->mucohort->id]) ?: null;
            if (!$this->rules) {
                // Repair missing record, sync stays inactive until rules are approved.
                $record = self::get_default_rules_record((int)$this->mucohort->id);
                $record->id = $DB->insert_record('tool_mucohort_rules', $record);
                $this->rules = $DB->get_record('tool_mucohort_rules', ['id' => $record->id], '*', MUST_EXIST);
            }
        }
        return clone($this->rules);
    }

    #[\Override]
    protected function refresh(): void {
        parent::refresh();
        $this->rules = null;
        $this->ruletypes = null;
    }

    /**
     * Returns active rules.
     *
     * @return ruleset|null null if rules were not approved yet
     * @throws broken_rules_exception if stored rules are not valid
     */
    public function get_active_ruleset(): ?ruleset {
        $json = $this->get_rules_record()->rulesjson;
        if ($json === null) {
            return null;
        }
        return ruleset::from_json($json);
    }

    /**
     * Returns draft rules waiting for approval.
     *
     * @return ruleset|null null if there is no draft
     * @throws broken_rules_exception if stored rules are not valid
     */
    public function get_pending_ruleset(): ?ruleset {
        $json = $this->get_rules_record()->pendingrulesjson;
        if ($json === null) {
            return null;
        }
        return ruleset::from_json($json);
    }

    /**
     * Store draft rules, active rules are not changed.
     *
     * @param ruleset|null $ruleset null means discard the draft
     */
    public function set_pending_ruleset(?ruleset $ruleset): void {
        global $DB;
        $record = $this->get_rules_record();
        if ($ruleset === null && $record->pendingrulesjson === null) {
            return;
        }
        $DB->set_field('tool_mucohort_rules', 'pendingrulesjson', $ruleset?->to_json(), ['id' => $record->id]);
        $this->rules = null;
        if ($ruleset === null) {
            \tool_mucohort\event\rules_draft_discarded::create_from_cohort($this)->trigger();
        } else {
            \tool_mucohort\event\rules_draft_updated::create_from_cohort($this)->trigger();
        }
    }

    /**
     * Start editing of rules - create draft rules as a copy of active rules if there is no draft yet.
     *
     * @return ruleset draft rules
     * @throws broken_rules_exception if stored rules are not valid
     */
    public function start_draft(): ruleset {
        $ruleset = $this->get_pending_ruleset();
        if (!$ruleset) {
            $ruleset = $this->get_active_ruleset() ?? new ruleset();
            $this->set_pending_ruleset($ruleset);
        }
        return $ruleset;
    }

    /**
     * Activate draft rules, membership is changed by the next sync.
     *
     * @param string $hash sha1 of draft rules JSON as previewed, approval is refused if the draft changed meanwhile
     * @throws broken_rules_exception if draft rules are empty or cannot be evaluated
     */
    public function approve_draft(string $hash): void {
        global $DB;

        $record = $this->get_rules_record();
        if ($record->pendingrulesjson === null || sha1($record->pendingrulesjson) !== $hash) {
            throw new \core\exception\moodle_exception('error_rule_changed', 'tool_mucohort');
        }
        $ruleset = ruleset::from_json($record->pendingrulesjson);
        if (!$ruleset->get_rules()) {
            throw new broken_rules_exception('rules_approve_norules');
        }
        if ($this->get_problems($ruleset, false)) {
            throw new broken_rules_exception('rules_approve_broken');
        }

        $update = (object)[
            'id' => $record->id,
            'rulesjson' => $ruleset->to_json(),
            'pendingrulesjson' => null,
            'broken' => 0,
        ];
        $DB->update_record('tool_mucohort_rules', $update);
        $this->rules = null;
        \tool_mucohort\event\rules_approved::create_from_cohort($this)->trigger();
        $this->update_schedule();
    }

    /**
     * Count users matching SQL condition on user table alias "u".
     *
     * @param sql $condition
     * @return int
     */
    public static function count_users(sql $condition): int {
        global $DB;
        $sql = $condition->wrap("SELECT COUNT('x') FROM {user} u WHERE ", '');
        return $DB->count_records_sql($sql->sql, $sql->params);
    }

    /**
     * Can the current user edit and approve rules?
     *
     * @return bool
     */
    public function can_manage_rules(): bool {
        return has_capability('tool/mucohort:manage', $this->get_context());
    }

    /**
     * Returns state of active rules.
     *
     * @return string STATE_INACTIVE, STATE_BROKEN, STATE_DISABLED or STATE_ACTIVE
     */
    public function get_state(): string {
        try {
            $ruleset = $this->get_active_ruleset();
        } catch (broken_rules_exception $ex) {
            return self::STATE_BROKEN;
        }
        if (!$ruleset) {
            return self::STATE_INACTIVE;
        }
        if ($this->get_problems($ruleset, false)) {
            return self::STATE_BROKEN;
        }
        if ($this->get_synctype() === self::SYNCTYPE_NONE) {
            return self::STATE_DISABLED;
        }
        return self::STATE_ACTIVE;
    }

    #[\Override]
    public function add_overview_details(\tool_mulib\output\entity_details $details): void {
        parent::add_overview_details($details);

        $state = $this->get_state();
        $url = new \core\url('/admin/tool/mucohort/cohort_rules.php', ['id' => $this->get_id()]);
        $value = \core\output\html_writer::link($url, get_string('rules_state_' . $state, 'tool_mucohort'));
        $details->add(get_string('rules_state', 'tool_mucohort'), $value);
        $details->add(get_string('rules_synctype', 'tool_mucohort'), self::get_synctypes()[$this->get_synctype()]);
        // Schedule settings do not matter when sync does not change membership.
        if ($this->get_synctype() !== self::SYNCTYPE_NONE) {
            $details->add(get_string('rules_syncinterval', 'tool_mucohort'), $this->format_schedule());
            $record = $this->get_rules_record();
            $instant = [];
            foreach (self::get_instant_sync_options() as $flag => $name) {
                if ($record->$flag) {
                    $instant[] = $name;
                }
            }
            $value = $instant ? implode(', ', $instant) : get_string('rules_instantsync_none', 'tool_mucohort');
            $details->add(get_string('rules_instantsync', 'tool_mucohort'), $value);
        }
        $url = new \core\url('/admin/tool/mucohort/cohort_sync.php', ['id' => $this->get_id()]);
        $summary = \core\output\html_writer::link($url, \tool_mucohort\local\sync::get_summary($this));
        $details->add(get_string('sync_last', 'tool_mucohort'), $summary);
        if ($this->get_rules_record()->pendingrulesjson !== null) {
            $url = new \core\url('/admin/tool/mucohort/cohort_draft.php', ['id' => $this->get_id()]);
            $value = \core\output\html_writer::link($url, get_string('rules_draft_pending', 'tool_mucohort'));
            $details->add(get_string('rules_draft', 'tool_mucohort'), $value);
        }
    }

    /**
     * Returns rule types available in this cohort.
     *
     * @return rule_types
     */
    public function get_rule_types(): rule_types {
        if (!$this->ruletypes) {
            $this->ruletypes = self::create_rule_types($this->get_cohort(), $this->get_mucohort());
        }
        return $this->ruletypes;
    }

    /**
     * Collect rule types available in a cohort.
     *
     * @param stdClass $cohort cohort record, id may be null for unsaved cohorts
     * @param stdClass $mucohort tool_mucohort record, id may be null for unsaved cohorts
     * @return rule_types
     */
    public static function create_rule_types(stdClass $cohort, stdClass $mucohort): rule_types {
        $hook = new rule_types($cohort, $mucohort);
        $hook->register('tool_mucohort', 'allusers', rule\include_all_users::class);
        $hook->register('tool_mucohort', 'suspended', rule\include_suspended::class);
        $hook->register('tool_mucohort', 'suspended', rule\exclude_suspended::class);
        $hook->register('tool_mucohort', 'userfield', rule\include_user_field::class);
        $hook->register('tool_mucohort', 'userfield', rule\exclude_user_field::class);
        $hook->register('tool_mucohort', 'profilefield', rule\include_profile_field::class);
        $hook->register('tool_mucohort', 'profilefield', rule\exclude_profile_field::class);
        $hook->register('tool_mucohort', 'extdb', rule\include_extdb::class);
        $hook->register('tool_mucohort', 'extdb', rule\exclude_extdb::class);
        $hook->register('tool_mucohort', 'cohort', rule\include_cohort::class);
        $hook->register('tool_mucohort', 'cohort', rule\exclude_cohort::class);
        $hook->register('tool_mucohort', 'courseenrolment', rule\include_course_enrolment::class);
        $hook->register('tool_mucohort', 'courseenrolment', rule\exclude_course_enrolment::class);
        $hook->register('tool_mucohort', 'coursecompletion', rule\include_course_completion::class);
        $hook->register('tool_mucohort', 'coursecompletion', rule\exclude_course_completion::class);
        if (\tool_mulib\local\mulib::is_muprog_available()) {
            $hook->register('tool_mucohort', 'programcompletion', rule\include_program_completion::class);
            $hook->register('tool_mucohort', 'programcompletion', rule\exclude_program_completion::class);
        }
        if (\tool_mulib\local\mulib::is_mucertify_available()) {
            $hook->register('tool_mucohort', 'certification', rule\include_certification::class);
            $hook->register('tool_mucohort', 'certification', rule\exclude_certification::class);
        }
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        return $hook;
    }

    /**
     * Returns rule type instance.
     *
     * @param array $rule rule data from ruleset
     * @return rule\base
     * @throws broken_rules_exception if rule type is not available
     */
    public function get_rule_instance(array $rule): rule\base {
        $classname = $this->get_rule_types()->get_class($rule['kind'], $rule['type']);
        if (!$classname) {
            throw new broken_rules_exception('error_rule_unknowntype', $rule['type']);
        }
        return new $classname($this->get_cohort(), $this->get_mucohort());
    }

    /**
     * Returns description of rule.
     *
     * @param array $rule rule data from ruleset
     * @return string html
     */
    public function get_rule_description(array $rule): string {
        try {
            return $this->get_rule_instance($rule)->get_description($rule['config']);
        } catch (broken_rules_exception $ex) {
            return clean_string($rule['type']);
        }
    }

    /**
     * Let rules prepare their data before preview.
     *
     * Problems do not stop the preview, they are reported later by evaluation of rules.
     *
     * @param ruleset $ruleset
     */
    public function before_preview(ruleset $ruleset): void {
        foreach ($ruleset->get_rules() as $rule) {
            try {
                $this->get_rule_instance($rule)->before_preview($rule['config'], new \null_progress_trace());
            } catch (\Throwable $ex) {
                // Rule evaluation explains what is wrong.
                continue;
            }
        }
    }

    /**
     * Let rules prepare their data before sync, rules may stop the sync.
     *
     * @param ruleset $ruleset
     * @param int|null $userid user of single user sync, null means full sync
     * @param \progress_trace $trace
     * @throws rule_not_ready_exception if sync should be skipped, rules are not broken
     * @throws broken_rules_exception if rules cannot be evaluated
     */
    public function before_sync(ruleset $ruleset, ?int $userid, \progress_trace $trace): void {
        foreach ($ruleset->get_rules() as $rule) {
            $this->get_rule_instance($rule)->before_sync($rule['config'], $userid, $trace);
        }
    }

    /**
     * Returns problems that prevent evaluation of rules.
     *
     * @param ruleset $ruleset
     * @param bool $includenotready false means rules waiting for cached data are not problems
     * @return array uuid => problem description
     */
    public function get_problems(ruleset $ruleset, bool $includenotready = true): array {
        $problems = [];
        foreach ($ruleset->get_rules() as $uuid => $rule) {
            try {
                $this->get_rule_instance($rule)->get_sql($rule['config'], null);
            } catch (\tool_mucohort\local\rule_not_ready_exception $ex) {
                if ($includenotready) {
                    $problems[$uuid] = $ex->getMessage();
                }
            } catch (broken_rules_exception $ex) {
                $problems[$uuid] = $ex->getMessage();
            }
        }
        return $problems;
    }

    /**
     * SQL condition on user table alias "u" for users matched by one rule.
     *
     * Deleted, unconfirmed, guest and other tenant users are never matched,
     * exceptions and add-only mode are ignored.
     *
     * @param ruleset $ruleset
     * @param string $uuid
     * @param int|null $userid
     * @return sql
     * @throws broken_rules_exception if rule cannot be evaluated
     */
    public function get_rule_match_sql(ruleset $ruleset, string $uuid, ?int $userid = null): sql {
        $rule = $ruleset->get_rule($uuid);
        if (!$rule) {
            throw new \core\exception\coding_exception('Unknown rule');
        }
        $conditions = [];
        if ($userid !== null) {
            $conditions[] = new sql('u.id = ?', [$userid]);
        }
        $conditions[] = $this->get_user_sql();
        $conditions[] = $this->get_single_rule_sql($uuid, $rule, $userid);
        return sql::join(' AND ', $conditions);
    }

    /**
     * SQL condition on user table alias "u" for users of candidate cohort that may become members.
     *
     * @return sql|null null if there is no candidate cohort
     */
    public function get_candidate_match_sql(): ?sql {
        if (!$this->mucohort->candidatecohortid) {
            return null;
        }
        $candidate = new sql(
            "EXISTS (SELECT 'x' FROM {cohort_members} ccm WHERE ccm.cohortid = ? AND ccm.userid = u.id)",
            [$this->mucohort->candidatecohortid]
        );
        return sql::join(' AND ', [$this->get_user_sql(), $candidate]);
    }

    /**
     * SQL condition on user table alias "u" for users that should be cohort members.
     *
     * Add-only mode and the candidate cohort are not considered here.
     *
     * @param ruleset $ruleset
     * @param int|null $userid restrict result to one user
     * @return sql
     * @throws broken_rules_exception if rules cannot be evaluated
     */
    public function get_target_sql(ruleset $ruleset, ?int $userid = null): sql {
        $includes = [];
        foreach ($ruleset->get_rules(rule\base::KIND_INCLUDE) as $uuid => $rule) {
            $includes[] = $this->get_single_rule_sql($uuid, $rule, $userid);
        }
        $excludes = [];
        foreach ($ruleset->get_rules(rule\base::KIND_EXCLUDE) as $uuid => $rule) {
            $excludes[] = $this->get_single_rule_sql($uuid, $rule, $userid);
        }

        if ($includes) {
            $matched = sql::join("\n AND ", $includes);
            if ($excludes) {
                $matched = sql::join("\n AND ", [$matched, sql::join("\n OR ", $excludes)->wrap('NOT (', ')')]);
            }
        } else {
            // No include rules means nobody.
            $matched = new sql('1=2');
        }

        $conditions = [];
        if ($userid !== null) {
            $conditions[] = new sql('u.id = ?', [$userid]);
        }
        $conditions[] = $this->get_user_sql();
        $conditions[] = $this->get_exception_sql(exception::TYPE_BLOCKED)->wrap('NOT ', '');
        $conditions[] = sql::join("\n OR ", [$this->get_exception_sql(exception::TYPE_FORCED), $matched->wrap('(', ')')])
            ->wrap('(', ')');
        return sql::join("\n AND ", $conditions);
    }

    /**
     * SQL condition on user table alias "u" for users that should be added to the cohort.
     *
     * @param ruleset $ruleset
     * @param int|null $userid restrict result to one user
     * @return sql
     * @throws broken_rules_exception if rules cannot be evaluated
     */
    public function get_additions_sql(ruleset $ruleset, ?int $userid = null): sql {
        $conditions = [];
        $conditions[] = $this->get_member_sql()->wrap('NOT ', '');
        $conditions[] = $this->get_target_sql($ruleset, $userid);
        if ($this->mucohort->candidatecohortid) {
            // Candidate cohort limits only additions, forced users are exempt.
            $candidate = new sql(
                "EXISTS (SELECT 'x' FROM {cohort_members} ccm WHERE ccm.cohortid = ? AND ccm.userid = u.id)",
                [$this->mucohort->candidatecohortid]
            );
            $conditions[] = sql::join(' OR ', [$candidate, $this->get_exception_sql(exception::TYPE_FORCED)])->wrap('(', ')');
        }
        return sql::join("\n AND ", $conditions);
    }

    /**
     * SQL condition on user table alias "u" for members that should be removed from the cohort.
     *
     * @param ruleset $ruleset
     * @param int|null $userid restrict result to one user
     * @param int|null $synctype null means the cohort setting, SYNCTYPE_FULL previews removals of add-only cohorts
     * @return sql
     * @throws broken_rules_exception if rules cannot be evaluated
     */
    public function get_removals_sql(ruleset $ruleset, ?int $userid = null, ?int $synctype = null): sql {
        $conditions = [];
        if ($userid !== null) {
            $conditions[] = new sql('u.id = ?', [$userid]);
        }
        $conditions[] = $this->get_member_sql();
        if (($synctype ?? $this->get_synctype()) === self::SYNCTYPE_ADDONLY) {
            // Only explicit decisions and tenant separation remove members.
            $removed = [$this->get_exception_sql(exception::TYPE_BLOCKED)];
            $tenant = $this->get_tenant_sql();
            if ($tenant) {
                $removed[] = $tenant->wrap('NOT ', '');
            }
            $conditions[] = sql::join(' OR ', $removed)->wrap('(', ')');
        } else {
            $conditions[] = $this->get_target_sql($ruleset, $userid)->wrap('NOT (', ')');
        }
        return sql::join("\n AND ", $conditions);
    }

    /**
     * SQL condition for one rule with a line comment referencing the rule.
     *
     * @param string $uuid
     * @param array $rule
     * @param int|null $userid
     * @return sql
     */
    private function get_single_rule_sql(string $uuid, array $rule, ?int $userid): sql {
        $sql = $this->get_rule_instance($rule)->get_sql($rule['config'], $userid);
        return $sql->wrap("(\n-- rule $uuid\n", "\n)");
    }

    /**
     * SQL condition for users that may be cohort members.
     *
     * @return sql
     */
    private function get_user_sql(): sql {
        global $CFG;
        $conditions = [new sql('u.deleted = 0 AND u.confirmed = 1 AND u.id <> ?', [$CFG->siteguest])];
        $tenant = $this->get_tenant_sql();
        if ($tenant) {
            $conditions[] = $tenant;
        }
        return sql::join(' AND ', $conditions);
    }

    /**
     * SQL condition excluding users of other tenants.
     *
     * @return sql|null null if there is no tenant restriction
     */
    private function get_tenant_sql(): ?sql {
        if (!\tool_mulib\local\mulib::is_mutenancy_active()) {
            return null;
        }
        $tenantid = $this->get_context()->tenantid;
        if (!$tenantid) {
            return null;
        }
        return new sql('(u.tenantid IS NULL OR u.tenantid = ?)', [$tenantid]);
    }

    /**
     * SQL condition for users with an exception.
     *
     * @param int $type
     * @return sql
     */
    private function get_exception_sql(int $type): sql {
        $alias = ($type === exception::TYPE_FORCED) ? 'mfe' : 'mbe';
        return new sql(
            "EXISTS (SELECT 'x' FROM {tool_mucohort_exception} $alias
                      WHERE $alias.mucohortid = ? AND $alias.userid = u.id AND $alias.type = ?)",
            [$this->mucohort->id, $type]
        );
    }

    /**
     * SQL condition for current cohort members.
     *
     * @return sql
     */
    private function get_member_sql(): sql {
        return new sql(
            "EXISTS (SELECT 'x' FROM {cohort_members} mcm WHERE mcm.cohortid = ? AND mcm.userid = u.id)",
            [$this->cohort->id]
        );
    }

    /**
     * Returns default tool_mucohort_rules record, sync is inactive until rules are approved.
     *
     * @param int $mucohortid
     * @return stdClass
     */
    private static function get_default_rules_record(int $mucohortid): stdClass {
        return (object)[
            'mucohortid' => $mucohortid,
            'broken' => 0,
            'rulesjson' => null,
            'pendingrulesjson' => null,
            'synctype' => self::SYNCTYPE_FULL,
            'syncinterval' => null,
            'syncpriority' => 1,
            'synconusercreated' => 0,
            'synconfirstlogin' => 0,
            'synconlogin' => 0,
            'timenextsync' => null,
        ];
    }
}
