<?php
require_once(__DIR__ . '/../../config.php');
require_login();
$orgid = required_param('orgid', PARAM_INT);
\local_fleetcert\service\access::org($orgid);
$page = optional_param('page', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);
if ($page < 0) {
    throw new invalid_parameter_exception('Page must be nonnegative');
}
$params = ['orgid' => $orgid, 'userid' => $userid, 'courseid' => $courseid, 'status' => $status];
$url = new moodle_url('/local/fleetcert/report.php', $params + ['page' => $page]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Training records');
$PAGE->set_heading('Training records');

if (optional_param('export', 0, PARAM_BOOL)) {
    $records = \local_fleetcert\service\compliance::stream($orgid, $userid, $courseid, $status);
    // Start the generator before headers so invalid filters/permissions produce normal errors.
    $records->rewind();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="training-records.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['userid', 'courseid', 'status', 'issued', 'expires'], ',', '"', '');
    while ($records->valid()) {
        $record = $records->current();
        $cells = array_map([\local_fleetcert\service\onboarding::class, 'csvcell'], array_map('strval', $record));
        fputcsv($out, $cells, ',', '"', '');
        $records->next();
    }
    fclose($out);
    exit;
}

// Fetch one extra row to show Next only when another page actually exists.
$rows = iterator_to_array(\local_fleetcert\service\compliance::stream(
    $orgid, $userid, $courseid, $status, $page * 100, 101), false);
$hasnext = count($rows) > 100;
$rows = array_slice($rows, 0, 100);
echo $OUTPUT->header();
echo html_writer::link(new moodle_url('/local/fleetcert/index.php', ['orgid' => $orgid]), 'Organization overview');

$courses = [0 => 'All required courses'];
foreach ($DB->get_records_sql('SELECT c.id, c.fullname FROM {course} c
    JOIN {local_fleetcert_rule} r ON r.courseid=c.id WHERE r.orgid=? ORDER BY c.fullname', [$orgid]) as $course) {
    $courses[$course->id] = format_string($course->fullname);
}
$statuses = ['' => 'All statuses', 'compliant' => 'Current', 'expiring' => 'Expiring',
    'expired' => 'Expired', 'incomplete' => 'Incomplete'];
echo html_writer::start_tag('form', ['method' => 'get', 'action' => (new moodle_url('/local/fleetcert/report.php'))->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'orgid', 'value' => $orgid]);
echo html_writer::label('Course', 'menucourseid');
echo html_writer::select($courses, 'courseid', $courseid, false);
echo html_writer::label('Status', 'menustatus');
echo html_writer::select($statuses, 'status', $status, false);
echo html_writer::label('Employee ID (0 for all)', 'fleetcert-userid');
echo html_writer::empty_tag('input', ['type' => 'number', 'min' => 0, 'id' => 'fleetcert-userid',
    'name' => 'userid', 'value' => $userid]);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Apply filters']);
echo html_writer::end_tag('form');

$table = new html_table();
$table->head = ['User ID', 'Course ID', 'Status', 'Completed', 'Expires'];
foreach ($rows as $record) {
    $table->data[] = [$record['userid'], $record['courseid'], s($record['status']),
        $record['issued'] ? userdate($record['issued']) : '—',
        $record['expires'] ? userdate($record['expires']) : '—'];
}
echo html_writer::table($table);
if (!$rows) {
    echo $OUTPUT->notification('No training records match these filters.', 'info');
}
echo html_writer::div(html_writer::link(new moodle_url('/local/fleetcert/report.php', $params + ['export' => 1]),
    'Export all matching records'));
if ($page > 0) {
    echo html_writer::link(new moodle_url('/local/fleetcert/report.php', $params + ['page' => $page - 1]), 'Previous page');
}
if ($hasnext) {
    echo html_writer::div(html_writer::link(new moodle_url('/local/fleetcert/report.php', $params + ['page' => $page + 1]), 'Next page'));
}
echo $OUTPUT->footer();
