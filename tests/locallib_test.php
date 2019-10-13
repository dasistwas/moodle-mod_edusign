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
 * Unit tests for (some of) mod/edusign/locallib.php.
 *
 * @package    mod_edusign
 * @category   phpunit
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/edusign/locallib.php');
require_once($CFG->dirroot . '/mod/edusign/upgradelib.php');
require_once($CFG->dirroot . '/mod/edusign/tests/generator.php');

/**
 * Unit tests for (some of) mod/edusign/locallib.php.
 *
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_edusign_locallib_testcase extends advanced_testcase
{

    // Use the generator helper.
    use mod_edusign_test_generator;

    public function test_return_links()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $edusign = $this->create_instance($course);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        $edusign->register_return_link('RETURNACTION', ['param' => 1]);
        $this->assertEquals('RETURNACTION', $edusign->get_return_action());
        $this->assertEquals(['param' => 1], $edusign->get_return_params());
    }

    public function test_get_feedback_plugins()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course);
        $installedplugins = array_keys(core_component::get_plugin_list('edusignfeedback'));

        foreach ($edusign->get_feedback_plugins() as $plugin) {
            $this->assertContains($plugin->get_type(), $installedplugins, 'Feedback plugin not in list of installed plugins');
        }
    }

    public function test_get_submission_plugins()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course);
        $installedplugins = array_keys(core_component::get_plugin_list('edusignsubmission'));

        foreach ($edusign->get_submission_plugins() as $plugin) {
            $this->assertContains($plugin->get_type(), $installedplugins, 'Submission plugin not in list of installed plugins');
        }
    }

    public function test_is_blind_marking()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course, ['blindmarking' => 1]);
        $this->assertEquals(true, $edusign->is_blind_marking());

        // Test cannot see student names.
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, get_string('hiddenuser', 'edusign')));

        // Test students cannot reveal identities.
        $nopermission = false;
        $student->ignoresesskey = true;
        $this->setUser($student);
        $this->expectException('required_capability_exception');
        $edusign->reveal_identities();
        $student->ignoresesskey = false;

        // Test teachers cannot reveal identities.
        $nopermission = false;
        $teacher->ignoresesskey = true;
        $this->setUser($teacher);
        $this->expectException('required_capability_exception');
        $edusign->reveal_identities();
        $teacher->ignoresesskey = false;

        // Test sesskey is required.
        $this->setUser($teacher);
        $this->expectException('moodle_exception');
        $edusign->reveal_identities();

        // Test editingteacher can reveal identities if sesskey is ignored.
        $teacher->ignoresesskey = true;
        $this->setUser($teacher);
        $edusign->reveal_identities();
        $this->assertEquals(false, $edusign->is_blind_marking());
        $teacher->ignoresesskey = false;

        // Test student names are visible.
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertEquals(false, strpos($output, get_string('hiddenuser', 'edusign')));

        // Set this back to default.
        $teacher->ignoresesskey = false;
    }

    /**
     * Data provider for test_get_edusign_perpage
     *
     * @return array Provider data
     */
    public function get_edusign_perpage_provider()
    {
        return array(
            array(
                'maxperpage' => -1,
                'userprefs' => array(
                    -1 => -1,
                    10 => 10,
                    20 => 20,
                    50 => 50,
                ),
            ),
            array(
                'maxperpage' => 15,
                'userprefs' => array(
                    -1 => 15,
                    10 => 10,
                    20 => 15,
                    50 => 15,
                ),
            ),
        );
    }

    /**
     * Test maxperpage
     *
     * @dataProvider get_edusign_perpage_provider
     * @param integer $maxperpage site config value
     * @param array $userprefs Array of user preferences and expected page sizes
     */
    public function test_get_edusign_perpage($maxperpage, $userprefs)
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course);

        set_config('maxperpage', $maxperpage, 'edusign');
        set_user_preference('edusign_perpage', null);
        $this->assertEquals(10, $edusign->get_edusign_perpage());
        foreach ($userprefs as $pref => $perpage) {
            set_user_preference('edusign_perpage', $pref);
            $this->assertEquals($perpage, $edusign->get_edusign_perpage());
        }
    }

    /**
     * Test filter by requires grading.
     *
     * This is specifically checking an edusignment with no grade to make sure we do not
     * get an exception thrown when rendering the grading table for this type of edusignment.
     */
    public function test_gradingtable_filter_by_requiresgrading_no_grade()
    {
        global $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'edusignfeedback_comments_enabled' => 0,
                'grade' => GRADE_TYPE_NONE
            ]);

        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', array(
            'id' => $edusign->get_course_module()->id,
            'action' => 'grading',
        )));

        // Render the table with the requires grading filter.
        $gradingtable = new edusign_grading_table($edusign, 1, edusign_FILTER_REQUIRE_GRADING, 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);

        // Test that the filter function does not throw errors for edusignments with no grade.
        $this->assertContains(get_string('nothingtodisplay'), $output);
    }


    /**
     * Test submissions with extension date.
     */
    public function test_gradingtable_extension_due_date()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Setup the edusignment.
        $this->setUser($teacher);
        $time = time();
        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'duedate' => time() - (4 * DAYSECS),
            ]);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', array(
            'id' => $edusign->get_course_module()->id,
            'action' => 'grading',
        )));

        // Check that the edusignment is late.
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'edusign'), $output);
        $this->assertContains(get_string('overdue', 'edusign', format_time((4 * DAYSECS))), $output);

        // Grant an extension.
        $extendedtime = $time + (2 * DAYSECS);
        $edusign->testable_save_user_extension($student->id, $extendedtime);
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'edusign'), $output);
        $this->assertContains(get_string('userextensiondate', 'edusign', userdate($extendedtime)), $output);

        // Simulate a submission.
        $this->setUser($student);
        $submission = $edusign->get_user_submission($student->id, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $student->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = [
            'itemid' => file_get_unused_draft_itemid(),
            'text' => 'Submission text',
            'format' => FORMAT_MOODLE,
        ];
        $plugin = $edusign->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Verify output.
        $this->setUser($teacher);
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_submitted', 'edusign'), $output);
        $this->assertContains(get_string('userextensiondate', 'edusign', userdate($extendedtime)), $output);
    }

    /**
     * Test that late submissions with extension date calculate correctly.
     */
    public function test_gradingtable_extension_date_calculation_for_lateness()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Setup the edusignment.
        $this->setUser($teacher);
        $time = time();
        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'duedate' => time() - (4 * DAYSECS),
            ]);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', array(
            'id' => $edusign->get_course_module()->id,
            'action' => 'grading',
        )));

        // Check that the edusignment is late.
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'edusign'), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('overdue', 'edusign', format_time((4 * DAYSECS) + $difftime)), $output);

        // Grant an extension that is in the past.
        $edusign->testable_save_user_extension($student->id, $time - (2 * DAYSECS));
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'edusign'), $output);
        $this->assertContains(get_string('userextensiondate', 'edusign', userdate($time - (2 * DAYSECS))), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('overdue', 'edusign', format_time((2 * DAYSECS) + $difftime)), $output);

        // Simulate a submission.
        $this->setUser($student);
        $submission = $edusign->get_user_submission($student->id, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $student->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = [
            'itemid' => file_get_unused_draft_itemid(),
            'text' => 'Submission text',
            'format' => FORMAT_MOODLE,
        ];
        $plugin = $edusign->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);
        $submittedtime = time();

        // Verify output.
        $this->setUser($teacher);
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_submitted', 'edusign'), $output);
        $this->assertContains(get_string('userextensiondate', 'edusign', userdate($time - (2 * DAYSECS))), $output);

        $difftime = $submittedtime - $time;
        $this->assertContains(get_string('submittedlateshort', 'edusign', format_time((2 * DAYSECS) + $difftime)), $output);
    }

    public function test_gradingtable_status_rendering()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Setup the edusignment.
        $this->setUser($teacher);
        $time = time();
        $edusign = $this->create_instance($course, [
            'edusignsubmission_onlinetext_enabled' => 1,
            'duedate' => $time - (4 * DAYSECS),
         ]);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', array(
            'id' => $edusign->get_course_module()->id,
            'action' => 'grading',
        )));

        // Check that the edusignment is late.
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'edusign'), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('overdue', 'edusign', format_time((4 * DAYSECS) + $difftime)), $output);

        // Simulate a student viewing the edusignment without submitting.
        $this->setUser($student);
        $submission = $edusign->get_user_submission($student->id, true);
        $submission->status = edusign_SUBMISSION_STATUS_NEW;
        $edusign->testable_update_submission($submission, $student->id, true, false);
        $submittedtime = time();

        // Verify output.
        $this->setUser($teacher);
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $difftime = $submittedtime - $time;
        $this->assertContains(get_string('overdue', 'edusign', format_time((4 * DAYSECS) + $difftime)), $output);

        $document = new DOMDocument();
        @$document->loadHTML($output);
        $xpath = new DOMXPath($document);
        $this->assertEquals('', $xpath->evaluate('string(//td[@id="mod_edusign_grading_r0_c8"])'));
    }

    /**
     * Check that group submission information is rendered correctly in the
     * grading table.
     */
    public function test_gradingtable_group_submissions_rendering()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        groups_add_member($group, $teacher);

        $students = [];

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $students[] = $student;
        groups_add_member($group, $student);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $students[] = $student;
        groups_add_member($group, $student);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $students[] = $student;
        groups_add_member($group, $student);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $students[] = $student;
        groups_add_member($group, $student);

        // Verify group edusignments.
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
            'teamsubmission' => 1,
            'edusignsubmission_onlinetext_enabled' => 1,
            'submissiondrafts' => 1,
            'requireallteammemberssubmit' => 0,
        ]);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', array(
            'id' => $edusign->get_course_module()->id,
            'action' => 'grading',
        )));

        // Add a submission.
        $this->setUser($student);
        $data = new stdClass();
        $data->onlinetext_editor = [
            'itemid' => file_get_unused_draft_itemid(),
            'text' => 'Submission text',
            'format' => FORMAT_MOODLE,
        ];
        $notices = array();
        $edusign->save_submission($data, $notices);

        $submission = $edusign->get_group_submission($student->id, 0, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $student->id, true, true);

        // Check output.
        $this->setUser($teacher);
        $gradingtable = new edusign_grading_table($edusign, 4, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $document = new DOMDocument();
        @$document->loadHTML($output);
        $xpath = new DOMXPath($document);

        // Check status.
        $this->assertSame(get_string('submissionstatus_submitted', 'edusign'), $xpath->evaluate('string(//td[@id="mod_edusign_grading_r0_c4"]/div[@class="submissionstatussubmitted"])'));
        $this->assertSame(get_string('submissionstatus_submitted', 'edusign'), $xpath->evaluate('string(//td[@id="mod_edusign_grading_r3_c4"]/div[@class="submissionstatussubmitted"])'));

        // Check submission last modified date
        $this->assertGreaterThan(0, strtotime($xpath->evaluate('string(//td[@id="mod_edusign_grading_r0_c8"])')));
        $this->assertGreaterThan(0, strtotime($xpath->evaluate('string(//td[@id="mod_edusign_grading_r3_c8"])')));

        // Check group.
        $this->assertSame($group->name, $xpath->evaluate('string(//td[@id="mod_edusign_grading_r0_c5"])'));
        $this->assertSame($group->name, $xpath->evaluate('string(//td[@id="mod_edusign_grading_r3_c5"])'));

        // Check submission text.
        $this->assertSame('Submission text', $xpath->evaluate('string(//td[@id="mod_edusign_grading_r0_c9"]/div/div)'));
        $this->assertSame('Submission text', $xpath->evaluate('string(//td[@id="mod_edusign_grading_r3_c9"]/div/div)'));

        // Check comments can be made.
        $this->assertSame(1, (int)$xpath->evaluate('count(//td[@id="mod_edusign_grading_r0_c10"]//textarea)'));
        $this->assertSame(1, (int)$xpath->evaluate('count(//td[@id="mod_edusign_grading_r3_c10"]//textarea)'));
    }

    public function test_show_intro()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        // Test whether we are showing the intro at the correct times.
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, ['alwaysshowdescription' => 1]);

        $this->assertEquals(true, $edusign->testable_show_intro());

        $tomorrow = time() + DAYSECS;

        $edusign = $this->create_instance($course, [
                'alwaysshowdescription' => 0,
                'allowsubmissionsfromdate' => $tomorrow,
            ]);
        $this->assertEquals(false, $edusign->testable_show_intro());
        $yesterday = time() - DAYSECS;
        $edusign = $this->create_instance($course, [
                'alwaysshowdescription' => 0,
                'allowsubmissionsfromdate' => $yesterday,
            ]);
        $this->assertEquals(true, $edusign->testable_show_intro());
    }

    public function test_has_submissions_or_grades()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course, ['edusignsubmission_onlinetext_enabled' => 1]);
        $instance = $edusign->get_instance();

        // Should start empty.
        $this->assertEquals(false, $edusign->has_submissions_or_grades());

        // Simulate a submission.
        $this->setUser($student);
        $submission = $edusign->get_user_submission($student->id, true);

        // The submission is still new.
        $this->assertEquals(false, $edusign->has_submissions_or_grades());

        // Submit the submission.
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $student->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $edusign->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Now test again.
        $this->assertEquals(true, $edusign->has_submissions_or_grades());
    }

    public function test_delete_grades()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course);

        // Simulate adding a grade.
        $this->setUser($teacher);
        $data = new stdClass();
        $data->grade = '50.0';
        $edusign->testable_apply_grade_to_user($data, $student->id, 0);

        // Now see if the data is in the gradebook.
        $gradinginfo = grade_get_grades($course->id, 'mod', 'edusign', $edusign->get_instance()->id);

        $this->assertNotEquals(0, count($gradinginfo->items));

        $edusign->testable_delete_grades();
        $gradinginfo = grade_get_grades($course->id, 'mod', 'edusign', $edusign->get_instance()->id);

        $this->assertEquals(0, count($gradinginfo->items));
    }

    public function test_delete_instance()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course, ['edusignsubmission_onlinetext_enabled' => 1]);

        // Simulate adding a grade.
        $this->setUser($teacher);
        $data = new stdClass();
        $data->grade = '50.0';
        $edusign->testable_apply_grade_to_user($data, $student->id, 0);

        // Simulate a submission.
        $this->add_submission($student, $edusign);

        // Now try and delete.
        $this->setUser($teacher);
        $this->assertEquals(true, $edusign->delete_instance());
    }

    public function test_reset_userdata()
    {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $now = time();
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'duedate' => $now,
            ]);

        // Simulate adding a grade.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);
        $this->mark_submission($teacher, $edusign, $student, 50.0);

        // Simulate a submission.
        $this->setUser($student);
        $submission = $edusign->get_user_submission($student->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $edusign->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        $this->assertEquals(true, $edusign->has_submissions_or_grades());
        // Now try and reset.
        $data = new stdClass();
        $data->reset_edusign_submissions = 1;
        $data->reset_gradebook_grades = 1;
        $data->reset_edusign_user_overrides = 1;
        $data->reset_edusign_group_overrides = 1;
        $data->courseid = $course->id;
        $data->timeshift = DAYSECS;
        $this->setUser($teacher);
        $edusign->reset_userdata($data);
        $this->assertEquals(false, $edusign->has_submissions_or_grades());

        // Reload the instance data.
        $instance = $DB->get_record('edusign', array('id' => $edusign->get_instance()->id));
        $this->assertEquals($now + DAYSECS, $instance->duedate);

        // Test reset using edusign_reset_userdata().
        $edusignduedate = $instance->duedate; // Keep old updated value for comparison.
        $data->timeshift = (2 * DAYSECS);
        edusign_reset_userdata($data);
        $instance = $DB->get_record('edusign', array('id' => $edusign->get_instance()->id));
        $this->assertEquals($edusignduedate + (2 * DAYSECS), $instance->duedate);

        // Create one more edusignment and reset, make sure time shifted for previous edusignment is not changed.
        $edusign2 = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'duedate' => $now,
            ]);
        $edusignduedate = $instance->duedate;
        $data->timeshift = 3 * DAYSECS;
        $edusign2->reset_userdata($data);
        $instance = $DB->get_record('edusign', array('id' => $edusign->get_instance()->id));
        $this->assertEquals($edusignduedate, $instance->duedate);
        $instance2 = $DB->get_record('edusign', array('id' => $edusign2->get_instance()->id));
        $this->assertEquals($now + 3 * DAYSECS, $instance2->duedate);

        // Reset both edusignments using edusign_reset_userdata() and make sure both edusignments have same date.
        $edusignduedate = $instance->duedate;
        $edusign2duedate = $instance2->duedate;
        $data->timeshift = (4 * DAYSECS);
        edusign_reset_userdata($data);
        $instance = $DB->get_record('edusign', array('id' => $edusign->get_instance()->id));
        $this->assertEquals($edusignduedate + (4 * DAYSECS), $instance->duedate);
        $instance2 = $DB->get_record('edusign', array('id' => $edusign2->get_instance()->id));
        $this->assertEquals($edusign2duedate + (4 * DAYSECS), $instance2->duedate);
    }

    public function test_plugin_settings()
    {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $now = time();
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'edusignsubmission_file_enabled' => 1,
                'edusignsubmission_file_maxfiles' => 12,
                'edusignsubmission_file_maxsizebytes' => 10,
            ]);

        $plugin = $edusign->get_submission_plugin_by_type('file');
        $this->assertEquals('12', $plugin->get_config('maxfilesubmissions'));
    }

    public function test_update_calendar()
    {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $this->setUser($teacher);
        $userctx = context_user::instance($teacher->id)->id;

        // Hack to pretend that there was an editor involved. We need both $_POST and $_REQUEST, and a sesskey.
        $draftid = file_get_unused_draft_itemid();
        $_REQUEST['introeditor'] = $draftid;
        $_POST['introeditor'] = $draftid;
        $_POST['sesskey'] = sesskey();

        // Write links to a draft area.
        $fakearealink1 = file_rewrite_pluginfile_urls(
            '<a href="@@PLUGINFILE@@/pic.gif">link</a>',
            'draftfile.php',
            $userctx,
            'user',
            'draft',
            $draftid
        );
        $fakearealink2 = file_rewrite_pluginfile_urls(
            '<a href="@@PLUGINFILE@@/pic.gif">new</a>',
            'draftfile.php',
            $userctx,
            'user',
            'draft',
            $draftid
        );

        // Create a new edusignment with links to a draft area.
        $now = time();
        $edusign = $this->create_instance($course, [
                'duedate' => $now,
                'intro' => $fakearealink1,
                'introformat' => FORMAT_HTML
            ]);

        // See if there is an event in the calendar.
        $params = array('modulename' => 'edusign', 'instance' => $edusign->get_instance()->id);
        $event = $DB->get_record('event', $params);
        $this->assertNotEmpty($event);
        $this->assertSame('link', $event->description);     // The pluginfile links are removed.

        // Make sure the same works when updating the edusignment.
        $instance = $edusign->get_instance();
        $instance->instance = $instance->id;
        $instance->intro = $fakearealink2;
        $instance->introformat = FORMAT_HTML;
        $edusign->update_instance($instance);
        $params = array('modulename' => 'edusign', 'instance' => $edusign->get_instance()->id);
        $event = $DB->get_record('event', $params);
        $this->assertNotEmpty($event);
        $this->assertSame('new', $event->description);     // The pluginfile links are removed.

        // Create an edusignment with a description that should be hidden.
        $edusign = $this->create_instance($course, [
                'duedate' => $now + 160,
                'alwaysshowdescription' => false,
                'allowsubmissionsfromdate' => $now + 60,
                'intro' => 'Some text',
            ]);

        // Get the event from the calendar.
        $params = array('modulename' => 'edusign', 'instance' => $edusign->get_instance()->id);
        $event = $DB->get_record('event', [
                'modulename' => 'edusign',
                'instance' => $edusign->get_instance()->id,
            ]);

        $this->assertEmpty($event->description);

        // Change the allowsubmissionfromdate to the past - do this directly in the DB
        // because if we call the edusignment update method - it will update the calendar
        // and we want to test that this works from cron.
        $DB->set_field('edusign', 'allowsubmissionsfromdate', $now - 60, array('id' => $edusign->get_instance()->id));
        // Run cron to update the event in the calendar.
        edusign::cron();
        $event = $DB->get_record('event', $params);

        $this->assertContains('Some text', $event->description);
    }

    public function test_update_instance()
    {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course, ['edusignsubmission_onlinetext_enabled' => 1]);

        $now = time();
        $instance = $edusign->get_instance();
        $instance->duedate = $now;
        $instance->instance = $instance->id;
        $instance->edusignsubmission_onlinetext_enabled = 1;

        $edusign->update_instance($instance);

        $instance = $DB->get_record('edusign', ['id' => $edusign->get_instance()->id]);
        $this->assertEquals($now, $instance->duedate);
    }

    public function test_cannot_submit_empty()
    {
        global $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, ['submissiondrafts' => 1]);

        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Test you cannot see the submit button for an offline edusignment regardless.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotContains(get_string('submitedusignment', 'edusign'), $output, 'Can submit empty offline edusignment');
    }

    public function test_cannot_submit_empty_no_submission()
    {
        global $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, [
            'submissiondrafts' => 1,
            'edusignsubmission_onlinetext_enabled' => 1,
        ]);

        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Test you cannot see the submit button for an online text edusignment with no submission.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotContains(get_string('submitedusignment', 'edusign'), $output, 'Cannot submit empty onlinetext edusignment');
    }

    public function test_can_submit_with_submission()
    {
        global $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, [
            'submissiondrafts' => 1,
            'edusignsubmission_onlinetext_enabled' => 1,
        ]);

        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Add a draft.
        $this->add_submission($student, $edusign);

        // Test you can see the submit button for an online text edusignment with a submission.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertContains(get_string('submitedusignment', 'edusign'), $output, 'Can submit non empty onlinetext edusignment');
    }

    /**
     * Test new_submission_empty
     *
     * We only test combinations of plugins here. Individual plugins are tested
     * in their respective test files.
     *
     * @dataProvider test_new_submission_empty_testcases
     * @param string $data The file submission data
     * @param bool $expected The expected return value
     */
    public function test_new_submission_empty($data, $expected)
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, [
                'edusignsubmission_file_enabled' => 1,
                'edusignsubmission_file_maxfiles' => 12,
                'edusignsubmission_file_maxsizebytes' => 10,
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);
        $this->setUser($student);
        $submission = new stdClass();

        if ($data['file'] && isset($data['file']['filename'])) {
            $itemid = file_get_unused_draft_itemid();
            $submission->files_filemanager = $itemid;
            $data['file'] += ['contextid' => context_user::instance($student->id)->id, 'itemid' => $itemid];
            $fs = get_file_storage();
            $fs->create_file_from_string((object)$data['file'], 'Content of ' . $data['file']['filename']);
        }

        if ($data['onlinetext']) {
            $submission->onlinetext_editor = ['text' => $data['onlinetext']];
        }

        $result = $edusign->new_submission_empty($submission);
        $this->assertTrue($result === $expected);
    }

    /**
     * Dataprovider for the test_new_submission_empty testcase
     *
     * @return array of testcases
     */
    public function test_new_submission_empty_testcases()
    {
        return [
            'With file and onlinetext' => [
                [
                    'file' => [
                        'component' => 'user',
                        'filearea' => 'draft',
                        'filepath' => '/',
                        'filename' => 'not_a_virus.exe'
                    ],
                    'onlinetext' => 'Balin Fundinul Uzbadkhazaddumu'
                ],
                false
            ]
        ];
    }

    public function test_list_participants()
    {
        global $CFG;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        // Create 10 students.
        for ($i = 0; $i < 10; $i++) {
            $this->getDataGenerator()->create_and_enrol($course, 'student');
        }

        $this->setUser($teacher);
        $edusign = $this->create_instance($course, ['grade' => 100]);

        $this->assertCount(10, $edusign->list_participants(null, true));
    }

    public function test_list_participants_activeenrol()
    {
        global $CFG, $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        // Create 10 students.
        for ($i = 0; $i < 10; $i++) {
            $this->getDataGenerator()->create_and_enrol($course, 'student');
        }

        // Create 10 suspended students.
        for ($i = 0; $i < 10; $i++) {
            $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        }

        $this->setUser($teacher);
        set_user_preference('grade_report_showonlyactiveenrol', false);
        $edusign = $this->create_instance($course, ['grade' => 100]);

        $this->assertCount(10, $edusign->list_participants(null, true));
    }

    public function test_list_participants_with_group_restriction()
    {
        global $CFG;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $unrelatedstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Turn on availability and a group restriction, and check that it doesn't show users who aren't in the group.
        $CFG->enableavailability = true;

        $specialgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $edusign = $this->create_instance($course, [
                'grade' => 100,
                'availability' => json_encode(
                    \core_availability\tree::get_root_json([\availability_group\condition::get_json($specialgroup->id)])
                ),
            ]);

        groups_add_member($specialgroup, $student);
        groups_add_member($specialgroup, $otherstudent);
        $this->assertEquals(2, count($edusign->list_participants(null, true)));
    }

    public function test_get_participant_user_not_exist()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $edusign = $this->create_instance($course);
        $this->assertNull($edusign->get_participant('-1'));
    }

    public function test_get_participant_not_enrolled()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->create_instance($course);

        $user = $this->getDataGenerator()->create_user();
        $this->assertNull($edusign->get_participant($user->id));
    }

    public function test_get_participant_no_submission()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->create_instance($course);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $participant = $edusign->get_participant($student->id);

        $this->assertEquals($student->id, $participant->id);
        $this->assertFalse($participant->submitted);
        $this->assertFalse($participant->requiregrading);
        $this->assertFalse($participant->grantedextension);
    }

    public function test_get_participant_granted_extension()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->create_instance($course);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $edusign->save_user_extension($student->id, time());
        $participant = $edusign->get_participant($student->id);

        $this->assertEquals($student->id, $participant->id);
        $this->assertFalse($participant->submitted);
        $this->assertFalse($participant->requiregrading);
        $this->assertTrue($participant->grantedextension);
    }

    public function test_get_participant_with_ungraded_submission()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->create_instance($course);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Simulate a submission.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);

        $participant = $edusign->get_participant($student->id);

        $this->assertEquals($student->id, $participant->id);
        $this->assertTrue($participant->submitted);
        $this->assertTrue($participant->requiregrading);
        $this->assertFalse($participant->grantedextension);
    }

    public function test_get_participant_with_graded_submission()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->create_instance($course);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Simulate a submission.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);

        $this->mark_submission($teacher, $edusign, $student, 50.0);

        $data = new stdClass();
        $data->grade = '50.0';
        $edusign->testable_apply_grade_to_user($data, $student->id, 0);

        $participant = $edusign->get_participant($student->id);

        $this->assertEquals($student->id, $participant->id);
        $this->assertTrue($participant->submitted);
        $this->assertFalse($participant->requiregrading);
        $this->assertFalse($participant->grantedextension);
    }

    /**
     * No active group and non-group submissions disallowed => 2 groups.
     */
    public function test_count_teams_no_active_non_group_allowed()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group1, $student1);
        groups_add_member($group2, $student2);

        $this->setUser($teacher);
        $edusign = $this->create_instance($course, ['teamsubmission' => 1]);

        $this->assertEquals(2, $edusign->count_teams());
    }

    /**
     * No active group and non group submissions allowed => 2 groups + the default one.
     */
    public function test_count_teams_non_group_allowed()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $this->getDataGenerator()->create_grouping_group(array('groupid' => $group1->id, 'groupingid' => $grouping->id));
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group(array('groupid' => $group2->id, 'groupingid' => $grouping->id));

        groups_add_member($group1, $student1);
        groups_add_member($group2, $student2);

        $edusign = $this->create_instance($course, [
                'teamsubmission' => 1,
                'teamsubmissiongroupingid' => $grouping->id,
                'preventsubmissionnotingroup' => false,
            ]);

        $this->setUser($teacher);
        $this->assertEquals(3, $edusign->count_teams());

        // Active group only.
        $this->assertEquals(1, $edusign->count_teams($group1->id));
        $this->assertEquals(1, $edusign->count_teams($group2->id));
    }

    /**
     * Active group => just selected one.
     */
    public function test_count_teams_no_active_group()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $this->getDataGenerator()->create_grouping_group(array('groupid' => $group1->id, 'groupingid' => $grouping->id));
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group(array('groupid' => $group2->id, 'groupingid' => $grouping->id));

        groups_add_member($group1, $student1);
        groups_add_member($group2, $student2);

        $edusign = $this->create_instance($course, [
                'teamsubmission' => 1,
                'preventsubmissionnotingroup' => true,
            ]);

        $this->setUser($teacher);
        $this->assertEquals(2, $edusign->count_teams());

        // Active group only.
        $this->assertEquals(1, $edusign->count_teams($group1->id));
        $this->assertEquals(1, $edusign->count_teams($group2->id));
    }

    /**
     * Active group => just selected one.
     */
    public function test_count_teams_groups_only()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));

        $edusign = $this->create_instance($course, [
                'teamsubmission' => 1,
                'teamsubmissiongroupingid' => $grouping->id,
                'preventsubmissionnotingroup' => false,
            ]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group1, $student1);

        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group2, $student2);

        $this->getDataGenerator()->create_grouping_group(array('groupid' => $group1->id, 'groupingid' => $grouping->id));
        $this->getDataGenerator()->create_grouping_group(array('groupid' => $group2->id, 'groupingid' => $grouping->id));

        $this->setUser($teacher);

        $edusign = $this->create_instance($course, [
                'teamsubmission' => 1,
                'preventsubmissionnotingroup' => true,
            ]);
        $this->assertEquals(2, $edusign->count_teams());
    }

    public function test_submit_to_default_group()
    {
        global $DB, $SESSION;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $edusign = $this->create_instance($course, [
                'teamsubmission' => 1,
                'edusignsubmission_onlinetext_enabled' => 1,
                'submissiondrafts' => 0,
                'groupmode' => VISIBLEGROUPS,
            ]);

        $usergroup = $edusign->get_submission_group($student->id);
        $this->assertFalse($usergroup, 'New student is in default group');

        // Add a submission.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);

        // Set active groups to all groups.
        $this->setUser($teacher);
        $SESSION->activegroup[$course->id]['aag'][0] = 0;
        $this->assertEquals(1, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_SUBMITTED));

        // Set an active group.
        $SESSION->activegroup[$course->id]['aag'][0] = (int) $group->id;
        $this->assertEquals(0, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_SUBMITTED));
    }

    public function test_count_submissions_no_draft()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);

        $edusign->get_user_submission($student->id, true);

        // Note: Drafts count as a submission.
        $this->assertEquals(0, $edusign->count_grades());
        $this->assertEquals(0, $edusign->count_submissions());
        $this->assertEquals(1, $edusign->count_submissions(true));
        $this->assertEquals(0, $edusign->count_submissions_need_grading());
        $this->assertEquals(1, $edusign->count_submissions_with_status(edusign_SUBMISSION_STATUS_NEW));
        $this->assertEquals(0, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_DRAFT));
        $this->assertEquals(0, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_SUBMITTED));
        $this->assertEquals(0, $edusign->count_submissions_with_status(edusign_SUBMISSION_STATUS_REOPENED));
    }

    public function test_count_submissions_draft()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);

        $this->add_submission($student, $edusign);

        // Note: Drafts count as a submission.
        $this->assertEquals(0, $edusign->count_grades());
        $this->assertEquals(1, $edusign->count_submissions());
        $this->assertEquals(1, $edusign->count_submissions(true));
        $this->assertEquals(0, $edusign->count_submissions_need_grading());
        $this->assertEquals(0, $edusign->count_submissions_with_status(edusign_SUBMISSION_STATUS_NEW));
        $this->assertEquals(1, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_DRAFT));
        $this->assertEquals(0, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_SUBMITTED));
        $this->assertEquals(0, $edusign->count_submissions_with_status(edusign_SUBMISSION_STATUS_REOPENED));
    }

    public function test_count_submissions_submitted()
    {
        global $SESSION;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);

        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);

        $this->assertEquals(0, $edusign->count_grades());
        $this->assertEquals(1, $edusign->count_submissions());
        $this->assertEquals(1, $edusign->count_submissions(true));
        $this->assertEquals(1, $edusign->count_submissions_need_grading());
        $this->assertEquals(0, $edusign->count_submissions_with_status(edusign_SUBMISSION_STATUS_NEW));
        $this->assertEquals(0, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_DRAFT));
        $this->assertEquals(1, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_SUBMITTED));
        $this->assertEquals(0, $edusign->count_submissions_with_status(edusign_SUBMISSION_STATUS_REOPENED));
    }

    public function test_count_submissions_graded()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);

        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);
        $this->mark_submission($teacher, $edusign, $student, 50.0);

        // Although it has been graded, it is still marked as submitted.
        $this->assertEquals(1, $edusign->count_grades());
        $this->assertEquals(1, $edusign->count_submissions());
        $this->assertEquals(1, $edusign->count_submissions(true));
        $this->assertEquals(0, $edusign->count_submissions_need_grading());
        $this->assertEquals(0, $edusign->count_submissions_with_status(edusign_SUBMISSION_STATUS_NEW));
        $this->assertEquals(0, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_DRAFT));
        $this->assertEquals(1, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_SUBMITTED));
        $this->assertEquals(0, $edusign->count_submissions_with_status(edusign_SUBMISSION_STATUS_REOPENED));
    }

    public function test_count_submissions_graded_group()
    {
        global $SESSION;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $othergroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group, $student);

        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'groupmode' => VISIBLEGROUPS,
            ]);

        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);

        // The user should still be listed when fetching all groups.
        $this->setUser($teacher);
        $SESSION->activegroup[$course->id]['aag'][0] = 0;
        $this->assertEquals(1, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_SUBMITTED));

        // The user should still be listed when fetching just their group.
        $SESSION->activegroup[$course->id]['aag'][0] = $group->id;
        $this->assertEquals(1, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_SUBMITTED));

        // The user should still be listed when fetching just their group.
        $SESSION->activegroup[$course->id]['aag'][0] = $othergroup->id;
        $this->assertEquals(0, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_SUBMITTED));
    }

    // TODO
    public function x_test_count_submissions_for_team()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $othergroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group, $student);

        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'teamsubmission' => 1,
            ]);

        // Add a graded submission.
        $this->add_submission($student, $edusign);

        // Simulate adding a grade.
        $this->setUser($teacher);
        $data = new stdClass();
        $data->grade = '50.0';
        $edusign->testable_apply_grade_to_user($data, $this->extrastudents[0]->id, 0);

        // Simulate a submission.
        $this->setUser($this->extrastudents[1]);
        $submission = $edusign->get_group_submission($this->extrastudents[1]->id, $groupid, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $this->extrastudents[1]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $edusign->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission.
        $this->setUser($this->extrastudents[2]);
        $submission = $edusign->get_group_submission($this->extrastudents[2]->id, $groupid, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $this->extrastudents[2]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $edusign->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission.
        $this->setUser($this->extrastudents[3]);
        $submission = $edusign->get_group_submission($this->extrastudents[3]->id, $groupid, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $this->extrastudents[3]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $edusign->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate adding a grade.
        $this->setUser($teacher);
        $data = new stdClass();
        $data->grade = '50.0';
        $edusign->testable_apply_grade_to_user($data, $this->extrastudents[3]->id, 0);
        $edusign->testable_apply_grade_to_user($data, $this->extrasuspendedstudents[0]->id, 0);

        // Create a new submission with status NEW.
        $this->setUser($this->extrastudents[4]);
        $submission = $edusign->get_group_submission($this->extrastudents[4]->id, $groupid, true);

        $this->assertEquals(2, $edusign->count_grades());
        $this->assertEquals(4, $edusign->count_submissions());
        $this->assertEquals(5, $edusign->count_submissions(true));
        $this->assertEquals(3, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_SUBMITTED));
        $this->assertEquals(1, $edusign->count_submissions_with_status(EDUSIGN_SUBMISSION_STATUS_DRAFT));
    }

    public function test_get_grading_userid_list_only_active()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $suspendedstudent = $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $this->setUser($teacher);

        $edusign = $this->create_instance($course);
        $this->assertCount(1, $edusign->testable_get_grading_userid_list());
    }

    public function test_get_grading_userid_list_all()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $suspendedstudent = $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $this->setUser($teacher);
        set_user_preference('grade_report_showonlyactiveenrol', false);

        $edusign = $this->create_instance($course);
        $this->assertCount(2, $edusign->testable_get_grading_userid_list());
    }

    public function test_cron()
    {
        $this->resetAfterTest();

        // First run cron so there are no messages waiting to be sent (from other tests).
        cron_setup_user();
        edusign::cron();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Now create an edusignment and add some feedback.
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'sendstudentnotifications' => 1,
            ]);

        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);
        $this->mark_submission($teacher, $edusign, $student, 50.0);

        $this->expectOutputRegex('/Done processing 1 edusignment submissions/');
        cron_setup_user();
        $sink = $this->redirectMessages();
        edusign::cron();
        $messages = $sink->get_messages();

        $this->assertEquals(1, count($messages));
        $this->assertEquals(1, $messages[0]->notification);
        $this->assertEquals($edusign->get_instance()->name, $messages[0]->contexturlname);
    }

    public function test_cron_without_notifications()
    {
        $this->resetAfterTest();

        // First run cron so there are no messages waiting to be sent (from other tests).
        cron_setup_user();
        edusign::cron();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Now create an edusignment and add some feedback.
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'sendstudentnotifications' => 1,
            ]);

        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);
        $this->mark_submission($teacher, $edusign, $student, 50.0, [
                'sendstudentnotifications' => 0,
            ]);

        cron_setup_user();
        $sink = $this->redirectMessages();
        edusign::cron();
        $messages = $sink->get_messages();

        $this->assertEquals(0, count($messages));
    }

    public function test_cron_regraded()
    {
        $this->resetAfterTest();

        // First run cron so there are no messages waiting to be sent (from other tests).
        cron_setup_user();
        edusign::cron();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Now create an edusignment and add some feedback.
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'sendstudentnotifications' => 1,
            ]);

        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);
        $this->mark_submission($teacher, $edusign, $student, 50.0);

        $this->expectOutputRegex('/Done processing 1 edusignment submissions/');
        cron_setup_user();
        edusign::cron();

        // Regrade.
        $this->mark_submission($teacher, $edusign, $student, 50.0);

        $this->expectOutputRegex('/Done processing 1 edusignment submissions/');
        cron_setup_user();
        $sink = $this->redirectMessages();
        edusign::cron();
        $messages = $sink->get_messages();

        $this->assertEquals(1, count($messages));
        $this->assertEquals(1, $messages[0]->notification);
        $this->assertEquals($edusign->get_instance()->name, $messages[0]->contexturlname);
    }

    /**
     * Test delivery of grade notifications as controlled by marking workflow.
     */
    public function test_markingworkflow_cron()
    {
        $this->resetAfterTest();

        // First run cron so there are no messages waiting to be sent (from other tests).
        cron_setup_user();
        edusign::cron();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Now create an edusignment and add some feedback.
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'sendstudentnotifications' => 1,
                'markingworkflow' => 1,
            ]);

        // Mark a submission but set the workflowstate to an unreleased state.
        // This should not trigger a notification.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);
        $this->mark_submission($teacher, $edusign, $student, 50.0, [
                'sendstudentnotifications' => 1,
                'workflowstate' => edusign_MARKING_WORKFLOW_STATE_READYFORRELEASE,
            ]);

        cron_setup_user();
        $sink = $this->redirectMessages();
        edusign::cron();
        $messages = $sink->get_messages();

        $this->assertEquals(0, count($messages));

        // Transition to the released state.
        $this->setUser($teacher);
        $submission = $edusign->get_user_submission($student->id, true);
        $submission->workflowstate = edusign_MARKING_WORKFLOW_STATE_RELEASED;
        $edusign->testable_apply_grade_to_user($submission, $student->id, 0);

        // Now run cron and see that one message was sent.
        cron_setup_user();
        $sink = $this->redirectMessages();
        $this->expectOutputRegex('/Done processing 1 edusignment submissions/');
        edusign::cron();
        $messages = $sink->get_messages();

        $this->assertEquals(1, count($messages));
        $this->assertEquals(1, $messages[0]->notification);
        $this->assertEquals($edusign->get_instance()->name, $messages[0]->contexturlname);
    }

    public function test_cron_message_includes_courseid()
    {
        $this->resetAfterTest();

        // First run cron so there are no messages waiting to be sent (from other tests).
        cron_setup_user();
        edusign::cron();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Now create an edusignment and add some feedback.
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'sendstudentnotifications' => 1,
            ]);

        // Mark a submission but set the workflowstate to an unreleased state.
        // This should not trigger a notification.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);
        $this->mark_submission($teacher, $edusign, $student);
        phpunit_util::stop_message_redirection();

        // Now run cron and see that one message was sent.
        cron_setup_user();
        $this->preventResetByRollback();
        $sink = $this->redirectEvents();
        $this->expectOutputRegex('/Done processing 1 edusignment submissions/');
        edusign::cron();

        $events = $sink->get_events();
        $event = reset($events);
        $this->assertInstanceOf('\core\event\notification_sent', $event);
        $this->assertEquals($edusign->get_course()->id, $event->other['courseid']);
        $sink->close();
    }

    public function test_is_graded()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course);

        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);
        $this->mark_submission($teacher, $edusign, $student, 50.0);

        $this->setUser($teacher);
        $this->assertEquals(true, $edusign->testable_is_graded($student->id));
        $this->assertEquals(false, $edusign->testable_is_graded($otherstudent->id));
    }

    public function test_can_grade()
    {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course);

        $this->setUser($student);
        $this->assertEquals(false, $edusign->can_grade());

        $this->setUser($teacher);
        $this->assertEquals(true, $edusign->can_grade());

        // Test the viewgrades capability for other users.
        $this->setUser();
        $this->assertTrue($edusign->can_grade($teacher->id));
        $this->assertFalse($edusign->can_grade($student->id));

        // Test the viewgrades capability - without mod/edusign:grade.
        $this->setUser($student);

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        edusign_capability('mod/edusign:viewgrades', CAP_ALLOW, $studentrole->id, $edusign->get_context()->id);
        $this->assertEquals(false, $edusign->can_grade());
    }

    public function test_can_view_submission()
    {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $editingteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $suspendedstudent = $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $edusign = $this->create_instance($course);

        $this->setUser($student);
        $this->assertEquals(true, $edusign->can_view_submission($student->id));
        $this->assertEquals(false, $edusign->can_view_submission($otherstudent->id));
        $this->assertEquals(false, $edusign->can_view_submission($teacher->id));

        $this->setUser($teacher);
        $this->assertEquals(true, $edusign->can_view_submission($student->id));
        $this->assertEquals(true, $edusign->can_view_submission($otherstudent->id));
        $this->assertEquals(true, $edusign->can_view_submission($teacher->id));
        $this->assertEquals(false, $edusign->can_view_submission($suspendedstudent->id));

        $this->setUser($editingteacher);
        $this->assertEquals(true, $edusign->can_view_submission($student->id));
        $this->assertEquals(true, $edusign->can_view_submission($otherstudent->id));
        $this->assertEquals(true, $edusign->can_view_submission($teacher->id));
        $this->assertEquals(true, $edusign->can_view_submission($suspendedstudent->id));

        // Test the viewgrades capability - without mod/edusign:grade.
        $this->setUser($student);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        edusign_capability('mod/edusign:viewgrades', CAP_ALLOW, $studentrole->id, $edusign->get_context()->id);
        $this->assertEquals(true, $edusign->can_view_submission($student->id));
        $this->assertEquals(true, $edusign->can_view_submission($otherstudent->id));
        $this->assertEquals(true, $edusign->can_view_submission($teacher->id));
        $this->assertEquals(false, $edusign->can_view_submission($suspendedstudent->id));
    }

    public function test_update_submission()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course);

        $this->add_submission($student, $edusign);
        $submission = $edusign->get_user_submission($student->id, 0);
        $edusign->testable_update_submission($submission, $student->id, true, true);

        $this->setUser($teacher);

        // Verify the gradebook update.
        $gradinginfo = grade_get_grades($course->id, 'mod', 'edusign', $edusign->get_instance()->id, $student->id);

        $this->assertTrue(isset($gradinginfo->items[0]->grades[$student->id]));
        $this->assertEquals($student->id, $gradinginfo->items[0]->grades[$student->id]->usermodified);
    }

    public function test_update_submission_team()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group, $student);

        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group, $otherstudent);

        $edusign = $this->create_instance($course, [
                'teamsubmission' => 1,
            ]);

        $gradinginfo = grade_get_grades($course->id, 'mod', 'edusign', $edusign->get_instance()->id, $student->id);
        $this->assertTrue(isset($gradinginfo->items[0]->grades[$student->id]));
        $this->assertNull($gradinginfo->items[0]->grades[$student->id]->usermodified);

        $gradinginfo = grade_get_grades($course->id, 'mod', 'edusign', $edusign->get_instance()->id, $otherstudent->id);
        $this->asserttrue(isset($gradinginfo->items[0]->grades[$otherstudent->id]));
        $this->assertNull($gradinginfo->items[0]->grades[$otherstudent->id]->usermodified);

        $this->add_submission($student, $edusign);
        $submission = $edusign->get_group_submission($student->id, 0, true);
        $edusign->testable_update_submission($submission, $student->id, true, true);

        // Verify the gradebook update for the student.
        $gradinginfo = grade_get_grades($course->id, 'mod', 'edusign', $edusign->get_instance()->id, $student->id);

        $this->assertTrue(isset($gradinginfo->items[0]->grades[$student->id]));
        $this->assertEquals($student->id, $gradinginfo->items[0]->grades[$student->id]->usermodified);

        // Verify the gradebook update for the other student.
        $gradinginfo = grade_get_grades($course->id, 'mod', 'edusign', $edusign->get_instance()->id, $otherstudent->id);

        $this->assertTrue(isset($gradinginfo->items[0]->grades[$otherstudent->id]));
        $this->assertEquals($otherstudent->id, $gradinginfo->items[0]->grades[$otherstudent->id]->usermodified);
    }

    public function test_update_submission_suspended()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $edusign = $this->create_instance($course);

        $this->add_submission($student, $edusign);
        $submission = $edusign->get_user_submission($student->id, 0);
        $edusign->testable_update_submission($submission, $student->id, true, false);

        $this->setUser($teacher);

        // Verify the gradebook update.
        $gradinginfo = grade_get_grades($course->id, 'mod', 'edusign', $edusign->get_instance()->id, $student->id);

        $this->assertTrue(isset($gradinginfo->items[0]->grades[$student->id]));
        $this->assertEquals($student->id, $gradinginfo->items[0]->grades[$student->id]->usermodified);
    }

    public function test_update_submission_blind()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, [
                'blindmarking' => 1,
            ]);

        $this->add_submission($student, $edusign);
        $submission = $edusign->get_user_submission($student->id, 0);
        $edusign->testable_update_submission($submission, $student->id, true, false);

        // Verify the gradebook update.
        $gradinginfo = grade_get_grades($course->id, 'mod', 'edusign', $edusign->get_instance()->id, $student->id);

        // The usermodified is not set because this is blind marked.
        $this->assertTrue(isset($gradinginfo->items[0]->grades[$student->id]));
        $this->assertNull($gradinginfo->items[0]->grades[$student->id]->usermodified);
    }

    public function test_group_submissions_submit_for_marking_requireallteammemberssubmit()
    {
        global $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group, $student);

        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group, $otherstudent);

        $edusign = $this->create_instance($course, [
                'teamsubmission' => 1,
                'edusignsubmission_onlinetext_enabled' => 1,
                'submissiondrafts' => 1,
                'requireallteammemberssubmit' => 1,
            ]);

        // Now verify group edusignments.
        $this->setUser($teacher);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Add a submission.
        $this->add_submission($student, $edusign);

        // Check we can see the submit button.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertContains(get_string('submitedusignment', 'edusign'), $output);

        $submission = $edusign->get_group_submission($student->id, 0, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $student->id, true, true);

        // Check that the student does not see "Submit" button.
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotContains(get_string('submitedusignment', 'edusign'), $output);

        // Change to another user in the same group.
        $this->setUser($otherstudent);
        $output = $edusign->view_student_summary($otherstudent, true);
        $this->assertContains(get_string('submitedusignment', 'edusign'), $output);

        $submission = $edusign->get_group_submission($otherstudent->id, 0, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $otherstudent->id, true, true);
        $output = $edusign->view_student_summary($otherstudent, true);
        $this->assertNotContains(get_string('submitedusignment', 'edusign'), $output);
    }

    public function test_group_submissions_submit_for_marking()
    {
        global $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group, $student);

        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group, $otherstudent);

        // Now verify group edusignments.
        $this->setUser($teacher);
        $time = time();
        $edusign = $this->create_instance($course, [
                'teamsubmission' => 1,
                'edusignsubmission_onlinetext_enabled' => 1,
                'submissiondrafts' => 1,
                'requireallteammemberssubmit' => 0,
                'duedate' => $time - (2 * DAYSECS),
            ]);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Add a submission.
        $this->add_submission($student, $edusign);

        // Check we can see the submit button.
        $output = $edusign->view_student_summary($student, true);
        $this->assertContains(get_string('submitedusignment', 'edusign'), $output);
        $this->assertContains(get_string('timeremaining', 'edusign'), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('overdue', 'edusign', format_time((2 * DAYSECS) + $difftime)), $output);

        $submission = $edusign->get_group_submission($student->id, 0, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $student->id, true, true);

        // Check that the student does not see "Submit" button.
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotContains(get_string('submitedusignment', 'edusign'), $output);

        // Change to another user in the same group.
        $this->setUser($otherstudent);
        $output = $edusign->view_student_summary($otherstudent, true);
        $this->assertNotContains(get_string('submitedusignment', 'edusign'), $output);

        // Check that time remaining is not overdue.
        $this->assertContains(get_string('timeremaining', 'edusign'), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('submittedlate', 'edusign', format_time((2 * DAYSECS) + $difftime)), $output);

        $submission = $edusign->get_group_submission($otherstudent->id, 0, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $otherstudent->id, true, true);
        $output = $edusign->view_student_summary($otherstudent, true);
        $this->assertNotContains(get_string('submitedusignment', 'edusign'), $output);
    }

    public function test_submissions_open()
    {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $editingteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $suspendedstudent = $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $this->setAdminUser();

        $now = time();
        $tomorrow = $now + DAYSECS;
        $oneweek = $now + WEEKSECS;
        $yesterday = $now - DAYSECS;

        $edusign = $this->create_instance($course);
        $this->assertEquals(true, $edusign->testable_submissions_open($student->id));

        $edusign = $this->create_instance($course, ['duedate' => $tomorrow]);
        $this->assertEquals(true, $edusign->testable_submissions_open($student->id));

        $edusign = $this->create_instance($course, ['duedate' => $yesterday]);
        $this->assertEquals(true, $edusign->testable_submissions_open($student->id));

        $edusign = $this->create_instance($course, ['duedate' => $yesterday, 'cutoffdate' => $tomorrow]);
        $this->assertEquals(true, $edusign->testable_submissions_open($student->id));

        $edusign = $this->create_instance($course, ['duedate' => $yesterday, 'cutoffdate' => $yesterday]);
        $this->assertEquals(false, $edusign->testable_submissions_open($student->id));

        $edusign->testable_save_user_extension($student->id, $tomorrow);
        $this->assertEquals(true, $edusign->testable_submissions_open($student->id));

        $edusign = $this->create_instance($course, ['submissiondrafts' => 1]);
        $this->assertEquals(true, $edusign->testable_submissions_open($student->id));

        $this->setUser($student);
        $submission = $edusign->get_user_submission($student->id, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $edusign->testable_update_submission($submission, $student->id, true, false);

        $this->setUser($teacher);
        $this->assertEquals(false, $edusign->testable_submissions_open($student->id));
    }

    public function test_get_graders()
    {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $editingteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setAdminUser();

        // Create an edusignment with no groups.
        $edusign = $this->create_instance($course);
        $this->assertCount(2, $edusign->testable_get_graders($student->id));
    }

    public function test_get_graders_separate_groups()
    {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $editingteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group1, $student);

        $this->setAdminUser();

        // Force create an edusignment with SEPARATEGROUPS.
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));

        $edusign = $this->create_instance($course, [
                'groupingid' => $grouping->id,
                'groupmode' => SEPARATEGROUPS,
            ]);

        $this->assertCount(4, $edusign->testable_get_graders($student->id));

        // Note the second student is in a group that is not in the grouping.
        // This means that we get all graders that are not in a group in the grouping.
        $this->assertCount(4, $edusign->testable_get_graders($otherstudent->id));
    }

    public function test_get_notified_users()
    {
        global $CFG, $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group(array('groupid' => $group1->id, 'groupingid' => $grouping->id));

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        groups_add_member($group1, $teacher);

        $editingteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        groups_add_member($group1, $editingteacher);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group1, $student);

        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $capability = 'mod/edusign:receivegradernotifications';
        $coursecontext = context_course::instance($course->id);
        $role = $DB->get_record('role', array('shortname' => 'teacher'));

        $this->setUser($teacher);

        // Create an edusignment with no groups.
        $edusign = $this->create_instance($course);

        $this->assertCount(3, $edusign->testable_get_notifiable_users($student->id));

        // Change nonediting teachers role to not receive grader notifications.
        edusign_capability($capability, CAP_PROHIBIT, $role->id, $coursecontext);

        // Only the editing teachers will be returned.
        $this->assertCount(1, $edusign->testable_get_notifiable_users($student->id));

        // Note the second student is in a group that is not in the grouping.
        // This means that we get all graders that are not in a group in the grouping.
        $this->assertCount(1, $edusign->testable_get_notifiable_users($otherstudent->id));
    }

    public function test_get_notified_users_in_grouping()
    {
        global $CFG, $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group(array('groupid' => $group1->id, 'groupingid' => $grouping->id));

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        groups_add_member($group1, $teacher);

        $editingteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        groups_add_member($group1, $editingteacher);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group1, $student);

        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        // Force create an edusignment with SEPARATEGROUPS.
        $edusign = $this->create_instance($course, [
                'groupingid' => $grouping->id,
                'groupmode' => SEPARATEGROUPS,
            ]);

        // Student is in a group - only the tacher and editing teacher in the group shoudl be present.
        $this->setUser($student);
        $this->assertCount(2, $edusign->testable_get_notifiable_users($student->id));

        // Note the second student is in a group that is not in the grouping.
        // This means that we get all graders that are not in a group in the grouping.
        $this->assertCount(1, $edusign->testable_get_notifiable_users($otherstudent->id));

        // Change nonediting teachers role to not receive grader notifications.
        $capability = 'mod/edusign:receivegradernotifications';
        $coursecontext = context_course::instance($course->id);
        $role = $DB->get_record('role', ['shortname' => 'teacher']);
        edusign_capability($capability, CAP_PROHIBIT, $role->id, $coursecontext);

        // Only the editing teachers will be returned.
        $this->assertCount(1, $edusign->testable_get_notifiable_users($student->id));

        // Note the second student is in a group that is not in the grouping.
        // This means that we get all graders that are not in a group in the grouping.
        // Unfortunately there are no editing teachers who are not in a group.
        $this->assertCount(0, $edusign->testable_get_notifiable_users($otherstudent->id));
    }

    public function test_group_members_only()
    {
        global $CFG;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group([
                'groupid' => $group1->id,
                'groupingid' => $grouping->id,
            ]);

        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group([
                'groupid' => $group2->id,
                'groupingid' => $grouping->id,
            ]);

        $group3 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // Add users in the following groups
        // - Teacher - Group 1.
        // - Student - Group 1.
        // - Student - Group 2.
        // - Student - Unrelated Group
        // - Student - No group.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        groups_add_member($group1, $teacher);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group1, $student);

        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group2, $otherstudent);

        $yetotherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group2, $otherstudent);

        $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setAdminUser();

        $CFG->enableavailability = true;
        $edusign = $this->create_instance($course, [], [
                'availability' => json_encode(
                    \core_availability\tree::get_root_json([\availability_grouping\condition::get_json()])
                ),
                'groupingid' => $grouping->id,
            ]);

        // The two students in groups should be returned, but not the teacher in the group, or the student not in the
        // group, or the student in an unrelated group.
        $this->setUser($teacher);
        $participants = $edusign->list_participants(0, true);
        $this->assertCount(2, $participants);
        $this->assertTrue(isset($participants[$student->id]));
        $this->assertTrue(isset($participants[$otherstudent->id]));
    }

    public function test_get_uniqueid_for_user()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $students = [];
        for ($i = 0; $i < 10; $i++) {
            $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
            $students[$student->id] = $student;
        }

        $this->setUser($teacher);
        $edusign = $this->create_instance($course);

        foreach ($students as $student) {
            $uniqueid = $edusign->get_uniqueid_for_user($student->id);
            $this->assertEquals($student->id, $edusign->get_user_id_for_uniqueid($uniqueid));
        }
    }

    public function test_show_student_summary()
    {
        global $CFG, $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($teacher);
        $edusign = $this->create_instance($course);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // No feedback should be available because this student has not been graded.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotRegexp('/Feedback/', $output, 'Do not show feedback if there is no grade');

        // Simulate adding a grade.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);
        $this->mark_submission($teacher, $edusign, $student);

        // Now we should see the feedback.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertRegexp('/Feedback/', $output, 'Show feedback if there is a grade');

        // Now hide the grade in gradebook.
        $this->setUser($teacher);
        require_once($CFG->libdir.'/gradelib.php');
        $gradeitem = new grade_item(array(
            'itemtype'      => 'mod',
            'itemmodule'    => 'edusign',
            'iteminstance'  => $edusign->get_instance()->id,
            'courseid'      => $course->id));

        $gradeitem->set_hidden(1, false);

        // No feedback should be available because the grade is hidden.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotRegexp('/Feedback/', $output, 'Do not show feedback if the grade is hidden in the gradebook');
    }

    public function test_show_student_summary_with_feedback()
    {
        global $CFG, $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'edusignfeedback_comments_enabled' => 1
            ]);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // No feedback should be available because this student has not been graded.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotRegexp('/Feedback/', $output);

        // Simulate adding a grade.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);
        $this->mark_submission($teacher, $edusign, $student, null, [
                'edusignfeedbackcomments_editor' => [
                    'text' => 'Tomato sauce',
                    'format' => FORMAT_MOODLE,
                ],
            ]);

        // Should have feedback but no grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertRegexp('/Feedback/', $output);
        $this->assertRegexp('/Tomato sauce/', $output);
        $this->assertNotRegexp('/Grade/', $output, 'Do not show grade when there is no grade.');
        $this->assertNotRegexp('/Graded on/', $output, 'Do not show graded date when there is no grade.');

        // Add a grade now.
        $this->mark_submission($teacher, $edusign, $student, 50.0, [
                'edusignfeedbackcomments_editor' => [
                    'text' => 'Bechamel sauce',
                    'format' => FORMAT_MOODLE,
                ],
            ]);

        // Should have feedback but no grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotRegexp('/Tomato sauce/', $output);
        $this->assertRegexp('/Bechamel sauce/', $output);
        $this->assertRegexp('/Grade/', $output);
        $this->assertRegexp('/Graded on/', $output);

        // Now hide the grade in gradebook.
        $this->setUser($teacher);
        $gradeitem = new grade_item(array(
            'itemtype'      => 'mod',
            'itemmodule'    => 'edusign',
            'iteminstance'  => $edusign->get_instance()->id,
            'courseid'      => $course->id));

        $gradeitem->set_hidden(1, false);

        // No feedback should be available because the grade is hidden.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotRegexp('/Feedback/', $output, 'Do not show feedback if the grade is hidden in the gradebook');
    }

    /**
     * Test reopen behavior when in "Manual" mode.
     */
    public function test_attempt_reopen_method_manual()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $edusign = $this->create_instance($course, [
                'attemptreopenmethod' => EDUSIGN_ATTEMPT_REOPEN_METHOD_MANUAL,
                'maxattempts' => 3,
                'submissiondrafts' => 1,
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Student should be able to see an add submission button.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotEquals(false, strpos($output, get_string('addsubmission', 'edusign')));

        // Add a submission.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);

        // Verify the student cannot make changes to the submission.
        $output = $edusign->view_student_summary($student, true);
        $this->assertEquals(false, strpos($output, get_string('addsubmission', 'edusign')));

        // Mark the submission.
        $this->mark_submission($teacher, $edusign, $student);

        // Check the student can see the grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotEquals(false, strpos($output, '50.0'));

        // Allow the student another attempt.
        $teacher->ignoresesskey = true;
        $this->setUser($teacher);
        $result = $edusign->testable_process_add_attempt($student->id);
        $this->assertEquals(true, $result);

        // Check that the previous attempt is now in the submission history table.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        // Need a better check.
        $this->assertNotEquals(false, strpos($output, 'Submission text'), 'Contains: Submission text');

        // Check that the student now has a button for Add a new attempt".
        $this->assertNotEquals(false, strpos($output, get_string('addnewattempt', 'edusign')));
        // Check that the student now does not have a button for Submit.
        $this->assertEquals(false, strpos($output, get_string('submitedusignment', 'edusign')));

        // Check that the student now has a submission history.
        $this->assertNotEquals(false, strpos($output, get_string('attempthistory', 'edusign')));

        $this->setUser($teacher);
        // Check that the grading table loads correctly and contains this user.
        // This is also testing that we do not get duplicate rows in the grading table.
        $gradingtable = new edusign_grading_table($edusign, 100, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, $student->lastname));

        // Should be 1 not 2.
        $this->assertEquals(1, $edusign->count_submissions());
        $this->assertEquals(1, $edusign->count_submissions_with_status('reopened'));
        $this->assertEquals(0, $edusign->count_submissions_need_grading());
        $this->assertEquals(1, $edusign->count_grades());

        // Change max attempts to unlimited.
        $formdata = clone($edusign->get_instance());
        $formdata->maxattempts = EDUSIGN_UNLIMITED_ATTEMPTS;
        $formdata->instance = $formdata->id;
        $edusign->update_instance($formdata);

        // Mark the submission again.
        $this->mark_submission($teacher, $edusign, $student, 60.0, [], 1);

        // Check the grade exists.
        $this->setUser($teacher);
        $grades = $edusign->get_user_grades_for_gradebook($student->id);
        $this->assertEquals(60, (int) $grades[$student->id]->rawgrade);

        // Check we can reopen still.
        $result = $edusign->testable_process_add_attempt($student->id);
        $this->assertEquals(true, $result);

        // Should no longer have a grade because there is no grade for the latest attempt.
        $grades = $edusign->get_user_grades_for_gradebook($student->id);
        $this->assertEmpty($grades);
    }

    /**
     * Test reopen behavior when in "Reopen until pass" mode.
     */
    public function test_attempt_reopen_method_untilpass()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $edusign = $this->create_instance($course, [
                'attemptreopenmethod' => edusign_ATTEMPT_REOPEN_METHOD_UNTILPASS,
                'maxattempts' => 3,
                'submissiondrafts' => 1,
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Set grade to pass to 80.
        $gradeitem = $edusign->get_grade_item();
        $gradeitem->gradepass = '80.0';
        $gradeitem->update();

        // Student should be able to see an add submission button.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotEquals(false, strpos($output, get_string('addsubmission', 'edusign')));

        // Add a submission.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);

        // Verify the student cannot make a new attempt.
        $output = $edusign->view_student_summary($student, true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'edusign')));

        // Mark the submission as non-passing.
        $this->mark_submission($teacher, $edusign, $student, 50.0);

        // Check the student can see the grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotEquals(false, strpos($output, '50.0'));

        // Check that the student now has a button for Add a new attempt.
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotEquals(false, strpos($output, get_string('addnewattempt', 'edusign')));

        // Check that the student now does not have a button for Submit.
        $this->assertEquals(false, strpos($output, get_string('submitedusignment', 'edusign')));

        // Check that the student now has a submission history.
        $this->assertNotEquals(false, strpos($output, get_string('attempthistory', 'edusign')));

        // Add a second submission.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);

        // Mark the submission as passing.
        $this->mark_submission($teacher, $edusign, $student, 80.0);

        // Check that the student does not have a button for Add a new attempt.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'edusign')));

        // Re-mark the submission as not passing.
        $this->mark_submission($teacher, $edusign, $student, 40.0, [], 1);

        // Check that the student now has a button for Add a new attempt.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertRegexp('/' . get_string('addnewattempt', 'edusign') . '/', $output);
        $this->assertNotEquals(false, strpos($output, get_string('addnewattempt', 'edusign')));
    }

    public function test_attempt_reopen_method_untilpass_passing()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $edusign = $this->create_instance($course, [
                'attemptreopenmethod' => edusign_ATTEMPT_REOPEN_METHOD_UNTILPASS,
                'maxattempts' => 3,
                'submissiondrafts' => 1,
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Set grade to pass to 80.
        $gradeitem = $edusign->get_grade_item();
        $gradeitem->gradepass = '80.0';
        $gradeitem->update();

        // Student should be able to see an add submission button.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotEquals(false, strpos($output, get_string('addsubmission', 'edusign')));

        // Add a submission as a student.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);

        // Mark the submission as passing.
        $this->mark_submission($teacher, $edusign, $student, 100.0);

        // Check the student can see the grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotEquals(false, strpos($output, '100.0'));

        // Check that the student does not have a button for Add a new attempt.
        $output = $edusign->view_student_summary($student, true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'edusign')));
    }

    public function test_attempt_reopen_method_untilpass_no_passing_requirement()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $edusign = $this->create_instance($course, [
                'attemptreopenmethod' => edusign_ATTEMPT_REOPEN_METHOD_UNTILPASS,
                'maxattempts' => 3,
                'submissiondrafts' => 1,
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);
        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Set grade to pass to 0, so that no attempts should reopen.
        $gradeitem = $edusign->get_grade_item();
        $gradeitem->gradepass = '0';
        $gradeitem->update();

        // Student should be able to see an add submission button.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotEquals(false, strpos($output, get_string('addsubmission', 'edusign')));

        // Add a submission.
        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign);

        // Mark the submission with any grade.
        $this->mark_submission($teacher, $edusign, $student, 0.0);

        // Check the student can see the grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotEquals(false, strpos($output, '0.0'));

        // Check that the student does not have a button for Add a new attempt.
        $output = $edusign->view_student_summary($student, true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'edusign')));
    }

    /**
     * Test student visibility for each stage of the marking workflow.
     */
    public function test_markingworkflow()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $edusign = $this->create_instance($course, [
                'markingworkflow' => 1,
            ]);

        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Mark the submission and set to notmarked.
        $this->mark_submission($teacher, $edusign, $student, 50.0, [
                'workflowstate' => edusign_MARKING_WORKFLOW_STATE_NOTMARKED,
            ]);

        // Check the student can't see the grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $edusign->get_user_grades_for_gradebook($student->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to inmarking.
        $this->mark_submission($teacher, $edusign, $student, 50.0, [
                'workflowstate' => edusign_MARKING_WORKFLOW_STATE_INMARKING,
            ]);

        // Check the student can't see the grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $edusign->get_user_grades_for_gradebook($student->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to readyforreview.
        $this->mark_submission($teacher, $edusign, $student, 50.0, [
                'workflowstate' => edusign_MARKING_WORKFLOW_STATE_READYFORREVIEW,
            ]);

        // Check the student can't see the grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $edusign->get_user_grades_for_gradebook($student->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to inreview.
        $this->mark_submission($teacher, $edusign, $student, 50.0, [
                'workflowstate' => edusign_MARKING_WORKFLOW_STATE_INREVIEW,
            ]);

        // Check the student can't see the grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $edusign->get_user_grades_for_gradebook($student->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to readyforrelease.
        $this->mark_submission($teacher, $edusign, $student, 50.0, [
                'workflowstate' => edusign_MARKING_WORKFLOW_STATE_READYFORRELEASE,
            ]);

        // Check the student can't see the grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $edusign->get_user_grades_for_gradebook($student->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to released.
        $this->mark_submission($teacher, $edusign, $student, 50.0, [
                'workflowstate' => edusign_MARKING_WORKFLOW_STATE_RELEASED,
            ]);

        // Check the student can see the grade.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotEquals(false, strpos($output, '50.0'));

        // Make sure the grade is pushed to the gradebook.
        $grades = $edusign->get_user_grades_for_gradebook($student->id);
        $this->assertEquals(50, (int)$grades[$student->id]->rawgrade);
    }

    /**
     * Test that a student allocated a specific marker is only shown to that marker.
     */
    public function test_markerallocation()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $otherteacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $edusign = $this->create_instance($course, [
                'markingworkflow' => 1,
                'markingallocation' => 1
            ]);

        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Allocate marker to submission.
        $this->mark_submission($teacher, $edusign, $student, null, [
            'allocatedmarker' => $teacher->id,
        ]);

        // Check the allocated marker can view the submission.
        $this->setUser($teacher);
        $users = $edusign->list_participants(0, true);
        $this->assertEquals(1, count($users));
        $this->assertTrue(isset($users[$student->id]));

        $cm = get_coursemodule_from_instance('edusign', $edusign->get_instance()->id);
        $context = context_module::instance($cm->id);
        $edusign = new mod_edusign_testable_edusign($context, $cm, $course);

        // Check that other teachers can't view this submission.
        $this->setUser($otherteacher);
        $users = $edusign->list_participants(0, true);
        $this->assertEquals(0, count($users));
    }

    /**
     * Ensure that a teacher cannot submit for students as standard.
     */
    public function test_teacher_submit_for_student()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $edusign = $this->create_instance($course, [
            'edusignsubmission_onlinetext_enabled' => 1,
            'submissiondrafts' => 1,
        ]);

        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Add a submission but do not submit.
        $this->add_submission($student, $edusign, 'Student submission text');

        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertContains('Student submission text', $output, 'Contains student submission text');

        // Check that a teacher can not edit the submission as they do not have the capability.
        $this->setUser($teacher);
        $this->expectException('moodle_exception');
        $this->expectExceptionMessage('error/nopermission');
        $this->add_submission($student, $edusign, 'Teacher edited submission text', false);
    }

    /**
     * Ensure that a teacher with the editothersubmission capability can submit on behalf of a student.
     */
    public function test_teacher_submit_for_student_with_capability()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $otherteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $edusign = $this->create_instance($course, [
            'edusignsubmission_onlinetext_enabled' => 1,
            'submissiondrafts' => 1,
        ]);

        // Add the required capability.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        edusign_capability('mod/edusign:editothersubmission', CAP_ALLOW, $roleid, $edusign->get_context()->id);
        role_edusign($roleid, $teacher->id, $edusign->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();

        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Add a submission but do not submit.
        $this->add_submission($student, $edusign, 'Student submission text');

        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertContains('Student submission text', $output, 'Contains student submission text');

        // Check that a teacher can edit the submission.
        $this->setUser($teacher);
        $this->add_submission($student, $edusign, 'Teacher edited submission text', false);

        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotContains('Student submission text', $output, 'Contains student submission text');
        $this->assertContains('Teacher edited submission text', $output, 'Contains teacher edited submission text');

        // Check that the teacher can submit the students work.
        $this->setUser($teacher);
        $this->submit_for_grading($student, $edusign, [], false);

        // Revert to draft so the student can edit it.
        $edusign->revert_to_draft($student->id);

        $this->setUser($student);

        // Check that the submission text was saved.
        $output = $edusign->view_student_summary($student, true);
        $this->assertContains('Teacher edited submission text', $output, 'Contains student submission text');

        // Check that the student can submit their work.
        $this->submit_for_grading($student, $edusign, []);

        $output = $edusign->view_student_summary($student, true);
        $this->assertNotContains(get_string('addsubmission', 'edusign'), $output);

        // An editing teacher without the extra role should still be able to revert to draft.
        $this->setUser($otherteacher);

        // Revert to draft so the submission is editable.
        $edusign->revert_to_draft($student->id);
    }

    /**
     * Ensure that disabling submit after the cutoff date works as expected.
     */
    public function test_disable_submit_after_cutoff_date()
    {
        global $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $now = time();
        $tomorrow = $now + DAYSECS;
        $lastweek = $now - (7 * DAYSECS);
        $yesterday = $now - DAYSECS;

        $this->setAdminUser();
        $edusign = $this->create_instance($course, [
                'duedate' => $yesterday,
                'cutoffdate' => $tomorrow,
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);

        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Student should be able to see an add submission button.
        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotEquals(false, strpos($output, get_string('addsubmission', 'edusign')));

        // Add a submission but don't submit now.
        $this->add_submission($student, $edusign);

        // Create another instance with cut-off and due-date already passed.
        $this->setAdminUser();
        $edusign = $this->create_instance($course, [
                'duedate' => $lastweek,
                'cutoffdate' => $yesterday,
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);

        $this->setUser($student);
        $output = $edusign->view_student_summary($student, true);
        $this->assertNotContains(
            $output,
            get_string('editsubmission', 'edusign'),
            'Should not be able to edit after cutoff date.'
        );
        $this->assertNotContains(
            $output,
            get_string('submitedusignment', 'edusign'),
            'Should not be able to submit after cutoff date.'
        );
    }

    /**
     * Testing for submission comment plugin settings.
     *
     * @dataProvider submission_plugin_settings_provider
     * @param   bool    $globalenabled
     * @param   array   $instanceconfig
     * @param   bool    $isenabled
     */
    public function test_submission_comment_plugin_settings($globalenabled, $instanceconfig, $isenabled)
    {
        global $CFG;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $CFG->usecomments = $globalenabled;
        $edusign = $this->create_instance($course, $instanceconfig);
        $plugin = $edusign->get_submission_plugin_by_type('comments');
        $this->assertEquals($isenabled, (bool) $plugin->is_enabled('enabled'));
    }

    public function submission_plugin_settings_provider()
    {
        return [
            'CFG->usecomments true, empty config => Enabled by default' => [
                true,
                [],
                true,
            ],
            'CFG->usecomments true, config enabled => Comments enabled' => [
                true,
                [
                    'edusignsubmission_comments_enabled' => 1,
                ],
                true,
            ],
            'CFG->usecomments true, config idisabled => Comments enabled' => [
                true,
                [
                    'edusignsubmission_comments_enabled' => 0,
                ],
                true,
            ],
            'CFG->usecomments false, empty config => Disabled by default' => [
                false,
                [],
                false,
            ],
            'CFG->usecomments false, config enabled => Comments disabled' => [
                false,
                [
                    'edusignsubmission_comments_enabled' => 1,
                ],
                false,
            ],
            'CFG->usecomments false, config disabled => Comments disabled' => [
                false,
                [
                    'edusignsubmission_comments_enabled' => 0,
                ],
                false,
            ],
        ];
    }

    /**
     * Testing for comment inline settings
     */
    public function test_feedback_comment_commentinline()
    {
        global $CFG;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $sourcetext = "Hello!

I'm writing to you from the Moodle Majlis in Muscat, Oman, where we just had several days of Moodle community goodness.

URL outside a tag: https://moodle.org/logo/logo-240x60.gif
Plugin url outside a tag: @@PLUGINFILE@@/logo-240x60.gif

External link 1:<img src='https://moodle.org/logo/logo-240x60.gif' alt='Moodle'/>
External link 2:<img alt=\"Moodle\" src=\"https://moodle.org/logo/logo-240x60.gif\"/>
Internal link 1:<img src='@@PLUGINFILE@@/logo-240x60.gif' alt='Moodle'/>
Internal link 2:<img alt=\"Moodle\" src=\"@@PLUGINFILE@@logo-240x60.gif\"/>
Anchor link 1:<a href=\"@@PLUGINFILE@@logo-240x60.gif\" alt=\"bananas\">Link text</a>
Anchor link 2:<a title=\"bananas\" href=\"../logo-240x60.gif\">Link text</a>
";

        // Note the internal images have been stripped and the html is purified (quotes fixed in this case).
        $filteredtext = "Hello!

I'm writing to you from the Moodle Majlis in Muscat, Oman, where we just had several days of Moodle community goodness.

URL outside a tag: https://moodle.org/logo/logo-240x60.gif
Plugin url outside a tag: @@PLUGINFILE@@/logo-240x60.gif

External link 1:<img src=\"https://moodle.org/logo/logo-240x60.gif\" alt=\"Moodle\" />
External link 2:<img alt=\"Moodle\" src=\"https://moodle.org/logo/logo-240x60.gif\" />
Internal link 1:
Internal link 2:
Anchor link 1:Link text
Anchor link 2:<a title=\"bananas\" href=\"../logo-240x60.gif\">Link text</a>
";

        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'edusignfeedback_comments_enabled' => 1,
                'edusignfeedback_comments_commentinline' => 1,
            ]);

        $this->setUser($student);

        // Add a submission but don't submit now.
        $this->add_submission($student, $edusign, $sourcetext);

        $this->setUser($teacher);

        $data = new stdClass();
        require_once($CFG->dirroot . '/mod/edusign/gradeform.php');
        $pagination = [
                'userid' => $student->id,
                'rownum' => 0,
                'last' => true,
                'useridlistid' => $edusign->get_useridlist_key_id(),
                'attemptnumber' => 0,
            ];
        $formparams = array($edusign, $data, $pagination);
        $mform = new mod_edusign_grade_form(null, [$edusign, $data, $pagination]);

        $this->assertEquals($filteredtext, $data->edusignfeedbackcomments_editor['text']);
    }

    /**
     * Testing for feedback comment plugin settings.
     *
     * @dataProvider feedback_plugin_settings_provider
     * @param   array   $instanceconfig
     * @param   bool    $isenabled
     */
    public function test_feedback_plugin_settings($instanceconfig, $isenabled)
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $edusign = $this->create_instance($course, $instanceconfig);
        $plugin = $edusign->get_feedback_plugin_by_type('comments');
        $this->assertEquals($isenabled, (bool) $plugin->is_enabled('enabled'));
    }

    public function feedback_plugin_settings_provider()
    {
        return [
            'No configuration => disabled' => [
                [],
                false,
            ],
            'Actively disabled' => [
                [
                    'edusignfeedback_comments_enabled' => 0,
                ],
                false,
            ],
            'Actively enabled' => [
                [
                    'edusignfeedback_comments_enabled' => 1,
                ],
                true,
            ],
        ];
    }

    /**
     * Testing if gradebook feedback plugin is enabled.
     */
    public function test_is_gradebook_feedback_enabled()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $adminconfig = get_config('edusign');
        $gradebookplugin = $adminconfig->feedback_plugin_for_gradebook;

        // Create edusignment with gradebook feedback enabled and grade = 0.
        $edusign = $this->create_instance($course, [
                "{$gradebookplugin}_enabled" => 1,
                'grades' => 0,
            ]);

        // Get gradebook feedback plugin.
        $gradebookplugintype = str_replace('edusignfeedback_', '', $gradebookplugin);
        $plugin = $edusign->get_feedback_plugin_by_type($gradebookplugintype);
        $this->assertEquals(1, $plugin->is_enabled('enabled'));
        $this->assertEquals(1, $edusign->is_gradebook_feedback_enabled());
    }

    /**
     * Testing if gradebook feedback plugin is disabled.
     */
    public function test_is_gradebook_feedback_disabled()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $adminconfig = get_config('edusign');
        $gradebookplugin = $adminconfig->feedback_plugin_for_gradebook;

        // Create edusignment with gradebook feedback disabled and grade = 0.
        $edusign = $this->create_instance($course, [
                "{$gradebookplugin}_enabled" => 0,
                'grades' => 0,
            ]);

        $gradebookplugintype = str_replace('edusignfeedback_', '', $gradebookplugin);
        $plugin = $edusign->get_feedback_plugin_by_type($gradebookplugintype);
        $this->assertEquals(0, $plugin->is_enabled('enabled'));
    }

    /**
     * Testing can_edit_submission.
     */
    public function test_can_edit_submission()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'submissiondrafts' => 1,
            ]);

        // Check student can edit their own submission.
        $this->assertTrue($edusign->can_edit_submission($student->id, $student->id));

        // Check student cannot edit others submission.
        $this->assertFalse($edusign->can_edit_submission($otherstudent->id, $student->id));

        // Check teacher cannot (by default) edit a students submission.
        $this->assertFalse($edusign->can_edit_submission($student->id, $teacher->id));
    }

    /**
     * Testing can_edit_submission with the editothersubmission capability.
     */
    public function test_can_edit_submission_with_editothersubmission()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'submissiondrafts' => 1,
            ]);

        // Add the required capability to edit a student submission.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        edusign_capability('mod/edusign:editothersubmission', CAP_ALLOW, $roleid, $edusign->get_context()->id);
        role_edusign($roleid, $teacher->id, $edusign->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Check student can edit their own submission.
        $this->assertTrue($edusign->can_edit_submission($student->id, $student->id));

        // Check student cannot edit others submission.
        $this->assertFalse($edusign->can_edit_submission($otherstudent->id, $student->id));

        // Retest - should now have access.
        $this->assertTrue($edusign->can_edit_submission($student->id, $teacher->id));
    }

    /**
     * Testing can_edit_submission
     */
    public function test_can_edit_submission_separategroups()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student4 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_edusign_grouping($grouping->id, $group1->id);
        groups_add_member($group1, $student1);
        groups_add_member($group1, $student2);

        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_edusign_grouping($grouping->id, $group2->id);
        groups_add_member($group2, $student3);
        groups_add_member($group2, $student4);

        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'submissiondrafts' => 1,
                'groupingid' => $grouping->id,
                'groupmode' => SEPARATEGROUPS,
            ]);

        // Verify a student does not have the ability to edit submissions for other users.
        $this->assertTrue($edusign->can_edit_submission($student1->id, $student1->id));
        $this->assertFalse($edusign->can_edit_submission($student2->id, $student1->id));
        $this->assertFalse($edusign->can_edit_submission($student3->id, $student1->id));
        $this->assertFalse($edusign->can_edit_submission($student4->id, $student1->id));
    }

    /**
     * Testing can_edit_submission
     */
    public function test_can_edit_submission_separategroups_with_editothersubmission()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student4 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_edusign_grouping($grouping->id, $group1->id);
        groups_add_member($group1, $student1);
        groups_add_member($group1, $student2);

        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_edusign_grouping($grouping->id, $group2->id);
        groups_add_member($group2, $student3);
        groups_add_member($group2, $student4);

        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
                'submissiondrafts' => 1,
                'groupingid' => $grouping->id,
                'groupmode' => SEPARATEGROUPS,
            ]);

        // Add the capability to the new edusignment for student 1.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        edusign_capability('mod/edusign:editothersubmission', CAP_ALLOW, $roleid, $edusign->get_context()->id);
        role_edusign($roleid, $student1->id, $edusign->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Verify student1 has the ability to edit submissions for other users in their group, but not other groups.
        $this->assertTrue($edusign->can_edit_submission($student1->id, $student1->id));
        $this->assertTrue($edusign->can_edit_submission($student2->id, $student1->id));
        $this->assertFalse($edusign->can_edit_submission($student3->id, $student1->id));
        $this->assertFalse($edusign->can_edit_submission($student4->id, $student1->id));

        // Verify other students do not have the ability to edit submissions for other users.
        $this->assertTrue($edusign->can_edit_submission($student2->id, $student2->id));
        $this->assertFalse($edusign->can_edit_submission($student1->id, $student2->id));
        $this->assertFalse($edusign->can_edit_submission($student3->id, $student2->id));
        $this->assertFalse($edusign->can_edit_submission($student4->id, $student2->id));
    }

    /**
     * Test if the view blind details capability works
     */
    public function test_can_view_blind_details()
    {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $manager = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course, [
                'blindmarking' => 1,
            ]);

        $this->assertTrue($edusign->is_blind_marking());

        // Test student names are hidden to teacher.
        $this->setUser($teacher);
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, get_string('hiddenuser', 'edusign')));    // "Participant" is somewhere on the page.
        $this->assertEquals(false, strpos($output, fullname($student)));    // Students full name doesn't appear.

        // Test student names are visible to manager.
        $this->setUser($manager);
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, get_string('hiddenuser', 'edusign')));
        $this->assertEquals(true, strpos($output, fullname($student)));
    }

    /**
     * Testing get_shared_group_members
     */
    public function test_get_shared_group_members()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student4 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_edusign_grouping($grouping->id, $group1->id);
        groups_add_member($group1, $student1);
        groups_add_member($group1, $student2);

        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_edusign_grouping($grouping->id, $group2->id);
        groups_add_member($group2, $student3);
        groups_add_member($group2, $student4);

        $edusign = $this->create_instance($course, [
                'groupingid' => $grouping->id,
                'groupmode' => SEPARATEGROUPS,
            ]);

        $cm = $edusign->get_course_module();

        // Get shared group members for students 0 and 1.
        $groupmembers = $edusign->get_shared_group_members($cm, $student1->id);
        $this->assertCount(2, $groupmembers);
        $this->assertContains($student1->id, $groupmembers);
        $this->assertContains($student2->id, $groupmembers);

        $groupmembers = $edusign->get_shared_group_members($cm, $student2->id);
        $this->assertCount(2, $groupmembers);
        $this->assertContains($student1->id, $groupmembers);
        $this->assertContains($student2->id, $groupmembers);

        $groupmembers = $edusign->get_shared_group_members($cm, $student3->id);
        $this->assertCount(2, $groupmembers);
        $this->assertContains($student3->id, $groupmembers);
        $this->assertContains($student4->id, $groupmembers);

        $groupmembers = $edusign->get_shared_group_members($cm, $student4->id);
        $this->assertCount(2, $groupmembers);
        $this->assertContains($student3->id, $groupmembers);
        $this->assertContains($student4->id, $groupmembers);
    }

    /**
     * Testing get_shared_group_members
     */
    public function test_get_shared_group_members_override()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student4 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_edusign_grouping($grouping->id, $group1->id);
        groups_add_member($group1, $student1);
        groups_add_member($group1, $student2);

        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_edusign_grouping($grouping->id, $group2->id);
        groups_add_member($group2, $student3);
        groups_add_member($group2, $student4);

        $edusign = $this->create_instance($course, [
                'groupingid' => $grouping->id,
                'groupmode' => SEPARATEGROUPS,
            ]);

        $cm = $edusign->get_course_module();

        // Add the capability to access allgroups for one of the students.
        $roleid = create_role('Access all groups role', 'accessallgroupsrole', '');
        edusign_capability('moodle/site:accessallgroups', CAP_ALLOW, $roleid, $edusign->get_context()->id);
        role_edusign($roleid, $student1->id, $edusign->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Get shared group members for students 0 and 1.
        $groupmembers = $edusign->get_shared_group_members($cm, $student1->id);
        $this->assertCount(4, $groupmembers);
        $this->assertContains($student1->id, $groupmembers);
        $this->assertContains($student2->id, $groupmembers);
        $this->assertContains($student3->id, $groupmembers);
        $this->assertContains($student4->id, $groupmembers);

        $groupmembers = $edusign->get_shared_group_members($cm, $student2->id);
        $this->assertCount(2, $groupmembers);
        $this->assertContains($student1->id, $groupmembers);
        $this->assertContains($student2->id, $groupmembers);

        $groupmembers = $edusign->get_shared_group_members($cm, $student3->id);
        $this->assertCount(2, $groupmembers);
        $this->assertContains($student3->id, $groupmembers);
        $this->assertContains($student4->id, $groupmembers);

        $groupmembers = $edusign->get_shared_group_members($cm, $student4->id);
        $this->assertCount(2, $groupmembers);
        $this->assertContains($student3->id, $groupmembers);
        $this->assertContains($student4->id, $groupmembers);
    }

    /**
     * Test get plugins file areas
     */
    public function test_get_plugins_file_areas()
    {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $edusign = $this->create_instance($course);

        // Test that all the submission and feedback plugins are returning the expected file aras.
        $usingfilearea = 0;
        $coreplugins = core_plugin_manager::standard_plugins_list('edusignsubmission');
        foreach ($edusign->get_submission_plugins() as $plugin) {
            $type = $plugin->get_type();
            if (!in_array($type, $coreplugins)) {
                continue;
            }
            $fileareas = $plugin->get_file_areas();

            if ($type == 'onlinetext') {
                $this->assertEquals(array('submissions_onlinetext' => 'Online text'), $fileareas);
                $usingfilearea++;
            } elseif ($type == 'file') {
                $this->assertEquals(array('submission_files' => 'File submissions'), $fileareas);
                $usingfilearea++;
            } else {
                $this->assertEmpty($fileareas);
            }
        }
        $this->assertEquals(2, $usingfilearea);

        $usingfilearea = 0;
        $coreplugins = core_plugin_manager::standard_plugins_list('edusignfeedback');
        foreach ($edusign->get_feedback_plugins() as $plugin) {
            $type = $plugin->get_type();
            if (!in_array($type, $coreplugins)) {
                continue;
            }
            $fileareas = $plugin->get_file_areas();

            if ($type == 'editpdf') {
                $this->assertEquals(array('download' => 'Annotate PDF'), $fileareas);
                $usingfilearea++;
            } elseif ($type == 'file') {
                $this->assertEquals(array('feedback_files' => 'Feedback files'), $fileareas);
                $usingfilearea++;
            } else {
                $this->assertEmpty($fileareas);
            }
        }
        $this->assertEquals(2, $usingfilearea);
    }

    /**
     * Test override exists
     *
     * This function needs to obey the group override logic as per the edusign grading table and
     * the overview block.
     */
    public function test_override_exists()
    {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // Data:
        // - student1 => group A only
        // - student2 => group B only
        // - student3 => Group A + Group B (No user override)
        // - student4 => Group A + Group B (With user override)
        // - student4 => No groups.
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group1, $student1);

        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group2, $student2);

        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group1, $student3);
        groups_add_member($group2, $student3);

        $student4 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($group1, $student4);
        groups_add_member($group2, $student4);

        $student5 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $edusign = $this->create_instance($course);
        $instance = $edusign->get_instance();

        // Overrides for each of the groups, and a user override.
        $overrides = [
            (object) [
                // Override for group 1, highest priority (numerically lowest sortorder).
                'edusignid' => $instance->id,
                'groupid' => $group1->id,
                'userid' => null,
                'sortorder' => 1,
                'allowsubmissionsfromdate' => 1,
                'duedate' => 2,
                'cutoffdate' => 3
            ],
            (object) [
                // Override for group 2, lower priority (numerically higher sortorder).
                'edusignid' => $instance->id,
                'groupid' => $group2->id,
                'userid' => null,
                'sortorder' => 2,
                'allowsubmissionsfromdate' => 5,
                'duedate' => 6,
                'cutoffdate' => 6
            ],
            (object) [
                // User override.
                'edusignid' => $instance->id,
                'groupid' => null,
                'userid' => $student3->id,
                'sortorder' => null,
                'allowsubmissionsfromdate' => 7,
                'duedate' => 8,
                'cutoffdate' => 9
            ],
        ];

        foreach ($overrides as &$override) {
            $override->id = $DB->insert_record('edusign_overrides', $override);
        }

        // User only in group 1 should see the group 1 override.
        $this->assertEquals($overrides[0], $edusign->override_exists($student1->id));

        // User only in group 2 should see the group 2 override.
        $this->assertEquals($overrides[1], $edusign->override_exists($student2->id));

        // User only in both groups with an override should see the user override as it has higher priority.
        $this->assertEquals($overrides[2], $edusign->override_exists($student3->id));

        // User only in both groups with no override should see the group 1 override as it has higher priority.
        $this->assertEquals($overrides[0], $edusign->override_exists($student4->id));

        // User with no overrides shoudl get nothing.
        $override = $edusign->override_exists($student5->id);
        $this->assertNull($override->duedate);
        $this->assertNull($override->cutoffdate);
        $this->assertNull($override->allowsubmissionsfromdate);
    }

    /**
     * Test the quicksave grades processor
     */
    public function test_process_save_quick_grades()
    {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $teacher->ignoresesskey = true;
        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'attemptreopenmethod' => EDUSIGN_ATTEMPT_REOPEN_METHOD_MANUAL,
            ]);

        // Initially grade the user.
        $grade = (object) [
                'attemptnumber' => '',
                'timemodified' => '',
            ];
        $data = [
                "grademodified_{$student->id}" => $grade->timemodified,
                "gradeattempt_{$student->id}" => $grade->attemptnumber,
                "quickgrade_{$student->id}" => '60.0',
            ];

        $result = $edusign->testable_process_save_quick_grades($data);
        $this->assertContains(get_string('quickgradingchangessaved', 'edusign'), $result);
        $grade = $edusign->get_user_grade($student->id, false);
        $this->assertEquals('60.0', $grade->grade);

        // Attempt to grade with a past attempts grade info.
        $edusign->testable_process_add_attempt($student->id);
        $data = array(
            'grademodified_' . $student->id => $grade->timemodified,
            'gradeattempt_' . $student->id => $grade->attemptnumber,
            'quickgrade_' . $student->id => '50.0'
        );
        $result = $edusign->testable_process_save_quick_grades($data);
        $this->assertContains(get_string('errorrecordmodified', 'edusign'), $result);
        $grade = $edusign->get_user_grade($student->id, false);
        $this->assertFalse($grade);

        // Attempt to grade a the attempt.
        $submission = $edusign->get_user_submission($student->id, false);
        $data = array(
            'grademodified_' . $student->id => '',
            'gradeattempt_' . $student->id => $submission->attemptnumber,
            'quickgrade_' . $student->id => '40.0'
        );
        $result = $edusign->testable_process_save_quick_grades($data);
        $this->assertContains(get_string('quickgradingchangessaved', 'edusign'), $result);
        $grade = $edusign->get_user_grade($student->id, false);
        $this->assertEquals('40.0', $grade->grade);

        // Catch grade update conflicts.
        // Save old data for later.
        $pastdata = $data;
        // Update the grade the 'good' way.
        $data = array(
            'grademodified_' . $student->id => $grade->timemodified,
            'gradeattempt_' . $student->id => $grade->attemptnumber,
            'quickgrade_' . $student->id => '30.0'
        );
        $result = $edusign->testable_process_save_quick_grades($data);
        $this->assertContains(get_string('quickgradingchangessaved', 'edusign'), $result);
        $grade = $edusign->get_user_grade($student->id, false);
        $this->assertEquals('30.0', $grade->grade);

        // Now update using 'old' data. Should fail.
        $result = $edusign->testable_process_save_quick_grades($pastdata);
        $this->assertContains(get_string('errorrecordmodified', 'edusign'), $result);
        $grade = $edusign->get_user_grade($student->id, false);
        $this->assertEquals('30.0', $grade->grade);
    }

    /**
     * Test updating activity completion when submitting an assessment.
     */
    public function test_update_activity_completion_records_solitary_submission()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'grade' => 100,
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'requireallteammemberssubmit' => 0,
            ]);
        $cm = $edusign->get_course_module();

        // Submit the edusignment as the student.
        $this->add_submission($student, $edusign);

        // Check that completion is not met yet.
        $completion = new completion_info($course);
        $completiondata = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(0, $completiondata->completionstate);

        // Update to mark as complete.
        $submission = $edusign->get_user_submission($student->id, true);
        $edusign->testable_update_activity_completion_records(
            0,
            0,
            $submission,
            $student->id,
            COMPLETION_COMPLETE,
            $completion
        );

        // Completion should now be met.
        $completiondata = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(1, $completiondata->completionstate);
    }

    /**
     * Test updating activity completion when submitting an assessment.
     */
    public function test_update_activity_completion_records_team_submission()
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        groups_add_member($group1, $student);
        groups_add_member($group1, $otherstudent);

        $edusign = $this->create_instance($course, [
                'grade' => 100,
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'teamsubmission' => 1,
            ]);

        $cm = $edusign->get_course_module();

        $this->add_submission($student, $edusign);
        $this->submit_for_grading($student, $edusign, ['groupid' => $group1->id]);

        $completion = new completion_info($course);

        // Check that completion is not met yet.
        $completiondata = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(0, $completiondata->completionstate);

        $completiondata = $completion->get_data($cm, false, $otherstudent->id);
        $this->assertEquals(0, $completiondata->completionstate);

        $submission = $edusign->get_user_submission($student->id, true);
        $submission->status = EDUSIGN_SUBMISSION_STATUS_SUBMITTED;
        $submission->groupid = $group1->id;

        $edusign->testable_update_activity_completion_records(1, 0, $submission, $student->id, COMPLETION_COMPLETE, $completion);

        // Completion should now be met.
        $completiondata = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(1, $completiondata->completionstate);

        $completiondata = $completion->get_data($cm, false, $otherstudent->id);
        $this->assertEquals(1, $completiondata->completionstate);
    }

    public function get_edusignments_with_rescaled_null_grades_provider()
    {
        return [
            'Negative less than one is errant' => [
                'grade' => -0.64,
                'count' => 1,
            ],
            'Negative more than one is errant' => [
                'grade' => -30.18,
                'count' => 1,
            ],
            'Negative one exactly is not errant' => [
                'grade' => edusign_GRADE_NOT_SET,
                'count' => 0,
            ],
            'Positive grade is not errant' => [
                'grade' => 1,
                'count' => 0,
            ],
            'Large grade is not errant' => [
                'grade' => 100,
                'count' => 0,
            ],
            'Zero grade is not errant' => [
                'grade' => 0,
                'count' => 0,
            ],
        ];
    }

    /**
     * Test determining if the edusignment as any null grades that were rescaled.
     * @dataProvider get_edusignments_with_rescaled_null_grades_provider
     */
    public function test_get_edusignments_with_rescaled_null_grades($grade, $count)
    {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'grade' => 100,
            ]);

        // Try getting a student's grade. This will give a grade of -1.
        // Then we can override it with a bad negative grade.
        $edusign->get_user_grade($student->id, true);

        // Set the grade to something errant.
        $DB->set_field(
            'edusign_grades',
            'grade',
            $grade,
            [
                'userid' => $student->id,
                'edusignment' => $edusign->get_instance()->id,
            ]
        );

        $this->assertCount($count, get_edusignments_with_rescaled_null_grades());
    }

    /**
     * Data provider for test_fix_null_grades
     * @return array[] Test data for test_fix_null_grades. Each element should contain grade, expectedcount and gradebookvalue
     */
    public function fix_null_grades_provider()
    {
        return [
            'Negative less than one is errant' => [
                'grade' => -0.64,
                'gradebookvalue' => null,
            ],
            'Negative more than one is errant' => [
                'grade' => -30.18,
                'gradebookvalue' => null,
            ],
            'Negative one exactly is not errant, but shouldn\'t be pushed to gradebook' => [
                'grade' => edusign_GRADE_NOT_SET,
                'gradebookvalue' => null,
            ],
            'Positive grade is not errant' => [
                'grade' => 1,
                'gradebookvalue' => 1,
            ],
            'Large grade is not errant' => [
                'grade' => 100,
                'gradebookvalue' => 100,
            ],
            'Zero grade is not errant' => [
                'grade' => 0,
                'gradebookvalue' => 0,
            ],
        ];
    }

    /**
     * Test fix_null_grades
     * @param number $grade The grade we should set in the edusign grading table.
     * @param number $expectedcount The finalgrade we expect in the gradebook after fixing the grades.
     * @dataProvider fix_null_grades_provider
     */
    public function test_fix_null_grades($grade, $gradebookvalue)
    {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course);

        // Try getting a student's grade. This will give a grade of -1.
        // Then we can override it with a bad negative grade.
        $edusign->get_user_grade($student->id, true);

        // Set the grade to something errant.
        // We don't set the grader here, so we expect it to be -1 as a result.
        $DB->set_field(
            'edusign_grades',
            'grade',
            $grade,
            [
                'userid' => $student->id,
                'edusignment' => $edusign->get_instance()->id,
            ]
        );
        $edusign->grade = $grade;
        $edusigntemp = clone $edusign->get_instance();
        $edusigntemp->cmidnumber = $edusign->get_course_module()->idnumber;
        edusign_update_grades($edusigntemp);

        // Check that the gradebook was updated with the edusign grade. So we can guarentee test results later on.
        $expectedgrade = $grade == -1 ? null : $grade; // edusign sends null to the gradebook for -1 grades.
        $gradegrade = grade_grade::fetch(array('userid' => $student->id, 'itemid' => $edusign->get_grade_item()->id));
        $this->assertEquals(-1, $gradegrade->usermodified);
        $this->assertEquals($expectedgrade, $gradegrade->rawgrade);

        // Call fix_null_grades().
        $method = new ReflectionMethod(edusign::class, 'fix_null_grades');
        $method->setAccessible(true);
        $result = $method->invoke($edusign);

        $this->assertSame(true, $result);

        $gradegrade = grade_grade::fetch(array('userid' => $student->id, 'itemid' => $edusign->get_grade_item()->id));

        $this->assertEquals(-1, $gradegrade->usermodified);
        $this->assertEquals($gradebookvalue, $gradegrade->finalgrade);

        // Check that the grade was updated in the gradebook by fix_null_grades.
        $this->assertEquals($gradebookvalue, $gradegrade->finalgrade);
    }

    /**
     * Test grade override displays 'Graded' for students
     */
    public function test_grade_submission_override()
    {
        global $DB, $PAGE, $OUTPUT;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $edusign = $this->create_instance($course, [
                'edusignsubmission_onlinetext_enabled' => 1,
            ]);

        // Simulate adding a grade.
        $this->setUser($teacher);
        $data = new stdClass();
        $data->grade = '50.0';
        $edusign->testable_apply_grade_to_user($data, $student->id, 0);

        // Set grade override.
        $gradegrade = grade_grade::fetch([
                'userid' => $student->id,
                'itemid' => $edusign->get_grade_item()->id,
            ]);

        // Check that grade submission is not overridden yet.
        $this->assertEquals(false, $gradegrade->is_overridden());

        // Simulate a submission.
        $this->setUser($student);
        $submission = $edusign->get_user_submission($student->id, true);

        $PAGE->set_url(new moodle_url('/mod/edusign/view.php', ['id' => $edusign->get_course_module()->id]));

        // Set override grade grade, and check that grade submission has been overridden.
        $gradegrade->set_overridden(true);
        $this->assertEquals(true, $gradegrade->is_overridden());

        // Check that submissionslocked message 'This edusignment is not accepting submissions' does not appear for student.
        $gradingtable = new edusign_grading_table($edusign, 1, '', 0, true);
        $output = $edusign->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'edusign'), $output);

        $edusignsubmissionstatus = $edusign->get_edusign_submission_status_renderable($student, true);
        $output2 = $edusign->get_renderer()->render($edusignsubmissionstatus);

        // Check that submissionslocked 'This edusignment is not accepting submissions' message does not appear for student.
        $this->assertNotContains(get_string('submissionslocked', 'edusign'), $output2);
        // Check that submissionstatus_marked 'Graded' message does appear for student.
        $this->assertContains(get_string('submissionstatus_marked', 'edusign'), $output2);
    }
}
