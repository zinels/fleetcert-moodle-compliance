<?php
require_once(__DIR__ . '/../../config.php');
require_login(); $orgid = required_param('orgid', PARAM_INT);
\local_fleetcert\service\access::org($orgid);
$page = optional_param('page', 0, PARAM_INT); $courseid = optional_param('courseid', 0, PARAM_INT);
$rows = \local_fleetcert\service\compliance::records($orgid, $page, 100, 0, $courseid);
$export = optional_param('export', 0, PARAM_BOOL);
if ($export) {
 header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="training-records.csv"');
 $out = fopen('php://output', 'w'); fputcsv($out, ['userid', 'courseid', 'status', 'issued', 'expires']);
 foreach ($rows as $r) { fputcsv($out, array_map([\local_fleetcert\service\onboarding::class, 'csvcell'], array_map('strval', $r))); }
 fclose($out); exit;
}
$PAGE->set_url('/local/fleetcert/report.php', ['orgid' => $orgid, 'page' => $page, 'courseid' => $courseid]);
$PAGE->set_context(context_system::instance()); $PAGE->set_title('Training records'); $PAGE->set_heading('Training records');
echo $OUTPUT->header();
$table = new html_table(); $table->head = ['User ID', 'Course ID', 'Status', 'Completed', 'Expires'];
foreach ($rows as $r) { $table->data[] = [$r['userid'], $r['courseid'], s($r['status']), $r['issued'] ? userdate($r['issued']) : '—', $r['expires'] ? userdate($r['expires']) : '—']; }
echo html_writer::table($table);
echo html_writer::link(new moodle_url('/local/fleetcert/report.php', ['orgid' => $orgid, 'page' => $page, 'courseid' => $courseid, 'export' => 1]), 'Export this page');
echo html_writer::div(html_writer::link(new moodle_url('/local/fleetcert/report.php', ['orgid' => $orgid, 'page' => $page + 1, 'courseid' => $courseid]), 'Next page'));
echo $OUTPUT->footer();
