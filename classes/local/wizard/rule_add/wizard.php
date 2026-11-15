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

namespace tool_mucohort\local\wizard\rule_add;

use core\url;
use stdClass;
use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\management;
use tool_mucohort\local\rule\base as rule;
use tool_mucohort\local\rule_not_ready_exception;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\rules;
use tool_mulib\muform\form;

/**
 * Add rule wizard - type, settings and check, the rule is added to draft rules.
 *
 * Rule types without settings skip the settings stage, the check stage adds the rule.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wizard extends \tool_mulib\muform\wizard_page {
    /** @var rules cohort with rules */
    private rules $cohort;
    /** @var array rule classes of the kind indexed by type */
    private array $classes;

    /**
     * Load the cohort and check access.
     *
     * @param int $cohortid
     * @param string $kind rule::KIND_INCLUDE or rule::KIND_EXCLUDE
     */
    public function __construct(
        int $cohortid,
        /** @var string rule kind */
        private string $kind
    ) {
        $cohort = base::fetch($cohortid);
        if (!($cohort instanceof rules) || !$cohort->can_manage_rules()) {
            throw new \required_capability_exception($cohort->get_context(), 'tool/mucohort:manage', 'nopermissions', '');
        }
        if ($kind !== rule::KIND_INCLUDE && $kind !== rule::KIND_EXCLUDE) {
            throw new \invalid_parameter_exception('Invalid rule kind');
        }
        $this->cohort = $cohort;
        $this->classes = $cohort->get_rule_types()->get_classes($kind);
    }

    #[\Override]
    protected function get_name(): string {
        return 'tool_mucohort_rule_add:' . $this->cohort->get_id() . ':' . $this->kind;
    }

    #[\Override]
    protected function get_stages(): array {
        return [
            'type' => get_string('rule_type', 'tool_mucohort'),
            'settings' => get_string('rule_settings', 'tool_mucohort'),
            'check' => get_string('rule_check', 'tool_mucohort'),
        ];
    }

    #[\Override]
    protected function get_page_url(): url {
        return new url('/admin/tool/mucohort/management/rule_add.php', ['id' => $this->cohort->get_id(), 'kind' => $this->kind]);
    }

    #[\Override]
    protected function get_return_url(): url {
        return new url('/admin/tool/mucohort/cohort_draft.php', ['id' => $this->cohort->get_id()]);
    }

    #[\Override]
    protected function setup_page(): void {
        global $PAGE;

        management::setup_cohort_page($this->get_page_url(), $this->cohort, 'cohort_draft');
        $PAGE->navbar->add($this->get_heading());
    }

    #[\Override]
    protected function get_heading(): string {
        return get_string('rule_add_' . $this->kind, 'tool_mucohort');
    }

    #[\Override]
    protected function get_unavailable_reason(): ?string {
        if ($this->cohort->get_rules_record()->pendingrulesjson === null) {
            // Rules must be modified via draft rules.
            return get_string('muform_wizard_unavailable', 'tool_mulib');
        }
        return null;
    }

    /**
     * Rule of the stored type.
     *
     * @return rule|null
     */
    private function get_rule(): ?rule {
        $type = $this->state['type'] ?? null;
        if (!isset($this->classes[$type])) {
            return null;
        }
        return new $this->classes[$type]($this->cohort->get_cohort(), $this->cohort->get_mucohort());
    }

    /**
     * The configuration is valid if the rule can be evaluated.
     *
     * @return bool
     */
    private function is_config_valid(): bool {
        $rule = $this->get_rule();
        if (!$rule || !is_array($this->state['config'] ?? null)) {
            return false;
        }
        try {
            $rule->get_sql($this->state['config'], null);
        } catch (rule_not_ready_exception $ex) {
            // Data for the rule is prepared when the rule is saved.
            return true;
        } catch (broken_rules_exception $ex) {
            return false;
        }
        return true;
    }

    #[\Override]
    protected function get_skipped_stages(): array {
        // Rule types without settings skip the settings stage.
        $rule = $this->get_rule();
        return ($rule && !$rule->has_settings()) ? ['settings'] : [];
    }

    #[\Override]
    protected function is_stage_valid(string $stage): bool {
        return match ($stage) {
            'type' => $this->get_rule() !== null,
            'settings' => $this->is_config_valid(),
            'check' => false,
        };
    }

    #[\Override]
    protected function create_form(string $stage, url $formurl): form {
        $type = $this->state['type'] ?? null;
        if ($stage === 'type') {
            $types = [];
            $descriptions = [];
            foreach ($this->classes as $t => $classname) {
                $types[$t] = $classname::get_name();
                $descriptions[$t] = $classname::get_type_description();
            }
            $current = $type ? ['type' => $type] : [];
            return new type($formurl, $current, ['types' => $types, 'descriptions' => $descriptions]);
        }
        $rule = $this->get_rule();
        if ($stage === 'settings') {
            $current = $rule->get_form_data($this->state['config'] ?? []);
            return new settings($formurl, $current, ['rule' => $rule]);
        }
        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule($this->kind, $type, $this->state['config']);
        $this->cohort->before_preview($ruleset);
        try {
            $count = (string)rules::count_users($this->cohort->get_rule_match_sql($ruleset, $uuid));
        } catch (rule_not_ready_exception $ex) {
            $count = $ex->getMessage();
        }
        $extra = [
            'description' => $this->cohort->get_rule_description($ruleset->get_rule($uuid)),
            'count' => $count,
        ];
        return new check($formurl, [], $extra);
    }

    #[\Override]
    protected function submit_stage(string $stage, stdClass $data, form $form): ?url {
        if ($stage === 'type') {
            if ($data->type !== ($this->state['type'] ?? null)) {
                $rule = new $this->classes[$data->type]($this->cohort->get_cohort(), $this->cohort->get_mucohort());
                // Rules without settings go straight to the check.
                $this->state = ['type' => $data->type, 'config' => $rule->has_settings() ? null : []];
            }
            return null;
        }
        if ($stage === 'settings') {
            $this->state['config'] = $this->get_rule()->get_config($data);
            return null;
        }
        $ruleset = $this->cohort->start_draft();
        $ruleset->add_rule($this->kind, $this->state['type'], $this->state['config']);
        $this->cohort->set_pending_ruleset($ruleset);
        $this->wizard->delete();
        return $this->get_return_url();
    }
}
