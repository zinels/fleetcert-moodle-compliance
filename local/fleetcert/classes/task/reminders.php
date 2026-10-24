<?php
namespace local_fleetcert\task;
defined('MOODLE_INTERNAL') || die();
/** Daily reminder delivery, deduplicated per employee, course and expiration. */
final class reminders extends \core\task\scheduled_task {
 public function get_name() { return get_string('reminders', 'local_fleetcert'); }
 public function execute() {
  global $DB;
  $lock = \core\lock\lock_config::get_lock_factory('local_fleetcert')->get_lock('reminders', 0);
  if (!$lock) { return; }
  try {
   $sql = "SELECT m.orgid, m.userid, r.courseid, r.validdays, r.reminddays, c.timecompleted
    FROM {local_fleetcert_member} m JOIN {user} u ON u.id = m.userid AND u.deleted = 0 AND u.suspended = 0
    JOIN {local_fleetcert_rule} r ON r.orgid = m.orgid
    JOIN {course_completions} c ON c.userid = m.userid AND c.course = r.courseid
    WHERE c.timecompleted IS NOT NULL";
   $rs = $DB->get_recordset_sql($sql);
   foreach ($rs as $r) {
    $state = \local_fleetcert\service\compliance::evaluate((int)$r->timecompleted, $r->validdays, $r->reminddays, time());
    if (!in_array($state['status'], ['expired', 'expiring'])) { continue; }
    $identity = ['orgid' => $r->orgid, 'userid' => $r->userid, 'courseid' => $r->courseid, 'expires' => $state['expires']];
    if ($DB->record_exists('local_fleetcert_notice', $identity)) { continue; }
    $message = new \core\message\message();
    $message->component = 'local_fleetcert'; $message->name = 'expiry';
    $message->userfrom = \core_user::get_noreply_user();
    $message->userto = \core_user::get_user($r->userid);
    $message->subject = 'Training renewal reminder';
    $message->fullmessage = 'Your simulated training record for course ' . $r->courseid .
     ' expires on ' . userdate($state['expires']) . '. Contact your training administrator about renewal.';
    $message->fullmessageformat = FORMAT_PLAIN; $message->fullmessagehtml = ''; $message->smallmessage = $message->subject;
    $message->notification = 1;
    if (message_send($message)) { $DB->insert_record('local_fleetcert_notice', (object)$identity); }
   }
   $rs->close();
  } finally { $lock->release(); }
 }
}
