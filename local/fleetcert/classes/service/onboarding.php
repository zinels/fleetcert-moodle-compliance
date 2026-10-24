<?php
namespace local_fleetcert\service;
defined('MOODLE_INTERNAL') || die();
/** Strict CSV validation and row transactions with safe retry identities. */
final class onboarding {
 public static function preview(string $csv): array {
  $stream = fopen('php://temp', 'r+'); fwrite($stream, $csv); rewind($stream);
  $header = fgetcsv($stream); $result = []; $seen = [];
  if ($header !== ['username', 'firstname', 'lastname', 'email']) {
   fclose($stream); throw new \invalid_parameter_exception('Expected username,firstname,lastname,email header');
  }
  while (($row = fgetcsv($stream)) !== false) {
   if (count($result) >= 2000) { throw new \invalid_parameter_exception('Maximum 2000 rows'); }
   $error = ''; $data = count($row) === 4 ? array_combine($header, array_map('trim', $row)) : [];
   if (!$data || in_array('', $data, true)) { $error = 'Missing required fields'; }
   else if (!validate_email($data['email'])) { $error = 'Invalid email'; }
   else if ($data['username'] !== clean_param($data['username'], PARAM_USERNAME) || strlen($data['username']) > 100 ||
    \core_text::strlen($data['firstname']) > 100 || \core_text::strlen($data['lastname']) > 100 || strlen($data['email']) > 100) {
    $error = 'Invalid or oversized field';
   } else if (isset($seen['u:' . $data['username']]) || isset($seen['e:' . strtolower($data['email'])])) { $error = 'Duplicate row'; }
   if ($data) { $seen['u:' . $data['username']] = true; $seen['e:' . strtolower($data['email'])] = true; }
   $result[] = ['data' => $data, 'error' => $error];
  }
  fclose($stream); return $result;
 }
 public static function commit(int $orgid, array $rows): array {
  global $DB, $CFG;
  access::org($orgid, 'manage');
  require_once($CFG->dirroot . '/user/lib.php'); require_once($CFG->libdir . '/enrollib.php');
  $factory = \core\lock\lock_config::get_lock_factory('local_fleetcert');
  $lock = $factory->get_lock('onboarding', 10);
  if (!$lock) { throw new \moodle_exception('locktimeout'); }
  try {
   foreach ($rows as &$row) {
    if ($row['error']) { continue; }
    $transaction = $DB->start_delegated_transaction();
    try {
     $data = $row['data'];
     $user = $DB->get_record('user', ['username' => $data['username'], 'mnethostid' => $CFG->mnet_localhost_id]);
     if ($user) {
      // Never attach an existing unrelated account based on an untrusted CSV identity.
      if ($user->deleted || $user->email !== $data['email'] || !$DB->record_exists('local_fleetcert_member', ['userid' => $user->id, 'orgid' => $orgid])) {
       throw new \invalid_parameter_exception('Account identity conflict');
      }
     } else {
      if ($DB->record_exists('user', ['email' => $data['email'], 'deleted' => 0])) { throw new \invalid_parameter_exception('Email already in use'); }
      $data += ['auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
       'password' => generate_password(32)];
      $id = user_create_user((object)$data);
      $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
      set_user_preference('auth_forcepasswordchange', 1, $id);
      $DB->insert_record('local_fleetcert_member', (object)['orgid' => $orgid, 'userid' => $id]);
     }
     $manual = enrol_get_plugin('manual');
     foreach ($DB->get_records('local_fleetcert_rule', ['orgid' => $orgid]) as $rule) {
      $instances = enrol_get_instances($rule->courseid, true); $instance = null;
      foreach ($instances as $candidate) { if ($candidate->enrol === 'manual') { $instance = $candidate; break; } }
      if (!$instance) { throw new \invalid_parameter_exception('Required course has no active manual enrolment'); }
      $role = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
      $manual->enrol_user($instance, $user->id, $role->id);
     }
     \local_fleetcert\event\administration::create(['context' => \context_system::instance(),
      'objectid' => $orgid, 'relateduserid' => $user->id, 'other' => ['action' => 'onboard']])->trigger();
     $transaction->allow_commit(); $row['error'] = 'Success';
    } catch (\Throwable $e) {
     try { $transaction->rollback($e); } catch (\Throwable $ignored) { }
     $row['error'] = 'Failed: identity conflict or course configuration; correct configuration and retry';
    }
   }
   unset($row); return $rows;
  } finally { $lock->release(); }
 }
 public static function csvcell(string $value): string {
  return preg_match('/^[\s]*[=+@-]/u', $value) ? "'" . $value : $value;
 }
}
