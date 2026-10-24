<?php
defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
 $ADMIN->add('localplugins', new admin_externalpage('local_fleetcert', 'FleetCert',
 new moodle_url('/local/fleetcert/index.php')));
}
