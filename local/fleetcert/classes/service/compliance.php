<?php
namespace local_fleetcert\service;
defined('MOODLE_INTERNAL') || die();

/** Configuration-driven calculations; Moodle owns completion timestamps. */
final class compliance {
    public const STATUSES = ['compliant', 'incomplete', 'expired', 'expiring'];

    public static function evaluate(?int $completed, int $validdays, int $reminddays, int $now): array {
        if ($validdays < 1 || $reminddays < 0 || $reminddays >= $validdays) {
            throw new \invalid_parameter_exception('Reminder days must be less than positive validity days');
        }
        if (!$completed || $completed > $now) {
            return ['status' => 'incomplete', 'issued' => 0, 'expires' => 0];
        }
        $expires = $completed + $validdays * DAYSECS;
        return [
            'status' => $expires <= $now ? 'expired' :
                ($expires <= $now + $reminddays * DAYSECS ? 'expiring' : 'compliant'),
            'issued' => $completed, 'expires' => $expires,
        ];
    }

    /** Authorize and stream matching records; exports need not stop at one page. */
    public static function stream(int $orgid, int $userid = 0, int $courseid = 0,
            string $status = '', int $offset = 0, int $limit = 0): \Generator {
        global $DB;
        access::org($orgid);
        if ($status !== '' && !in_array($status, self::STATUSES, true)) {
            throw new \invalid_parameter_exception('Unknown training status');
        }
        if ($offset < 0 || $limit < 0) {
            throw new \invalid_parameter_exception('Invalid pagination');
        }
        $now = time();
        $params = ['orgid' => $orgid];
        $extra = '';
        foreach (['userid' => $userid, 'courseid' => $courseid] as $field => $value) {
            if ($value < 0) {
                throw new \invalid_parameter_exception('Filters must be nonnegative IDs');
            }
            if ($value) {
                $alias = $field === 'userid' ? 'm' : 'r';
                $extra .= " AND $alias.$field = :$field";
                $params[$field] = $value;
            }
        }
        // Apply filters before pagination so pages never hide matching records.
        if ($status === 'incomplete') {
            $extra .= ' AND (c.timecompleted IS NULL OR c.timecompleted = 0 OR c.timecompleted > :future)';
            $params['future'] = $now;
        } else if ($status !== '') {
            $extra .= ' AND c.timecompleted > 0 AND c.timecompleted <= :future';
            $params['future'] = $now;
            $expiry = '(c.timecompleted + r.validdays * ' . DAYSECS . ')';
            if ($status === 'expired') {
                $extra .= " AND $expiry <= :expirednow";
                $params['expirednow'] = $now;
            } else {
                $window = '(:windownow + r.reminddays * ' . DAYSECS . ')';
                $params['windownow'] = $now;
                if ($status === 'expiring') {
                    $extra .= " AND $expiry > :validnow AND $expiry <= $window";
                    $params['validnow'] = $now;
                } else {
                    $extra .= " AND $expiry > $window";
                }
            }
        }
        $sql = "SELECT m.id AS memberid, m.userid, r.courseid, r.validdays, r.reminddays, c.timecompleted
                  FROM {local_fleetcert_member} m
                  JOIN {user} u ON u.id = m.userid AND u.deleted = 0 AND u.suspended = 0
                  JOIN {local_fleetcert_rule} r ON r.orgid = m.orgid
             LEFT JOIN {course_completions} c ON c.userid = m.userid AND c.course = r.courseid
                 WHERE m.orgid = :orgid $extra
              ORDER BY m.userid, r.courseid";
        $rs = $DB->get_recordset_sql($sql, $params, $offset, $limit);
        try {
            foreach ($rs as $row) {
                yield ['userid' => (int)$row->userid, 'courseid' => (int)$row->courseid] +
                    self::evaluate((int)$row->timecompleted, (int)$row->validdays, (int)$row->reminddays, $now);
            }
        } finally {
            $rs->close();
        }
    }

    public static function records(int $orgid, int $page = 0, int $limit = 100,
            int $userid = 0, int $courseid = 0, string $status = ''): array {
        if ($page < 0 || $limit < 1 || $limit > 500) {
            throw new \invalid_parameter_exception('Invalid pagination');
        }
        return iterator_to_array(self::stream($orgid, $userid, $courseid, $status, $page * $limit, $limit), false);
    }

    public static function summary(int $orgid): array {
        global $DB;
        access::org($orgid);
        $employees = $DB->count_records_sql("SELECT COUNT(1) FROM {local_fleetcert_member} m
            JOIN {user} u ON u.id = m.userid
            WHERE m.orgid = ? AND u.deleted = 0 AND u.suspended = 0", [$orgid]);
        $counts = array_fill_keys(self::STATUSES, 0);
        $bad = [];
        foreach (self::stream($orgid) as $record) {
            $counts[$record['status']]++;
            if (in_array($record['status'], ['incomplete', 'expired'], true)) {
                $bad[$record['userid']] = true;
            }
        }
        $configured = $DB->record_exists('local_fleetcert_rule', ['orgid' => $orgid]);
        return ['employees' => $employees, 'compliantemployees' => $configured ? $employees - count($bad) : 0,
            'configured' => (int)$configured] + $counts;
    }
}
