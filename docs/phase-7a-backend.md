# Phase 7A appointment backend

## Scope and calling convention

This phase adds no HTTP endpoints, requests, views, WhatsApp integration, or Phase 8 work.
The appointment migration is pending on the application MySQL database. Do not apply
it as part of verification; the feature tests build an isolated SQLite in-memory schema.

`create($data, $company, $actorType, $actorId)` accepts a server-selected Company.
All mutations now require that same explicit tenant context:

```php
$backend->reschedule($appointment, $company, $newStart, $employeeId);
$backend->cancelByCustomer($appointment, $company);
$backend->cancelByCompany($appointment, $company, 'user', $managerId);
$backend->markCompleted($appointment, $company, 'user', $managerId);
$backend->markNoShow($appointment, $company, 'user', $managerId);
```

Future HTTP callers must authorize the actor and obtain Company from authenticated
server context. Passing Company is tenant scoping, not a substitute for authorization.
An input `company_id` is ignored. Each mutation reloads by appointment ID through
the supplied company's relation and checks associated customer, service, and employee
ownership. Unsaved changes and stale relationships on the supplied model are ignored.

## Locks and transaction boundaries

- Creation acquires the selected Employee row with `lockForUpdate()` before authoritative
  eligibility/slot checks. It checks current ownership, active flags, assignment,
  availability, horizon, and overlaps, then inserts while retaining the employee lock.
- Any-barber selection enumerates assigned, tenant-owned candidates by employee ID
  ascending. Each candidate is locked and revalidated; an ineligible or occupied
  candidate is skipped. Acquired locks remain until transaction completion. Database
  errors are not treated as eligibility failures.
- Rescheduling first observes the persisted employee ID to choose a mutex, locks the
  destination Employee, and then locks/reloads the Appointment. Status and the slot are
  validated only afterward. If a concurrent reschedule changed the default employee
  while waiting, the transaction is released and selection retried (bounded to five).
- The overlap read additionally uses `lockForUpdate()` to obtain a current read under
  MySQL's REPEATABLE READ isolation, even if candidate selection established an earlier
  consistent snapshot. This is **not** the primary serialization mechanism: the
  Employee row is always locked first. The overlap predicate is strictly
  `existing.starts_at < new_end AND existing.ends_at > new_start`; adjacency is allowed.
- Terminal actions lock/reload the Appointment before status checks or writes. They
  never acquire employee locks. Transition, event, violation, and audit writes share
  one transaction. Customer rows are locked before booking eligibility and before
  recording appointment violations, serializing appointment-originated restriction work.
- Laravel retries detected deadlocks up to three transaction attempts. Cross-employee
  reschedules and overlapping locking reads can deadlock; retries redo all checks.
  Failed attempts roll back all their writes.
- Globally unique booking codes use `AP-` plus eight random characters excluding
  `O`, `0`, `I`, `1`. The database unique constraint is authoritative. An insert-time
  booking-code collision retries the whole transaction, at most ten times; unrelated
  unique violations propagate.

All booking/rescheduling callers must use this service. Direct database writes bypass
its concurrency protocol. Concurrent administrative schedule/assignment changes are
not fully serialized by the existing Phase 5 management code. Likewise existing
manual restriction operations do not yet participate in the customer-row protocol;
this phase does not claim to fix all Phase 6 concurrency paths.

## Status and history

`AppointmentStatus` remains the single transition matrix: confirmed can become
completed, no_show, cancelled_by_customer, or cancelled_by_company. Every terminal
status rejects subsequent actions, including a repeat of the same action. Rejections
create no new events or violations. Rescheduling retains confirmed status.

Completion/no-show is allowed at or after start, never before it. Customer cancellation
is late when `now + late_cancellation_hours >= starts_at`, including the exact boundary.
Company cancellation and manager rescheduling do not create violations. Customer
cancellation remains possible while a company is suspended; manager mutations and
new bookings require an active company, preserving the existing distinction.

Original customer identity, service name/duration/price, and employee name snapshots
are retained. Rescheduling uses the stored duration for both end-time calculation and
AvailabilityService validation. Changing the current employee changes `employee_id`,
not the original employee-name snapshot. The event records old/new employee IDs and
their names at reschedule time, plus old/new UTC start/end instants.

Normal model updates to booking identity/snapshots throw. Normal event updates/deletes
throw. These are application guards, not database immutability: raw SQL, bulk builder
writes, or explicitly suppressed model events can bypass them. There are no such
mutation paths in AppointmentService. Restrictive foreign keys prevent parent deletion
from cascading away appointment or event history. Events with equal second timestamps
are ordered by ID as a tie-breaker.

## Timezone convention

The application timezone stays UTC. AvailabilityService continues using Europe/Istanbul
for business dates, weekly windows, and booking horizon. Service callers supply Carbon
instants with an explicit timezone; future HTTP parsing must explicitly interpret local
input in Europe/Istanbul rather than rely on the app default. The service copies inputs,
converts them to UTC, and drops fractional seconds to match existing database precision.
It never changes the caller's Carbon object.

Appointments, terminal timestamps, and event creation instants are persisted in UTC.
Appointment start/end and event created_at use DATETIME, avoiding session-timezone
conversion for those values. Existing Laravel created_at/updated_at timestamps retain
the project convention. Event metadata uses ISO 8601 with an explicit UTC offset.
Presentation should convert UTC instants to Europe/Istanbul. No app-wide timezone or
existing Phase 1–6 data conversion is included.

## Verification and remaining MySQL checks

Run `php vendor/bin/phpunit tests/Feature/AppointmentManagementTest.php`, then
`php vendor/bin/phpunit`. The appointment test class refuses non-memory/non-SQLite
connections before RefreshDatabase runs. Its grammar instrumentation verifies actual
query lock intent, order, and transaction nesting. SQLite cannot prove MySQL locking.

Before production approval, use a separately provisioned disposable MySQL test database
and independent connections/processes to verify:

1. Two bookings competing for an initially empty employee slot: exactly one succeeds.
2. Any-barber contenders fall back deterministically after waiting on the first employee.
3. Two stale callers attempting conflicting terminal actions: one event/violation at most.
4. Reschedule versus booking into the destination slot, and opposite employee swaps:
   no overlapping confirmed appointments after successful commits/deadlock retries.
5. Unique-code collision across different companies and employees: loser retries cleanly.

Do not use the real application database for these tests. The pending migration keeps
VARCHAR status, a global booking-code unique index, explicit foreign keys, and no soft
deletes or MySQL ENUM. Its new composite slot index supports the scoped overlap query.
