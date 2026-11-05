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

namespace tool_mucohort\phpunit\local;

use tool_mucohort\local\rule\base;
use tool_mucohort\local\broken_rules_exception;
use tool_mucohort\local\ruleset;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Ruleset tests.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mucohort\local\ruleset::class)]
#[Group('MuTMS')]
final class ruleset_test extends \basic_testcase {
    public function test_empty(): void {
        $ruleset = new ruleset();
        $this->assertSame([], $ruleset->get_rules());
        $expected = <<<'JSON'
{
    "version": 1,
    "rules": {}
}
JSON;
        $this->assertSame($expected, $ruleset->to_json());
        $this->assertEquals($ruleset, ruleset::from_json($ruleset->to_json()));
    }

    public function test_rules(): void {
        $ruleset = new ruleset();
        $exclude1 = $ruleset->add_rule(base::KIND_EXCLUDE, 'tool_mucohort:suspended', []);
        $include1 = $ruleset->add_rule(base::KIND_INCLUDE, 'tool_mucohort:cohort', ['cohortids' => [3, 2]]);
        $include2 = $ruleset->add_rule(base::KIND_INCLUDE, 'tool_mucohort:allusers', []);
        $exclude2 = $ruleset->add_rule(base::KIND_EXCLUDE, 'tool_mucohort:cohort', ['cohortid' => 5]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $include1);

        $this->assertSame([$include1, $include2, $exclude1, $exclude2], array_keys($ruleset->get_rules()));
        $this->assertSame([$include1, $include2], array_keys($ruleset->get_rules(base::KIND_INCLUDE)));
        $this->assertSame([$exclude1, $exclude2], array_keys($ruleset->get_rules(base::KIND_EXCLUDE)));
        $this->assertSame(
            ['sortorder' => 1, 'kind' => 'include', 'type' => 'tool_mucohort:cohort', 'config' => ['cohortids' => [3, 2]]],
            $ruleset->get_rule($include1)
        );
        $this->assertSame(2, $ruleset->get_rule($exclude2)['sortorder']);
        $this->assertNull($ruleset->get_rule('xyz'));

        $json = $ruleset->to_json();
        $restored = ruleset::from_json($json);
        $this->assertSame($json, $restored->to_json());
        $this->assertSame(array_keys($ruleset->get_rules()), array_keys($restored->get_rules()));
        $this->assertSame($ruleset->get_hash(), $restored->get_hash());

        // Rules are stored sorted by UUID.
        $data = json_decode($json, true);
        $uuids = array_keys($data['rules']);
        $sorted = $uuids;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $uuids);
        $this->assertSame(['sortorder', 'kind', 'type', 'config'], array_keys($data['rules'][$include1]));
        $this->assertStringContainsString('"config": {}', $json);

        // Reordering does not change rule hashes.
        $hash1 = ruleset::get_rule_hash($ruleset->get_rule($include1));
        $hash = $ruleset->get_hash();
        $ruleset->reorder_rules(base::KIND_INCLUDE, [$include2]);
        $this->assertSame([$include2, $include1], array_keys($ruleset->get_rules(base::KIND_INCLUDE)));
        $this->assertSame([$exclude1, $exclude2], array_keys($ruleset->get_rules(base::KIND_EXCLUDE)));
        $this->assertSame($hash1, ruleset::get_rule_hash($ruleset->get_rule($include1)));
        $this->assertNotSame($hash, $ruleset->get_hash());

        $ruleset->update_rule($include1, ['cohortids' => [4]]);
        $this->assertSame(['cohortids' => [4]], $ruleset->get_rule($include1)['config']);
        $this->assertSame(2, $ruleset->get_rule($include1)['sortorder']);
        $this->assertNotSame($hash1, ruleset::get_rule_hash($ruleset->get_rule($include1)));

        $ruleset->delete_rule($include1);
        $this->assertSame([$include2, $exclude1, $exclude2], array_keys($ruleset->get_rules()));
    }

    public function test_config_key_order(): void {
        $ruleset1 = new ruleset();
        $uuid = $ruleset1->add_rule(base::KIND_INCLUDE, 'abc:def', ['b' => 1, 'a' => 2]);
        $this->assertSame(['a' => 2, 'b' => 1], $ruleset1->get_rule($uuid)['config']);
    }

    public function test_from_json_invalid(): void {
        $valid = '{"version": 1, "rules": {"019a2b3c-4d5e-7f00-8000-000000000001": '
            . '{"sortorder": 1, "kind": "include", "type": "tool_mucohort:allusers", "config": {}}}}';
        $ruleset = ruleset::from_json($valid);
        $this->assertCount(1, $ruleset->get_rules());

        $invalids = [
            '',
            'xx',
            '[]',
            '{"version": 2, "rules": {}}',
            '{"version": 1, "rules": 1}',
            '{"version": 1}',
            str_replace('019a2b3c', 'xxxx', $valid),
            str_replace('"sortorder": 1', '"sortorder": "1"', $valid),
            str_replace('"include"', '"other"', $valid),
            str_replace('tool_mucohort:allusers', 'allusers', $valid),
            str_replace('"config": {}', '"config": 1', $valid),
        ];
        foreach ($invalids as $json) {
            try {
                ruleset::from_json($json);
                $this->fail('Exception expected: ' . $json);
            } catch (broken_rules_exception $ex) {
                $this->assertSame('error_rules_invalid', $ex->errorcode);
            }
        }
    }
}
