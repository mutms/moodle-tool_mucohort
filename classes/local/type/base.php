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

use core\context;
use stdClass;

/**
 * Base for cohort types, one instance per cohort.
 *
 * All membership and cohort changes go through the core cohort API,
 * so that core events are triggered and other plugins keep working.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var string type identifier, also stored in tool_mucohort.type for advanced cohorts */
    public const string TYPE = '';

    /**
     * Constructor.
     *
     * @param stdClass $cohort cohort record
     * @param stdClass|null $mucohort tool_mucohort record for advanced cohorts
     */
    final protected function __construct(
        /** @var stdClass cohort record */
        protected stdClass $cohort,
        /** @var stdClass|null tool_mucohort record */
        protected ?stdClass $mucohort
    ) {
    }

    /**
     * Fetch cohort from database.
     *
     * @param int $cohortid
     * @param int $strictness IGNORE_MISSING or MUST_EXIST
     * @return base|null null if cohort does not exist and IGNORE_MISSING used
     */
    final public static function fetch(int $cohortid, int $strictness = MUST_EXIST): ?base {
        global $DB;
        $cohort = $DB->get_record('cohort', ['id' => $cohortid], '*', $strictness);
        if (!$cohort) {
            return null;
        }
        return self::from_record($cohort);
    }

    /**
     * Create type instance from cohort record.
     *
     * @param stdClass $cohort full cohort record
     * @return base
     */
    final public static function from_record(stdClass $cohort): base {
        global $DB;

        if ($cohort->component === '' || $cohort->component === null) {
            return new standard($cohort, null);
        }
        if ($cohort->component === 'tool_mucohort') {
            $mucohort = $DB->get_record('tool_mucohort', ['cohortid' => $cohort->id]);
            if ($mucohort) {
                if ($mucohort->type === curated::TYPE) {
                    return new curated($cohort, $mucohort);
                }
                if ($mucohort->type === rules::TYPE) {
                    return new rules($cohort, $mucohort);
                }
            }
        }
        // Cohorts of other plugins and invalid advanced cohorts.
        return new plugin($cohort, null);
    }

    /**
     * Returns all type classes that can be created or converted to.
     *
     * @return array type => class name
     */
    final public static function get_classes(): array {
        return [
            standard::TYPE => standard::class,
            curated::TYPE => curated::class,
            rules::TYPE => rules::class,
        ];
    }

    /**
     * Returns human readable name of this type.
     *
     * @return string plain text
     */
    public static function get_name(): string {
        return get_string('type_' . static::TYPE, 'tool_mucohort');
    }

    /**
     * Capability for creating, updating, moving and deleting of cohorts of this type.
     *
     * @return string
     */
    public static function get_manage_capability(): string {
        return 'tool/mucohort:manage';
    }

    /**
     * May the current user create cohorts of this type in the context?
     *
     * @param context $context system or category context
     * @return bool
     */
    public static function can_create(context $context): bool {
        if (!static::get_create_form_class()) {
            return false;
        }
        return has_capability(static::get_manage_capability(), $context);
    }

    /**
     * Returns form class for creating of new cohorts of this type.
     *
     * The form must include 'description' editor and 'customfields' elements,
     * they are saved by the create handler.
     *
     * @return string|null null if cohorts of this type cannot be created
     */
    public static function get_create_form_class(): ?string {
        return null;
    }

    /**
     * Create new cohort of this type.
     *
     * @param stdClass $data data from create form, custom fields are not saved here
     * @return base
     */
    public static function create(stdClass $data): base {
        throw new \core\exception\coding_exception('Cohorts of this type cannot be created');
    }

    /**
     * Returns short description of this type.
     *
     * @return string plain text
     */
    public static function get_description(): string {
        return get_string('type_' . static::TYPE . '_desc', 'tool_mucohort');
    }

    /**
     * Is the cohort type enabled for new cohorts and conversions?
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        $config = get_config('tool_mucohort', 'enabledtypes');
        if ($config === false) {
            // Not configured yet, all types are enabled by default.
            return true;
        }
        return in_array(static::TYPE, explode(',', $config), true);
    }

    /**
     * Returns cohort id.
     *
     * @return int
     */
    final public function get_id(): int {
        return (int)$this->cohort->id;
    }

    /**
     * Returns cohort record.
     *
     * @return stdClass
     */
    final public function get_cohort(): stdClass {
        return clone($this->cohort);
    }

    /**
     * Returns tool_mucohort record of advanced cohorts.
     *
     * @return stdClass|null
     */
    final public function get_mucohort(): ?stdClass {
        return $this->mucohort ? clone($this->mucohort) : null;
    }

    /**
     * Returns cohort type identifier.
     *
     * @return string
     */
    final public function get_type(): string {
        return static::TYPE;
    }

    /**
     * Returns human readable type name of this cohort.
     *
     * @return string plain text
     */
    public function get_type_name(): string {
        return static::get_name();
    }

    /**
     * Returns cohort context.
     *
     * Cohorts with missing context are managed from the system context,
     * so that only users with system level permissions may see and fix them.
     *
     * @return context
     */
    final public function get_context(): context {
        $context = context::instance_by_id($this->cohort->contextid, IGNORE_MISSING);
        if (!$context) {
            return \core\context\system::instance();
        }
        return $context;
    }

    /**
     * Is the cohort context missing?
     *
     * @return bool
     */
    final public function is_context_missing(): bool {
        return !context::instance_by_id($this->cohort->contextid, IGNORE_MISSING);
    }

    /**
     * Returns formatted cohort name.
     *
     * @return string html
     */
    final public function get_formatted_name(): string {
        return format_string($this->cohort->name, true, ['context' => $this->get_context()]);
    }

    /**
     * Can the current user see the cohort in cohort management?
     *
     * @return bool
     */
    public function can_view(): bool {
        return has_any_capability(['moodle/cohort:view', 'moodle/cohort:manage'], $this->get_context());
    }

    /**
     * Can the current user update, move or delete the cohort?
     *
     * @return bool
     */
    public function can_manage(): bool {
        return has_capability(static::get_manage_capability(), $this->get_context());
    }

    /**
     * Can the current user move the cohort to another context?
     *
     * @return bool
     */
    public function can_move(): bool {
        return $this->can_manage();
    }

    /**
     * Can the current user delete the cohort?
     *
     * @return bool
     */
    public function can_delete(): bool {
        return $this->can_manage();
    }

    /**
     * Can the current user list cohort members?
     *
     * @return bool
     */
    abstract public function can_view_members(): bool;

    /**
     * Can the current user manage access records and the curator?
     *
     * @return bool
     */
    public function can_manage_access(): bool {
        return false;
    }

    /**
     * Can the current user manage forced and blocked users?
     *
     * @return bool
     */
    public function can_manage_exceptions(): bool {
        return false;
    }

    /**
     * Can the current user add and remove cohort members directly?
     *
     * @return bool
     */
    public function can_manage_members(): bool {
        return false;
    }

    /**
     * Returns form class for updating of cohort settings.
     *
     * The form must include 'description' editor and 'customfields' elements,
     * they are saved by the update handler.
     *
     * @return string|null null if cohort cannot be updated
     */
    public function get_update_form_class(): ?string {
        return null;
    }

    /**
     * Returns form class for adding of members.
     *
     * @return string|null null if members cannot be added directly
     */
    public function get_member_add_form_class(): ?string {
        return null;
    }

    /**
     * Returns form class for member removal confirmation.
     *
     * @return string|null null if members cannot be removed directly
     */
    public function get_member_remove_form_class(): ?string {
        return null;
    }

    /**
     * SQL condition on user table alias "u" for users that may be added as new members.
     *
     * @return \tool_mulib\local\sql
     */
    public function get_new_member_sql(): \tool_mulib\local\sql {
        return new \tool_mulib\local\sql(
            "NOT EXISTS (SELECT 'x' FROM {cohort_members} ncm WHERE ncm.cohortid = ? AND ncm.userid = u.id)",
            [$this->cohort->id]
        );
    }

    /**
     * SQL condition on user table alias "u" for members that may be removed directly.
     *
     * @return \tool_mulib\local\sql
     */
    public function get_removable_member_sql(): \tool_mulib\local\sql {
        return new \tool_mulib\local\sql('1=1');
    }

    /**
     * Add members, permissions and restrictions are validated by the add form.
     *
     * @param int[] $userids
     */
    public function add_members(array $userids): void {
        throw new \core\exception\coding_exception('Members cannot be added to this cohort type');
    }

    /**
     * Remove member, permissions are validated by the remove form handler.
     *
     * @param int $userid
     */
    public function remove_member(int $userid): void {
        throw new \core\exception\coding_exception('Members cannot be removed from this cohort type');
    }

    /**
     * Returns current data for update form.
     *
     * @return array
     */
    public function get_update_form_data(): array {
        $current = (array)$this->get_cohort();
        $current['idnumber'] = (string)$current['idnumber'];
        return $current;
    }

    /**
     * Update cohort settings, the context is changed only via move().
     *
     * @param stdClass $data cohort data, custom fields are not saved here
     */
    public function update(stdClass $data): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $record = new stdClass();
        $record->id = $this->cohort->id;
        $record->contextid = $this->cohort->contextid;
        foreach (['name', 'description', 'descriptionformat', 'visible', 'theme'] as $field) {
            if (property_exists($data, $field)) {
                $record->$field = $data->$field;
            }
        }
        if (property_exists($data, 'idnumber')) {
            $record->idnumber = self::normalise_idnumber($data->idnumber);
        }
        if (isset($record->visible)) {
            $record->visible = (int)(bool)$record->visible;
        }

        cohort_update_cohort($record);
        $this->refresh();
    }

    /**
     * Move cohort to a different context.
     *
     * @param int $contextid system or category context
     */
    public function move(int $contextid): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $context = self::validate_context($contextid);
        if ($this->cohort->contextid == $context->id) {
            return;
        }

        get_file_storage()->move_area_files_to_new_context(
            $this->cohort->contextid,
            $context->id,
            'cohort',
            'description',
            $this->cohort->id
        );
        // Custom field data is stored in the cohort context.
        \tool_mulib\local\customfield_util::change_instances_context(
            'core_cohort',
            'cohort',
            0,
            new \tool_mulib\local\sql('SELECT id FROM {cohort} WHERE id = ?', [$this->cohort->id]),
            $context
        );

        $record = (object)['id' => $this->cohort->id, 'contextid' => $context->id];
        cohort_update_cohort($record);
        $this->refresh();
    }

    /**
     * Delete cohort, the instance must not be used afterwards.
     */
    public function delete(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        cohort_delete_cohort($this->cohort);
    }

    /**
     * Add type specific details to the cohort overview.
     *
     * @param \tool_mulib\output\entity_details $details
     */
    public function add_overview_details(\tool_mulib\output\entity_details $details): void {
    }

    /**
     * Delete all data of advanced cohort except the cohort record itself.
     *
     * Role assignments of role sync are removed too.
     *
     * @param int $mucohortid
     */
    final public static function delete_mucohort_data(int $mucohortid): void {
        global $DB;

        $extdbids = $DB->get_fieldset('tool_mucohort_extdb', 'id', ['mucohortid' => $mucohortid]);
        foreach ($extdbids as $extdbid) {
            $DB->delete_records('tool_mucohort_extdb_user', ['extdbid' => $extdbid]);
        }
        $DB->delete_records('tool_mucohort_extdb', ['mucohortid' => $mucohortid]);
        $DB->delete_records('tool_mucohort_sync', ['mucohortid' => $mucohortid]);
        $DB->delete_records('tool_mucohort_rules', ['mucohortid' => $mucohortid]);
        $DB->delete_records('tool_mucohort_curated', ['mucohortid' => $mucohortid]);
        $DB->delete_records('tool_mucohort_access', ['mucohortid' => $mucohortid]);
        $DB->delete_records('tool_mucohort_exception', ['mucohortid' => $mucohortid]);
        \tool_mucohort\local\role_sync::remove_all($mucohortid);
        $DB->delete_records('tool_mucohort', ['id' => $mucohortid]);
    }

    /**
     * Reload records from database.
     */
    protected function refresh(): void {
        global $DB;
        $this->cohort = $DB->get_record('cohort', ['id' => $this->cohort->id], '*', MUST_EXIST);
        if ($this->mucohort) {
            $this->mucohort = $DB->get_record('tool_mucohort', ['id' => $this->mucohort->id], '*', MUST_EXIST);
        }
    }

    /**
     * Validate cohort context.
     *
     * @param int $contextid
     * @return context system or category context
     */
    final protected static function validate_context(int $contextid): context {
        $context = context::instance_by_id($contextid);
        if ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSECAT) {
            throw new \core\exception\invalid_parameter_exception('Invalid cohort context');
        }
        return $context;
    }

    /**
     * Normalise cohort idnumber, empty value means NULL.
     *
     * @param string|null $idnumber
     * @return string|null
     */
    final public static function normalise_idnumber(?string $idnumber): ?string {
        if ($idnumber === null || trim($idnumber) === '') {
            return null;
        }
        return $idnumber;
    }

    /**
     * Is the idnumber already used by another cohort?
     *
     * @param string $idnumber
     * @param int|null $cohortid cohort that is being updated
     * @return bool
     */
    final public static function is_idnumber_used(string $idnumber, ?int $cohortid): bool {
        global $DB;
        $params = ['idnumber' => $idnumber];
        $select = "idnumber = :idnumber";
        if ($cohortid) {
            $select .= " AND id <> :id";
            $params['id'] = $cohortid;
        }
        return $DB->record_exists_select('cohort', $select, $params);
    }
}
