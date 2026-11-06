<?php
namespace local_fleetcert\external;
defined('MOODLE_INTERNAL') || die();
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/** Authorized organization summary, using the same calculations as the UI. */
final class summary extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([
            'orgid' => new external_value(PARAM_INT, 'Organization ID'),
        ]);
    }
    public static function execute($orgid) {
        $params = self::validate_parameters(self::execute_parameters(), ['orgid' => $orgid]);
        self::validate_context(\context_system::instance());
        return \local_fleetcert\service\compliance::summary($params['orgid']);
    }
    public static function execute_returns() {
        $fields = [];
        foreach (['employees','compliantemployees','configured','compliant','incomplete','expired','expiring'] as $key) {
            $fields[$key] = new external_value(PARAM_INT, $key);
        }
        return new external_single_structure($fields);
    }
}
