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
 * Certification rule tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\local\rule\include_certification::class)]
#[CoversClass(\tool_mucohort\local\rule\exclude_certification::class)]
#[CoversClass(\tool_mucohort\muform\autocomplete\rule_certificationid::class)]
#[CoversClass(\tool_mucohort\muform\autocompletemany\rule_certificationids::class)]
#[Group('MuTMS')]
final class certification_test extends \advanced_testcase {
    /** @var rules */
    private rules $cohort;

    public function setUp(): void {
        parent::setUp();
        if (!\tool_mulib\local\mulib::is_mucertify_available()) {
            $this->markTestSkipped('certifications not available');
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

    /**
     * Add certification period.
     *
     * @param int $certificationid
     * @param int $userid
     * @param array $data
     */
    private function add_period(int $certificationid, int $userid, array $data): void {
        global $DB;
        $now = time();
        $record = [
            'certificationid' => $certificationid,
            'userid' => $userid,
            'timewindowstart' => $now - DAYSECS,
            'timecertified' => $now - HOURSECS,
            'timefrom' => $now - HOURSECS,
            'timeuntil' => $now + WEEKSECS,
            'evidencejson' => '{}',
            'first' => 1,
            'recertifiable' => 1,
        ];
        $DB->insert_record('tool_mucertify_period', array_merge($record, $data));
    }

    public function test_certification(): void {
        global $DB;

        /** @var \tool_mucertify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mucertify');
        $certification1 = $generator->create_certification(['fullname' => 'Certification 1']);
        $certification2 = $generator->create_certification(['fullname' => 'Certification 2']);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        $user5 = $this->getDataGenerator()->create_user();
        $now = time();
        $this->add_period((int)$certification1->id, (int)$user1->id, []);
        $this->add_period((int)$certification2->id, (int)$user2->id, ['timeuntil' => null]);
        $this->add_period((int)$certification1->id, (int)$user3->id, ['timeuntil' => $now - 10]);
        $this->add_period((int)$certification1->id, (int)$user4->id, ['timerevoked' => $now - 10]);
        $this->add_period((int)$certification1->id, (int)$user5->id, ['timecertified' => null, 'timefrom' => null, 'timeuntil' => null]);

        $type = 'tool_mucohort:certification';
        $this->assertSame([(int)$user1->id], $this->match(base::KIND_INCLUDE, $type, ['certificationids' => [(int)$certification1->id]]));
        $this->assertSame(
            [(int)$user1->id, (int)$user2->id],
            $this->match(base::KIND_INCLUDE, $type, ['certificationids' => [(int)$certification1->id, (int)$certification2->id]])
        );
        $this->assertSame([(int)$user2->id], $this->match(base::KIND_EXCLUDE, $type, ['certificationid' => (int)$certification2->id]));
        $this->assertSame([], $this->match(base::KIND_INCLUDE, $type, ['certificationids' => [(int)$certification1->id]], (int)$user2->id));

        $this->assertSame(
            'Certified in any of these certifications: Certification 1, Certification 2',
            $this->describe(base::KIND_INCLUDE, $type, ['certificationids' => [(int)$certification1->id, (int)$certification2->id]])
        );
        $this->assertSame('Certified in: Certification 2', $this->describe(base::KIND_EXCLUDE, $type, ['certificationid' => (int)$certification2->id]));

        $DB->delete_records('tool_mucertify_certification', ['id' => $certification2->id]);
        $this->assertSame(
            'Certified in: Missing certification (' . $certification2->id . ')',
            $this->describe(base::KIND_EXCLUDE, $type, ['certificationid' => (int)$certification2->id])
        );
        try {
            $this->match(base::KIND_EXCLUDE, $type, ['certificationid' => (int)$certification2->id]);
            $this->fail('Exception expected');
        } catch (broken_rules_exception $ex) {
            $this->assertStringContainsString('Certification does not exist', $ex->getMessage());
        }
    }

    public function test_certification_pickers(): void {
        $this->setAdminUser();
        $context = \core\context\system::instance();
        /** @var \tool_mucertify_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mucertify');
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $certification1 = $generator->create_certification(['fullname' => 'Alpha', 'contextid' => $catcontext->id]);
        $certification2 = $generator->create_certification(['fullname' => 'Beta']);

        $source = new \tool_mucohort\muform\autocompletemany\rule_certificationids($context->id, []);
        $this->assertSame([(string)$certification1->id => 'Alpha', (string)$certification2->id => 'Beta'], $source->search('', 10, []));
        $this->assertSame([(string)$certification2->id => 'Beta'], $source->search('bet', 10, []));
        $this->assertSame([(string)$certification1->id => 'Alpha'], $source->labels([(string)$certification1->id, '999999']));

        $source = new \tool_mucohort\muform\autocomplete\rule_certificationid($context->id, 0);
        $this->assertSame('Beta', $source->label((string)$certification2->id));
        $this->assertNull($source->label('999999'));

        // Other users see only certifications they may view, current values stay valid.
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mucohort:manage', CAP_ALLOW, $roleid, $context->id);
        $this->getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        $viewroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mucertify:view', CAP_ALLOW, $viewroleid, $catcontext->id);
        $this->getDataGenerator()->role_assign($viewroleid, $user->id, $catcontext->id);
        $this->setUser($user);
        $source = new \tool_mucohort\muform\autocompletemany\rule_certificationids($context->id, [(int)$certification2->id]);
        $this->assertSame([(string)$certification1->id => 'Alpha'], $source->search('', 10, []));
        $this->assertEqualsCanonicalizing(
            [(string)$certification1->id => 'Alpha', (string)$certification2->id => 'Beta'],
            $source->labels([(string)$certification1->id, (string)$certification2->id])
        );
        $source = new \tool_mucohort\muform\autocomplete\rule_certificationid($context->id, 0);
        $this->assertNull($source->label((string)$certification2->id));
    }
}
