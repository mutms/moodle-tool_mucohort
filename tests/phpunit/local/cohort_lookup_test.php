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

use tool_mucohort\local\access;
use tool_mucohort\local\type\curated;
use tool_mulib\local\cohort_util;
use tool_mulib\local\search_util;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests of access records in cohort lookups of MuTMS plugins.
 *
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\local\cohort_util::class)]
#[CoversClass(\tool_mulib\local\search_util::class)]
#[CoversTrait(\tool_mulib\muform\util\autocomplete\cohort_trait::class)]
#[Group('MuTMS')]
final class cohort_lookup_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_access_records(): void {
        global $DB;

        $syscontext = \core\context\system::instance();
        $this->setAdminUser();
        $visible = $this->getDataGenerator()->create_cohort(['name' => 'Visible']);
        $hidden = $this->getDataGenerator()->create_cohort(['name' => 'Hidden', 'visible' => 0]);
        $cohort = curated::create((object)['contextid' => $syscontext->id, 'name' => 'Curated', 'visible' => 0]);
        $curated = $DB->get_record('cohort', ['id' => $cohort->get_id()], '*', MUST_EXIST);
        $user = $this->getDataGenerator()->create_user();

        $picker = new class {
            use \tool_mulib\muform\util\autocomplete\cohort_trait;

            /**
             * Search.
             *
             * @param string $query
             * @return array|null
             */
            public function search(string $query): ?array {
                return $this->search_cohorts(\core\context\system::instance(), $query, 10);
            }

            /**
             * Validate.
             *
             * @param array $ids
             * @return array
             */
            public function validate(array $ids): array {
                return $this->validate_cohorts(\core\context\system::instance(), $ids);
            }
        };

        $this->setUser($user);
        $this->assertSame([(int)$visible->id], array_keys($picker->search('')));
        $this->assertFalse(search_util::is_cohort_visible($curated));
        $this->assertFalse(cohort_util::has_access_record($curated, (int)$user->id));
        $this->assertSame([(int)$curated->id], array_keys($picker->validate([(string)$curated->id])));

        // Any access record allows use of the hidden advanced cohort.
        $this->setAdminUser();
        access::set($cohort, $user->id, [], false);
        $this->setUser($user);
        $this->assertSame([(int)$curated->id, (int)$visible->id], array_keys($picker->search('')));
        $this->assertTrue(search_util::is_cohort_visible($curated));
        $this->assertTrue(cohort_util::has_access_record($curated, (int)$user->id));
        $this->assertSame([], $picker->validate([(string)$curated->id]));
        $this->assertSame([(int)$hidden->id], array_keys($picker->validate([(string)$hidden->id])));

        // Access records of converted cohorts are ignored.
        $this->assertFalse(cohort_util::has_access_record($hidden, (int)$user->id));
        $DB->set_field('cohort', 'component', '', ['id' => $curated->id]);
        $curated = $DB->get_record('cohort', ['id' => $curated->id], '*', MUST_EXIST);
        $this->assertFalse(cohort_util::has_access_record($curated, (int)$user->id));
        $this->assertSame([(int)$visible->id], array_keys($picker->search('')));

        // Guests never have access records.
        $this->assertSame('1=2', cohort_util::get_access_record_sql('c', (int)guest_user()->id)->sql);
        $this->assertSame('1=2', cohort_util::get_access_record_sql('c', 0)->sql);
    }
}
