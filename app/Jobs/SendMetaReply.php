<?php

namespace App\Jobs;

use App\Models\WhatsappInboundMessage;
use App\Whatsapp\Meta\MetaClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{DB, Queue};

class SendMetaReply implements ShouldQueue
{
    use Queueable;
    public int $tries = 5;
    public int $timeout = 45;
    public function __construct(public int $inboundId) { $this->onConnection('database')->onQueue('whatsapp')->beforeCommit(); }
    public function backoff(): array { return [10, 30, 120, 300]; }

    public function handle(MetaClient $client): void
    {
        try {
            $receipt = WhatsappInboundMessage::findOrFail($this->inboundId);
            $company = $receipt->company;
            $message = DB::transaction(function () use ($company) {
                $m = $company->whatsappOutboundMessages()->where('inbound_message_id', $this->inboundId)
                    ->where('status', '!=', 'sent')->orderBy('part')->lockForUpdate()->first();
                if (!$m || in_array($m->status, ['failed', 'uncertain'], true)) return null;
                if ($m->status === 'sending') {
                    if ($m->claimed_at?->lt(now()->subSeconds(90))) $m->update(['status' => 'uncertain', 'last_error_code' => 'worker_interrupted',
                        'last_error_summary' => 'Delivery outcome unknown; automatic resend withheld.']);
                    else Queue::connection('database')->later(91, new self($this->inboundId), '', 'whatsapp');
                    return null;
                }
                if ($m->next_attempt_at?->isFuture()) return null;
                if ($m->attempts >= 5) {
                    $m->update(['status' => 'failed', 'payload' => null, 'last_error_code' => 'retries_exhausted', 'last_error_summary' => 'Delivery retry limit reached.']);
                    return null;
                }
                $m->update(['status' => 'sending', 'claimed_at' => now(), 'attempts' => $m->attempts + 1]);
                return $m;
            }, 3);
            if (!$message) return;
            $result = $client->send($company, $message);
            DB::transaction(function () use ($company, $message, $result) {
                $m = $company->whatsappOutboundMessages()->lockForUpdate()->findOrFail($message->id);
                $status = $result['status'];
                if ($status === 'retrying' && $m->attempts >= 5) $status = 'failed';
                $delay = [10, 30, 120, 300][min(3, $m->attempts - 1)];
                $m->update(['status' => $status, 'external_message_id' => $result['external_id'] ?? null,
                    'sent_at' => $status === 'sent' ? now() : null, 'last_error_code' => $result['code'] ?? null,
                    'last_error_summary' => $result['summary'] ?? null,
                    'next_attempt_at' => $status === 'retrying' ? now()->addSeconds($delay) : null,
                    'payload' => in_array($status, ['sent', 'failed'], true) ? null : $m->payload]);
                if ($status === 'sent') {
                    Queue::connection('database')->push(new self($this->inboundId), '', 'whatsapp');
                } elseif ($status === 'retrying') {
                    Queue::connection('database')->later($delay, new self($this->inboundId), '', 'whatsapp');
                }
            }, 3);
        } catch (\Throwable) {
            throw new \RuntimeException('Meta delivery processing failed safely.');
        }
    }
}
