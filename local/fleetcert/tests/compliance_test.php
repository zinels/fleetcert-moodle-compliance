<?php
namespace local_fleetcert;
defined('MOODLE_INTERNAL') || die();
/** @covers \local_fleetcert\service\compliance */
final class compliance_test extends \advanced_testcase {
 public function test_expiration_boundary(): void {
  $r = service\compliance::evaluate(100, 365, 30, 100 + 365 * DAYSECS);
  $this->assertSame('expired', $r['status']);
  $this->assertSame('expiring', service\compliance::evaluate(100, 365, 30, 100 + 340 * DAYSECS)['status']);
  $this->assertSame('incomplete', service\compliance::evaluate(null, 365, 30, 100)['status']);
 }
 public function test_invalid_rule(): void {
  $this->expectException(\invalid_parameter_exception::class);
  service\compliance::evaluate(null, 30, 30, time());
 }
 public function test_csv_validation(): void {
  $rows = service\onboarding::preview("username,firstname,lastname,email\na,A,B,a@example.invalid\na,A,B,a@example.invalid\nb,A,B,broken\n");
  $this->assertSame('', $rows[0]['error']);
  $this->assertSame('Duplicate row', $rows[1]['error']);
  $this->assertSame('Invalid email', $rows[2]['error']);
  $this->assertSame("'=SUM(A1)", service\onboarding::csvcell('=SUM(A1)'));
 }
 public function test_tenant_isolation(): void {
  global $DB;
  $this->resetAfterTest();
  $orgid = $DB->insert_record('local_fleetcert_org', (object)['name' => 'Synthetic']);
  $user = $this->getDataGenerator()->create_user(); $this->setUser($user);
  $this->expectException(\required_capability_exception::class);
  service\compliance::records($orgid);
 }
}
