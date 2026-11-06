<?php
namespace local_fleetcert\service;
defined('MOODLE_INTERNAL') || die();

/** Site-admin-only organization assignments; Moodle roles still control capabilities. */
final class managers {
    public static function assign(int $orgid, int $userid, bool $remove = false): void {
        global $DB;
        if (!is_siteadmin()) {
            throw new \required_capability_exception(\context_system::instance(),
                'moodle/site:config', 'nopermissions', '');
        }
        access::org($orgid, 'manage');
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        if (!$remove && ($user->suspended || isguestuser($user))) {
            throw new \invalid_parameter_exception('Choose an active, non-guest user');
        }
        $identity = ['orgid' => $orgid, 'userid' => $userid];
        if ($remove) {
            $DB->delete_records('local_fleetcert_manager', $identity);
        } else if (!$DB->record_exists('local_fleetcert_manager', $identity)) {
            $DB->insert_record('local_fleetcert_manager', (object)$identity);
        }
        \local_fleetcert\event\administration::create([
            'context' => \context_system::instance(), 'objectid' => $orgid,
            'relateduserid' => $userid,
            'other' => ['action' => $remove ? 'manager_removed' : 'manager_assigned'],
        ])->trigger();
    }
}
