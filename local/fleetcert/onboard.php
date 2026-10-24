<?php
require_once(__DIR__ . '/../../config.php');
require_login(); $orgid = required_param('orgid', PARAM_INT);
\local_fleetcert\service\access::org($orgid, 'manage');
$PAGE->set_url('/local/fleetcert/onboard.php', ['orgid' => $orgid]);
$PAGE->set_context(context_system::instance()); $PAGE->set_title('FleetCert onboarding'); $PAGE->set_heading('Employee onboarding');
$form = new \local_fleetcert\form\upload($PAGE->url);
$key = 'fleetcert_preview_' . $orgid;
$rows = $SESSION->$key ?? [];
if ($form->get_data()) { $rows = \local_fleetcert\service\onboarding::preview($form->get_file_content('employees')); $SESSION->$key = $rows; }
if (optional_param('commit', 0, PARAM_BOOL) && data_submitted()) {
 require_sesskey(); $rows = \local_fleetcert\service\onboarding::commit($orgid, $rows); $SESSION->$key = $rows;
}
if (optional_param('errors', 0, PARAM_BOOL)) {
 require_sesskey(); header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="onboarding-results.csv"');
 $out = fopen('php://output', 'w'); fputcsv($out, ['row', 'result']);
 foreach ($rows as $i => $r) { fputcsv($out, [$i + 2, $r['error'] ?: 'Valid']); } fclose($out); exit;
}
echo $OUTPUT->header(); $form->display();
$table = new html_table(); $table->head = ['CSV row', 'Username', 'Validation/result'];
foreach ($rows as $i => $r) { $table->data[] = [$i + 2, s($r['data']['username'] ?? ''), s($r['error'] ?: 'Valid')]; }
echo html_writer::table($table);
if ($rows) {
 echo $OUTPUT->single_button(new moodle_url('/local/fleetcert/onboard.php', ['orgid' => $orgid, 'commit' => 1, 'sesskey' => sesskey()]), 'Commit valid rows', 'post');
 echo html_writer::link(new moodle_url('/local/fleetcert/onboard.php', ['orgid' => $orgid, 'errors' => 1, 'sesskey' => sesskey()]), 'Download results');
}
echo $OUTPUT->footer();
