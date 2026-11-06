<?php
namespace local_fleetcert;
defined('MOODLE_INTERNAL') || die();

/** Database-backed coverage of tenant boundaries, filtering and manager assignment. */
final class integration_test extends \advanced_testcase {
    private function fixture(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $org = $DB->insert_record('local_fleetcert_org', (object)['name' => 'Test organization']);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $DB->insert_record('local_fleetcert_rule', (object)[
            'orgid' => $org, 'courseid' => $course->id, 'validdays' => 365, 'reminddays' => 30,
        ]);
        $users = [];
        $ages = ['compliant' => 20, 'expiring' => 350, 'expired' => 400, 'incomplete' => null, 'future' => -10];
        foreach ($ages as $key => $age) {
            $user = $this->getDataGenerator()->create_user();
            $users[$key] = $user;
            $DB->insert_record('local_fleetcert_member', (object)['orgid' => $org, 'userid' => $user->id]);
            if ($age !== null) {
                $DB->insert_record('course_completions', (object)[
                    'userid' => $user->id, 'course' => $course->id, 'timecompleted' => time() - $age * DAYSECS,
                ]);
            }
        }
        return [$org, $course, $users];
    }

    public function test_filters_summary_and_streamed_export(): void {
        [$org, $course, $users] = $this->fixture();
        $expired = service\compliance::records($org, 0, 1, 0, 0, 'expired');
        $this->assertCount(1, $expired);
        $this->assertSame((int)$users['expired']->id, $expired[0]['userid']);
        $this->assertSame([], service\compliance::records($org, 1, 1, 0, 0, 'expired'));
        $this->assertCount(2, service\compliance::records($org, 0, 100, 0, 0, 'incomplete'));
        $this->assertCount(1, service\compliance::records($org, 0, 100, $users['compliant']->id, $course->id));
        $summary = service\compliance::summary($org);
        $this->assertEquals(5, $summary['employees']);
        $this->assertEquals(2, $summary['compliantemployees']);
        $this->assertEquals(2, $summary['incomplete']);
        $this->assertEquals(1, $summary['expired']);
        $this->assertCount(5, iterator_to_array(service\compliance::stream($org)));
        $this->assertEquals($summary, external\summary::execute($org));
        $this->assertEquals($expired, external\records::execute($org, 0, 1, 0, 0, 'expired'));
    }

    public function test_export_stream_exceeds_one_page(): void {
        global $DB;
        [$org] = $this->fixture();
        for ($i = 0; $i < 101; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $DB->insert_record('local_fleetcert_member', (object)['orgid' => $org, 'userid' => $user->id]);
        }
        $this->assertCount(100, service\compliance::records($org));
        $this->assertCount(106, iterator_to_array(service\compliance::stream($org)));
    }

    public function test_privileged_manager_cannot_read_another_organization(): void {
        global $DB;
        [$org] = $this->fixture();
        $other = $DB->insert_record('local_fleetcert_org', (object)['name' => 'Other organization']);
        $user = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        assign_capability('local/fleetcert:view', CAP_ALLOW, $role, \context_system::instance()->id);
        role_assign($role, $user->id, \context_system::instance()->id);
        service\managers::assign($other, $user->id);
        $this->setUser($user);
        $this->assertEquals(0, service\compliance::summary($other)['employees']);
        $this->expectException(\required_capability_exception::class);
        service\compliance::records($org);
    }

    public function test_manager_assignments_are_idempotent_and_revocable(): void {
        global $DB;
        [$org] = $this->fixture();
        $user = $this->getDataGenerator()->create_user();
        service\managers::assign($org, $user->id);
        service\managers::assign($org, $user->id);
        $identity = ['orgid' => $org, 'userid' => $user->id];
        $this->assertEquals(1, $DB->count_records('local_fleetcert_manager', $identity));
        service\managers::assign($org, $user->id, true);
        $this->assertFalse($DB->record_exists('local_fleetcert_manager', $identity));
    }

    public function test_non_admin_cannot_assign_managers(): void {
        [$org, , $users] = $this->fixture();
        $this->setUser($users['compliant']);
        $this->expectException(\required_capability_exception::class);
        service\managers::assign($org, $users['incomplete']->id);
    }

    public function test_onboarding_retry_and_row_rollback(): void {
        global $DB, $CFG;
        [$org, $course] = $this->fixture();
        require_once($CFG->libdir . '/enrollib.php');
        $manual = enrol_get_plugin('manual');
        if (!$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual', 'status' => 0])) {
            $manual->add_instance($course);
        }
        $csv = "username,firstname,lastname,email\nfleetcert.success,Test,Employee,success@example.invalid\n";
        $result = service\onboarding::commit($org, service\onboarding::preview($csv));
        $this->assertSame('Success', $result[0]['error']);
        $user = $DB->get_record('user', ['username' => 'fleetcert.success'], '*', MUST_EXIST);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id));
        $retry = service\onboarding::commit($org, service\onboarding::preview($csv));
        $this->assertSame('Success', $retry[0]['error']);
        $this->assertEquals(1, $DB->count_records('local_fleetcert_member', ['userid' => $user->id]));

        $broken = $this->getDataGenerator()->create_course();
        $DB->set_field('enrol', 'status', 1, ['courseid' => $broken->id, 'enrol' => 'manual']);
        $DB->insert_record('local_fleetcert_rule', (object)[
            'orgid' => $org, 'courseid' => $broken->id, 'validdays' => 365, 'reminddays' => 30,
        ]);
        $csv = "username,firstname,lastname,email\nfleetcert.rollback,Test,Employee,rollback@example.invalid\n";
        $result = service\onboarding::commit($org, service\onboarding::preview($csv));
        $this->assertStringStartsWith('Failed:', $result[0]['error']);
        $this->assertFalse($DB->record_exists('user', ['username' => 'fleetcert.rollback']));
    }

    public function test_reminders_do_not_repeat_on_ordinary_reruns(): void {
        global $DB;
        $this->fixture();
        $sink = $this->redirectMessages();
        $task = new task\reminders();
        $task->execute();
        $this->assertCount(2, $sink->get_messages());
        $this->assertEquals(2, $DB->count_records('local_fleetcert_notice'));
        $task->execute();
        $this->assertCount(2, $sink->get_messages());
        $this->assertEquals(2, $DB->count_records('local_fleetcert_notice'));
        $sink->close();
    }

    public function test_unknown_status_is_rejected(): void {
        [$org] = $this->fixture();
        $this->expectException(\invalid_parameter_exception::class);
        service\compliance::records($org, 0, 100, 0, 0, 'invented');
    }
}
