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

namespace tool_mucohort\phpunit\muform\cohortpicker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use tool_mucohort\local\type\curated;
use tool_mucohort\muform\cohortpicker\candidate_cohortid;
use tool_mucohort\muform\cohortpicker\compare_cohortids;
use tool_mucohort\muform\cohortpicker\rule_cohortid;
use tool_mucohort\muform\cohortpicker\rule_cohortids;

/**
 * Tests of cohort picker sources of advanced cohorts.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(candidate_cohortid::class)]
#[CoversClass(compare_cohortids::class)]
#[CoversClass(rule_cohortid::class)]
#[CoversClass(rule_cohortids::class)]
#[Group('MuTMS')]
final class sources_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Ids of cohorts offered by a source.
     *
     * @param \tool_mulib\muform\cohortpicker\base $source
     * @param string $query
     * @param string[] $exclude
     * @return int[]
     */
    private function search(\tool_mulib\muform\cohortpicker\base $source, string $query, array $exclude = []): array {
        return array_column($source->search($query, $exclude)['cohorts'], 'id');
    }

    /**
     * Values accepted by a source.
     *
     * @param \tool_mulib\muform\cohortpicker\base $source
     * @param string[] $values
     * @return string[]
     */
    private function accepted(\tool_mulib\muform\cohortpicker\base $source, array $values): array {
        return array_map('strval', array_keys($source->labels($values)));
    }

    public function test_sources(): void {
        $syscontext = \core\context\system::instance();
        $category1 = $this->getDataGenerator()->create_category();
        $catcontext1 = \core\context\coursecat::instance($category1->id);

        $this->setAdminUser();
        $advanced = curated::create((object)['contextid' => $catcontext1->id, 'name' => 'Kohorta 0']);
        $advancedid = $advanced->get_id();
        $cohort1 = $this->getDataGenerator()->create_cohort(['name' => 'Kohorta 1', 'visible' => 0]);
        $incategory = ['contextid' => $catcontext1->id];
        $cohort2 = $this->getDataGenerator()->create_cohort(['name' => 'Kohorta 2', 'visible' => 0] + $incategory);
        $cohort3 = $this->getDataGenerator()->create_cohort(['name' => 'Kohorta 3', 'visible' => 1] + $incategory);
        $ids = [(int)$cohort1->id, (int)$cohort2->id, (int)$cohort3->id];
        $all = [(string)$advancedid, (string)$cohort1->id, (string)$cohort2->id, (string)$cohort3->id];

        $user1 = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mucohort:manage', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $user1->id, $catcontext1->id);

        $sources = [
            new candidate_cohortid((int)$catcontext1->id, $advancedid, 0),
            new rule_cohortid((int)$catcontext1->id, $advancedid, 0),
            new rule_cohortids((int)$catcontext1->id, $advancedid, ''),
        ];
        $this->assertSame([(int)$catcontext1->id, $advancedid, 0], $sources[0]->get_args());
        $this->assertSame([(int)$catcontext1->id, $advancedid, 0], $sources[1]->get_args());
        $this->assertSame([(int)$catcontext1->id, $advancedid, ''], $sources[2]->get_args());
        foreach ($sources as $source) {
            $this->assertSame($ids, $this->search($source, 'Kohorta'));
            $this->assertSame([(int)$cohort2->id], $this->search($source, 'ta 2'));
            $this->assertSame([(string)$cohort1->id, (string)$cohort2->id, (string)$cohort3->id], $this->accepted($source, $all));
        }

        // The advanced cohort is offered only when it is not the edited one.
        $source = new candidate_cohortid((int)$catcontext1->id, 0, 0);
        $this->assertSame(array_merge([$advancedid], $ids), $this->search($source, 'Kohorta'));
        $this->assertSame($all, $this->accepted($source, $all));

        $this->setUser($user1);

        $sources = [
            new candidate_cohortid((int)$catcontext1->id, $advancedid, 0),
            new rule_cohortid((int)$catcontext1->id, $advancedid, 0),
            new rule_cohortids((int)$catcontext1->id, $advancedid, ''),
        ];
        foreach ($sources as $source) {
            $this->assertSame([(int)$cohort3->id], $this->search($source, 'Kohorta'));
            $this->assertSame([(string)$cohort3->id], $this->accepted($source, $all));
        }

        // Current values stay valid, the edited cohort never is.
        $sources = [
            new candidate_cohortid((int)$catcontext1->id, $advancedid, (int)$cohort1->id),
            new rule_cohortid((int)$catcontext1->id, $advancedid, (int)$cohort1->id),
            new rule_cohortids((int)$catcontext1->id, $advancedid, $cohort1->id . ',' . $advancedid),
        ];
        foreach ($sources as $source) {
            $this->assertSame([(int)$cohort3->id], $this->search($source, 'Kohorta'));
            $this->assertSame([(string)$cohort1->id, (string)$cohort3->id], $this->accepted($source, $all));
        }
        $source = new rule_cohortids((int)$catcontext1->id, $advancedid, $cohort1->id . ',' . $cohort2->id);
        $this->assertSame([(int)$catcontext1->id, $advancedid, $cohort1->id . ',' . $cohort2->id], $source->get_args());
        $this->assertSame([(string)$cohort1->id, (string)$cohort2->id, (string)$cohort3->id], $this->accepted($source, $all));

        foreach ([candidate_cohortid::class, rule_cohortid::class, rule_cohortids::class] as $class) {
            try {
                new $class((int)$syscontext->id);
                $this->fail('Exception expected');
            } catch (\core\exception\moodle_exception $ex) {
                $this->assertInstanceOf(\core\exception\required_capability_exception::class, $ex);
            }
        }
    }

    public function test_compare_cohortids(): void {
        $syscontext = \core\context\system::instance();
        $category1 = $this->getDataGenerator()->create_category();
        $catcontext1 = \core\context\coursecat::instance($category1->id);

        $incategory = ['contextid' => $catcontext1->id];
        $target = $this->getDataGenerator()->create_cohort(['name' => 'Kohorta 0'] + $incategory);
        $cohort1 = $this->getDataGenerator()->create_cohort(['name' => 'Kohorta 1']);
        $cohort2 = $this->getDataGenerator()->create_cohort(['name' => 'Kohorta 2'] + $incategory);
        $cohort3 = $this->getDataGenerator()->create_cohort(['name' => 'Kohorta 3', 'visible' => 0] + $incategory);
        $all = [(string)$target->id, (string)$cohort1->id, (string)$cohort2->id, (string)$cohort3->id];

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mucohort:viewmembers', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $user1->id, $catcontext1->id);

        $this->setAdminUser();
        $source = new compare_cohortids((int)$target->id);
        $this->assertSame([(int)$target->id], $source->get_args());
        $this->assertSame([(int)$cohort1->id, (int)$cohort2->id, (int)$cohort3->id], $this->search($source, 'Kohorta'));
        $this->assertSame([(string)$cohort1->id, (string)$cohort2->id, (string)$cohort3->id], $this->accepted($source, $all));

        // Members must be visible and the cohort usable.
        $this->setUser($user1);
        $source = new compare_cohortids((int)$target->id);
        $this->assertSame([(int)$cohort2->id], $this->search($source, 'Kohorta'));
        $this->assertSame([(string)$cohort2->id], $this->accepted($source, $all));

        $this->setUser($user2);
        try {
            new compare_cohortids((int)$target->id);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\required_capability_exception::class, $ex);
        }
    }
}
