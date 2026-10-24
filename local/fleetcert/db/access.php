<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
 'local/fleetcert:manage' => ['captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
 'riskbitmask' => RISK_PERSONAL | RISK_CONFIG, 'archetypes' => ['manager' => CAP_ALLOW]],
 'local/fleetcert:view' => ['captype' => 'read', 'contextlevel' => CONTEXT_SYSTEM,
 'riskbitmask' => RISK_PERSONAL, 'archetypes' => ['manager' => CAP_ALLOW]],
];
