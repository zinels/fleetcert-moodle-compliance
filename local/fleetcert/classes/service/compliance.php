<?php
namespace local_fleetcert\service;
defined('MOODLE_INTERNAL') || die();
/** Configuration-driven calculation; completion timestamps remain owned by Moodle. */
final class compliance {
 public static function evaluate(?int $completed, int $validdays, int $reminddays, int $now): array {
  if ($validdays < 1 || $reminddays < 0 || $reminddays >= $validdays) {
   throw new \invalid_parameter_exception('Reminder days must be less than positive validity days');
  }
  if (!$completed) { return ['status' => 'incomplete', 'issued' => 0, 'expires' => 0]; }
  $expires = $completed + $validdays * DAYSECS;
  return ['status' => $expires <= $now ? 'expired' :
   ($expires <= $now + $reminddays * DAYSECS ? 'expiring' : 'compliant'),
   'issued' => $completed, 'expires' => $expires];
 }
 public static function records(int $orgid, int $page = 0, int $limit = 100, int $userid = 0, int $courseid = 0): array {
  global $DB;
  access::org($orgid);
  if ($page < 0 || $limit < 1 || $limit > 500) { throw new \invalid_parameter_exception('Invalid pagination'); }
  $params = ['orgid' => $orgid]; $extra = '';
  if ($userid) { $extra .= ' AND m.userid = :userid'; $params['userid'] = $userid; }
  if ($courseid) { $extra .= ' AND r.courseid = :courseid'; $params['courseid'] = $courseid; }
  $sql = "SELECT m.id AS memberid, m.userid, r.courseid, r.validdays, r.reminddays, c.timecompleted
   FROM {local_fleetcert_member} m
   JOIN {user} u ON u.id = m.userid AND u.deleted = 0 AND u.suspended = 0
   JOIN {local_fleetcert_rule} r ON r.orgid = m.orgid
   LEFT JOIN {course_completions} c ON c.userid = m.userid AND c.course = r.courseid
   WHERE m.orgid = :orgid $extra ORDER BY m.userid, r.courseid";
  $rs = $DB->get_recordset_sql($sql, $params, $page * $limit, $limit);
  $result = []; $now = time();
  foreach ($rs as $row) {
   $result[] = ['userid' => (int)$row->userid, 'courseid' => (int)$row->courseid] +
    self::evaluate((int)$row->timecompleted, (int)$row->validdays, (int)$row->reminddays, $now);
  }
  $rs->close(); return $result;
 }
 public static function summary(int $orgid): array {
  global $DB;
  access::org($orgid);
  $employees = $DB->count_records_sql("SELECT COUNT(1) FROM {local_fleetcert_member} m
   JOIN {user} u ON u.id = m.userid WHERE m.orgid = ? AND u.deleted = 0 AND u.suspended = 0", [$orgid]);
  $counts = ['compliant' => 0, 'incomplete' => 0, 'expired' => 0, 'expiring' => 0]; $bad = [];
  for ($page = 0; ; $page++) {
   $rows = self::records($orgid, $page, 500);
   foreach ($rows as $r) { $counts[$r['status']]++; if (in_array($r['status'], ['incomplete', 'expired'])) { $bad[$r['userid']] = true; } }
   if (count($rows) < 500) { break; }
  }
  $configured = $DB->record_exists('local_fleetcert_rule', ['orgid' => $orgid]);
  return ['employees' => $employees, 'compliantemployees' => $configured ? $employees - count($bad) : 0,
   'configured' => (int)$configured] + $counts;
 }
}
