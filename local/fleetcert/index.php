<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('local/fleetcert:view', context_system::instance());
$orgid = optional_param('orgid', 0, PARAM_INT);
$PAGE->set_url('/local/fleetcert/index.php', ['orgid' => $orgid]);
$PAGE->set_context(context_system::instance()); $PAGE->set_title('FleetCert'); $PAGE->set_heading('FleetCert');
if ($orgid) { \local_fleetcert\service\access::org($orgid); }
$form = null;
if (is_siteadmin()) {
 $form = new \local_fleetcert\form\configuration($PAGE->url);
 if ($data = $form->get_data()) {
  if ($data->name !== '') {
   $id = $DB->insert_record('local_fleetcert_org', (object)['name' => $data->name]);
  } else {
   \local_fleetcert\service\access::org($orgid, 'manage');
   $course = get_course($data->courseid);
   if (!$course->enablecompletion || $course->id == SITEID) { throw new invalid_parameter_exception('Enable completion on the required course'); }
   $record = $DB->get_record('local_fleetcert_rule', ['orgid' => $orgid, 'courseid' => $course->id]);
   $rule = (object)['orgid' => $orgid, 'courseid' => $course->id, 'validdays' => $data->validdays, 'reminddays' => $data->reminddays];
   if ($record) { $rule->id = $record->id; $DB->update_record('local_fleetcert_rule', $rule); }
   else { $DB->insert_record('local_fleetcert_rule', $rule); } $id = $orgid;
  }
  \local_fleetcert\event\administration::create(['context' => context_system::instance(), 'objectid' => $id,
   'other' => ['action' => 'configuration']])->trigger();
  redirect(new moodle_url('/local/fleetcert/index.php', ['orgid' => $id]));
 }
}
echo $OUTPUT->header();
echo $OUTPUT->notification('Synthetic training compliance tracking. These records are not legally recognized certifications.', 'info');
$orgs = is_siteadmin() ? $DB->get_records('local_fleetcert_org') : $DB->get_records_sql(
 'SELECT o.* FROM {local_fleetcert_org} o JOIN {local_fleetcert_manager} m ON m.orgid = o.id WHERE m.userid = ?', [$USER->id]);
foreach ($orgs as $org) { echo html_writer::div(html_writer::link(new moodle_url('/local/fleetcert/index.php', ['orgid' => $org->id]), format_string($org->name))); }
if ($orgid) {
 $summary = \local_fleetcert\service\compliance::summary($orgid);
 $table = new html_table(); $table->head = ['Measure', 'Count'];
 foreach ($summary as $key => $value) { $table->data[] = [s($key), (int)$value]; } echo html_writer::table($table);
 echo html_writer::link(new moodle_url('/local/fleetcert/report.php', ['orgid' => $orgid]), 'Training records and CSV export');
 echo html_writer::div(html_writer::link(new moodle_url('/local/fleetcert/onboard.php', ['orgid' => $orgid]), 'Bulk onboarding'));
 $table = new html_table(); $table->head = ['Required course', 'Validity days', 'Reminder days'];
 foreach ($DB->get_records('local_fleetcert_rule', ['orgid' => $orgid]) as $r) { $table->data[] = [(int)$r->courseid, (int)$r->validdays, (int)$r->reminddays]; }
 echo html_writer::table($table);
}
if ($form) { $form->display(); } echo $OUTPUT->footer();
