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

namespace tool_mucohort\phpunit\local\type;

use tool_mucohort\local\type\base;
use tool_mucohort\local\type\curated;
use tool_mucohort\local\type\plugin;
use tool_mucohort\local\type\rules;
use tool_mucohort\local\type\standard;

/**
 * Cohort type base tests.
 *
 * @group       MuTMS
 * @package     tool_mucohort
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mucohort\local\type\base
 */
final class base_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_fetch(): void {
        global $DB;

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort(['component' => 'tool_mutenancy']);
        $cohort3 = $this->getDataGenerator()->create_cohort(['component' => 'tool_mucohort']);
        $cohort4 = $this->getDataGenerator()->create_cohort(['component' => 'tool_mucohort']);
        $cohort5 = $this->getDataGenerator()->create_cohort(['component' => 'tool_mucohort']);
        $now = time();
        $DB->insert_record('tool_mucohort', ['cohortid' => $cohort3->id, 'type' => curated::TYPE, 'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record('tool_mucohort', ['cohortid' => $cohort4->id, 'type' => rules::TYPE, 'timecreated' => $now, 'timemodified' => $now]);

        $cohort = base::fetch($cohort1->id);
        $this->assertInstanceOf(standard::class, $cohort);
        $this->assertSame('standard', $cohort->get_type());
        $this->assertSame((int)$cohort1->id, $cohort->get_id());
        $this->assertEquals($cohort1, $cohort->get_cohort());
        $this->assertNull($cohort->get_mucohort());
        $this->assertSame((int)$cohort1->contextid, $cohort->get_context()->id);

        $this->assertInstanceOf(plugin::class, base::fetch($cohort2->id));

        $cohort = base::fetch($cohort3->id);
        $this->assertInstanceOf(curated::class, $cohort);
        $this->assertSame('curated', $cohort->get_type());
        $this->assertSame((string)$cohort3->id, (string)$cohort->get_mucohort()->cohortid);

        $this->assertInstanceOf(rules::class, base::fetch($cohort4->id));

        // Invalid advanced cohort without record is reported as plugin cohort.
        $this->assertInstanceOf(plugin::class, base::fetch($cohort5->id));

        $this->assertNull(base::fetch(-1, IGNORE_MISSING));
        try {
            base::fetch(-1);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\dml_missing_record_exception::class, $ex);
        }
    }

    public function test_get_context(): void {
        global $DB;

        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $syscontext = \core\context\system::instance();
        $record = $this->getDataGenerator()->create_cohort(['contextid' => $catcontext->id]);

        $cohort = base::fetch($record->id);
        $this->assertSame($catcontext->id, $cohort->get_context()->id);
        $this->assertFalse($cohort->is_context_missing());

        // Broken data, cohort in a deleted context.
        $DB->set_field('cohort', 'contextid', -1, ['id' => $record->id]);
        $cohort = base::fetch($record->id);
        $this->assertSame($syscontext->id, $cohort->get_context()->id);
        $this->assertTrue($cohort->is_context_missing());

        $this->setAdminUser();
        $this->assertTrue($cohort->can_manage());
        $cohort->move($catcontext->id);
        $this->assertSame($catcontext->id, $cohort->get_context()->id);
        $this->assertFalse($cohort->is_context_missing());
    }

    public function test_get_name(): void {
        $this->assertSame('Standard', standard::get_name());
        $this->assertSame('Curated', curated::get_name());
        $this->assertSame('Rule-based', rules::get_name());
        $this->assertSame('Plugin: Multi-tenancy', plugin::get_component_type_name('tool_mutenancy'));
        $this->assertSame('Plugin: missing (local_gone)', plugin::get_component_type_name('local_gone'));

        $cohort = $this->getDataGenerator()->create_cohort(['component' => 'local_gone']);
        $this->assertSame('Plugin: missing (local_gone)', base::fetch($cohort->id)->get_type_name());
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->assertSame('Standard', base::fetch($cohort->id)->get_type_name());
    }

    public function test_is_enabled(): void {
        $this->assertTrue(standard::is_enabled());
        $this->assertTrue(curated::is_enabled());
        $this->assertTrue(rules::is_enabled());
        $this->assertFalse(plugin::is_enabled());

        set_config('enabledtypes', 'curated', 'tool_mucohort');
        $this->assertFalse(standard::is_enabled());
        $this->assertTrue(curated::is_enabled());
        $this->assertFalse(rules::is_enabled());

        set_config('enabledtypes', '', 'tool_mucohort');
        $this->assertFalse(standard::is_enabled());
        $this->assertFalse(curated::is_enabled());
        $this->assertFalse(rules::is_enabled());
    }

    public function test_get_classes(): void {
        $this->assertSame([
            'standard' => standard::class,
            'curated' => curated::class,
            'rules' => rules::class,
        ], base::get_classes());
    }

    public function test_update(): void {
        $record = $this->getDataGenerator()->create_cohort(['idnumber' => 'x']);
        $cohort = base::fetch($record->id);

        $cohort->update((object)[
            'name' => 'New name',
            'idnumber' => '',
            'visible' => 0,
        ]);
        $record = $cohort->get_cohort();
        $this->assertSame('New name', $record->name);
        $this->assertNull($record->idnumber);
        $this->assertSame('0', (string)$record->visible);

        $cohort->update((object)['idnumber' => 'y']);
        $record = $cohort->get_cohort();
        $this->assertSame('New name', $record->name);
        $this->assertSame('y', $record->idnumber);
    }

    public function test_move(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $syscontext = \core\context\system::instance();
        $cohort = base::fetch($this->getDataGenerator()->create_cohort()->id);

        $sink = $this->redirectEvents();
        $cohort->move($catcontext->id);
        $this->assertSame($catcontext->id, $cohort->get_context()->id);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\core\event\cohort_updated::class, $events[0]);
        $sink->clear();

        $cohort->move($catcontext->id);
        $this->assertCount(0, $sink->get_events());

        $cohort->move($syscontext->id);
        $this->assertSame($syscontext->id, $cohort->get_context()->id);

        $course = $this->getDataGenerator()->create_course();
        try {
            $cohort->move(\core\context\course::instance($course->id)->id);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\invalid_parameter_exception::class, $ex);
        }
    }

    public function test_delete(): void {
        global $DB;

        $record = $this->getDataGenerator()->create_cohort();
        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($record->id, $user->id);

        base::fetch($record->id)->delete();
        $this->assertFalse($DB->record_exists('cohort', ['id' => $record->id]));
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $record->id]));
    }

    public function test_is_idnumber_used(): void {
        $cohort1 = $this->getDataGenerator()->create_cohort(['idnumber' => 'abc']);
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $this->assertTrue(base::is_idnumber_used('abc', null));
        $this->assertFalse(base::is_idnumber_used('abc', $cohort1->id));
        $this->assertTrue(base::is_idnumber_used('abc', $cohort2->id));
        $this->assertFalse(base::is_idnumber_used('xyz', null));
    }

    public function test_normalise_idnumber(): void {
        $this->assertNull(base::normalise_idnumber(null));
        $this->assertNull(base::normalise_idnumber(''));
        $this->assertNull(base::normalise_idnumber('  '));
        $this->assertSame('x', base::normalise_idnumber('x'));
    }
}
