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

use tool_mucohort\local\exception;
use tool_mucohort\local\bulk_changes;
use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;

/**
 * Bulk changes tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\bulk_changes
 */
final class bulk_changes_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_guessing(): void {
        $this->assertTrue(bulk_changes::guess_headers(['Full name', 'Email address']));
        $this->assertFalse(bulk_changes::guess_headers(['jane@example.com']));
        $this->assertSame(2, bulk_changes::guess_column(['Full name', 'Email address', 'Username', 'ID number']));
        $this->assertSame(1, bulk_changes::guess_column(['Full name', 'ID number']));
        $this->assertSame(0, bulk_changes::guess_column(['a', 'b']));

        $types = array_fill_keys(['userid', 'username', 'email', 'idnumber'], 'x');
        $state = bulk_changes::save_source([['jane@example.com'], ['john@example.com']]);
        $this->assertSame(['rows' => [['jane@example.com'], ['john@example.com']], 'headers' => false, 'column' => 0], $state);
        $this->assertSame('email', bulk_changes::guess_identifier_type($state, $types));
        $state = bulk_changes::save_source([['jane'], ['john@example.com']]);
        $this->assertSame('username', bulk_changes::guess_identifier_type($state, $types));
        $state = bulk_changes::save_source([['ID number'], ['123']]);
        $this->assertTrue($state['headers']);
        $this->assertSame('idnumber', bulk_changes::guess_identifier_type($state, $types));
        $this->assertSame('userid', bulk_changes::guess_identifier_type($state, ['userid' => 'x']));
    }

    public function test_state_validation(): void {
        $this->setAdminUser();
        $cohort = base::fetch($this->getDataGenerator()->create_cohort()->id);

        $this->assertFalse(bulk_changes::is_source_valid([]));
        $this->assertFalse(bulk_changes::is_source_valid(['rows' => [['a', 'b'], ['c']]]));
        $state = bulk_changes::save_source([['a', 'b'], ['c', 'd']]);
        $this->assertTrue(bulk_changes::is_source_valid($state));
        $this->assertFalse(bulk_changes::is_columns_valid($state));
        $state['headers'] = true;
        $state['column'] = 2;
        $this->assertFalse(bulk_changes::is_columns_valid($state));
        $state['column'] = 1;
        $this->assertTrue(bulk_changes::is_columns_valid($state));
        $this->assertSame(['d'], bulk_changes::get_identifiers($state));
        $this->assertFalse(bulk_changes::is_options_valid($cohort, $state));
        $state['action'] = bulk_changes::ACTION_FORCE;
        $state['identifiertype'] = bulk_changes::IDENTIFIER_USERNAME;
        $this->assertFalse(bulk_changes::is_options_valid($cohort, $state));
        $state['action'] = bulk_changes::ACTION_ADD;
        $this->assertTrue(bulk_changes::is_options_valid($cohort, $state));
    }

    public function test_standard(): void {
        global $DB;

        $this->setAdminUser();
        $cohort = base::fetch($this->getDataGenerator()->create_cohort()->id);
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1', 'email' => 'same@example.com']);
        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2', 'email' => 'same@example.com']);
        $user3 = $this->getDataGenerator()->create_user(['username' => 'user3', 'email' => 'User3@Example.com']);
        $deleted = $this->getDataGenerator()->create_user(['username' => 'deleted', 'deleted' => 1]);
        cohort_add_member($cohort->get_id(), $user2->id);

        $this->assertSame(
            [bulk_changes::ACTION_ADD, bulk_changes::ACTION_REMOVE],
            array_keys(bulk_changes::get_actions($cohort))
        );

        $identifiers = ['USER1', 'user2', 'user3', 'User1', 'missing', 'deleted', 'guest'];
        $check = bulk_changes::check($cohort, $identifiers, bulk_changes::IDENTIFIER_USERNAME, bulk_changes::ACTION_ADD);
        $results = array_map(fn($o) => $o->result, $check->outcomes);
        $this->assertSame(['ok', 'already', 'ok', 'duplicate', 'notfound', 'notfound', 'notfound'], $results);
        $this->assertSame([(int)$user1->id, (int)$user3->id], $check->userids);
        $this->assertSame(2, $check->counts[bulk_changes::RESULT_OK]);
        $this->assertTrue(bulk_changes::is_importable($check));

        $check = bulk_changes::check($cohort, ['same@example.com', 'user3@example.COM'], bulk_changes::IDENTIFIER_EMAIL, bulk_changes::ACTION_ADD);
        $results = array_map(fn($o) => $o->result, $check->outcomes);
        $this->assertSame(['ambiguous', 'ok'], $results);

        $check = bulk_changes::check($cohort, [(string)$user1->id, 'x', '0'], bulk_changes::IDENTIFIER_USERID, bulk_changes::ACTION_REMOVE);
        $results = array_map(fn($o) => $o->result, $check->outcomes);
        $this->assertSame(['already', 'notfound', 'notfound'], $results);
        $this->assertFalse(bulk_changes::is_importable($check));

        $state = [
            'rows' => [['Username'], ['user1'], ['user2'], ['user3']],
            'headers' => true,
            'column' => 0,
            'action' => bulk_changes::ACTION_ADD,
            'identifiertype' => bulk_changes::IDENTIFIER_USERNAME,
        ];
        $this->assertSame(2, bulk_changes::import($cohort, $state));
        $this->assertSame(3, $DB->count_records('cohort_members', ['cohortid' => $cohort->get_id()]));

        $state['rows'] = [['Username'], ['user1'], ['missing']];
        $state['action'] = bulk_changes::ACTION_REMOVE;
        $this->assertSame(1, bulk_changes::import($cohort, $state));
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $user1->id]));
        $this->assertSame(2, $DB->count_records('cohort_members', ['cohortid' => $cohort->get_id()]));
    }

    public function test_curated(): void {
        global $DB;

        $this->setAdminUser();
        $candidate = $this->getDataGenerator()->create_cohort();
        $cohort = curated::create((object)[
            'contextid' => \core\context\system::instance()->id,
            'name' => 'Curated',
            'candidatecohortid' => $candidate->id,
        ]);
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['username' => 'user' . $i]);
            cohort_add_member($candidate->id, $users[$i]->id);
        }
        $outsider = $this->getDataGenerator()->create_user(['username' => 'outsider']);
        exception::set($cohort, $users[1]->id, exception::TYPE_BLOCKED, null);
        exception::set($cohort, $users[2]->id, exception::TYPE_FORCED, null);

        $this->assertSame(
            [
                bulk_changes::ACTION_ADD,
                bulk_changes::ACTION_REMOVE,
                bulk_changes::ACTION_FORCE,
                bulk_changes::ACTION_BLOCK,
                bulk_changes::ACTION_UNEXCEPT,
            ],
            array_keys(bulk_changes::get_actions($cohort))
        );

        $identifiers = ['user0', 'user1', 'user2', 'user3', 'outsider'];
        $check = bulk_changes::check($cohort, $identifiers, bulk_changes::IDENTIFIER_USERNAME, bulk_changes::ACTION_ADD);
        $results = array_map(fn($o) => $o->result, $check->outcomes);
        $this->assertSame(['ok', 'notallowed', 'already', 'ok', 'notallowed'], $results);
        $this->assertNull($check->capacity);

        // Member limit refuses the whole addition.
        $cohort->update((object)['maxmembers' => 2]);
        $cohort = base::fetch($cohort->get_id());
        $check = bulk_changes::check($cohort, $identifiers, bulk_changes::IDENTIFIER_USERNAME, bulk_changes::ACTION_ADD);
        $this->assertSame(1, $check->capacity);
        $this->assertFalse(bulk_changes::is_importable($check));
        $state = [
            'rows' => [['user0'], ['user3']],
            'headers' => false,
            'column' => 0,
            'action' => bulk_changes::ACTION_ADD,
            'identifiertype' => bulk_changes::IDENTIFIER_USERNAME,
        ];
        try {
            bulk_changes::import($cohort, $state);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $e) {
            $this->assertSame('error_maxmembers', $e->errorcode);
        }
        $cohort->update((object)['maxmembers' => '']);
        $cohort = base::fetch($cohort->get_id());
        $this->assertSame(2, bulk_changes::import($cohort, $state));

        // Forced users are not removed.
        $state['rows'] = [['user0'], ['user2']];
        $state['action'] = bulk_changes::ACTION_REMOVE;
        $check = bulk_changes::check($cohort, ['user0', 'user2'], bulk_changes::IDENTIFIER_USERNAME, bulk_changes::ACTION_REMOVE);
        $this->assertSame(['ok', 'notallowed'], array_map(fn($o) => $o->result, $check->outcomes));
        $this->assertSame(1, bulk_changes::import($cohort, $state));
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $users[2]->id]));

        // Exceptions ignore the candidate cohort, the reason is stored.
        $state['rows'] = [['user1'], ['user3'], ['outsider']];
        $state['action'] = bulk_changes::ACTION_FORCE;
        $state['reason'] = 'Teachers';
        $check = bulk_changes::check($cohort, ['user1', 'user2', 'outsider'], bulk_changes::IDENTIFIER_USERNAME, bulk_changes::ACTION_FORCE);
        $this->assertSame(['ok', 'already', 'ok'], array_map(fn($o) => $o->result, $check->outcomes));
        $this->assertSame(3, bulk_changes::import($cohort, $state));
        $this->assertSame(4, exception::count($cohort, exception::TYPE_FORCED));
        $this->assertSame('Teachers', exception::get_record($cohort, (int)$outsider->id)->reason);
        // Existing members get the forced exception too.
        $this->assertSame(exception::TYPE_FORCED, (int)exception::get_record($cohort, (int)$users[3]->id)->type);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $outsider->id]));

        $state['rows'] = [['outsider']];
        $state['action'] = bulk_changes::ACTION_BLOCK;
        $state['reason'] = null;
        $this->assertSame(1, bulk_changes::import($cohort, $state));
        $this->assertSame(exception::TYPE_BLOCKED, (int)exception::get_record($cohort, (int)$outsider->id)->type);
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $outsider->id]));

        // Removing of exceptions does not change membership.
        $state['rows'] = [['outsider'], ['user3'], ['user4']];
        $state['action'] = bulk_changes::ACTION_UNEXCEPT;
        $check = bulk_changes::check($cohort, ['outsider', 'user3', 'user4'], bulk_changes::IDENTIFIER_USERNAME, bulk_changes::ACTION_UNEXCEPT);
        $this->assertSame(['ok', 'ok', 'already'], array_map(fn($o) => $o->result, $check->outcomes));
        $this->assertSame(2, bulk_changes::import($cohort, $state));
        $this->assertNull(exception::get_record($cohort, (int)$outsider->id));
        $this->assertNull(exception::get_record($cohort, (int)$users[3]->id));
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $outsider->id]));
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->get_id(), 'userid' => $users[3]->id]));
    }

    public function test_permissions(): void {
        global $CFG;

        $syscontext = \core\context\system::instance();
        $cohort = base::fetch($this->getDataGenerator()->create_cohort()->id);
        $manager = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $manager->id, $syscontext);
        $this->setUser($manager);

        $this->assertSame([bulk_changes::ACTION_ADD, bulk_changes::ACTION_REMOVE], array_keys(bulk_changes::get_actions($cohort)));

        // Hidden user fields cannot be used for matching.
        $CFG->showuseridentity = 'email,idnumber';
        $this->assertSame(['userid'], array_keys(bulk_changes::get_identifier_types($cohort)));
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $roleid, $syscontext);
        $this->assertSame(['userid', 'email', 'idnumber'], array_keys(bulk_changes::get_identifier_types($cohort)));
        $CFG->showuseridentity = 'username';
        $this->assertSame(['userid', 'username'], array_keys(bulk_changes::get_identifier_types($cohort)));

        $this->setAdminUser();
        $CFG->showuseridentity = '';
        $this->assertSame(['userid', 'username'], array_keys(bulk_changes::get_identifier_types($cohort)));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame([], bulk_changes::get_actions($cohort));
    }
}
