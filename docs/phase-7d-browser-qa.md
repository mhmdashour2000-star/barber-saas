# Phase 7D browser QA

## Environment and method

Verified `E:\next custimar`, branch `phase-7`, Laravel 13.30.1, PHP 8.3.1, MySQL, UTC app timezone, linked `public/storage`, all 12 migrations applied, and 49 total routes. Exactly six company appointment routes remain: index, show, reschedule, cancel, complete, no-show. No appointment create/store/edit/destroy routes were added.

QA used an isolated Chrome 153 profile, the real local Laravel application and MySQL database, browser form submissions, confirmation dialogs, DevTools network/console monitoring, keyboard input, DOM inspection, and visual screenshot review. Widths were 1440, 820, and 390 pixels. Passwords were generated randomly and held in the QA helper's memory; no password was written to these notes or screenshots. The app's existing employee password display was tested without capturing that screen.

## Browser workflow results

| Area | Result and evidence |
|---|---|
| Manager login | Correct login reached the dashboard after a generic wrong-password error; normal login was not impeded by throttling. |
| Dashboard/navigation | Dashboard and appointment link loaded; manager pages had no admin navigation links. Desktop and mobile sidebar controls work after the CSS fix. |
| Appointment list | Booking codes, long customer/service names, phone, employee, status and Istanbul times rendered. Today returned the one QA appointment on the current Istanbul business day, Upcoming returned two, and Completed/No-show/Cancelled each returned one. Search with no match displayed the empty state. |
| Detail and snapshots | Own-company detail loaded with customer, phone, original/current employee, price 123.45 TRY and duration 30 minutes. No create/edit/delete appointment UI. |
| Reschedule | An out-of-hours 23:50 start produced a visible validation error. A valid 14:00 Istanbul reschedule persisted as 11:00 UTC, retained snapshots and changed the employee from Primary to Alternate. History showed both employees and old/new local times. Foreign employee ID 5 was absent from choices. |
| Cancellation | Browser confirmation appeared; appointment 2 became cancelled_by_company. No violation was attached to this appointment. Mutation forms disappeared. |
| Completion | Future completion was hidden and a deliberate direct POST returned 422. Appointment 3 completed with history; terminal actions disappeared. |
| No-show | Future no-show was hidden. Appointment 4 became no_show with exactly one violation. A repeated direct POST returned 422 and did not duplicate the violation. |
| Tenant isolation | The second QA company's manager received 404 when opening company 6's appointment 1. |
| Suspension | Admin suspended only QA company 6. Its manager could list/show appointments, saw no mutation forms, and a deliberate cancellation POST returned 403. Admin subsequently restored the QA company to active. |
| Admin | Login, dashboard/company navigation, company detail, and suspend/reactivate controls worked. No manager appointment links appeared in admin navigation. |
| Employees | Browser-created employee 6 displayed credentials once, then not on refresh or Back. A second employee tested navigating away from the credential page and returning with Back: no replay. Name editing and deactivation worked. |
| Employee authentication limitation | The existing project has no employee login route/controller. The display describes Company Code + Username + Password, but an actual employee authentication flow cannot be verified. No new login feature was added. |
| Services | Service page/edit loaded. Employee assignments were changed from [3,4] to [3], verified in MySQL, then restored to [3,4]. |
| Weekly availability | Service and employee Monday hours were saved through their browser forms. |
| Exceptions | Company custom hours 11:00–16:00 saved after the checkbox fix. Repeating the company/date showed a clear duplicate-date error. A separate service closure on the same date saved and displayed alongside it without target ambiguity. |
| Customers | Detail, violation count, block modal, manual block, repeated block and unblock worked. Repeated block reused the active row. The expired fixture and lifted history remained visible; the customer ended unblocked with one violation. |

## Verified bugs and minimal fixes

1. **Desktop sidebar absent despite reserved sidebar space.** `flowbite/dist/flowbite.css` includes a complete utility layer. Importing it after generated Tailwind utilities let generic `hidden` and translation rules override project responsive rules. Reordered the two imports. Chrome verified the desktop sidebar, desktop toggle and mobile drawer afterwards.
2. **Custom availability hours could not be saved.** Unchecked HTML checkboxes submit no value, while the request requires `is_closed`. Added a hidden `0` field before the checkbox. A browser reproduction originally returned “The is closed field is required”; the same workflow now saves custom windows.
3. **Availability validation discarded the entered custom hours.** Replaced hardcoded time values with escaped old input and preserved the unchecked state through the explicit boolean field. Added labels to the time inputs and allowed them to shrink within the mobile form.
4. **Availability form used the previous UTC calendar date.** At the observed Istanbul/UTC day boundary, the UI default/minimum disagreed with server validation. Both now use the named business timezone, without changing app timezone.
5. **Hidden mobile navigation remained keyboard-focusable.** Real Tab key events reached links with negative screen X coordinates. Off-screen sidebars are now inert, expose expanded/control state, focus the close control when opened, and close on Escape with focus returned to the toggle. Both manager/admin layout variants were kept consistent. Repeating the Tab test reached only visible controls.
6. **Appointment success message appeared twice.** The company layout already renders the flash message. Removed the duplicate block from appointment detail.

## Local server asset diagnosis

The normal `php artisan serve` instance produced Chrome `ERR_INVALID_HTTP_RESPONSE` for static CSS/JS/favicon files. Requests without compression appeared successful, which initially obscured the cause. Raw socket comparison identified an empty gzip stream **before** the HTTP status line when the request sent `Accept-Encoding: gzip`. The loaded MAMP ini has `zlib.output_compression = On`.

A temporary loopback asset proxy allowed workflow testing while diagnosing this. Final direct-browser checks used Laravel's standard development router with compression disabled **only for that PHP process**. Static responses then began with `HTTP/1.1`, Chrome loaded the stylesheet/scripts, and direct desktop/mobile screenshots had no asset errors. No system ini, production config, dependencies, or framework stack was changed.

Reproduce the working local server from PowerShell:

```powershell
Set-Location 'E:\next custimar\public'
php -d zlib.output_compression=Off -S 127.0.0.1:8019 'E:\next custimar\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
```

The missing `php_gd2.dll` / redundant `php_mysqli.dll` warnings were present initially. At the final check those two directives had been commented out externally during the session, and `php -v` emitted no startup warnings. The working PDO MySQL directive remains enabled. The assistant did not edit php.ini; `zlib.output_compression` remains On in that system file, so the process-only override is still needed for this PHP development server.

## Responsive and error review

Desktop, tablet and mobile layouts were visually inspected. Detail grids stack, filters remain usable, long snapshot text wraps, and wide tables scroll inside their containers. There was no whole-document horizontal overflow at the tested widths. The block modal fits at 390px. Status colors, confirmation dialogs, validation messages, and empty states were reviewed. This is a basic keyboard/visual check, not a complete screen-reader or cross-browser certification.

After the local transport correction there were no unexpected JavaScript exceptions, missing assets, 419 responses, 500 responses, SQL errors or timezone parsing errors. Deliberately rejected requests produced the expected 422/403/404 responses. The Laravel log stayed at 233,369 bytes throughout the workflow checks; no new application error entries were added. Read-only checks found zero occurrences of the generated QA password in database session payloads or audit descriptions.

Screenshots (QA records only):

- [Desktop list](qa/phase-7d/appointments-desktop.png)
- [Tablet list](qa/phase-7d/appointments-tablet.png)
- [Mobile list](qa/phase-7d/appointments-mobile.png)
- [Mobile detail](qa/phase-7d/appointment-detail-mobile.png)
- [Mobile sidebar](qa/phase-7d/sidebar-mobile.png)
- [Block modal](qa/phase-7d/customer-block-mobile.png)
- [Mobile availability](qa/phase-7d/availability-mobile.png)

## QA data retained

All records use the prefix `QA7D-20260912-221919` or usernames beginning `qa7d_`:

- Companies 6 (`SLN-BBPPH`, primary) and 7 (isolation), both active at completion.
- Users 7 (manager), 8 (QA admin), and 9 (isolation manager), with `example.test` emails.
- Employees 3/4 (primary and alternate), 5 (foreign company), 6 (browser creation/edit/deactivation), 7 (credential Back-navigation check).
- Service 2; customer 1.
- Appointments 1–5: confirmed/rescheduled, cancelled_by_company, completed, no_show, confirmed/future respectively.
- Nine appointment events and one no-show violation.
- Two block-history rows: one expired fixture and one manually lifted block.
- Exceptions 1/2: company custom hours and service closure on 2026-09-23; one custom window.
- Related QA weekly schedules, service assignments, audit events, sessions and non-secret cache receipts.

Only newly created QA completion/no-show fixtures were backdated to make them eligible for the browser actions. No original records were backdated. Original users 1–6, companies 1–5, employees 1–2, service 1, original assignment and audit rows were checked against their earlier SHA-256 snapshots and are unchanged. No QA or original business records were deleted during cleanup. No migration was created or run in Phase 7D.

## Phase 7D files

- `resources/css/app.css`
- `resources/views/company/availability/index.blade.php`
- `resources/views/company/appointments/show.blade.php`
- `resources/views/components/layouts/company.blade.php`
- `resources/views/layouts/company.blade.php`
- `resources/views/components/layouts/admin.blade.php`
- `resources/views/layouts/admin.blade.php`
- `tests/Feature/Phase7DBrowserRegressionTest.php`
- This document and the seven screenshots above.

Earlier uncommitted Phase 7A–7C changes were preserved; no commit was made.

## Automated verification

- New browser-regression tests: 3 passed / 19 assertions.
- Focused availability, appointment HTTP, employee and browser regressions: 76 passed / 422 assertions.
- Full suite: 211 passed / 1,034 assertions, zero failures/errors/skips.
- Final `npm run build`: passed, 109 modules, 3.94 seconds. Only optional font-fallback and plugin timing notices remain.
- Final `git diff --check`: passed, exit 0; Git printed line-ending advisories only.
- Final `php artisan route:list`: 49 total routes, exactly six appointment routes, no create/store/edit/destroy additions.

The full suite supplies regression evidence for backend creation, Any Barber ordering, overlap/employee locks, restrictions, snapshots, timezone conversion, transitions/events, tenant isolation and security. SQLite execution does not prove simultaneous MySQL row-lock behavior; no destructive or concurrent stress workload was run against real MySQL.
