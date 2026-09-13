<?php

namespace App\Whatsapp\Meta;

use App\Jobs\ProcessMetaInbound;
use App\Whatsapp\InboundMessageProcessor;
use Illuminate\Support\Facades\{DB, Queue};

class Ingress
{
    public function __construct(private InboundMessageProcessor $processor) {}

    public function accept(array $event): void
    {
        // Receipt and database queue insertion must commit on the same database connection.
        if (config('queue.connections.database.connection') !== null
            && config('queue.connections.database.connection') !== config('database.default')) {
            throw new \RuntimeException('Database queue connection must match the application database.');
        }
        DB::transaction(function () use ($event) {
            $input = $event['input'];
            $id = $this->processor->receive($input);
            if ($id === null) return;
            $receipt = $input->company->whatsappInboundMessages()->lockForUpdate()->findOrFail($id);
            if (!$receipt->transport_queued_at) {
                $receipt->update(['transport_payload' => ['selection' => $input->payload, 'unsupported' => $event['unsupported'],
                    'customer_phone' => $input->customerPhone, 'destination_id' => $event['destination_id']], 'transport_queued_at' => now()]);
            }
            // Duplicate queue deliveries are intentionally safe and also recover an interrupted dispatch/worker.
            Queue::connection('database')->push(new ProcessMetaInbound($id), '', 'whatsapp');
        }, 3);
    }
}
