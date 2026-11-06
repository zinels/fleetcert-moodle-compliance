# Development and verification

## Fast checks

From the repository root:

```sh
php tests/smoke.php
find local docker tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

The smoke runner substitutes only Moodle's invalid-parameter exception type and loads the pure evaluator/CSV-escaping helper. It makes no claim about database integration, authentication, or Moodle configuration.

## Moodle PHPUnit

The `.github/workflows/moodle.yml` workflow uses MoodleHQ's [Moodle Plugin CI](https://moodlehq.github.io/moodle-plugin-ci/) to install Moodle 4.5.15 and MySQL 8.0, then run the plugin tests on PHP 8.3. The test database is disposable and separate from the development instance.

To run the same plugin suite in a local Moodle development installation:

1. Install Composer development dependencies in the Moodle root (`composer install`). Composer is not bundled in this repository's Docker image.
2. Configure `$CFG->phpunit_prefix = 'phpu_';` and `$CFG->phpunit_dataroot = '/var/www/phpunitdata';` **before** `lib/setup.php` is required. Create that writable directory as the web-server user.
3. Initialize: `php admin/tool/phpunit/cli/init.php`.
4. Run: `vendor/bin/phpunit --testsuite local_fleetcert_testsuite`.

Never run initialization against a production database/dataroot. Tests use generated accounts, direct completion fixtures, and Moodle's message sink; they do not send real reminder emails or execute browser flows.

## Manual acceptance checks

- Complete a fresh installation and plugin upgrade; seed the synthetic demo twice.
- Compare dashboard totals with the course records and full CSV export.
- Validate employee/course/status filters and Previous/Next behavior.
- Assign a manager with the view capability to one organization; confirm another organization's UI and REST calls are denied. Confirm assignment removal revokes access.
- Import valid/invalid CSV rows; retry the same valid accounts. Introduce a disabled manual enrolment and verify that failed rows leave no account or membership behind.
- Enable the restricted integration service and call records/summary with a dedicated user's token. Test an expired-only page and a forbidden organization.
- Run `php admin/cli/cron.php` twice and inspect reminders. Configure a real mail transport only when testing delivery with authorized recipients.

The daily reminder task is scheduled at 06:17 in Moodle. Production requires Moodle cron every minute; no cron sidecar is included here.

## Known boundaries

Manager assignment does not grant Moodle roles. Revoke organization assignments separately from changing system capabilities. Imports are synchronous, limited to 2,000 preview rows, and stored in the Moodle session. Renewal remains administrator-managed. Reports use current completion timestamps and configured validity days, not an immutable certification ledger. Privacy export/deletion and production retention remain unimplemented.
