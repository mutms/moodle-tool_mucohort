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
use tool_mucohort\local\access;

/**
 * Base for advanced cohorts - curated and rule-based cohorts with component tool_mucohort.
 *
 * Capabilities are meant for oversight, daily operation is delegated via access records.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class advanced extends base {
    #[\Override]
    final public static function create(stdClass $data): static {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $context = self::validate_context((int)$data->contextid);
        $now = time();

        $trans = $DB->start_delegated_transaction();

        $record = new stdClass();
        $record->contextid = $context->id;
        $record->name = $data->name;
        $record->idnumber = self::normalise_idnumber($data->idnumber ?? null);
        $record->description = $data->description ?? '';
        $record->descriptionformat = $data->descriptionformat ?? FORMAT_HTML;
        $record->visible = (int)(bool)($data->visible ?? 1);
        $record->component = 'tool_mucohort';
        $record->theme = $data->theme ?? '';
        $record->timecreated = $now;
        $record->timemodified = $now;
        $cohortid = cohort_add_cohort($record);

        $mucohort = new stdClass();
        $mucohort->cohortid = $cohortid;
        $mucohort->type = static::TYPE;
        $mucohort->candidatecohortid = $data->candidatecohortid ?? null;
        $mucohort->timecreated = $now;
        $mucohort->timemodified = $now;
        $mucohort->id = $DB->insert_record('tool_mucohort', $mucohort);

        static::create_type_records($mucohort, $data);

        $trans->allow_commit();

        /** @var static $result */
        $result = self::fetch((int)$cohortid);
        return $result;
    }

    /**
     * Insert type specific records, called inside a transaction when cohort is created or converted to this type.
     *
     * @param stdClass $mucohort tool_mucohort record
     * @param stdClass $data data from create or convert form
     */
    abstract public static function create_type_records(stdClass $mucohort, stdClass $data): void;

    /**
     * Delete type specific records, called inside a transaction when cohort is converted to another type.
     *
     * @param int $mucohortid
     */
    abstract public static function delete_type_records(int $mucohortid): void;

    #[\Override]
    public function get_update_form_data(): array {
        $current = parent::get_update_form_data();
        $current['candidatecohortid'] = $this->mucohort->candidatecohortid;
        return $current;
    }

    #[\Override]
    public function update(stdClass $data): void {
        global $DB;

        $trans = $DB->start_delegated_transaction();

        parent::update($data);

        $mucohort = $this->get_mucohort();
        if (property_exists($data, 'candidatecohortid')) {
            $mucohort->candidatecohortid = $data->candidatecohortid;
        }
        $mucohort->timemodified = time();
        $DB->update_record('tool_mucohort', $mucohort);

        $trans->allow_commit();

        $this->refresh();
    }

    #[\Override]
    public function move(int $contextid): void {
        parent::move($contextid);
        // Roles in cohort context follow the cohort.
        \tool_mucohort\local\role_sync::cohort_moved($this);
    }

    #[\Override]
    public function delete(): void {
        global $DB;

        $trans = $DB->start_delegated_transaction();
        self::delete_mucohort_data((int)$this->mucohort->id);
        parent::delete();
        $trans->allow_commit();
    }

    #[\Override]
    public function can_view(): bool {
        if (parent::can_view()) {
            return true;
        }
        return access::get_record($this) !== null;
    }

    #[\Override]
    public function can_view_members(): bool {
        if (has_capability('tool/mucohort:viewmembers', $this->get_context())) {
            return true;
        }
        return access::has_flag($this, 'viewmembers');
    }

    #[\Override]
    public function can_manage_access(): bool {
        return has_capability('tool/mucohort:manageaccess', $this->get_context());
    }

    /**
     * Can the current user configure roles of cohort members?
     *
     * Role assignment permissions in the target context are checked when roles are added.
     *
     * @return bool
     */
    public function can_manage_roles(): bool {
        return has_capability('tool/mucohort:manageroles', $this->get_context());
    }

    #[\Override]
    public function can_manage_exceptions(): bool {
        if (has_capability('tool/mucohort:manageexceptions', $this->get_context())) {
            return true;
        }
        return access::has_flag($this, 'manageexceptions');
    }

    /**
     * Returns access record flags supported by this type.
     *
     * @return array flag => name
     */
    public function get_access_flags(): array {
        return [
            'viewmembers' => get_string('access_viewmembers', 'tool_mucohort'),
            'manageexceptions' => get_string('access_manageexceptions', 'tool_mucohort'),
        ];
    }

    /**
     * Returns descriptions of access record flags supported by this type.
     *
     * @return array flag => plain text description
     */
    public function get_access_flag_descriptions(): array {
        $result = [];
        foreach (array_keys($this->get_access_flags()) as $flag) {
            $result[$flag] = get_string('access_' . $flag . '_desc', 'tool_mucohort');
        }
        return $result;
    }

    #[\Override]
    public function add_overview_details(\tool_mulib\output\entity_details $details): void {
        $curator = access::get_curator($this);
        $value = $curator ? s(fullname($curator)) : get_string('curator_none', 'tool_mucohort');
        $details->add(get_string('curator', 'tool_mucohort'), $value);

        $a = (object)[
            'forced' => \tool_mucohort\local\exception::count($this, \tool_mucohort\local\exception::TYPE_FORCED),
            'blocked' => \tool_mucohort\local\exception::count($this, \tool_mucohort\local\exception::TYPE_BLOCKED),
        ];
        $details->add(get_string('tab_exceptions', 'tool_mucohort'), get_string('exceptions_count', 'tool_mucohort', $a));

        $details->add(get_string('candidatecohort', 'tool_mucohort'), self::format_candidate_cohort($this->mucohort, true));
    }

    /**
     * Format candidate cohort for display.
     *
     * @param stdClass $mucohort
     * @param bool $link link to candidate cohort members if the current user may see them
     * @return string html
     */
    public static function format_candidate_cohort(stdClass $mucohort, bool $link = false): string {
        if (!$mucohort->candidatecohortid) {
            return get_string('candidatecohort_none', 'tool_mucohort');
        }
        $candidate = base::fetch((int)$mucohort->candidatecohortid, IGNORE_MISSING);
        if (!$candidate) {
            return get_string('candidatecohort_deleted', 'tool_mucohort');
        }
        $name = $candidate->get_formatted_name();
        if ($link && $candidate->can_view_members()) {
            $url = new \core\url('/admin/tool/mucohort/cohort_members.php', ['id' => $candidate->get_id()]);
            $name = \core\output\html_writer::link($url, $name);
        }
        return $name;
    }
}
