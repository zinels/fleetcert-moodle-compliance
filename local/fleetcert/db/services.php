<?php
defined('MOODLE_INTERNAL') || die();
$functions = ['local_fleetcert_records' => [
 'classname' => 'local_fleetcert\\external\\records', 'methodname' => 'execute',
 'description' => 'Retrieve authorized organization training records', 'type' => 'read',
 'capabilities' => 'local/fleetcert:view',
]];
$services = ['FleetCert integration' => ['functions' => ['local_fleetcert_records'], 'restrictedusers' => 1,
 'enabled' => 0, 'shortname' => 'fleetcert']];
