<?php

namespace Tests\Feature;

use App\Jobs\{ProcessMetaInbound, SendMetaReply};
use App\Models\{Company, User, WhatsappInboundMessage, WhatsappOutboundMessage};
use App\Whatsapp\{ConversationResponse, InboundMessageProcessor};
use App\Whatsapp\Meta\{MetaClient, PayloadParser, ResponseRenderer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Queue};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MetaWhatsappTransportTest extends TestCase
{
    use RefreshDatabase;
    private Company $company;
    private $realQueue;
    private const URL = '/webhooks/whatsapp/meta';

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Transport tests require isolated SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realQueue = Queue::getFacadeRoot();
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.fake-out']]], 200)]);
        config(['whatsapp.meta.graph_version' => 'v99.0', 'whatsapp.meta.verify_token' => 'fake-verify',
            'whatsapp.meta.app_secret' => 'fake-secret', 'whatsapp.meta.access_tokens' => ['10001' => 'fake-access-token']]);
        $this->company = Company::create(['name' => 'Transport Test', 'code' => 'SLN-META', 'phone' => '+12025550100',
            'manager_id' => User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER])->id,
            'status' => Company::STATUS_ACTIVE, 'whatsapp_enabled' => true]);
        $this->company->forceFill(['whatsapp_phone_number_id' => '10001', 'whatsapp_business_account_id' => '20001'])->save();
    }

    private function payload(string $type = 'text', string $id = 'wamid.fake-in'): array
    {
        $message = ['from' => '12025550101', 'id' => $id, 'timestamp' => (string) now()->timestamp, 'type' => $type];
        if ($type === 'text') $message['text'] = ['body' => 'Hello fake customer'];
        if ($type === 'interactive') $message['interactive'] = ['type' => 'button_reply', 'button_reply' => ['id' => '1:1:book', 'title' => 'Forged display title']];
        return ['object' => 'whatsapp_business_account', 'entry' => [['id' => '20001', 'changes' => [['field' => 'messages',
            'value' => ['metadata' => ['phone_number_id' => '10001'], 'messages' => [$message]]]]]]];
    }

    private function postSigned(array|string $payload, ?string $signature = null)
    {
        $raw = is_array($payload) ? json_encode($payload) : $payload;
        return $this->call('POST', self::URL, [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature ?? 'sha256='.hash_hmac('sha256', $raw, 'fake-secret')], $raw);
    }

    private function process(): WhatsappInboundMessage
    {
        $r = WhatsappInboundMessage::sole();
        app()->call([new ProcessMetaInbound($r->id), 'handle']);
        return $r->fresh();
    }

    private function send(): void
    {
        app()->call([new SendMetaReply(WhatsappInboundMessage::sole()->id), 'handle']);
    }

    public static function verificationCases(): array
    {
        return [['subscribe', 'fake-verify', '001234', 200], ['subscribe', 'wrong', '123', 403],
            ['wrong', 'fake-verify', '123', 403], ['subscribe', 'fake-verify', '', 403], ['', '', '', 403]];
    }

    #[DataProvider('verificationCases')]
    public function test_verification(string $mode, string $token, string $challenge, int $status): void
    {
        $response = $this->get(self::URL.'?'.http_build_query(['hub.mode' => $mode, 'hub.verify_token' => $token, 'hub.challenge' => $challenge]));
        $response->assertStatus($status);
        if ($status === 200) $response->assertContent($challenge)->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        else $response->assertDontSee('fake-verify');
    }

    public function test_invalid_signature_never_processes_payload(): void
    {
        $this->postSigned($this->payload(), 'sha256='.str_repeat('0', 64))->assertForbidden();
        $this->postSigned('invalid json', 'wrong')->assertForbidden();
        $this->assertDatabaseCount('customers', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_missing_signature_is_rejected(): void
    {
        $this->postJson(self::URL, $this->payload())->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_signed_malformed_payload_has_no_trace(): void
    {
        $this->postSigned('{')->assertStatus(400)->assertContent('Invalid payload');
        $this->postSigned(['object' => 'whatsapp_business_account', 'entry' => [null, 'bad', ['changes' => [null, 'bad']]]])->assertOk();
        Queue::assertNothingPushed();
    }

    public function test_unknown_and_disabled_destinations_do_not_create_customers(): void
    {
        $p = $this->payload(); $p['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = '99999';
        $this->postSigned($p)->assertOk();
        $this->company->update(['whatsapp_enabled' => false]);
        $this->postSigned($this->payload())->assertOk();
        $this->assertDatabaseCount('customers', 0);
        Queue::assertNothingPushed();
    }

    public function test_tenant_spoofing_is_ignored_and_queue_payload_contains_only_id(): void
    {
        $p = $this->payload(); $p['company_id'] = 999;
        $p['entry'][0]['changes'][0]['value']['messages'][0]['company_id'] = 999;
        $this->postSigned($p)->assertOk();
        $r = WhatsappInboundMessage::sole();
        $this->assertSame($this->company->id, $r->company_id);
        $this->assertSame('received', $r->status);
        $this->assertStringNotContainsString('Hello fake customer', $r->getRawOriginal('transport_payload'));
        $this->assertArrayNotHasKey('transport_payload', $r->toArray());
        Queue::assertPushed(ProcessMetaInbound::class, fn ($job) => $job->inboundId === $r->id && !str_contains(serialize($job), 'Hello fake customer'));
        Http::assertNothingSent();
    }

    public function test_duplicate_webhook_receipt_and_successful_jobs_are_idempotent(): void
    {
        $this->postSigned($this->payload())->assertOk();
        $this->postSigned($this->payload())->assertOk();
        $this->assertDatabaseCount('whatsapp_inbound_messages', 1);
        $this->process(); $this->process(); $this->send(); $this->send();
        $this->assertDatabaseCount('whatsapp_outbound_messages', 1);
        $this->assertSame('sent', WhatsappOutboundMessage::sole()->status);
        Http::assertSentCount(1);
    }

    public function test_end_to_end_signed_webhook_to_fake_graph(): void
    {
        $this->postSigned($this->payload())->assertOk();
        Queue::assertPushed(ProcessMetaInbound::class);
        $r = $this->process();
        $this->assertSame('processed', $r->status);
        $this->assertNull($r->transport_payload);
        $this->assertDatabaseHas('whatsapp_conversations', ['state' => 'awaiting_name']);
        Queue::assertPushed(SendMetaReply::class);
        $out = WhatsappOutboundMessage::sole();
        $this->assertStringNotContainsString('Welcome', $out->getRawOriginal('payload'));
        $this->send();
        Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v99.0/10001/messages'
            && $request->hasHeader('Authorization', 'Bearer fake-access-token')
            && $request['to'] === '12025550101' && $request['messaging_product'] === 'whatsapp'
            && $request['type'] === 'text' && str_contains($request['text']['body'], 'name'));
        $this->assertNull($out->fresh()->payload);
        $this->assertSame('wamid.fake-out', $out->fresh()->external_message_id);
    }

    public static function parserTypes(): array { return [['text', 'text'], ['button_reply', 'button'], ['list_reply', 'list']]; }

    #[DataProvider('parserTypes')]
    public function test_parser_supported_variants(string $variant, string $expected): void
    {
        $p = $this->payload($variant === 'text' ? 'text' : 'interactive');
        if ($variant !== 'text') $p['entry'][0]['changes'][0]['value']['messages'][0]['interactive'] = ['type' => $variant, $variant => ['id' => 'safe-selection', 'title' => 'untrusted-title']];
        $events = app(PayloadParser::class)->messages($p);
        $this->assertCount(1, $events);
        $this->assertSame($expected, $events[0]['input']->messageType);
        $this->assertSame('+12025550101', $events[0]['input']->customerPhone);
        if ($variant !== 'text') $this->assertSame('safe-selection', $events[0]['input']->payload);
    }

    public function test_multiple_entries_and_changes_are_processed(): void
    {
        $p = $this->payload();
        $next = $this->payload(id: 'second');
        $p['entry'][] = $next['entry'][0];
        $this->postSigned($p)->assertOk();
        $this->assertDatabaseCount('whatsapp_inbound_messages', 2);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_status_only_events_do_not_create_conversations(): void
    {
        $p = $this->payload();
        unset($p['entry'][0]['changes'][0]['value']['messages']);
        $p['entry'][0]['changes'][0]['value']['statuses'] = [['id' => 'unmatched', 'status' => 'read']];
        $this->postSigned($p)->assertOk();
        $this->assertDatabaseCount('whatsapp_conversations', 0);
        Queue::assertNothingPushed();
    }

    public function test_unsupported_media_returns_help_without_using_it_as_a_name(): void
    {
        $this->postSigned($this->payload('image'))->assertOk();
        $r = $this->process();
        $this->assertStringContainsString('Please send text', $r->response['text']);
        $this->assertDatabaseHas('whatsapp_conversations', ['state' => 'start']);
        $this->send();
        Http::assertSentCount(1);
    }

    public function test_stale_interactive_selection_does_not_start_booking(): void
    {
        $this->company->customers()->create(['name' => 'Known', 'phone' => '+12025550101', 'active' => true]);
        $this->postSigned($this->payload())->assertOk(); $this->process();
        $p = $this->payload('interactive', 'old-button');
        $this->postSigned($p)->assertOk();
        // A signed stale button must not match the current conversation revision.
        $p['entry'][0]['changes'][0]['value']['messages'][0]['id'] = 'stale-button';
        $p['entry'][0]['changes'][0]['value']['messages'][0]['interactive']['button_reply']['id'] = '999:0:book';
        $this->postSigned($p)->assertOk();
        $id = WhatsappInboundMessage::where('external_message_id', 'stale-button')->value('id');
        app()->call([new ProcessMetaInbound($id), 'handle']);
        $this->assertDatabaseHas('whatsapp_conversations', ['state' => 'main_menu']);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_renderer_buttons_and_confirmation_include_summary(): void
    {
        $r = new ConversationResponse('confirmation', 'Confirm?', [['id' => '1:2:confirm', 'label' => 'Confirm'], ['id' => '1:2:decline', 'label' => 'Cancel']],
            ['summary' => ['service' => 'Haircut', 'time' => '12:00', 'timezone' => 'Europe/Istanbul', 'price_minor_units' => 25000]]);
        $parts = app(ResponseRenderer::class)->render($r);
        $this->assertSame('button', $parts[0]['interactive']['type']);
        $this->assertCount(2, $parts[0]['interactive']['action']['buttons']);
        $this->assertStringContainsString('250.00', $parts[0]['interactive']['body']['text']);
        $this->assertStringContainsString('Europe/Istanbul', $parts[0]['interactive']['body']['text']);
    }

    public function test_paginated_time_response_uses_existing_jobs_to_send_only_one_list(): void
    {
        $this->postSigned($this->payload())->assertOk();
        $r = WhatsappInboundMessage::sole();
        $choices = [];
        for ($i = 0; $i < 8; $i++) $choices[] = ['id' => '1:2:time:'.sprintf('10:%02d', $i), 'label' => sprintf('10:%02d', $i)];
        $choices[] = ['id' => '1:2:time_page:next', 'label' => 'More times'];
        $r->update(['status' => 'processed', 'response' => (new ConversationResponse('list', 'Choose a time (Europe/Istanbul). Page 1 of 3.',
            $choices, ['title' => 'Available times']))->jsonSerialize()]);
        $this->process(); $this->process(); $this->send(); $this->send();
        $this->assertDatabaseCount('whatsapp_outbound_messages', 1);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['interactive']['type'] === 'list'
            && $request['interactive']['header']['text'] === 'Available times'
            && count($request['interactive']['action']['sections'][0]['rows']) === 9
            && $request['interactive']['action']['sections'][0]['rows'][0]['title'] === '10:00');
    }

    public function test_paginated_renderer_rejects_oversized_page_instead_of_sending_multiple_messages(): void
    {
        $choices = array_fill(0, 11, ['id' => '1:2:time:12:00', 'label' => '12:00']);
        $this->expectException(\InvalidArgumentException::class);
        app(ResponseRenderer::class)->render(new ConversationResponse('list', 'Choose', $choices));
    }

    public function test_renderer_groups_all_choices_and_respects_limits(): void
    {
        $choices = [];
        for ($i = 0; $i < 27; $i++) $choices[] = ['id' => '1:2:service:'.$i, 'label' => str_repeat('Long label ', 8).$i];
        $parts = app(ResponseRenderer::class)->render(new ConversationResponse('choices', str_repeat('İ', 5000), $choices));
        $ids = [];
        foreach ($parts as $part) {
            if ($part['type'] === 'text') { $this->assertLessThanOrEqual(4096, mb_strlen($part['text']['body'])); continue; }
            $this->assertLessThanOrEqual(1024, mb_strlen($part['interactive']['body']['text']));
            $rows = $part['interactive']['action']['sections'][0]['rows'];
            $this->assertLessThanOrEqual(10, count($rows));
            foreach ($rows as $row) { $ids[] = $row['id']; $this->assertLessThanOrEqual(24, mb_strlen($row['title'])); }
        }
        $this->assertSame(array_column($choices, 'id'), $ids);
    }

    public static function errors(): array { return [[429, 'retrying'], [500, 'retrying'], [400, 'failed'], [401, 'failed'], [403, 'failed']]; }

    #[DataProvider('errors')]
    public function test_provider_failures_are_classified_and_sanitized(int $httpStatus, string $status): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['error' => ['code' => 190, 'message' => 'fake-access-token raw sensitive error']], $httpStatus)]);
        $this->postSigned($this->payload()); $this->process(); $this->send();
        $out = WhatsappOutboundMessage::sole();
        $this->assertSame($status, $out->status);
        $this->assertSame('meta_190', $out->last_error_code);
        $this->assertStringNotContainsString('fake-access-token', $out->last_error_summary);
        if ($status === 'failed') { $this->send(); Http::assertSentCount(1); }
    }

    public function test_retryable_failure_can_recover_without_reprocessing_business(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['*' => Http::sequence()->push([], 503)->push(['messages' => [['id' => 'recovered']]], 200)]);
        $this->postSigned($this->payload()); $this->process(); $this->send(); $this->send();
        Http::assertSentCount(1); // A duplicate job cannot bypass persisted backoff.
        $this->travel(11)->seconds();
        $this->send(); $this->send();
        $this->assertSame('sent', WhatsappOutboundMessage::sole()->status);
        $this->assertSame(2, WhatsappOutboundMessage::sole()->attempts);
        Http::assertSentCount(2);
        $this->assertSame(1, WhatsappInboundMessage::sole()->attempts);
    }

    public function test_network_failure_is_retryable(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('fake-access-token'));
        $this->postSigned($this->payload()); $this->process(); $this->send();
        $this->assertSame('retrying', WhatsappOutboundMessage::sole()->status);
        $this->assertSame('connection', WhatsappOutboundMessage::sole()->last_error_code);
    }

    public function test_interrupted_send_is_not_automatically_resent(): void
    {
        $this->postSigned($this->payload()); $this->process();
        WhatsappOutboundMessage::sole()->update(['status' => 'sending', 'claimed_at' => now()->subMinutes(3)]);
        $this->send();
        $this->assertSame('uncertain', WhatsappOutboundMessage::sole()->status);
        Http::assertNothingSent();
    }

    public function test_delivery_callbacks_are_scoped_and_cannot_regress_read(): void
    {
        $this->postSigned($this->payload()); $this->process(); $this->send();
        $p = $this->payload(); unset($p['entry'][0]['changes'][0]['value']['messages']);
        foreach (['read', 'sent', 'failed'] as $status) {
            $p['entry'][0]['changes'][0]['value']['statuses'] = [['id' => 'wamid.fake-out', 'status' => $status]];
            $this->postSigned($p)->assertOk();
        }
        $this->assertSame('read', WhatsappOutboundMessage::sole()->delivery_status);
        $this->assertSame('sent', WhatsappOutboundMessage::sole()->status);
        $this->assertDatabaseCount('whatsapp_conversations', 1);
        Http::assertSentCount(1);
    }

    public function test_routes_are_stateless_and_management_csrf_is_preserved(): void
    {
        $this->get('/login')->assertOk(); // Boot the HTTP kernel's middleware groups.
        $router = app('router');
        $meta = $router->getRoutes()->getByName('webhooks.whatsapp.meta.receive');
        $settings = $router->getRoutes()->getByName('company.settings.update');
        $csrf = \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class;
        $this->assertNotContains($csrf, $router->gatherRouteMiddleware($meta));
        $this->assertNotContains(\Illuminate\Session\Middleware\StartSession::class, $router->gatherRouteMiddleware($meta));
        $this->assertContains($csrf, $router->gatherRouteMiddleware($settings), json_encode($router->gatherRouteMiddleware($settings)));
    }

    public function test_manager_and_admin_diagnostics_do_not_expose_secrets(): void
    {
        $this->actingAs($this->company->manager)->get('/company/settings')->assertOk()->assertSee('Provider configuration:')
            ->assertDontSee('fake-access-token')->assertDontSee('fake-secret')->assertDontSee('fake-verify');
        $admin = User::factory()->create(['role' => User::ROLE_SYSTEM_ADMIN]);
        $this->actingAs($admin)->get('/admin/companies/'.$this->company->id)->assertOk()->assertSee('WhatsApp diagnostics')
            ->assertDontSee('fake-access-token')->assertDontSee('fake-secret')->assertDontSee('fake-verify');
    }

    public function test_real_database_queue_contains_only_internal_identifier(): void
    {
        Queue::swap($this->realQueue);
        $this->postSigned($this->payload())->assertOk();
        $job = DB::table('jobs')->sole();
        $this->assertSame('whatsapp', $job->queue);
        $this->assertStringContainsString('ProcessMetaInbound', $job->payload);
        $this->assertStringNotContainsString('Hello fake customer', $job->payload);
        $this->assertStringNotContainsString('fake-access-token', $job->payload);
        // Execute the actual serialized database job through Laravel's queue handler.
        Queue::connection('database')->pop('whatsapp')->fire();
        $this->assertSame('processed', WhatsappInboundMessage::sole()->status);
        $this->assertDatabaseCount('whatsapp_outbound_messages', 1);
    }

    public function test_queue_failure_rolls_back_ingress_and_returns_safe_retry_status(): void
    {
        Queue::shouldReceive('connection')->andThrow(new \RuntimeException('fake-secret internal failure'));
        $this->postSigned($this->payload())->assertStatus(503)->assertContent('Temporarily unavailable');
        $this->assertDatabaseCount('whatsapp_inbound_messages', 0);
        $this->assertDatabaseCount('whatsapp_conversations', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_two_salons_same_sender_and_message_id_remain_separate(): void
    {
        $other = Company::create(['name' => 'Other', 'code' => 'SLN-OTHER', 'phone' => '+12025550102',
            'manager_id' => User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER])->id,
            'status' => Company::STATUS_ACTIVE, 'whatsapp_enabled' => true]);
        $other->forceFill(['whatsapp_phone_number_id' => '10002'])->save();
        $this->postSigned($this->payload())->assertOk();
        $p = $this->payload(); $p['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = '10002';
        $this->postSigned($p)->assertOk();
        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseCount('whatsapp_inbound_messages', 2);
        $this->assertSame(1, $other->whatsappInboundMessages()->count());
        $this->assertSame(1, $this->company->whatsappInboundMessages()->count());
    }

    public function test_retries_are_bounded_and_failed_messages_do_not_send_again(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 503)]);
        $this->postSigned($this->payload()); $this->process();
        for ($i = 0; $i < 6; $i++) { $this->send(); $this->travel(301)->seconds(); }
        $this->assertSame('failed', WhatsappOutboundMessage::sole()->status);
        $this->assertSame(5, WhatsappOutboundMessage::sole()->attempts);
        Http::assertSentCount(5);
    }

    public function test_success_without_provider_id_is_uncertain_and_not_resent(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 200)]);
        $this->postSigned($this->payload()); $this->process(); $this->send(); $this->send();
        $this->assertSame('uncertain', WhatsappOutboundMessage::sole()->status);
        Http::assertSentCount(1);
    }

    public function test_missing_configuration_never_calls_graph(): void
    {
        config(['whatsapp.meta.access_tokens' => []]);
        $this->postSigned($this->payload()); $this->process(); $this->send();
        $this->assertSame('failed', WhatsappOutboundMessage::sole()->status);
        $this->assertSame('configuration', WhatsappOutboundMessage::sole()->last_error_code);
        Http::assertNothingSent();
    }

    public function test_grouped_delivery_preserves_order_and_distinct_idempotency_keys(): void
    {
        $this->postSigned($this->payload());
        $r = WhatsappInboundMessage::sole();
        $choices = [];
        for ($i = 0; $i < 12; $i++) $choices[] = ['id' => '1:2:'.$i, 'label' => 'Choice '.$i];
        $r->update(['status' => 'processed', 'response' => (new ConversationResponse('choices', 'Choose', $choices))->jsonSerialize()]);
        $this->process();
        $rows = WhatsappOutboundMessage::orderBy('part')->get();
        $this->assertCount(3, $rows);
        $this->assertSame(3, $rows->pluck('idempotency_key')->unique()->count());
        $this->send();
        $this->assertSame('sent', $rows[0]->fresh()->status);
        $this->assertSame('pending', $rows[1]->fresh()->status);
        $this->send(); $this->send(); $this->send();
        Http::assertSentCount(3);
        $this->assertSame(3, WhatsappOutboundMessage::where('status', 'sent')->count());
    }
}
