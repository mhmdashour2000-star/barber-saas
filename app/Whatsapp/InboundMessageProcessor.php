<?php

namespace App\Whatsapp;

use App\Models\Company;
use App\Services\CustomerRestrictionService;
use Illuminate\Support\Facades\DB;
use Throwable;

class InboundMessageProcessor
{
    public function __construct(private ConversationEngine $engine, private CustomerRestrictionService $customers) {}

    public function handle(InboundMessage $input): ConversationResponse
    {
        $id = null;
        $revision = null;
        try {
            // Short bootstrap mutex only. Release it before taking scheduling/appointment locks.
            $id = DB::transaction(function () use ($input) {
                $company = Company::query()->lockForUpdate()->findOrFail($input->company->id);
                if (!$company->whatsapp_enabled) {
                    return null;
                }
                $existing = $company->whatsappInboundMessages()->where('provider', $input->provider)
                    ->where('external_message_id', $input->externalMessageId)->first();
                if ($existing) {
                    if (!hash_equals($existing->fingerprint, $input->fingerprint())) {
                        throw new \InvalidArgumentException('Message identity was reused with different content.');
                    }
                    return $existing->id;
                }
                $customer = $this->customers->findOrCreateCustomer($company, $input->customerPhone);
                $company->whatsappConversations()->firstOrCreate(['customer_id' => $customer->id]);

                return $company->whatsappInboundMessages()->create([
                    'customer_id' => $customer->id, 'provider' => $input->provider,
                    'external_message_id' => $input->externalMessageId, 'fingerprint' => $input->fingerprint(),
                    'message_type' => $input->messageType, 'received_at' => $input->receivedAt->utc(),
                ])->id;
            }, 3);
            if ($id === null) {
                return new ConversationResponse('text', 'WhatsApp booking is not enabled for this salon.');
            }

            return DB::transaction(function () use ($input, $id, &$revision) {
                $company = $input->company->fresh();
                $message = $company->whatsappInboundMessages()->lockForUpdate()->findOrFail($id);
                if ($message->status === 'processed') {
                    return ConversationResponse::fromArray($message->response);
                }
                $conversation = $company->whatsappConversations()->where('customer_id', $message->customer_id)->lockForUpdate()->firstOrFail();
                $revision = $conversation->revision;
                $customer = $company->customers()->findOrFail($message->customer_id);
                $staleRetry = $message->processing_revision !== null && (int) $message->processing_revision !== $revision;
                $response = $staleRetry
                    ? new ConversationResponse('text', 'This retry belongs to an older conversation step. Send menu to continue.')
                    : ($company->whatsapp_enabled
                    ? $this->engine->handle($conversation, $customer, $company, $input)
                    : new ConversationResponse('text', 'WhatsApp booking is not enabled for this salon.'));
                $message->update(['status' => 'processed', 'response' => $response->jsonSerialize(),
                    'processed_at' => now(), 'attempts' => $message->attempts + 1, 'failure_code' => null, 'processing_revision' => $revision]);

                return $response;
            }, 3);
        } catch (Throwable $exception) {
            // Rollback includes conversation, appointment and event writes. Do not log message bodies or exception arguments.
            if ($id !== null) {
                try {
                    $completed = DB::transaction(function () use ($input, $id, $revision) {
                        $message = $input->company->whatsappInboundMessages()->lockForUpdate()->find($id);
                        if ($message?->status === 'processed') {
                            return ConversationResponse::fromArray($message->response);
                        }
                        $message?->update(['status' => 'failed', 'failure_code' => 'processing_failed', 'attempts' => $message->attempts + 1,
                            'processing_revision' => $message->processing_revision ?? $revision]);
                        return null;
                    }, 3);
                    if ($completed) {
                        return $completed;
                    }
                } catch (Throwable) {
                    // A database outage may also prevent recording failure. The unchanged receipt is retryable.
                }
            }

            return new ConversationResponse('text', 'We could not process this message. Please retry.', data: ['retryable' => true]);
        }
    }
}
