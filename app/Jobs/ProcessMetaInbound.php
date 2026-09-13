<?php

namespace App\Jobs;

use App\Models\WhatsappInboundMessage;
use App\Whatsapp\{InboundMessage, InboundMessageProcessor, ConversationResponse};
use App\Whatsapp\Meta\ResponseRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{DB, Queue};

class ProcessMetaInbound implements ShouldQueue
{
    use Queueable;
    public int $tries = 5;
    public int $timeout = 60;
    public function __construct(public int $inboundId) { $this->onConnection('database')->onQueue('whatsapp')->beforeCommit(); }
    public function backoff(): array { return [10, 30, 120, 300]; }

    public function handle(InboundMessageProcessor $processor, ResponseRenderer $renderer): void
    {
        try {
            $receipt = WhatsappInboundMessage::findOrFail($this->inboundId);
            $company = $receipt->company;
            if (!$company->whatsapp_enabled || $receipt->provider !== 'meta') return;
            $transport = $receipt->transport_payload;
            if ($receipt->status === 'processed') {
                $response = ConversationResponse::fromArray($receipt->response);
            } elseif (is_array($transport)) {
                if ($transport['destination_id'] !== $company->whatsapp_phone_number_id) return;
                if ($transport['unsupported']) {
                    $response = new ConversationResponse('text', 'Please send text up to 2,000 characters or choose an offered button or list option.');
                    // Unsupported media must not be interpreted as a name or business selection.
                    DB::transaction(function () use ($company, $response) {
                        $r = $company->whatsappInboundMessages()->lockForUpdate()->findOrFail($this->inboundId);
                        if ($r->status !== 'processed') $r->update(['status' => 'processed', 'response' => $response->jsonSerialize(), 'processed_at' => now()]);
                    });
                } else {
                    $customer = $company->customers()->findOrFail($receipt->customer_id);
                    $response = $processor->handle(new InboundMessage('meta', $receipt->external_message_id, $company, $transport['customer_phone'],
                        $receipt->message_type, $transport['selection'], CarbonImmutable::instance($receipt->received_at)));
                    if ($response->data['retryable'] ?? false) throw new \RuntimeException('Domain processing retry required.');
                }
            } else { return; }
            $parts = $renderer->render($response);
            DB::transaction(function () use ($company, $parts) {
                $r = $company->whatsappInboundMessages()->lockForUpdate()->findOrFail($this->inboundId);
                $customer = $company->customers()->findOrFail($r->customer_id);
                foreach ($parts as $part => $body) {
                    $company->whatsappOutboundMessages()->firstOrCreate(['inbound_message_id' => $r->id, 'part' => $part],
                        ['customer_id' => $customer->id, 'provider' => 'meta', 'idempotency_key' => hash('sha256', 'meta:'.$r->id.':'.$part),
                            'message_type' => $body['type'], 'destination_id' => $r->transport_payload['destination_id'] ?? $company->whatsapp_phone_number_id,
                            'payload' => ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => ltrim($r->transport_payload['customer_phone'] ?? $customer->phone, '+')] + $body]);
                }
                $r->update(['transport_payload' => null]);
                Queue::connection('database')->push(new SendMetaReply($r->id), '', 'whatsapp');
            }, 3);
        } catch (\Throwable) {
            // Queue/failed-job records contain only an internal ID and this constant error, not raw HTTP/DTO data.
            throw new \RuntimeException('Meta inbound processing failed safely.');
        }
    }
}
