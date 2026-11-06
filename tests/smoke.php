<?php
/** Framework-free checks of pure logic; this is NOT a Moodle integration harness. */
declare(strict_types=1);
define('MOODLE_INTERNAL', true);
define('DAYSECS', 86400);
// The evaluator uses Moodle's exception type. Only this type is substituted here.
class invalid_parameter_exception extends InvalidArgumentException {}
require __DIR__ . '/../local/fleetcert/classes/service/compliance.php';
require __DIR__ . '/../local/fleetcert/classes/service/onboarding.php';
use local_fleetcert\service\compliance;
use local_fleetcert\service\onboarding;
$count = 0;
$check = static function ($actual, $expected, string $label) use (&$count): void {
    $count++;
    if ($actual !== $expected) throw new RuntimeException($label . ': ' . var_export($actual, true));
};
$completed = 1000000;
$expires = $completed + 365 * DAYSECS;
foreach ([[$completed, 'compliant'], [$expires - 30 * DAYSECS - 1, 'compliant'],
          [$expires - 30 * DAYSECS, 'expiring'], [$expires - 1, 'expiring'],
          [$expires, 'expired'], [$expires + 1, 'expired']] as [$now, $expected]) {
    $check(compliance::evaluate($completed, 365, 30, $now)['status'], $expected, 'Expiration boundary');
}
$check(compliance::evaluate(null,365,30,$completed)['status'],'incomplete','Missing completion');
$check(compliance::evaluate($completed+1,365,30,$completed)['status'],'incomplete','Future completion');
$check(compliance::evaluate(0,365,30,$completed)['expires'],0,'Missing expiry');
foreach ([[0,0],[30,30],[30,-1]] as [$valid,$remind]) {
    $threw = false;
    try { compliance::evaluate(null,$valid,$remind,$completed); }
    catch (invalid_parameter_exception $e) { $threw = true; }
    $check($threw,true,'Invalid configuration rejected');
}
foreach (['=SUM(A1)','+formula','-formula','@formula',"\t=SUM(A1)"] as $text) {
    $check(onboarding::csvcell($text),"'".$text,'CSV formula escaped');
}
$check(onboarding::csvcell('Ordinary text'),'Ordinary text','Normal text preserved');
echo "Passed $count pure-logic checks (Moodle integration is tested separately).\n";
