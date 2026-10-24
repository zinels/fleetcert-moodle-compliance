<?php
namespace local_fleetcert\external;
defined('MOODLE_INTERNAL') || die();
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
final class records extends external_api {
 public static function execute_parameters() {
  return new external_function_parameters([
   'orgid' => new external_value(PARAM_INT, 'Organization ID'),
   'page' => new external_value(PARAM_INT, 'Zero based page', VALUE_DEFAULT, 0),
   'limit' => new external_value(PARAM_INT, 'Page size 1-500', VALUE_DEFAULT, 100),
   'userid' => new external_value(PARAM_INT, 'Employee filter', VALUE_DEFAULT, 0),
   'courseid' => new external_value(PARAM_INT, 'Course filter', VALUE_DEFAULT, 0),
  ]);
 }
 public static function execute($orgid, $page = 0, $limit = 100, $userid = 0, $courseid = 0) {
  $p = self::validate_parameters(self::execute_parameters(), compact('orgid', 'page', 'limit', 'userid', 'courseid'));
  self::validate_context(\context_system::instance());
  return \local_fleetcert\service\compliance::records($p['orgid'], $p['page'], $p['limit'], $p['userid'], $p['courseid']);
 }
 public static function execute_returns() {
  return new external_multiple_structure(new external_single_structure([
   'userid' => new external_value(PARAM_INT, 'Moodle user ID'),
   'courseid' => new external_value(PARAM_INT, 'Course ID'),
   'status' => new external_value(PARAM_ALPHA, 'compliant, incomplete, expired or expiring'),
   'issued' => new external_value(PARAM_INT, 'Completion epoch or zero'),
   'expires' => new external_value(PARAM_INT, 'Expiry epoch or zero'),
  ]));
 }
}
