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

namespace tool_mucohort\local\wizard\bulk_changes;

use core\url;
use stdClass;
use tool_mucohort\local\bulk_changes;
use tool_mucohort\local\management;
use tool_mucohort\local\type\base;
use tool_mulib\muform\form;

/**
 * Bulk changes of members or exceptions, one change is applied to a pasted or uploaded list of users.
 *
 * Three stages: source, identifier column, options with a dry run. The parsed rows and the answers
 * live in the wizard state, the source stage is locked once the data were read.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wizard extends \tool_mulib\muform\wizard_page {
    /** @var base cohort */
    private base $cohort;
    /** @var array actions the user may use, action => name */
    private array $actions;

    /**
     * Load the cohort and check access.
     *
     * @param int $cohortid
     * @param string $action action preselected when the wizard starts
     */
    public function __construct(
        int $cohortid,
        /** @var string action preselected when the wizard starts */
        private string $action
    ) {
        $this->cohort = base::fetch($cohortid);
        $this->actions = bulk_changes::get_actions($this->cohort);
        if (!$this->actions) {
            throw new \required_capability_exception($this->cohort->get_context(), 'moodle/cohort:assign', 'nopermissions', '');
        }
    }

    #[\Override]
    protected function get_name(): string {
        // The state belongs to this cohort, it cannot be replayed against another one.
        return 'tool_mucohort_bulk_changes:' . $this->cohort->get_id();
    }

    #[\Override]
    protected function get_stages(): array {
        return [
            'source' => get_string('users'),
            'columns' => get_string('bulkchanges_columns', 'tool_mucohort'),
            'options' => get_string('bulkchanges_changes', 'tool_mucohort'),
        ];
    }

    #[\Override]
    protected function get_locked_stages(): array {
        return ['source'];
    }

    #[\Override]
    protected function get_page_url(): url {
        return new url('/admin/tool/mucohort/management/bulk_changes.php', ['id' => $this->cohort->get_id()]);
    }

    #[\Override]
    protected function get_return_url(): url {
        $action = $this->state['action'] ?? $this->state['defaultaction'] ?? null;
        $exceptionactions = [bulk_changes::ACTION_FORCE, bulk_changes::ACTION_BLOCK, bulk_changes::ACTION_UNEXCEPT];
        if (in_array($action, $exceptionactions, true)) {
            return new url('/admin/tool/mucohort/cohort_exceptions.php', ['id' => $this->cohort->get_id()]);
        }
        return new url('/admin/tool/mucohort/cohort_members.php', ['id' => $this->cohort->get_id()]);
    }

    #[\Override]
    protected function setup_page(): void {
        global $PAGE;

        management::setup_cohort_page($this->get_page_url(), $this->cohort, 'cohort_members');
        $PAGE->navbar->add($this->get_heading());
    }

    #[\Override]
    protected function get_heading(): string {
        return get_string('bulkchanges', 'tool_mucohort');
    }

    #[\Override]
    protected function start_state(): ?string {
        if (isset($this->actions[$this->action])) {
            $this->wizard->set_data(['defaultaction' => $this->action]);
        }
        return null;
    }

    #[\Override]
    protected function is_stage_valid(string $stage): bool {
        return match ($stage) {
            'source' => bulk_changes::is_source_valid($this->state),
            'columns' => bulk_changes::is_columns_valid($this->state),
            'options' => bulk_changes::is_options_valid($this->cohort, $this->state),
        };
    }

    #[\Override]
    protected function create_form(string $stage, url $formurl): form {
        $state = $this->state;
        if ($stage === 'source') {
            $extra = $this->is_stage_locked($stage) ? ['rows' => $state['rows']] : [];
            return new source($formurl, [], $extra);
        }
        if ($stage === 'columns') {
            $current = [];
            if (bulk_changes::is_columns_valid($state)) {
                $current = ['headers' => (int)$state['headers'], 'column' => $state['column']];
            }
            return new columns($formurl, $current, ['rows' => $state['rows']]);
        }
        // Options of an earlier confirmation are only defaults of the form.
        $current = array_filter([
            'action' => $state['action'] ?? $state['defaultaction'] ?? null,
            'identifiertype' => $state['identifiertype'] ?? null,
            'reason' => $state['reason'] ?? null,
        ], fn($value) => $value !== null);
        return new options($formurl, $current, ['cohort' => $this->cohort, 'state' => $state]);
    }

    #[\Override]
    protected function submit_stage(string $stage, stdClass $data, form $form): ?url {
        $defaultaction = $this->state['defaultaction'] ?? null;
        $base = $defaultaction ? ['defaultaction' => $defaultaction] : [];
        if ($stage === 'source') {
            $this->state = $base + bulk_changes::save_source($form->get_rows());
        } else if ($stage === 'columns') {
            // New columns mean a new dry run.
            $this->state = $base + [
                'rows' => $this->state['rows'],
                'headers' => !empty($data->headers),
                'column' => (int)$data->column,
            ];
        } else {
            $this->state['action'] = $data->action;
            $this->state['identifiertype'] = $data->identifiertype;
            $reason = trim((string)($data->reason ?? ''));
            $isexception = ($data->action === bulk_changes::ACTION_FORCE || $data->action === bulk_changes::ACTION_BLOCK);
            $this->state['reason'] = ($isexception && $reason !== '') ? $reason : null;
        }
        return null;
    }

    #[\Override]
    protected function finish(): ?url {
        // Everything was answered, the dry run is repeated with current data.
        $count = bulk_changes::import($this->cohort, $this->state);
        $this->wizard->delete();
        \core\notification::success(get_string('bulkchanges_done', 'tool_mucohort', $count));
        return $this->get_return_url();
    }
}
