<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());
if (!is_siteadmin()) {
    throw new required_capability_exception(context_system::instance(), 'moodle/site:config', 'nopermissions', '');
}
$orgid = required_param('orgid', PARAM_INT);
\local_fleetcert\service\access::org($orgid, 'manage');
$url = new moodle_url('/local/fleetcert/managers.php', ['orgid' => $orgid]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Organization managers');
$PAGE->set_heading('Organization managers');
$form = new \local_fleetcert\form\manager($url);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/fleetcert/index.php', ['orgid' => $orgid]));
}
if ($data = $form->get_data()) {
    \local_fleetcert\service\managers::assign($orgid, $data->userid, $data->operation === 'remove');
    redirect($url, 'Manager assignment saved');
}
echo $OUTPUT->header();
echo $OUTPUT->notification('Assignment permits access to this organization only. The user also needs a system role with FleetCert view/manage capabilities. This screen does not grant Moodle roles.', 'info');
$table = new html_table();
$table->head = ['User ID', 'Name'];
foreach ($DB->get_records_sql('SELECT u.id, u.firstname, u.lastname FROM {user} u
    JOIN {local_fleetcert_manager} m ON m.userid=u.id WHERE m.orgid=? AND u.deleted=0', [$orgid]) as $user) {
    $table->data[] = [$user->id, s(fullname($user))];
}
echo html_writer::table($table);
$form->display();
echo $OUTPUT->footer();
