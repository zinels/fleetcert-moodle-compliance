<?php
namespace local_fleetcert\event;
defined('MOODLE_INTERNAL') || die();
final class administration extends \core\event\base {
 protected function init() { $this->data['crud'] = 'u'; $this->data['edulevel'] = self::LEVEL_OTHER; $this->data['objecttable'] = 'local_fleetcert_org'; }
 public static function get_name() { return 'FleetCert administration'; }
 public function get_description() { return 'FleetCert organization ' . $this->objectid . ': ' . $this->other['action']; }
}
