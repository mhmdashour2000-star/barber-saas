# Phase 8C — Live Meta acceptance

**Status: PHASE 8C BLOCKED BY LIVE PREREQUISITES**

## Observed readiness

Workspace: `E:\next custimar`, branch `phase-8-whatsapp`. Phase 8B files are still modified/untracked, not committed. Nothing is staged. They were preserved; no automatic commit was made.

| Requirement | Observed result |
| --- | --- |
| Laravel / PHP | 13.30.1 / 8.3.1 |
| Database / queue | MySQL / database |
| Migrations | All 14 applied; latest batch 8 |
| Routes | 51 total; GET/POST `/webhooks/whatsapp/meta`; six existing appointment routes unchanged |
| Application environment | `local`, debug enabled, HTTP localhost |
| Public HTTPS origin | Not supplied or configured; public reachability unverified |
| Graph version | Missing |
| Verification token / app secret | Both missing; values were not displayed |
| Access-token references | No nonempty entries |
| Company configuration | 7 companies; 0 enabled; 0 phone-number IDs; 0 WABA IDs; 0 complete configurations |
| Queue tables | `jobs` and `failed_jobs` exist; both empty |
| Queue worker | Not started because live prerequisites are missing; existing worker activity could not be established (Windows process command-line inspection was denied) |
| Webhook verification | Not attempted against a public endpoint |

Live actions stopped at the prerequisite gate. No tunnel was installed or started, no public endpoint was exposed, no environment values or company rows were changed, and no Meta account configuration was automated. Neither a Meta test number nor a production number was used. No Graph messaging request was made.

## Git and secret safety

`git check-ignore .env` confirms `.env` is ignored, and `git ls-files` confirms it is not tracked. Normal `git add .` therefore excludes it; forced adds remain unsafe. The captured `git diff --cached` was empty. `.env.example` has only empty Meta secret/version placeholders and an empty access-token map.

A non-printing scan covered 196 tracked/untracked source files and found no long Meta access-token-shaped strings. A marker-only scan of one Laravel log found no Authorization Bearer, verification query or compact raw webhook-envelope markers. This is a limited scan, not proof that arbitrary secrets never existed. No configured live values were available for exact-value scanning. There was no live acceptance interval from which to assess HTTP 500/419 errors or delivery spam.

Secrets belong only in protected local/server environment configuration, never source, Blade, docs, tests, logs or the database. The existing reference design remains unchanged. Do not send secret values through chat. Protect config-cache files, which contain resolved environment secrets, and redact webhook query strings at the web server/proxy because GET verification carries the token in its URL. Disable request/response capture in tunnel inspectors or other debugging middleware before live traffic.

## Required configuration and where it goes

Use the existing `config/whatsapp.php` path; do not add another path to `config/services.php`.

| Environment name | Purpose |
| --- | --- |
| `META_WHATSAPP_GRAPH_VERSION` | Supported version selected for the Meta app, in `vNN.N` form |
| `META_WHATSAPP_VERIFY_TOKEN` | Private verification token matching the dashboard entry |
| `META_WHATSAPP_APP_SECRET` | App secret for signed webhook authentication |
| `META_WHATSAPP_ACCESS_TOKENS` | Protected JSON map: verified phone-number ID → access token |
| `META_WHATSAPP_TIMEOUT` | Optional, defaults to 20 seconds |
| `META_WHATSAPP_CONNECT_TIMEOUT` | Optional, defaults to 5 seconds |

The token map is read by `App\Whatsapp\Meta\Configuration::token()`. `Configuration::complete()` checks configuration presence, not token validity or live connectivity. Neither the app secret nor verify token is stored in MySQL. Do not introduce an access-token database column.

The user must choose the QA salon and recipient before company configuration. Match the company's code, salon number and Meta account independently. Use manager settings for the normalized WhatsApp number and enablement. Provider IDs are intentionally prohibited in manager HTTP requests: a trusted operator must follow Phase 8B's documented maintenance procedure to look up the exact company by code, then explicitly `forceFill()` only its verified `whatsapp_phone_number_id` and `whatsapp_business_account_id`. Check the existing unique destination-ID constraint before saving. Do not guess a company or alter another salon. Store the access token only in the protected environment map keyed by that phone-number ID.

## Public HTTPS and safe acceptance environment

Required callback: `https://PUBLIC_HOST/webhooks/whatsapp/meta`. No actual public hostname is known, so this is a placeholder, not a working URL.

Provide either an existing HTTPS staging deployment or a trusted temporary tunnel chosen/configured by the user. Localhost cannot be the Meta callback. Do not expose the current debug-enabled application as-is. For a dedicated acceptance instance, use `APP_DEBUG=false`, a suitable staging environment and `APP_URL` matching its public HTTPS origin. Leave the ordinary private local development instance unchanged. Prefer a proxy that exposes only the exact webhook path for tunnel acceptance, with administrative routes kept private. Use a valid certificate and no login/interstitial in front of the callback.

Before configuring Meta, verify publicly reachable GET/POST behavior without bypassing signature validation. Invalid verification/signature requests should return safe 403 responses, never login redirects, CSRF 419 pages or debug traces. Then perform the real dashboard challenge. Do not put real tokens into shell command history or printed URLs. A locally forged successful test is not evidence of real Meta acceptance.

## Manual Meta dashboard procedure — not performed

Dashboard labels can vary by app setup; use the app's WhatsApp setup area corresponding to the locations below rather than creating a second app unnecessarily.

1. Select the intended Meta app, or create one with the WhatsApp use case if none exists. Complete its WhatsApp product/account setup. Meta's sample identifies the WhatsApp app use case and **App settings → Basic** as the app-secret location. Copy that secret privately into the environment. [Meta sample app setup](https://github.com/fbsamples/business-messaging-sample-tech-provider-app/blob/main/README.md).
2. In the app's WhatsApp API setup/Getting Started area, locate the test number, Phone Number ID, WABA ID and testing access token. Match these to the QA salon. Add and verify the controlled recipient using the test-number setup before messaging. Use the dashboard test-message action if its setup requires one, then send `Hello` from that recipient to the test number. Meta provides an official test-message example. [Meta Cloud API setup](https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api), [Meta test message](https://www.postman.com/meta/whatsapp-business-platform/request/9php9mt/send-test-message).
3. Enter the public callback and matching private verify token in the WhatsApp webhook configuration. Complete verification; subscribe to the `messages` field and confirm the intended WABA is subscribed to this app. Do not confuse a dashboard sample callback with a real customer message.
4. Keep the worker active, then verify a genuine signed inbound receipt and its real outbound response before attempting booking. Do not use the production salon number until the controlled test-number flow succeeds.

## Queue-worker procedure — not started

The implementation uses the named `whatsapp` queue, so bare `queue:work --tries=5` on the default queue is insufficient. From the project directory, use the project PHP executable after the prerequisites are ready:

```powershell
& 'E:\MAMP\bin\php\php8.3.1\php.exe' artisan queue:work database --queue=whatsapp --sleep=1 --tries=5 --timeout=60
```

Keep one controlled worker running throughout acceptance and retain its safe process identifier for later shutdown. Use a platform-appropriate supervised process for production. The database queue must use the application database; existing `retry_after` is 90 seconds, above the 60-second job timeout. Check that web and worker share the same `APP_KEY` and Meta configuration. Restart workers through normal deployment procedures when configuration changes. Do not clear queues or failed jobs automatically.

No Laravel scheduler is currently required for this WhatsApp implementation: idle expiry is checked on the next inbound message, while delayed retries are consumed by the database worker. `routes/console.php` contains no application schedule. A scheduler may be needed for future retention/monitoring work, but none was added here.

## Live acceptance checklist and results

Every row below is **NOT RUN — blocked**, not a pass.

| Flow | Evidence required on resumption | Result |
| --- | --- | --- |
| GET verification | Exact Meta challenge returned; invalid token still 403; safe public response | Blocked |
| Signed inbound / duplicate | Genuine signature accepted; correct salon; one receipt; job dispatched; replay safe | Blocked |
| First contact | QA phone normalized; scoped customer/conversation; name prompt; acknowledged Meta ID and reply visible on phone | Blocked |
| Specific-barber booking | Correct service/barber/day/time, explicit confirmation, code, snapshots and UTC/Istanbul correspondence | Blocked |
| Any Barber booking | Virtual choice stays unbound until AppointmentService selects eligible employee; assigned barber shown | Blocked |
| Stale choice | Previous interactive ID rejected without booking mutation | Blocked |
| Inquiry | Only QA customer's own upcoming appointment, correct code and snapshots/local time | Blocked |
| Cancellation | Identifiable QA booking, explicit confirmation, authoritative transition, delivered response | Blocked |
| Unsupported image | Safe text guidance; no media download/binary retention | Blocked |
| Delivery callbacks | Sent/delivered/read where available; no business rerun or status regression | Blocked |
| Live log review | No secret/raw payload leakage, 500/419/tenant errors or duplicate reply spam | Blocked |

Select controlled QA services, employees and availability with the user; do not change unrelated schedules/appointments. Prefer a booking far enough ahead to cancel without a violation. If late-cancellation behavior is tested, use a clearly identified QA customer only. Retry/error classification stays covered by fakes unless a natural live failure occurs; never deliberately break real credentials or spam Meta.

## QA records and database acceptance

**QA records created: none. QA identifiers: none.** No company was chosen or changed. No migrations or live writes were performed in Phase 8C.

Read-only baseline: inbound messages 0, conversations 0, outbound messages 0, jobs 0, failed jobs 0, existing appointments 5, existing appointment events 9 and existing violations 1. These existing rows are not Phase 8C QA records. Automated tests use isolated SQLite, not this MySQL database.

When live work resumes, record only safe internal QA company/customer/inbound/outbound/conversation/appointment/event identifiers, booking code, statuses and relevant UTC/local times here. Record expected violations separately. Keep phone/token values and raw message bodies out of the document. Preserve history; do not wipe QA records automatically.

## Token lifecycle

No token is currently configured, so acceptance is using neither a temporary nor a production/system-user token. Record the selected token category and expiry privately before testing. Meta documents temporary user tokens and system-user alternatives; a temporary dashboard token is not production readiness. Rotate the environment-map value for the same verified phone-number ID, refresh configuration using the deployment process, and restart workers without changing source. Confirm a controlled reply afterwards; never print the token. [Meta token guidance](https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api).

## Production requirements and hosting

Required: valid public HTTPS web server serving Laravel's `public` directory; `APP_DEBUG=false`; correct public `APP_URL`; protected environment/config cache; compatible PHP/MySQL; stable backed-up encryption key; database backups; log rotation with query/header redaction; supervised WhatsApp worker with restart-on-failure; queue-age/failed-job/uncertain-delivery monitoring; safe deployment worker restarts.

No Hostinger plan was supplied, so persistent-worker support for the selected plan is **unverified**. Hostinger documents hPanel cron tasks for Web/Cloud plans and greater background-process control on VPS. This does not establish Supervisor or reliable always-running worker support on shared hosting. Obtain plan-specific confirmation; a cron-only plan is not automatically equivalent to this application's interactive queue-worker requirement. [Hostinger server capabilities](https://www.hostinger.com/support/which-server-capabilities-are-supported-at-hostinger/).

## Resilience limits and next phase

No live evidence was collected, so no new Phase 8D feature recommendation is justified. Existing 8B limits remain: a timeout can occur after Meta accepted a send; an interrupted claim may be uncertain; unmatched early callbacks cannot be reconciled; multiple workers do not guarantee provider-timestamp ordering. Preserve the existing safeguards and evaluate reconciliation, ordering or retry tooling only after controlled acceptance supplies evidence. Do not claim exactly-once external delivery.

## Automated verification

Full suite rerun: **284 tests / 1,415 assertions passing**, zero failures/errors. Meta tests continue to use HTTP fakes. No new application code or config-template changes were required by this blocked phase.

The restricted build reproduced `spawn EPERM` and the accompanying Tailwind native-module/invalid-UTF-8 error. The host build passed outside the sandbox (109 modules); the optional fontaine warning was nonblocking. `git diff --check` passed with existing informational CRLF notices. No packages or lockfiles were changed.

## Required user inputs to resume

Provide the nonsecret HTTPS staging origin (or chosen/configured tunnel), QA company code and controlled-recipient plan. Configure the required secrets privately in the protected environment, provision verified provider IDs through the existing supported path, and confirm the relevant Meta app/test-number setup. Do not paste secrets into chat. Then verify/start the dedicated worker and perform real dashboard verification and phone-based acceptance.

PHASE 8C BLOCKED BY LIVE PREREQUISITES
