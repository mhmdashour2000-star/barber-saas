<?php

namespace App\Whatsapp\Meta;

use App\Models\{Company, WhatsappOutboundMessage};
use Illuminate\Support\Facades\Http;

class MetaClient
{
    public function __construct(private Configuration $configuration) {}

    /** Safe classified result only: never throw HTTP exceptions containing request tokens or provider bodies. */
    public function send(Company $company, WhatsappOutboundMessage $message): array
    {
        if (!$company->whatsapp_enabled || !$this->configuration->complete($company)
            || $message->destination_id !== $company->whatsapp_phone_number_id) {
            return ['status' => 'failed', 'code' => 'configuration', 'summary' => 'Provider configuration unavailable or changed.'];
        }
        try {
            $response = Http::withToken($this->configuration->token($company))->acceptJson()
                ->timeout(max(1, min(25, config('whatsapp.meta.timeout', 20))))
                ->connectTimeout(max(1, min(10, config('whatsapp.meta.connect_timeout', 5))))
                ->withOptions(['allow_redirects' => false])
                ->post('https://graph.facebook.com/'.config('whatsapp.meta.graph_version').'/'.$message->destination_id.'/messages', $message->payload);
            if ($response->successful()) {
                $id = $response->json('messages.0.id');
                return is_string($id) && strlen($id) <= 191
                    ? ['status' => 'sent', 'external_id' => $id]
                    : ['status' => 'uncertain', 'code' => 'missing_ack', 'summary' => 'Provider accepted request without a usable message identifier.'];
            }
            $retry = $response->status() === 429 || $response->serverError();
            $code = $response->json('error.code');
            return ['status' => $retry ? 'retrying' : 'failed',
                'code' => is_int($code) ? 'meta_'.$code : 'http_'.$response->status(),
                'summary' => $retry ? 'Temporary provider failure.' : 'Provider rejected delivery; configuration or recipient review required.'];
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return ['status' => 'retrying', 'code' => 'connection', 'summary' => 'Provider connection failed or timed out.'];
        } catch (\Throwable) {
            return ['status' => 'failed', 'code' => 'transport', 'summary' => 'Transport could not prepare or send the request.'];
        }
    }
}
