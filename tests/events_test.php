<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Contains the event tests for the module edusign.
 *
 * @package   mod_edusign
 * @copyright 2014 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/edusign/tests/generator.php');
require_once($CFG->dirroot . '/mod/edusign/tests/fixtures/event_mod_edusign_fixtures.php');
require_once($CFG->dirroot . '/mod/edusign/locallib.php');

/**
 * Contains the event tests for the module edusign.
 *
 * @package   mod_edusign
 * @copyright 2014 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edusign_events_testcase extends advanced_testcase
{
    // Use the generator helper.
    use mod_edusign_test_generator;

    /**
     * Basic tests for the submission_created() abstract class.
     */
    public function test_base_event()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_edusign');
        $instance = $generator->create_instance(array('course' => $course->id));
        $modcontext = context_module::instance($instance->cmid);

        $data = array(
            'context' => $modcontext,
        );

        $event = \mod_edusign_unittests\event\nothing_happened::create($data);
        $edusign = $event->get_edusign();
        $this->assertDebuggingCalled();
        $this->assertInstanceOf('edusign', $edusign);

        $event = \mod_edusign_unittests\event\nothing_happened::create($data);
        $event->set_edusign($edusign);
        $edusign2 = $event->get_edusign();
        $this->assertDebuggingNotCalled();
        $this->assertSame($edusign, $edusign2);
    }

    /**
     * Basic tests for the submission_created() abstract class.
     */
    public function test_submission_created()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_edusign');
        $instance = $generator->create_instance(array('course' => $course->id));
        $modcontext = context_module::instance($instance->cmid);

        // Standard Event parameters.
        $params = array(
            'context' => $modcontext,
            'courseid' => $course->id
        );

        $eventinfo = $params;
        $eventinfo['other'] = array(
            'submissionid' => '17',
            'submissionattempt' => 0,
            'submissionstatus' => 'submitted'
        );

        $sink = $this->redirectEvents();
        $event = \mod_edusign_unittests\event\submission_created::create($eventinfo);
        $event->trigger();
        $result = $sink->get_events();
        $event = reset($result);
        $sink->close();

        $this->assertEquals($modcontext->id, $event->contextid);
        $this->assertEquals($course->id, $event->courseid);

        // Check that an error occurs when teamsubmission is not set.
        try {
            \mod_edusign_unittests\event\submission_created::create($params);
            $this->fail('Other must contain the key submissionid.');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }
        // Check that the submission status debugging is fired.
        $subinfo = $params;
        $subinfo['other'] = array('submissionid' => '23');
        try {
            \mod_edusign_unittests\event\submission_created::create($subinfo);
            $this->fail('Other must contain the key submissionattempt.');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }

        $subinfo['other'] = array('submissionattempt' => '0');
        try {
            \mod_edusign_unittests\event\submission_created::create($subinfo);
            $this->fail('Other must contain the key submissionstatus.');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }
    }

    /**
     * Basic tests for the submission_updated() abstract class.
     */
    public function test_submission_updated()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_edusign');
        $instance = $generator->create_instance(array('course' => $course->id));
        $modcontext = context_module::instance($instance->cmid);

        // Standard Event parameters.
        $params = array(
            'context' => $modcontext,
            'courseid' => $course->id
        );

        $eventinfo = $params;
        $eventinfo['other'] = array(
            'submissionid' => '17',
            'submissionattempt' => 0,
            'submissionstatus' => 'submitted'
        );

        $sink = $this->redirectEvents();
        $event = \mod_edusign_unittests\event\submission_updated::create($eventinfo);
        $event->trigger();
        $result = $sink->get_events();
        $event = reset($result);
        $sink->close();

        $this->assertEquals($modcontext->id, $event->contextid);
        $this->assertEquals($course->id, $event->courseid);

        // Check that an error occurs when teamsubmission is not set.
        try {
            \mod_edusign_unittests\event\submission_created::create($params);
            $this->fail('Other must contain the key submissionid.');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }
        // Check that the submission status debugging is fired.
        $subinfo = $params;
        $subinfo['other'] = array('submissionid' => '23');
        try {
            \mod_edusign_unittests\event\submission_created::create($subinfo);
            $this->fail('Other must contain the key submissionattempt.');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }

        $subinfo['other'] = array('submissionattempt' => '0');
        try {
            \mod_edusign_unittests\event\submission_created::create($subinfo);
            $this->fail('Other must contain the key submissionstatus.');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }
    }

    public function test_extension_granted()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        $now = time();
        $tomorrow = $now + DAYSECS;
        $yesterday = $now - DAYSECS;

        $edusign = $this->create_instance($course, [
            'duedate' => $yesterday,
            'cutoffdate' => $yesterday,
        ]);
        $sink = $this->redirectEvents();

        $edusign->testable_save_user_extension($student->id, $tomorrow);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_edusign\event\extension_granted', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($edusign->get_instance()->id, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);

        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'grant extension',
            'view.php?id=' . $edusign->get_course_module()->id,
            $student->id,
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();
    }

    public function test_submission_locked()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $teacher->ignoresesskey = true;
        $this->setUser($teacher);

        $edusign = $this->create_instance($course);
        $sink = $this->redirectEvents();

        $edusign->lock_submission($student->id);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_edusign\event\submission_locked', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($edusign->get_instance()->id, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'lock submission',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('locksubmissionforstudent', 'edusign', array('id' => $student->id,
                'fullname' => fullname($student))),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();
    }

    public function test_identities_revealed()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $teacher->ignoresesskey = true;
        $this->setUser($teacher);

        $edusign = $this->create_instance($course, ['blindmarking' => 1]);
        $sink = $this->redirectEvents();

        $edusign->reveal_identities();

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_edusign\event\identities_revealed', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($edusign->get_instance()->id, $event->objectid);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'reveal identities',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('revealidentities', 'edusign'),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();
    }

    /**
     * Test the submission_status_viewed event.
     */
    public function test_submission_status_viewed()
    {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $this->setUser($teacher);

        $edusign = $this->create_instance($course);

        // We need to set the URL in order to view the feedback.
        $PAGE->set_url('/a_url');

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->view();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\submission_status_viewed', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'view',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('viewownsubmissionstatus', 'edusign'),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_submission_status_updated()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        $edusign = $this->create_instance($course);
        $submission = $edusign->get_user_submission($student->id, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $student->id, true, false);

        $sink = $this->redirectEvents();
        $edusign->revert_to_draft($student->id);

        $events = $sink->get_events();
        $this->assertCount(2, $events);
        $event = $events[1];
        $this->assertInstanceOf('\mod_edusign\event\submission_status_updated', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($submission->id, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $this->assertEquals(EDUSIGN_SUBMISSION_STATUS_DRAFT, $event->other['newstatus']);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'revert submission to draft',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('reverttodraftforstudent', 'edusign', array('id' => $student->id,
                'fullname' => fullname($student))),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();
    }

    public function test_marker_updated()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $teacher->ignoresesskey = true;
        $this->setUser($teacher);

        $edusign = $this->create_instance($course);

        $sink = $this->redirectEvents();
        $edusign->testable_process_set_batch_marking_allocation($student->id, $teacher->id);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_edusign\event\marker_updated', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($edusign->get_instance()->id, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $this->assertEquals($teacher->id, $event->userid);
        $this->assertEquals($teacher->id, $event->other['markerid']);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'set marking allocation',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('setmarkerallocationforlog', 'edusign', array('id' => $student->id,
                'fullname' => fullname($student), 'marker' => fullname($teacher))),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();
    }

    public function test_workflow_state_updated()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $teacher->ignoresesskey = true;
        $this->setUser($teacher);

        $edusign = $this->create_instance($course);

        // Test process_set_batch_marking_workflow_state.
        $sink = $this->redirectEvents();
        $edusign->testable_process_set_batch_marking_workflow_state($student->id, edusign_MARKING_WORKFLOW_STATE_INREVIEW);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_edusign\event\workflow_state_updated', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($edusign->get_instance()->id, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $this->assertEquals($teacher->id, $event->userid);
        $this->assertEquals(edusign_MARKING_WORKFLOW_STATE_INREVIEW, $event->other['newstate']);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'set marking workflow state',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('setmarkingworkflowstateforlog', 'edusign', array('id' => $student->id,
                'fullname' => fullname($student), 'state' => edusign_MARKING_WORKFLOW_STATE_INREVIEW)),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();

        // Test setting workflow state in apply_grade_to_user.
        $sink = $this->redirectEvents();
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = 'readyforrelease';
        $edusign->testable_apply_grade_to_user($data, $student->id, 0);

        $events = $sink->get_events();
        $this->assertCount(4, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_edusign\event\workflow_state_updated', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($edusign->get_instance()->id, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $this->assertEquals($teacher->id, $event->userid);
        $this->assertEquals(edusign_MARKING_WORKFLOW_STATE_READYFORRELEASE, $event->other['newstate']);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'set marking workflow state',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('setmarkingworkflowstateforlog', 'edusign', array('id' => $student->id,
                'fullname' => fullname($student), 'state' => edusign_MARKING_WORKFLOW_STATE_READYFORRELEASE)),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();

        // Test setting workflow state in process_save_quick_grades.
        $sink = $this->redirectEvents();

        $data = array(
            'grademodified_' . $student->id => time(),
            'gradeattempt_' . $student->id => '',
            'quickgrade_' . $student->id => '60.0',
            'quickgrade_' . $student->id . '_workflowstate' => 'inmarking'
        );
        $edusign->testable_process_save_quick_grades($data);

        $events = $sink->get_events();
        $this->assertCount(4, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_edusign\event\workflow_state_updated', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($edusign->get_instance()->id, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $this->assertEquals($teacher->id, $event->userid);
        $this->assertEquals(edusign_MARKING_WORKFLOW_STATE_INMARKING, $event->other['newstate']);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'set marking workflow state',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('setmarkingworkflowstateforlog', 'edusign', array('id' => $student->id,
                'fullname' => fullname($student), 'state' => edusign_MARKING_WORKFLOW_STATE_INMARKING)),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();
    }

    public function test_submission_duplicated()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($student);

        $edusign = $this->create_instance($course);
        $submission1 = $edusign->get_user_submission($student->id, true, 0);
        $submission2 = $edusign->get_user_submission($student->id, true, 1);
        $submission2->status = edusign_SUBMISSION_STATUS_REOPENED;
        $edusign->testable_update_submission($submission2, $student->id, time(), $edusign->get_instance()->teamsubmission);

        $sink = $this->redirectEvents();
        $notices = null;
        $edusign->copy_previous_attempt($notices);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_edusign\event\submission_duplicated', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($submission2->id, $event->objectid);
        $this->assertEquals($student->id, $event->userid);
        $submission2->status = EDUSIGN_SUBMISSION_STATUS_DRAFT;
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'submissioncopied',
            'view.php?id=' . $edusign->get_course_module()->id,
            $edusign->testable_format_submission_for_log($submission2),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();
    }

    public function test_submission_unlocked()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $teacher->ignoresesskey = true;
        $this->setUser($teacher);

        $edusign = $this->create_instance($course);
        $sink = $this->redirectEvents();

        $edusign->unlock_submission($student->id);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_edusign\event\submission_unlocked', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($edusign->get_instance()->id, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'unlock submission',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('unlocksubmissionforstudent', 'edusign', array('id' => $student->id,
                'fullname' => fullname($student))),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();
    }

    public function test_submission_graded()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $teacher->ignoresesskey = true;
        $this->setUser($teacher);

        $edusign = $this->create_instance($course);

        // Test apply_grade_to_user.
        $sink = $this->redirectEvents();

        $data = new stdClass();
        $data->grade = '50.0';
        $edusign->testable_apply_grade_to_user($data, $student->id, 0);
        $grade = $edusign->get_user_grade($student->id, false, 0);

        $events = $sink->get_events();
        $this->assertCount(3, $events);
        $event = $events[2];
        $this->assertInstanceOf('\mod_edusign\event\submission_graded', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($grade->id, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'grade submission',
            'view.php?id=' . $edusign->get_course_module()->id,
            $edusign->format_grade_for_log($grade),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();

        // Test process_save_quick_grades.
        $sink = $this->redirectEvents();

        $grade = $edusign->get_user_grade($student->id, false);
        $data = array(
            'grademodified_' . $student->id => time(),
            'gradeattempt_' . $student->id => $grade->attemptnumber,
            'quickgrade_' . $student->id => '60.0'
        );
        $edusign->testable_process_save_quick_grades($data);
        $grade = $edusign->get_user_grade($student->id, false);
        $this->assertEquals('60.0', $grade->grade);

        $events = $sink->get_events();
        $this->assertCount(3, $events);
        $event = $events[2];
        $this->assertInstanceOf('\mod_edusign\event\submission_graded', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($grade->id, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'grade submission',
            'view.php?id=' . $edusign->get_course_module()->id,
            $edusign->format_grade_for_log($grade),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();

        // Test update_grade.
        $sink = $this->redirectEvents();
        $data = clone($grade);
        $data->grade = '50.0';
        $edusign->update_grade($data);
        $grade = $edusign->get_user_grade($student->id, false, 0);
        $this->assertEquals('50.0', $grade->grade);
        $events = $sink->get_events();

        $this->assertCount(3, $events);
        $event = $events[2];
        $this->assertInstanceOf('\mod_edusign\event\submission_graded', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($grade->id, $event->objectid);
        $this->assertEquals($student->id, $event->relateduserid);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'grade submission',
            'view.php?id=' . $edusign->get_course_module()->id,
            $edusign->format_grade_for_log($grade),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $sink->close();
    }

    /**
     * Test the submission_viewed event.
     */
    public function test_submission_viewed()
    {
        global $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        $edusign = $this->create_instance($course);
        $submission = $edusign->get_user_submission($student->id, true);

        // We need to set the URL in order to view the submission.
        $PAGE->set_url('/a_url');
        // A hack - these variables are used by the view_plugin_content function to
        // determine what we actually want to view - would usually be set in URL.
        global $_POST;
        $_POST['plugin'] = 'comments';
        $_POST['sid'] = $submission->id;

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->view('viewpluginedusignsubmission');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\submission_viewed', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($submission->id, $event->objectid);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'view submission',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('viewsubmissionforuser', 'edusign', $student->id),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the feedback_viewed event.
     */
    public function test_feedback_viewed()
    {
        global $DB, $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        $edusign = $this->create_instance($course);
        $submission = $edusign->get_user_submission($student->id, true);

        // Insert a grade for this submission.
        $grade = new stdClass();
        $grade->edusignment = $edusign->get_instance()->id;
        $grade->userid = $student->id;
        $gradeid = $DB->insert_record('edusign_grades', $grade);

        // We need to set the URL in order to view the feedback.
        $PAGE->set_url('/a_url');
        // A hack - these variables are used by the view_plugin_content function to
        // determine what we actually want to view - would usually be set in URL.
        global $_POST;
        $_POST['plugin'] = 'comments';
        $_POST['gid'] = $gradeid;
        $_POST['sid'] = $submission->id;

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->view('viewpluginedusignfeedback');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\feedback_viewed', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEquals($gradeid, $event->objectid);
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'view feedback',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('viewfeedbackforuser', 'edusign', $student->id),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the grading_form_viewed event.
     */
    public function test_grading_form_viewed()
    {
        global $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        $edusign = $this->create_instance($course);

        // We need to set the URL in order to view the feedback.
        $PAGE->set_url('/a_url');
        // A hack - this variable is used by the view_single_grade_page function.
        global $_POST;
        $_POST['rownum'] = 1;
        $_POST['userid'] = $student->id;

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->view('grade');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\grading_form_viewed', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'view grading form',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('viewgradingformforstudent', 'edusign', array('id' => $student->id,
                'fullname' => fullname($student))),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the grading_table_viewed event.
     */
    public function test_grading_table_viewed()
    {
        global $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        $edusign = $this->create_instance($course);

        // We need to set the URL in order to view the feedback.
        $PAGE->set_url('/a_url');
        // A hack - this variable is used by the view_single_grade_page function.
        global $_POST;
        $_POST['rownum'] = 1;
        $_POST['userid'] = $student->id;

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->view('grading');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\grading_table_viewed', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'view submission grading table',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('viewsubmissiongradingtable', 'edusign'),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the submission_form_viewed event.
     */
    public function test_submission_form_viewed()
    {
        global $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($student);

        $edusign = $this->create_instance($course);

        // We need to set the URL in order to view the submission form.
        $PAGE->set_url('/a_url');

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->view('editsubmission');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\submission_form_viewed', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'view submit edusignment form',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('editsubmission', 'edusign'),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the submission_form_viewed event.
     */
    public function test_submission_confirmation_form_viewed()
    {
        global $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($student);

        $edusign = $this->create_instance($course);

        // We need to set the URL in order to view the submission form.
        $PAGE->set_url('/a_url');

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->view('submit');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\submission_confirmation_form_viewed', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'view confirm submit edusignment form',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('viewownsubmissionform', 'edusign'),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the reveal_identities_confirmation_page_viewed event.
     */
    public function test_reveal_identities_confirmation_page_viewed()
    {
        global $PAGE;
        $this->resetAfterTest();

        // Set to the admin user so we have the permission to reveal identities.
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->create_instance($course);

        // We need to set the URL in order to view the submission form.
        $PAGE->set_url('/a_url');

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->view('revealidentities');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\reveal_identities_confirmation_page_viewed', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'view',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('viewrevealidentitiesconfirm', 'edusign'),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the statement_accepted event.
     */
    public function test_statement_accepted()
    {
        // We want to be a student so we can submit edusignments.
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($student);

        // We do not want to send any messages to the student during the PHPUNIT test.
        set_config('submissionreceipts', false, 'edusign');

        $edusign = $this->create_instance($course);

        // Create the data we want to pass to the submit_for_grading function.
        $data = new stdClass();
        $data->submissionstatement = 'We are the Borg. You will be assimilated. Resistance is futile. - do you agree
            to these terms?';

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->submit_for_grading($data, array());
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\statement_accepted', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'submission statement accepted',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string(
                'submissionstatementacceptedlog',
                'mod_edusign',
                fullname($student)
            ),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        // Enable the online text submission plugin.
        $submissionplugins = $edusign->get_submission_plugins();
        foreach ($submissionplugins as $plugin) {
            if ($plugin->get_type() === 'onlinetext') {
                $plugin->enable();
                break;
            }
        }

        // Create the data we want to pass to the save_submission function.
        $data = new stdClass();
        $data->onlinetext_editor = array(
            'text' => 'Online text',
            'format' => FORMAT_HTML,
            'itemid' => file_get_unused_draft_itemid()
        );
        $data->submissionstatement = 'We are the Borg. You will be assimilated. Resistance is futile. - do you agree
            to these terms?';

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->save_submission($data, $notices);
        $events = $sink->get_events();
        $event = $events[2];

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\statement_accepted', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the batch_set_workflow_state_viewed event.
     */
    public function test_batch_set_workflow_state_viewed()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $edusign = $this->create_instance($course);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->testable_view_batch_set_workflow_state($student->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\batch_set_workflow_state_viewed', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'view batch set marking workflow state',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('viewbatchsetmarkingworkflowstate', 'edusign'),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the batch_set_marker_allocation_viewed event.
     */
    public function test_batch_set_marker_allocation_viewed()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $edusign = $this->create_instance($course);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->testable_view_batch_markingallocation($student->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_edusign\event\batch_set_marker_allocation_viewed', $event);
        $this->assertEquals($edusign->get_context(), $event->get_context());
        $expected = array(
            $edusign->get_course()->id,
            'edusign',
            'view batch set marker allocation',
            'view.php?id=' . $edusign->get_course_module()->id,
            get_string('viewbatchmarkingallocation', 'edusign'),
            $edusign->get_course_module()->id
        );
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override created event.
     *
     * There is no external API for creating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_created()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->getDataGenerator()->get_plugin_generator('mod_edusign')->create_instance(['course' => $course->id]);

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => context_module::instance($edusign->cmid),
            'other' => array(
                'edusignid' => $edusign->id
            )
        );
        $event = \mod_edusign\event\user_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_edusign\event\user_override_created', $event);
        $this->assertEquals(context_module::instance($edusign->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override created event.
     *
     * There is no external API for creating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_created()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->getDataGenerator()->get_plugin_generator('mod_edusign')->create_instance(['course' => $course->id]);

        $params = array(
            'objectid' => 1,
            'context' => context_module::instance($edusign->cmid),
            'other' => array(
                'edusignid' => $edusign->id,
                'groupid' => 2
            )
        );
        $event = \mod_edusign\event\group_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_edusign\event\group_override_created', $event);
        $this->assertEquals(context_module::instance($edusign->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override updated event.
     *
     * There is no external API for updating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_updated()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->getDataGenerator()->get_plugin_generator('mod_edusign')->create_instance(['course' => $course->id]);

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => context_module::instance($edusign->cmid),
            'other' => array(
                'edusignid' => $edusign->id
            )
        );
        $event = \mod_edusign\event\user_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_edusign\event\user_override_updated', $event);
        $this->assertEquals(context_module::instance($edusign->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override updated event.
     *
     * There is no external API for updating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_updated()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->getDataGenerator()->get_plugin_generator('mod_edusign')->create_instance(['course' => $course->id]);

        $params = array(
            'objectid' => 1,
            'context' => context_module::instance($edusign->cmid),
            'other' => array(
                'edusignid' => $edusign->id,
                'groupid' => 2
            )
        );
        $event = \mod_edusign\event\group_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_edusign\event\group_override_updated', $event);
        $this->assertEquals(context_module::instance($edusign->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override deleted event.
     */
    public function test_user_override_deleted()
    {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $edusigninstance = $this->getDataGenerator()->create_module('edusign', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('edusign', $edusigninstance->id, $course->id);
        $context = context_module::instance($cm->id);
        $edusign = new edusign($context, $cm, $course);

        // Create an override.
        $override = new stdClass();
        $override->edusign = $edusigninstance->id;
        $override->userid = 2;
        $override->id = $DB->insert_record('edusign_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->delete_override($override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_edusign\event\user_override_deleted', $event);
        $this->assertEquals(context_module::instance($cm->id), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override deleted event.
     */
    public function test_group_override_deleted()
    {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $edusigninstance = $this->getDataGenerator()->create_module('edusign', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('edusign', $edusigninstance->id, $course->id);
        $context = context_module::instance($cm->id);
        $edusign = new edusign($context, $cm, $course);

        // Create an override.
        $override = new stdClass();
        $override->edusign = $edusigninstance->id;
        $override->groupid = 2;
        $override->id = $DB->insert_record('edusign_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $edusign->delete_override($override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_edusign\event\group_override_deleted', $event);
        $this->assertEquals(context_module::instance($cm->id), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test that all events generated with blindmarking enabled are anonymous
     */
    public function test_anonymous_events()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_edusign');
        $instance = $generator->create_instance(array('course' => $course->id, 'blindmarking' => 1));

        $cm = get_coursemodule_from_instance('edusign', $instance->id, $course->id);
        $context = context_module::instance($cm->id);
        $edusign = new edusign($context, $cm, $course);

        $this->setUser($teacher);
        $sink = $this->redirectEvents();

        $edusign->lock_submission($student1->id);

        $events = $sink->get_events();
        $event = reset($events);

        $this->assertTrue((bool)$event->anonymous);

        $edusign->reveal_identities();
        $sink = $this->redirectEvents();
        $edusign->lock_submission($student2->id);

        $events = $sink->get_events();
        $event = reset($events);

        $this->assertFalse((bool)$event->anonymous);
    }
}
