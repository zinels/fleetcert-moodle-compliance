# Portfolio notes

FleetCert is a native PHP Moodle plugin for organization-specific training requirements. It adds bulk onboarding, training-status reports, full filtered CSV exports, organization-scoped REST records and summaries, manager assignment, and scheduled reminders while keeping Moodle responsible for identity, enrolment, and completion.

## Resume bullets

- Implemented configurable organization/course requirements and a shared evaluator for incomplete, valid, expiring, and expired training records.
- Built CSV onboarding with preview validation, Moodle account/enrolment APIs, per-row transactions, and tenant-aware account reuse.
- Added organization-scoped reports, full streamed exports, REST records/summary services, manager assignment, and reminder deduplication, with fast checks and a MySQL-backed Moodle PHPUnit workflow.

## Describing the boundaries

This is an alpha portfolio project using synthetic data, not a production certification system. Explain the current CI result separately from manual browser testing and Docker/demo verification. Recurring renewal history and Moodle Privacy API support remain unfinished. Do not claim legal certification, SOC 2 compliance, production deployment, or verified external email delivery.

## Discussion topics

- Customer differences are data: one requirement per organization/course replaces customer-specific branches.
- A Moodle capability and an organization assignment answer different questions: what a user may do, and whose records they may access.
- A safe account retry needs email and tenant membership checks as well as a matching username.
- Reporting should distinguish employees from training records and should export the complete filtered result.
- A reminder ledger prevents ordinary repeats but does not guarantee exactly-once delivery across crashes.
- A mature renewal model needs issuance history and an explicit completion-reset policy.
