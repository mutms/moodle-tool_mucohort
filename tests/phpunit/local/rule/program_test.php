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
// phpcs:disable moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_mucohort\phpunit\local\rule;

use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\rule\base;
use tool_mucohort\local\ruleset;
use tool_mucohort\local\type\rules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Program completion rule tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\local\rule\include_program_completion::class)]
#[CoversClass(\tool_mucohort\local\rule\exclude_program_completion::class)]
#[CoversClass(\tool_mucohort\muform\autocomplete\rule_programid::class)]
#[CoversClass(\tool_mucohort\muform\autocompletemany\rule_programids::class)]
#[Group('MuTMS')]
final class program_test extends \advanced_testcase {
    /** @var rules */
    private rules $cohort;

    public function setUp(): void {
        parent::setUp();
        if (!\tool_mulib\local\mulib::is_muprog_available()) {
            $this->markTestSkipped('programs not available');
        }
        $this->resetAfterTest();
        $this->cohort = rules::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Rules cohort',
        ]);
    }

    /**
     * Returns ids of users matched by one rule, admin is ignored.
     *
     * @param string $kind
     * @param string $type
     * @param array $config
     * @param int|null $userid
     * @return int[]
     */
    private function match(string $kind, string $type, array $config, ?int $userid = null): array {
        global $DB;
        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule($kind, $type, $config);
        $sql = $this->cohort->get_rule_match_sql($ruleset, $uuid, $userid)->wrap("SELECT u.id FROM {user} u WHERE ", " AND u.id <> 2 ORDER BY u.id");
        return array_map('intval', $DB->get_fieldset_sql($sql->sql, $sql->params));
    }

    /**
     * Returns rule description.
     *
     * @param string $kind
     * @param string $type
     * @param array $config
     * @return string
     */
    private function describe(string $kind, string $type, array $config): string {
        $ruleset = new ruleset();
        $uuid = $ruleset->add_rule($kind, $type, $config);
        return $this->cohort->get_rule_description($ruleset->get_rule($uuid));
    }

    public function test_completion(): void {
        global $DB;

        /** @var \tool_muprog_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_muprog');
        $program1 = $generator->create_program(['fullname' => 'Program 1']);
        $program2 = $generator->create_program(['fullname' => 'Program 2']);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        foreach ([$user1, $user2, $user3] as $user) {
            $generator->create_program_allocation(['programid' => $program1->id, 'userid' => $user->id]);
            $generator->create_program_allocation(['programid' => $program2->id, 'userid' => $user->id]);
        }
        $DB->set_field('tool_muprog_allocation', 'timecompleted', time(), ['programid' => $program1->id, 'userid' => $user1->id]);
        $DB->set_field('tool_muprog_allocation', 'timecompleted', time(), ['programid' => $program2->id, 'userid' => $user2->id]);

        $type = 'tool_mucohort:programcompletion';
        $this->assertSame([(int)$user1->id], $this->match(base::KIND_INCLUDE, $type, ['programids' => [(int)$program1->id]]));
        $this->assertSame(
            [(int)$user1->id, (int)$user2->id],
            $this->match(base::KIND_INCLUDE, $type, ['programids' => [(int)$program1->id, (int)$program2->id]])
        );
        $this->assertSame([(int)$user2->id], $this->match(base::KIND_EXCLUDE, $type, ['programid' => (int)$program2->id]));
        $this->assertSame([], $this->match(base::KIND_INCLUDE, $type, ['programids' => [(int)$program1->id]], (int)$user2->id));

        $this->assertSame(
            'Completed any of these programs: Program 1, Program 2',
            $this->describe(base::KIND_INCLUDE, $type, ['programids' => [(int)$program1->id, (int)$program2->id]])
        );
        $this->assertSame('Completed program: Program 2', $this->describe(base::KIND_EXCLUDE, $type, ['programid' => (int)$program2->id]));

        $DB->delete_records('tool_muprog_program', ['id' => $program2->id]);
        $this->assertSame('Completed program: Missing program (' . $program2->id . ')', $this->describe(base::KIND_EXCLUDE, $type, ['programid' => (int)$program2->id]));
        try {
            $this->match(base::KIND_EXCLUDE, $type, ['programid' => (int)$program2->id]);
            $this->fail('Exception expected');
        } catch (broken_rules_exception $ex) {
            $this->assertStringContainsString('Program does not exist', $ex->getMessage());
        }
    }

    public function test_program_pickers(): void {
        $this->setAdminUser();
        $context = \core\context\system::instance();
        /** @var \tool_muprog_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_muprog');
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $program1 = $generator->create_program(['fullname' => 'Alpha', 'contextid' => $catcontext->id]);
        $program2 = $generator->create_program(['fullname' => 'Beta']);

        $source = new \tool_mucohort\muform\autocompletemany\rule_programids($context->id, []);
        $this->assertSame([(string)$program1->id => 'Alpha', (string)$program2->id => 'Beta'], $source->search('', 10, []));
        $this->assertSame([(string)$program1->id => 'Alpha'], $source->labels([(string)$program1->id, '999999']));

        $source = new \tool_mucohort\muform\autocomplete\rule_programid($context->id, 0);
        $this->assertSame('Beta', $source->label((string)$program2->id));
        $this->assertNull($source->label('999999'));

        // Other users see only programs they may view, current values stay valid.
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mucohort:manage', CAP_ALLOW, $roleid, $context->id);
        $this->getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        $viewroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/muprog:view', CAP_ALLOW, $viewroleid, $catcontext->id);
        $this->getDataGenerator()->role_assign($viewroleid, $user->id, $catcontext->id);
        $this->setUser($user);
        $source = new \tool_mucohort\muform\autocompletemany\rule_programids($context->id, [(int)$program2->id]);
        $this->assertSame([(string)$program1->id => 'Alpha'], $source->search('', 10, []));
        $this->assertEqualsCanonicalizing(
            [(string)$program1->id => 'Alpha', (string)$program2->id => 'Beta'],
            $source->labels([(string)$program1->id, (string)$program2->id])
        );
        $source = new \tool_mucohort\muform\autocomplete\rule_programid($context->id, 0);
        $this->assertNull($source->label((string)$program2->id));
    }
}
