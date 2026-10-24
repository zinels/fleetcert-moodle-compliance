<?php
/** Synthetic demo fixture: only run on a disposable local Moodle instance. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');
if (getenv('FLEETCERT_DEMO') !== '1') { cli_error('Set FLEETCERT_DEMO=1 on a disposable development instance.'); }
$admin = get_admin(); \core\session\manager::set_user($admin);
$courses = [];
foreach (['Defensive driving', 'Load securement', 'Workplace safety'] as $i => $name) {
 $shortname = 'FC-DEMO-' . $i;
 $course = $DB->get_record('course', ['shortname' => $shortname]);
 if (!$course) {
  $course = create_course((object)['fullname' => $name . ' (synthetic demo)', 'shortname' => $shortname,
   'category' => $DB->get_field('course_categories', 'id', [], IGNORE_MULTIPLE), 'enablecompletion' => 1]);
  $manual = enrol_get_plugin('manual');
  if (!$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) { $manual->add_instance($course); }
 }
 $courses[] = $course;
}
foreach (['Northline Logistics', 'Harbor Freight Training', 'Prairie Fleet Operations'] as $i => $name) {
 $name .= ' (synthetic)';
 $org = $DB->get_record('local_fleetcert_org', ['name' => $name]);
 if (!$org) { $org = (object)['id' => $DB->insert_record('local_fleetcert_org', (object)['name' => $name])]; }
 foreach ($courses as $course) {
  if (!$DB->record_exists('local_fleetcert_rule', ['orgid' => $org->id, 'courseid' => $course->id])) {
   $DB->insert_record('local_fleetcert_rule', (object)['orgid' => $org->id, 'courseid' => $course->id,
    'validdays' => [180, 365, 730][$i], 'reminddays' => 30]);
  }
 }
 $rows = [];
 for ($n = $i + 1; $n <= 1000; $n += 3) {
  $rows[] = ['data' => ['username' => 'synthetic.driver' . $n, 'firstname' => 'Demo', 'lastname' => 'Driver ' . $n,
   'email' => 'driver' . $n . '@example.invalid'], 'error' => ''];
 }
 $results = \local_fleetcert\service\onboarding::commit($org->id, $rows);
 foreach ($results as $n => $row) {
  if ($row['error'] !== 'Success') { mtrace('Row failed; inspect enrolment configuration'); continue; }
  if ($n % 4 === 0) { continue; }
  $userid = $DB->get_field('user', 'id', ['username' => $row['data']['username']]);
  foreach ($courses as $course) {
   if (!$DB->record_exists('course_completions', ['userid' => $userid, 'course' => $course->id])) {
    $age = [0, 20, [180, 365, 730][$i] - 10, [180, 365, 730][$i] + 10][$n % 4];
    $completion = new completion_completion(['userid' => $userid, 'course' => $course->id]);
    $completion->mark_complete(time() - $age * DAYSECS);
   }
  }
 }
 mtrace('Seeded organization ' . $org->id);
}
