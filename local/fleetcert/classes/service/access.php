<?php
namespace local_fleetcert\service;
defined('MOODLE_INTERNAL') || die();
/** Central tenant authorization for UI and external services. */
final class access {
 public static function org(int $orgid, string $capability = 'view'): void {
  global $DB, $USER;
  require_capability('local/fleetcert:' . $capability, \context_system::instance());
  $DB->get_record('local_fleetcert_org', ['id' => $orgid], '*', MUST_EXIST);
  if (!is_siteadmin() && !$DB->record_exists('local_fleetcert_manager', ['orgid' => $orgid, 'userid' => $USER->id])) {
   throw new \required_capability_exception(\context_system::instance(), 'local/fleetcert:' . $capability, 'nopermissions', '');
  }
 }
}
