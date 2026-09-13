# Phase 8B — Meta WhatsApp Cloud API transport

## Scope and boundaries

Meta transport now wraps the existing Phase 8A domain. No booking, availability, restriction, snapshot, timezone or cancellation rules moved into the controller or jobs. `InboundMessageProcessor::receive()` extracts its existing receipt/bootstrap transaction so ingress can persist identity and deduplication before queueing; `handle()` still runs the same provider-neutral conversation flow.

The application has exactly two new public routes:

| Method | URL | Purpose |
| --- | --- | --- |
| GET | `/webhooks/whatsapp/meta` | Verification handshake |
| POST | `/webhooks/whatsapp/meta` | Signed messages and delivery callbacks |

Neither route accepts a company ID in its URL. Their web middleware exclusions remove session startup and Laravel 13's `PreventRequestForgery` only for these routes. Management routes retain CSRF protection. No manager authentication is required by Meta. No simulator, new management endpoint, payments or employee login was added.

## Configuration and secret references

Set the following in protected deployment environment variables or the local untracked `.env` before live testing:

| Variable | Required value |
| --- | --- |
| `META_WHATSAPP_GRAPH_VERSION` | A currently supported version selected for your Meta app, in `vNN.N` form; no default version is hardcoded |
| `META_WHATSAPP_VERIFY_TOKEN` | A private random token you also enter in Meta's webhook configuration |
| `META_WHATSAPP_APP_SECRET` | The Meta app secret used to authenticate raw webhook bodies |
| `META_WHATSAPP_ACCESS_TOKENS` | JSON object mapping each trusted phone-number ID to its access token |
| `META_WHATSAPP_TIMEOUT` | Optional total HTTP timeout, default 20 seconds, clamped to 1–25 |
| `META_WHATSAPP_CONNECT_TIMEOUT` | Optional connection timeout, default 5 seconds, clamped to 1–10 |

Token-reference shape (illustrative placeholders only):

```dotenv
META_WHATSAPP_ACCESS_TOKENS='{"PHONE_NUMBER_ID":"ACCESS_TOKEN_FROM_SECRET_STORE"}'
```

No access token column or token-editing form exists. Tokens are resolved by the company's trusted `whatsapp_phone_number_id`, remain in protected server configuration, and are attached only as a Bearer header to the fixed HTTPS Graph host. Redirect following is disabled. Environment/config-cache files and process access must be restricted; never publish `config:show whatsapp`, dump configuration, log HTTP headers, or commit populated `.env` files. Laravel's config cache contains resolved secrets when used, so protect it as secret material. Apply configuration changes through normal deployment config-cache/worker restart procedures. No real values were written during implementation.

Provision the existing company fields `whatsapp_phone_number_id` and `whatsapp_business_account_id` using a trusted administrative database/Eloquent maintenance session, after independently matching the company code, salon number and Meta account. These identifiers remain excluded from manager request validation and mass assignment. For example, a trusted operator may look up `Company::where('code', 'YOUR_SALON_CODE')->sole()` and explicitly `forceFill()` the two verified identifiers; do not expose that operation through a public endpoint. Use the existing settings page to configure the canonical number and enablement. The existing unique destination-ID constraint prevents accidental sharing between companies.

Manager settings show configuration complete/incomplete, never secrets or a claim of verified connectivity. The existing admin company detail page shows enablement, number, configuration completeness, last inbound UTC timestamp, latest outbound/delivery status and latest safe error summary. It is not a messaging inbox.

## Verification and authenticity

GET requires mode `subscribe`, an exact constant-time match of the configured verify token, and a nonempty string challenge. It supports PHP's underscore-normalized query keys as well as dotted keys. Success returns the challenge unchanged as plain text with no-store caching; invalid/missing fields return a generic 403. Production reverse-proxy access logs must redact the handshake query string because Meta sends the verify token in the URL.

POST checks `X-Hub-Signature-256` against `sha256=` plus HMAC SHA-256 of the **raw body**, using `hash_equals`, before invoking the parser, tenant resolver or domain. Empty configuration fails closed. Invalid/missing signatures return 403. Authenticated invalid JSON returns a generic 400; bodies over 1 MiB return 413. Valid accepted, duplicate, status-only and irrelevant events return 200. Persistence/dispatch outages return a generic 503 so Meta can retry. No stack trace, provider body or business validation error is returned to Meta.

## Parsing and tenant resolution

`Meta/PayloadParser` iterates all supported `entry[].changes[]` message values. `CompanyResolver` resolves only enabled companies by the signed metadata's `phone_number_id`. Unknown/disabled destinations are ignored. Public `company_id` fields and interactive titles have no authority.

Text, interactive button replies and list replies become the existing `InboundMessage` DTO, with provider `meta`, external message ID, explicit international sender phone, message type, text/selection ID and UTC receipt timestamp. Meta sender digits get an explicit `+` before canonical PhoneHelper normalization, preserving foreign country codes. Selection titles are deliberately discarded; the domain validates revision-bearing IDs against current choices and tenant relations.

Unsupported media and text longer than the domain's 2,000-character limit receive a safe supported-input response without invoking the conversation engine or treating media as a customer name. No media downloads occur. Malformed individual members are skipped; status-only events never create customers or conversations. Full raw webhook JSON is not retained.

## Durable ingress and queue lifecycle

1. Signature verification and parsing run in the HTTP request.
2. `Ingress` calls the existing receipt/bootstrap service in a short transaction. The customer/conversation identity and deduplication receipt persist before business processing.
3. Only the normalized selection, sender, destination and unsupported flag are persisted using Laravel `encrypted:array` in `transport_payload`.
4. An ID-only `ProcessMetaInbound` job is inserted into the database queue **inside the same transaction**. Jobs explicitly use before-commit insertion; another worker cannot see them before commit. A separate `DB_QUEUE_CONNECTION` is rejected because it would break this atomicity.
5. HTTP returns 200 without calling Graph.
6. The processing job reloads the receipt and calls Phase 8A's processor, or generates media help. Already processed receipts reuse the cached response. The renderer produces ordered Meta payloads.
7. Outbound rows are created with deterministic keys and an ID-only `SendMetaReply` job is inserted atomically. Normalized inbound transport content is then erased.
8. Delivery jobs claim and send one ordered part per invocation, then atomically record the outcome and enqueue the next part or delayed retry.

Use the database queue on the same database as the application. Keep `DB_QUEUE_RETRY_AFTER` above the 60-second processing timeout (the existing default is 90). A deployment worker command is:

```sh
php artisan queue:work database --queue=whatsapp --sleep=1 --tries=5 --timeout=60
```

This command was **not** started against real messages during implementation. Start with one supervised WhatsApp worker to preserve simple V1 arrival/reply ordering. Database conversation locks serialize mutation with multiple workers, but do not guarantee provider-timestamp ordering across separate messages. Windows worker timeout enforcement differs from a production process supervisor; supervise/restart workers appropriately. Do not run an unbounded queue worker as a request handler.

## Outbound rendering and persistence

The renderer is Meta-specific; domain responses remain unchanged. Text responses use text messages. Up to three choices/confirmation actions use reply buttons. Larger sets use lists of at most ten rows, split into ordered groups. Every choice is retained with its original revision-bearing ID (maximum 200 bytes enforced). Titles use conservative 20-character button/24-character row limits; long or grouped labels also appear in a full numbered legend. Text is split at 4,096 Unicode characters and interactive bodies at a conservative 1,024-character ceiling. Summaries explicitly render service, barber, booking code where available, duration, price, date/time and Istanbul timezone. No template or proactive messaging is implemented.

The button/list payload shapes follow [Meta's published interactive examples](https://whatsapp.github.io/WhatsApp-Nodejs-SDK/api-reference/messages/interactive/). That SDK documentation is archived; the implementation uses Laravel HTTP directly, not that SDK. Validate transport limits and the configured supported Graph version again during live acceptance.

`whatsapp_outbound_messages` stores company/customer/inbound foreign keys, provider, deterministic idempotency key, ordered part number, destination, encrypted payload, send and delivery status, attempts, next retry time, safe error fields, claim time, Meta acknowledgement ID and sent time. The unique key is SHA-256 of `meta:{inbound_id}:{part}`; a second unique constraint covers inbound ID plus part. Payload ciphertext is erased after acknowledged success or permanent failure. Unknown outcomes retain encrypted payload for investigation. No tokens or raw Meta responses enter this table.

The cached Phase 8A response remains the replay source. Both cached replies and encrypted pending data can contain customer/appointment information; establish a retention policy and restrict database access. Protect and back up `APP_KEY`; planned key rotation must account for encrypted pending work and Phase 8A fingerprint compatibility.

## Idempotency, failures and exact limits

Duplicate signed webhooks may enqueue duplicate ID-only jobs. They reuse one domain receipt, one conversation result and the same outbound rows. A sent part is never intentionally sent again. Row locking permits only one claim at a time, and parts advance in order. No database transaction is held across the Graph request.

Connection failures/timeouts, HTTP 429 and 5xx are retryable, with persisted delays of 10, 30, 120 and 300 seconds and at most five sends per part. Duplicate jobs cannot bypass a pending retry time. Other HTTP failures, including credential/recipient/payload rejection, stop automatically. Safe summaries and numeric Meta/HTTP codes are recorded; raw provider errors and request headers are discarded. Queue exceptions contain only a constant safe message; job payloads contain only an internal receipt ID. Inbound processing jobs also have five attempts with bounded backoff.

**Exactly-once external delivery cannot be guaranteed across the database/HTTP boundary.** A network timeout may occur after Meta accepted the message; retrying it can duplicate delivery. No undocumented Graph idempotency header is assumed. If a worker disappears while a send is claimed, a later attempt marks an expired claim uncertain and withholds automatic resend. A successful HTTP response without a usable Meta ID is also uncertain. Operators must investigate before deciding whether to resend. A claim can remain `sending` until a retry/duplicate job revisits it; diagnostics expose that state. Permanent or uncertain failure of a part stops subsequent parts of that reply. Failed processing jobs may be retried through standard Laravel queue operations after the cause is corrected; review uncertain outbound state first.

## Delivery callbacks

Signed callbacks match outbound rows by trusted destination company and Meta external message ID. `delivery_status` progresses through sent/delivered/read; stale callbacks cannot regress read/delivered to sent or failed. Failure callbacks record a safe generic issue. The local `status=sent` acknowledgement is retained so delivery failures do not cause automatic duplicate submission. Unmatched callbacks are ignored and never run business flow. A callback arriving before the send acknowledgement is saved, or for an uncertain send whose Meta ID was never captured, cannot be reconciled in this minimal version.

## Manual Meta setup and live-test prerequisites

Use Meta's [official Cloud API collection and setup links](https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api) for business account, phone-number and token provisioning. It documents the `whatsapp_business_messaging` and `whatsapp_business_management` permissions and supported token types.

1. Create/configure the Meta app and WhatsApp Business Account; register the intended salon phone number and obtain the correct phone-number ID/WABA ID. Verify the association before assigning a tenant.
2. Provision the token through protected environment configuration, set the graph version, app secret and your private verification token, and configure the company's identifiers and enablement.
3. Deploy to a public HTTPS origin with `APP_DEBUG=false`, working MySQL, the same stable `APP_KEY` for web and workers, and a supervised database worker. A controlled HTTPS tunnel can be used for local testing; do not expose a debug server or diagnostic dumps.
4. In the app's WhatsApp webhook configuration, set the callback to `https://YOUR_HOST/webhooks/whatsapp/meta` and enter the matching verify token. Complete GET verification and subscribe the app/WABA to the messages webhook field.
5. Use a permitted test recipient/account first. Start with an inbound customer message, verify a signed receipt, a queued job and one acknowledged reply, then exercise booking/inquiry/cancellation. Check app mode, account permissions and current messaging-window rules before production traffic.

No real Graph request or real credential was used during this implementation. `.env` and company provider identifiers still require operator setup; `.env.example` contains placeholders only. Neither configuration completeness nor the automated suite proves live Meta connectivity.

## Verification and MySQL safety

Focused Phase 8B: 39 tests / 231 assertions passing. Full suite: 284 tests / 1,415 assertions passing, preserving all 245 baseline tests. Tests use isolated SQLite memory databases, fake recipients/credentials and `Http::fake()` with stray-request prevention. Coverage includes signature/handshake, malformed and multi-entry fixtures, tenant spoofing/isolation, real SQLite database-queue serialization, transactional enqueue rollback, supported/unsupported parsing, stale buttons, renderer grouping/limits, end-to-end fake Graph requests, duplicate jobs, bounded retries, interrupted delivery, status monotonicity and secret-free diagnostics.

Migration `2026_09_14_000002_add_meta_whatsapp_transport` was applied with normal `php artisan migrate` in batch 8. All 14 migrations are applied. Before migration, MySQL had zero configured destination IDs or duplicate groups and already had the destination unique constraint. After migration, every original row/column hash across all 25 existing tables matched, including the original 13 migration-ledger rows; only the new migration row was added. The new table is empty, its deterministic and inbound/part unique indexes are present, and all three foreign keys exist. Existing companies (7), customers (1), appointments (5), events (9), audits (62) and jobs (0) are preserved. No destructive database commands were used.

Route review confirms only the intended GET/POST webhook additions (51 routes total). Existing appointment routes remain index, show, reschedule, cancel, complete and no-show. No create/store/edit/destroy appointment routes or public management routes were added.

`npm run build` passed outside the restricted sandbox (109 modules). Inside it, Vite reproduced `spawn EPERM` and the accompanying Tailwind native-module/invalid-UTF-8 loading error. No dependencies or lockfiles were changed. The optional `fontaine` warning remains nonblocking. `git diff --check` passed; existing CRLF normalization notices are informational.

## Phase 8C follow-up

Perform controlled live acceptance, operational monitoring, secret provisioning/rotation and a retention policy. Consider reconciliation tooling for uncertain outcomes and early delivery callbacks, and stronger per-conversation transport ordering before increasing worker concurrency. These are operational/transport follow-ups; the provider-neutral appointment domain remains unchanged. No automatic commit was made.
