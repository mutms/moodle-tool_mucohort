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

/**
 * Curated cohort - advanced cohort with membership managed by people.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class curated extends advanced {
    /** @var string type identifier */
    public const string TYPE = 'curated';

    /** @var stdClass|null tool_mucohort_curated record */
    private ?stdClass $curated = null;

    #[\Override]
    public static function get_create_form_class(): ?string {
        return \tool_mucohort\local\form\curated_cohort_create::class;
    }

    #[\Override]
    public static function create_type_records(stdClass $mucohort, stdClass $data): void {
        global $DB;

        $curated = new stdClass();
        $curated->mucohortid = $mucohort->id;
        $curated->maxmembers = self::normalise_maxmembers($data->maxmembers ?? null);
        $DB->insert_record('tool_mucohort_curated', $curated);
    }

    #[\Override]
    public static function delete_type_records(int $mucohortid): void {
        global $DB;
        $DB->delete_records('tool_mucohort_curated', ['mucohortid' => $mucohortid]);
    }

    #[\Override]
    public function get_update_form_class(): ?string {
        return \tool_mucohort\local\form\curated_cohort_update::class;
    }

    #[\Override]
    public function get_update_form_data(): array {
        $current = parent::get_update_form_data();
        $current['maxmembers'] = $this->get_curated()->maxmembers;
        return $current;
    }

    #[\Override]
    public function update(stdClass $data): void {
        global $DB;

        $trans = $DB->start_delegated_transaction();

        parent::update($data);

        if (property_exists($data, 'maxmembers')) {
            $curated = $this->get_curated();
            $curated->maxmembers = self::normalise_maxmembers($data->maxmembers);
            $DB->update_record('tool_mucohort_curated', $curated);
        }

        $trans->allow_commit();

        $this->refresh();
    }

    /**
     * Returns tool_mucohort_curated record.
     *
     * @return stdClass
     */
    public function get_curated(): stdClass {
        global $DB;
        if (!$this->curated) {
            $this->curated = $DB->get_record('tool_mucohort_curated', ['mucohortid' => $this->mucohort->id]) ?: null;
            if (!$this->curated) {
                // Repair missing record, there is nothing to lose.
                $record = (object)['mucohortid' => $this->mucohort->id, 'maxmembers' => null];
                $record->id = $DB->insert_record('tool_mucohort_curated', $record);
                $this->curated = $record;
            }
        }
        return clone($this->curated);
    }

    #[\Override]
    protected function refresh(): void {
        parent::refresh();
        $this->curated = null;
    }

    #[\Override]
    public function get_access_flags(): array {
        return [
            'viewmembers' => get_string('access_viewmembers', 'tool_mucohort'),
            'managemembers' => get_string('access_managemembers', 'tool_mucohort'),
            'manageexceptions' => get_string('access_manageexceptions', 'tool_mucohort'),
        ];
    }

    #[\Override]
    public function get_member_add_form_class(): ?string {
        return \tool_mucohort\local\form\curated_member_add::class;
    }

    #[\Override]
    public function get_member_remove_form_class(): ?string {
        return \tool_mucohort\local\form\member_remove::class;
    }

    /**
     * SQL condition on user table alias "u" for users that may be added as new members.
     *
     * New members must not be members already, must not be blocked, must be in the candidate cohort
     * if set (a deleted candidate cohort blocks all additions) and must not belong to other tenants.
     *
     * @return \tool_mulib\local\sql
     */
    #[\Override]
    public function get_new_member_sql(): \tool_mulib\local\sql {
        $mucohort = $this->mucohort;
        $conditions = [];
        $conditions[] = new \tool_mulib\local\sql(
            "NOT EXISTS (SELECT 'x' FROM {cohort_members} ncm WHERE ncm.cohortid = ? AND ncm.userid = u.id)",
            [$this->cohort->id]
        );
        $conditions[] = new \tool_mulib\local\sql(
            "NOT EXISTS (SELECT 'x' FROM {tool_mucohort_exception} nce
                          WHERE nce.mucohortid = ? AND nce.userid = u.id AND nce.type = ?)",
            [$mucohort->id, \tool_mucohort\local\exception::TYPE_BLOCKED]
        );
        if ($mucohort->candidatecohortid) {
            $conditions[] = new \tool_mulib\local\sql(
                "EXISTS (SELECT 'x' FROM {cohort_members} ccm WHERE ccm.cohortid = ? AND ccm.userid = u.id)",
                [$mucohort->candidatecohortid]
            );
        }
        if (\tool_mulib\local\mulib::is_mutenancy_active()) {
            $tenantid = $this->get_context()->tenantid;
            if ($tenantid) {
                $conditions[] = new \tool_mulib\local\sql("(u.tenantid IS NULL OR u.tenantid = ?)", [$tenantid]);
            }
        }
        return \tool_mulib\local\sql::join(' AND ', $conditions);
    }

    /**
     * Returns how many new members can be added.
     *
     * @return int|null null means no limit
     */
    public function get_remaining_capacity(): ?int {
        global $DB;
        $maxmembers = $this->get_curated()->maxmembers;
        if ($maxmembers === null) {
            return null;
        }
        $count = $DB->count_records('cohort_members', ['cohortid' => $this->cohort->id]);
        return max(0, (int)$maxmembers - $count);
    }

    #[\Override]
    public function add_members(array $userids): void {
        global $DB;

        $userids = array_unique(array_map('intval', $userids));
        if (!$userids) {
            return;
        }
        $capacity = $this->get_remaining_capacity();
        if ($capacity !== null && count($userids) > $capacity) {
            throw new \core\exception\moodle_exception('error_maxmembers', 'tool_mucohort', '', $capacity);
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids);
        $sql = (new \tool_mulib\local\sql(
            "SELECT u.id
               FROM {user} u
              WHERE u.deleted = 0 AND u.confirmed = 1 AND u.id $insql /* allowed */",
            $inparams
        ))->replace_comment('allowed', $this->get_new_member_sql()->wrap('AND (', ')'));
        $allowed = $DB->get_fieldset_sql($sql->sql, $sql->params);
        if (count($allowed) !== count($userids)) {
            throw new \core\exception\moodle_exception('error_membernotallowed', 'tool_mucohort');
        }

        foreach ($allowed as $userid) {
            \tool_mucohort\local\membership_writer::add_member($this->get_id(), (int)$userid);
        }
    }

    #[\Override]
    public function get_removable_member_sql(): \tool_mulib\local\sql {
        // Forced users must not be removed directly.
        return new \tool_mulib\local\sql(
            "NOT EXISTS (SELECT 'x' FROM {tool_mucohort_exception} rce
                          WHERE rce.mucohortid = ? AND rce.userid = u.id AND rce.type = ?)",
            [$this->mucohort->id, \tool_mucohort\local\exception::TYPE_FORCED]
        );
    }

    #[\Override]
    public function remove_member(int $userid): void {
        $exception = \tool_mucohort\local\exception::get_record($this, $userid);
        if ($exception && $exception->type == \tool_mucohort\local\exception::TYPE_FORCED) {
            throw new \core\exception\moodle_exception('error_memberforced', 'tool_mucohort');
        }
        \tool_mucohort\local\membership_writer::remove_member($this->get_id(), $userid);
    }

    #[\Override]
    public function can_manage_members(): bool {
        if (has_capability('tool/mucohort:managemembers', $this->get_context())) {
            return true;
        }
        return \tool_mucohort\local\access::has_flag($this, 'managemembers');
    }

    #[\Override]
    public function add_overview_details(\tool_mulib\output\entity_details $details): void {
        global $OUTPUT;

        parent::add_overview_details($details);
        $details->add(get_string('maxmembers', 'tool_mucohort'), $this->format_maxmembers());

        $count = count($this->get_other_tenant_memberids());
        if ($count) {
            $value = (string)$count;
            if ($this->can_manage_members()) {
                $url = new \core\url('/admin/tool/mucohort/management/members_remove_othertenant.php', ['id' => $this->get_id()]);
                $button = new \tool_mulib\output\muform\dialog\button(
                    $url,
                    get_string('members_remove_othertenant', 'tool_mucohort')
                );
                $button->set_form_size('sm');
                $value .= ' ' . $OUTPUT->render($button);
            }
            $details->add(get_string('othertenantmembers', 'tool_mucohort'), $value);
        }
    }

    /**
     * Returns members that belong to other tenants than the cohort.
     *
     * Users of other tenants are never added, but they stay members when the cohort
     * is moved to a tenant or when users move to another tenant.
     *
     * @return int[]
     */
    public function get_other_tenant_memberids(): array {
        global $DB;

        if (!\tool_mulib\local\mulib::is_mutenancy_active()) {
            return [];
        }
        $tenantid = $this->get_context()->tenantid;
        if (!$tenantid) {
            return [];
        }
        $sql = "SELECT u.id
                  FROM {cohort_members} cm
                  JOIN {user} u ON u.id = cm.userid
                 WHERE cm.cohortid = :cohortid AND u.tenantid IS NOT NULL AND u.tenantid <> :tenantid
              ORDER BY u.id ASC";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['cohortid' => $this->cohort->id, 'tenantid' => $tenantid]));
    }

    /**
     * Remove members that belong to other tenants, including forced users.
     *
     * @return int number of removed members
     */
    public function remove_other_tenant_members(): int {
        $count = 0;
        foreach ($this->get_other_tenant_memberids() as $userid) {
            if (\tool_mucohort\local\membership_writer::remove_member($this->get_id(), $userid)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Format maximum number of members for display.
     *
     * @return string
     */
    public function format_maxmembers(): string {
        $maxmembers = $this->get_curated()->maxmembers;
        if ($maxmembers === null) {
            return get_string('maxmembers_unlimited', 'tool_mucohort');
        }
        if ((int)$maxmembers === 0) {
            return get_string('maxmembers_closed', 'tool_mucohort');
        }
        return (string)$maxmembers;
    }

    /**
     * Normalise maximum number of members, empty value means no limit.
     *
     * @param int|string|null $maxmembers
     * @return int|null
     */
    public static function normalise_maxmembers(int|string|null $maxmembers): ?int {
        if ($maxmembers === null || $maxmembers === '') {
            return null;
        }
        return max(0, (int)$maxmembers);
    }
}
