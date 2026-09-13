# Phase 8A — WhatsApp domain foundation

## Boundary and architecture

The internal entry point is `InboundMessageProcessor::handle(InboundMessage)`, returning a serializable `ConversationResponse` (text, choices or confirmation, with optional business data). `ConversationEngine` owns explicit conversation transitions. No public route, simulator page, provider HTTP client, credentials, webhook parser or outbound messages were added. Service-level feature tests are the local harness.

A future authenticated provider adapter must validate the provider request, resolve the destination through `CompanyResolver::byPhoneNumberId()`, then construct the DTO. Never accept a public company/customer ID as authority. DTO construction rejects malformed normalized input; an adapter must catch that validation exception and return a safe transport response. The internal processor catches processing failures and returns a generic retry response without exposing exception details.

AppointmentService remains authoritative for creation, Any Barber selection, overlap locking, snapshots, terminal transitions and cancellation violations. CustomerRestrictionService remains authoritative for identity and blocks. The only availability extension is `AvailabilityService::getBookableSlots()`; it combines existing effective windows, full service duration, future times and confirmed appointment overlaps. Its suggestions are not reservations. The configurable 15-minute increment starts at each effective window's opening. Final booking revalidates under existing AppointmentService locks.

## Company configuration and identity

Companies gain `whatsapp_enabled` (false by default), nullable unique `whatsapp_phone_number`, nullable unique `whatsapp_phone_number_id`, and nullable `whatsapp_business_account_id`. Managers can edit only enablement and the normalized number. Provider IDs are prohibited by settings validation, excluded from mass assignment and hidden from normal model serialization/UI. No token or secret storage is introduced.

`PhoneHelper::normalize()` is the canonical path shared by customers and inbound DTOs. Explicit + / 00 international prefixes preserve country codes; legacy Turkish local formats remain supported. The inbound boundary requires a normalized international number of 8–15 digits. Customers remain unique by company and canonical phone, with no app account. Real MySQL inspection found zero noncanonical customers or normalized duplicates; the existing company/phone unique constraint was retained. No customer history was rewritten.

## Persistence and idempotency

`whatsapp_conversations` has company/customer foreign keys, one row per company/customer, state, contextual JSON, monotonic revision, last-message and expiry timestamps. `whatsapp_inbound_messages` has scoped foreign keys, provider/external ID, message type, keyed content fingerprint, received/processed times, status, attempt count, processing revision, generic failure code and cached structured response. Provider/company/external ID is unique; MySQL external IDs use binary collation. Surrounding whitespace in external IDs is rejected.

Raw inbound text/provider payloads are not persisted or logged. A keyed HMAC binds the canonical phone, message type and selection payload to the receipt; received time is intentionally excluded so a retry may have a different reception timestamp. Reusing an ID with changed content is rejected. Cached replies contain the minimum booking/choice details needed to replay the same business response, including names and appointment snapshots where applicable. These are customer data and require restricted database access; retention policy remains a deployment decision. App-key rotation changes fingerprints and needs a deliberate receipt compatibility policy before production.

Processing uses two transactions:

1. A short company row mutex serializes customer/conversation bootstrap and receipt creation. It commits before any appointment scheduling lock is taken.
2. Lock the receipt, then the company/customer conversation. Process the transition and save its reply/status atomically with appointment, event and source-audit writes. AppointmentService retains its existing employee/appointment/customer lock order. No customer row lock is held in advance of a booking's scheduling locks.

Processed retries replay the cached reply without advancing the conversation or refreshing expiration. A failure rolls back domain writes, then records a generic failed receipt and the attempted conversation revision. Retrying that failure may process again only if the conversation revision is unchanged; otherwise it returns an older-step recovery message. This prevents an old failed textual confirmation from confirming a later offer. Concurrent deliveries serialize on the conversation; ordering is processing order, not provider timestamp order. Transactions retry database deadlocks up to three times. If a database outage prevents even failure recording, the receipt remains received/retryable; exact stale-retry protection across that exceptional outage requires future durable ingress orchestration.

SQLite tests prove rollback/replay and service paths, not MySQL concurrent lock scheduling. A production adapter also needs transport acknowledgement/retry policy and reliable outbound delivery (for example an outbox); cached business responses alone do not guarantee exactly-once delivery to a phone.

## State transitions and expiration

| State | Accepted transition |
| --- | --- |
| START | Unknown/placeholder name → AWAITING_NAME; known customer → MAIN_MENU |
| AWAITING_NAME | Valid text name → MAIN_MENU |
| MAIN_MENU | Book → SELECT_SERVICE; inquiry → INQUIRY_MENU; cancel → CANCEL_SELECT |
| SELECT_SERVICE | Active scoped service → SELECT_BARBER |
| SELECT_BARBER | Assigned active employee or virtual Any → SELECT_DAY |
| SELECT_DAY | Available business day → SELECT_TIME |
| SELECT_TIME | Available time → CONFIRM_BOOKING |
| CONFIRM_BOOKING | Explicit confirm → BOOKING_COMPLETE; decline → MAIN_MENU; conflict → refreshed availability |
| BOOKING_COMPLETE / INQUIRY_MENU | Main menu actions |
| CANCEL_SELECT | Own upcoming confirmed appointment → CANCEL_CONFIRM |
| CANCEL_CONFIRM | Explicit confirm delegates cancellation; decline keeps booking; returns MAIN_MENU |

`menu`/`restart` discards selections. Configurable idle expiration defaults to 24 hours from successful processing, so queue delay does not immediately expire a fresh reply. Expired/unknown state resets on the next new message. Customer identity/history remains intact. Invalid selections retain current choices; malformed stored selections reset safely. Choice IDs carry conversation/revision/action; old interactive buttons cannot apply to a new prompt. Text supports displayed number, label or action. Only currently offered actions are accepted, then referenced models are reloaded through tenant relations.

## Booking, inquiry and cancellation

Only active company services and assigned active company employees are offered. Days use the company's existing inclusive booking horizon and omit days with no slots. Any Barber stays null until AppointmentService chooses the lowest eligible employee, including its established fallback. Confirmation shows customer, service, barber/Any, local date/time, duration and price in minor currency units. Service details and context are revalidated; a nested transaction verifies the created snapshots still match the confirmed offer, rolling back if concurrent service changes altered it. Slot conflicts return refreshed time/day selection. Newly blocked customers are clearly rejected, with inquiry/cancellation still accessible.

Inquiry returns only this company's customer's upcoming confirmed appointments, using booking codes and saved service/employee snapshots. Cancellation lists the same eligible appointments, requires confirmation, and calls AppointmentService with the customer actor. Early/late violation behavior remains unchanged. Already cancelled/terminal selections cause no second transition or violation. Suspended salons cannot accept new bookings; the existing domain policy still permits customer cancellation.

Existing AppointmentService events/audits remain authoritative. Additional `whatsapp.booking.created` and `whatsapp.booking.cancelled` audit entries identify the channel using only a booking code, with no user-account impersonation, full inbound content or duplicate AppointmentEvents. They commit/roll back with the domain action and are not repeated on message replay.

## Timezone

Laravel and persisted timestamps remain UTC. Scheduling date/time inputs and all response summaries use `Europe/Istanbul` via Carbon, never fixed +3 arithmetic. The aware local start passes to AppointmentService, which normalizes UTC once. Receipt times normalize UTC; UI/provider renderers should use the returned local dates/timezone rather than convert them again.

## Verification

Phase 8A focused suite: 34 tests / 150 assertions. Full suite: 245 tests / 1,184 assertions; zero failures/errors. This preserves the 211-test / 1,034-assertion baseline. Tests cover first contact, persistence, expiration, normalization/isolation, active choices, closures/horizon/slots, blocked and stale confirmation paths, Any Barber fallback, snapshots/UTC, inquiry ownership, early/late/repeated cancellation, failure rollback/retry, old failed confirmations, destination resolution and manager configuration controls. No network calls are allowed by the harness.

Normal MySQL migration `2026_09_14_000001_create_whatsapp_foundation` applied in batch 7. All 13 migrations are applied. Before/after sorted-row hashes over every original column matched across all 22 pre-existing non-ledger tables. The migration ledger changed only by the expected new migration (12 → 13 rows); both new tables are empty. Existing companies: 7, customers: 1, appointments: 5, appointment events: 9, audits: 62, unchanged. No destructive migration commands or real test bookings were used.

`npm run build` passed outside the execution sandbox (109 modules). Inside the sandbox it reproduced Windows `spawn EPERM` with the accompanying Tailwind native-module/UTF-8 loading error; no packages or lockfile were changed. The optional `fontaine` warning remains nonblocking. `git diff --check` passed; Git printed existing CRLF normalization notices, not whitespace errors. No WhatsApp routes were added.

## Limits and Phase 8B

This is a domain foundation, not a connected WhatsApp service. Phase 8B should add authenticated provider verification/parsing, secure configuration/provisioning, safe destination resolution, transport-specific choice rendering/pagination, reliable acknowledgement/outbound delivery and observability without message-content logging. Long choice lists currently remain provider-neutral arrays. Slot enumeration makes repeated database reads across the horizon; profile before scaling. No availability is held while the customer is deciding. No retention/pruning job, real credentials, production webhook, queue worker, simulator UI or payment behavior is included.

The branch is `phase-8-whatsapp`. Earlier uncommitted Phase 7 work was preserved. No automatic commit was made.
