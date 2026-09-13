# Phase 7C hardening

## Scope and working tree

Project: `E:\next custimar`, branch `phase-7`. Phase 7B changes were already present but uncommitted at the start of this work. They were preserved. No commit, dependency installation, package update, framework change, WhatsApp integration, payment integration, or new product feature was performed.

Phase 7C files:

- `app/Http/Controllers/Admin/AuthController.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Company/AvailabilityController.php`
- `app/Http/Controllers/Company/EmployeeController.php`
- `app/Http/Requests/Company/StoreAvailabilityExceptionRequest.php`
- `app/Models/CustomerBlock.php`
- `app/Services/AppointmentService.php`
- `app/Services/AvailabilityService.php`
- `app/Services/CustomerRestrictionService.php`
- `app/Support/LoginThrottle.php` (new)
- `app/Support/TemporaryEmployeeCredentials.php` (new)
- `database/migrations/2026_09_13_000001_harden_availability_and_restriction_integrity.php` (new)
- `resources/views/company/employees/index.blade.php`
- `tests/Feature/Phase7CHardeningTest.php` (new)
- `docs/phase-7c-hardening.md` (new)

## One-time employee credentials

The creation POST hashes the employee password through the existing model cast. It encrypts the credential envelope with Laravel's authenticated `Crypt::encryptString`, using the application encryption key. The envelope contains the manager ID, company ID, credentials, and a five-minute expiry. Only ciphertext is flashed under `new_employee_credentials`; no plaintext password is stored in the session payload, cache, audit log, or employee row.

The existing redirect to the employee index is preserved. The index pulls (removes) the ciphertext, decrypts it in request memory, validates the manager/company binding and expiry, and passes the credentials directly to Blade. A cache `add` atomically records a boolean receipt under a SHA-256 hash of the ciphertext until expiry. This receipt contains no credentials and prevents a concurrent request or stale session write from displaying the same envelope again. Use the existing shared database cache in production; do not replace it with per-process array caching. Tampered, expired, differently bound, and legacy plaintext values are discarded.

The response uses `Cache-Control: private, no-store, max-age=0`. Clipboard arguments use Laravel's JavaScript escaping rather than interpolating user-controlled text into a template literal. The password is necessarily present in the first HTML response and browser memory for the requested display/copy UX. It is not placed in a URL or browser storage. A failed/lost display response consumes the credential; refresh cannot recover it. HTTPS remains the transport requirement in deployment.

A read-only check of all 10 real database sessions found zero legacy plaintext credential envelopes. No existing sessions were deleted or modified.

## Login throttling

Both login portals share Laravel RateLimiter keys derived from SHA-256 of trimmed, lowercase email plus IP. Five failed attempts are allowed per 60-second decay window. Further attempts receive a generic throttle validation error (HTTP 429 for JSON; normal validation redirect for browser forms). Failed authentication increments the count; successful authentication clears it. Switching login portals or email case does not bypass the limit. Different identities/IPs remain independent.

Admin authentication includes the required role in its credential query. Wrong-role credentials receive the same generic error as an unknown account; they do not create an authenticated session. Existing generic credential failure messages remain on the general portal. No CAPTCHA was added.

## Restrictions and locking

All restriction mutations run inside a transaction and acquire the tenant-scoped customer row with `lockForUpdate` before reading/inserting restriction records. Transactions retry deadlocks up to three times. Block and violation reads used for decisions are locking current reads, avoiding stale MySQL REPEATABLE READ snapshots after waiting for the customer mutex. Booking eligibility now also uses this path. Rescheduling checks the same eligibility as booking; blocked/inactive customers cannot bypass restrictions through rescheduling.

The deterministic overlap policy is to reuse the latest active block (ordered by start time, then ID), preserving its source, reason, duration, and history. A manual request during an automatic block therefore retains the automatic block unchanged. Automatic creation also rechecks the actual threshold under the customer lock rather than trusting the supplied count. Unblock lifts every currently active block, preserving expired historical rows. Repeated unblock returns false without another audit entry.

Repeated violations for the same customer, appointment, and violation type return the existing record. Appointment ownership/customer membership is checked before accepting that reference. A new automatic block stores the latest violation ID as `violation_cursor_id`. This resets the cycle even when a new violation arrives in the same second. Historical blocks with a NULL cursor retain the previous `occurred_at > starts_at` cutoff; no historical violations or blocks are rewritten or deleted.

Lock ordering remains employee -> appointment (when applicable) -> customer -> restriction rows for scheduling paths. Standalone restriction mutations start at customer and never acquire an appointment write lock. Terminal appointment transitions still lock/reload the appointment and reject repeat terminal transitions. SQLite tests verify outcomes and transaction/query ordering, but cannot prove MySQL concurrent lock semantics. A multi-connection stress test belongs on an isolated MySQL test database, not the real business database.

## Availability exceptions

Before migration, MySQL contained zero exception rows and zero exception windows, with no duplicate effective company/service/employee target/date groups. No reconciliation or history removal was needed.

The migration adds three unique indexes. Company exceptions use `(company_id, company_exception_date)`, where the virtual date is populated only for type `company`. Service and employee indexes use company, type, target ID, and date. This handles nullable target IDs without allowing duplicate company closures. The HTTP validator reports duplicate dates; the controller also converts a concurrent unique-constraint failure to a validation error.

The first attempted design referenced cascading foreign-key columns in a stored generated expression. MySQL 5.7.24 rejected that statement before changing the schema. Inspection confirmed all original columns/indexes remained intact. The corrected generated expression uses only type/date, and normal `php artisan migrate` succeeded.

The migration preflights duplicate/invalid targets before DDL and refuses to alter them silently on another installation. Such installations need explicit reconciliation preserving their reasons/windows before retrying. No duplicate data was present in this project's real database.

AvailabilityService explicitly orders exception selection: full closure first, newest ID second. This supplies a deterministic, fail-closed fallback for pre-constraint legacy data. Exception windows are sorted by start time and ID. A company closure overrides service/employee windows; a target closure closes that target. Target custom windows replace its weekly schedule. Company custom windows restrict those windows by intersection. Empty custom windows mean closed. Validation of exception dates now uses Istanbul's current business day, rather than UTC's calendar date.

## Timezone convention

- Laravel app timezone and persisted internal datetimes remain UTC.
- Appointment service inputs are timezone-aware Carbon instants; the service normalizes them to UTC seconds at persistence boundaries.
- Salon scheduling inputs, exception dates, and weekly availability are interpreted in `Europe/Istanbul`.
- The manager index, detail, form defaults, and event history display Istanbul local time. Conversion uses copies, leaving model instants unchanged.
- Reschedule form values are local wall-clock strings parsed with the named timezone exactly once. Event metadata contains ISO-8601 timestamps with explicit UTC offsets.
- Today filtering uses Istanbul midnight through the next Istanbul midnight, converts both to UTC, and uses a half-open interval.
- The dashboard currently has no appointment-aware metrics requiring conversion.

Tests cover winter/summer 2015 (different historical Istanbul offsets), modern Istanbul time, UTC persistence, UI rendering, rescheduling, and the Istanbul/UTC day boundary. No hardcoded three-hour arithmetic is used.

## Appointment UI and security review

Blade/rendered-response review: responsive filter stacking and detail grids, horizontally scrollable appointment table, enum-based status badges, empty states, visible validation errors, explicit snapshot labels, current versus original employee labels, and local timestamps are present. Cancel, complete, and no-show use confirmation prompts. Completion/no-show appear only after the start instant; terminal and suspended views hide mutation forms. Server-side checks independently enforce these restrictions.

The route list contains exactly index, show, reschedule, cancel, complete, and no-show. There are no manager create/store/edit/destroy appointment routes or hidden create/delete forms. Existing tenant-scoped lookup, spoofing, suspension, snapshot, and terminal-transition regressions remain covered. Model event hooks reject ordinary appointment-event updates/deletes; raw SQL, query-builder mutations, or explicitly disabled model events remain privileged bypasses, not database-enforced immutability. No new API for those operations was added.

No interactive browser was available in this session. Responsive markup and rendered behavior were reviewed and tested; an actual mobile/desktop click-through remains a reviewer check.

## Frontend and PHP diagnostics

Node v24.15.0, npm 11.3.0. Direct loading of `@tailwindcss/oxide` succeeds. A minimal Node child-process launch fails with EPERM inside the execution sandbox and succeeds outside it. The unchanged project builds successfully outside that sandbox. This identifies an execution-environment restriction; it is not evidence of corrupted node_modules, incompatible Node, or a broken Tailwind binary. The native-module/invalid-UTF-8 reports from the restricted build are downstream symptoms, not grounds for replacing dependencies.

Safe remedy: run `npm run build` in a normal terminal with child-process execution permitted. No reinstall or lockfile change is needed. `npm ls --depth=0` reports only the optional platform dependency `@laravel/multiplex` as unmet. Vite emits a non-blocking warning that optimized font fallbacks need optional `fontaine`; this was not installed solely to silence a warning.

CLI PHP 8.3.1 loads `E:\MAMP\bin\php\php8.3.1\php.ini`. The ini loads working `php_gd.dll` and `php_pdo_mysql.dll`, but also references missing `php_gd2.dll` and `php_mysqli.dll`. Running `php -n -m` confirms mysqli is compiled into this PHP binary, so the extra mysqli DLL directive is redundant. GD is already loaded through php_gd.dll. No current application code directly calls mysqli or GD; Laravel's working MySQL connection uses PDO MySQL.

Safest manual correction: back up that ini, comment only `extension=php_gd2.dll` and `extension=php_mysqli.dll`, retain `extension=php_gd.dll` and `extension=php_pdo_mysql.dll`, then verify `php -m` and `php artisan migrate:status`. No system configuration was modified during Phase 7C.

## Real database preservation

The additive migration is applied as batch 6. All 23 tables were checked before and after; counts and SHA-256 hashes of existing rows were identical except for the migration ledger increasing from 11 to 12 rows. No data migrations, destructive commands, or business-record writes were performed against real MySQL. Feature tests use isolated SQLite in memory.

## Verification results

- Existing focused employee/customer/availability/appointment suites: 124 passed, 609 assertions.
- Final Phase 7C hardening suite: 28 passed, 187 assertions.
- Full `php vendor/bin/phpunit`: 208 passed, 1,015 assertions; zero failures/errors/skips.
- `git diff --check`: passed (exit 0; only line-ending advisory warnings).
- Final `npm run build` outside the subprocess-restricted sandbox: passed, 109 modules, 5.90 seconds. All ten appointment status badge utility classes are present in the generated CSS.
- Existing PHP startup DLL warnings are independent of the passing test results.
