# Resume bullets

- Implemented a native PHP Moodle local plugin with XMLDB-backed organization requirements and a reusable completion-based compliance evaluator.
- Built CSV onboarding with validation previews, Moodle account creation and enrolment APIs, per-row transactions, and tenant-aware account reuse.
- Added capability-protected training reports, paginated Moodle External Services records, scheduled expiry reminders, and a Docker Compose development configuration.

# Application email draft

Subject: PHP Developer application — Moodle training compliance project

Hello,

I’m applying for the PHP Developer role. I’ve attached FleetCert, a Moodle local plugin I built to explore training compliance for logistics teams:
https://github.com/zinels/fleetcert-moodle-compliance

The plugin stores customer requirements as organization/course configuration and evaluates Moodle completion timestamps against those rules. One important decision was to keep Moodle responsible for accounts, enrolments and completion rather than maintain a second version of that data. Onboarding retries also needed an explicit identity policy: an existing username is reused only when its email and organization membership match.

The next change I would make is a proper renewal history model. Moodle’s current course completion timestamp is useful for an initial implementation, but recurring training needs explicit issuance history and a careful course-reset policy. I would also expand MySQL-backed integration tests. Runtime validation is still outstanding because my authoring environment could not start Docker; the repository documents that limitation.

I’d welcome the opportunity to discuss these decisions and how they relate to your Moodle platform.

Best,
Tanzeel
