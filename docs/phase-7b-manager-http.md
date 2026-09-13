# Phase 7B manager appointments

The normal `php artisan migrate` command applied the appointment migration on the
application MySQL database as batch 5. Both new tables were empty afterward. All 21
pre-existing tables remained; counts and aggregate row hashes for the 20 non-migration
tables matched before and immediately after migration. Only the migration ledger changed.
No seeds or database resets were run. This supersedes the pending-migration status in
the historical Phase 7A design note. The applied migration must no longer be edited.

## HTTP contract

All six explicit routes are inside the existing `company` middleware group:

| Method | Path | Name suffix |
| --- | --- | --- |
| GET | /company/appointments | index |
| GET | /company/appointments/{appointment} | show |
| PATCH | /company/appointments/{appointment}/reschedule | reschedule |
| POST | /company/appointments/{appointment}/cancel | cancel |
| POST | /company/appointments/{appointment}/complete | complete |
| POST | /company/appointments/{appointment}/no-show | no-show |

Names start with `company.appointments.`. IDs must be numeric. There are no manager
create, store, edit, or destroy endpoints. All appointment lookups use the authenticated
manager's company relation; a foreign appointment returns 404, including on mutation.
Suspended managers retain list/detail access but every mutation returns 403.

Rescheduling accepts only a local `starts_at` and optional `employee_id`. Supported
datetime formats are `Y-m-dTH:i`, `Y-m-dTH:i:s`, and `Y-m-d H:i:s` (literal T).
The local time is explicitly interpreted in Europe/Istanbul; AppointmentService then
normalizes persistence to UTC. Empty/omitted employee_id keeps the current employee.
Ownership is validated in the request; assignment, activity, schedule, horizon, duration,
and conflicts remain authoritative in AppointmentService. Booking identity, ownership,
status, end time, and snapshot fields are prohibited on the reschedule request.

The other three mutation endpoints ignore payload fields and invoke only their fixed
service action with server-derived company/user IDs. Cancel invokes cancelByCompany,
which creates no violation. Invalid business actions yield Laravel validation errors
(422 JSON, or redirect with errors for browser forms). Unexpected database failures
are not swallowed. No-show/repeated-action protection remains in the locked service.

## Pages

The list supports all/today/upcoming/completed/no-show/cancelled filters, search across
booking code and customer name/phone snapshots, and pagination. Today uses Istanbul
midnight boundaries converted to UTC. Upcoming means confirmed with a future start.
All displayed dates use Istanbul time.

The detail page shows original snapshots, current employee, read-only event history,
and an inline reschedule form. Terminal appointments have no mutation forms. Completion
and no-show buttons appear only at/after start; the backend rechecks independently.
The employee selector lists active employees assigned to the original service, but
availability remains a service check after submission.

Both existing company layout variants have an Appointments sidebar link. Admin layouts
are untouched. Dashboard metrics remain unchanged because they contain no placeholder
appointment counts requiring replacement. No financial metrics were added.

## Verification

`AppointmentHttpTest` uses isolated SQLite memory storage and covers authorization,
tenant isolation on every action, forbidden routes, local-time rescheduling, prohibited
fields, conflicts, employee eligibility, cancellation without violations, timed actions,
repeat/terminal rejection, suspended read-only access, UI action visibility, filters,
search, sidebar scope, and malformed-form redisplay.

Run the HTTP suite, full PHPUnit suite, route listing, and `git diff --check`.
The Windows frontend build currently reports `spawn EPERM` and an unloadable Tailwind
native module while loading Vite config. Dependencies/configuration are not changed to
work around that environment failure. Browser appearance and a fresh production asset
build still require verification in a working frontend build environment.

Phase 7A's documented MySQL concurrency-test requirement remains outstanding; SQLite
HTTP tests do not prove multi-connection locking semantics.

Recorded Phase 7B results: HTTP suite 40 tests / 256 assertions passed; full suite
180 tests / 826 assertions passed. Route listing contained 49 total routes, exactly
six for company appointments. `git diff --check` passed. A final MySQL comparison
matched the post-migration counts and row hashes for all 23 tables, confirming the
test runs did not change application data. No commit was created.
