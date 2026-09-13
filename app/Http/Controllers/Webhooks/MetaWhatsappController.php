<?php

namespace App\Http\Controllers\Webhooks;

use App\Whatsapp\Meta\{PayloadParser, Ingress, DeliveryStatuses};
use Illuminate\Http\Request;

class MetaWhatsappController
{
    public function verify(Request $request)
    {
        // PHP normalizes dots in query keys; support both the HTTP and direct-test representations.
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $expected = (string) config('whatsapp.meta.verify_token');
        if ($mode !== 'subscribe' || $expected === '' || !is_string($token) || !hash_equals($expected, $token)
            || !is_string($challenge) || $challenge === '' || strlen($challenge) > 2000) return response('Forbidden', 403);
        return response($challenge, 200)->header('Content-Type', 'text/plain')->header('Cache-Control', 'no-store');
    }

    public function receive(Request $request, PayloadParser $parser, Ingress $ingress, DeliveryStatuses $statuses)
    {
        $secret = (string) config('whatsapp.meta.app_secret');
        $signature = $request->header('X-Hub-Signature-256', '');
        $raw = $request->getContent();
        if ($secret === '' || !preg_match('/^sha256=[a-f0-9]{64}$/D', $signature)
            || !hash_equals('sha256='.hash_hmac('sha256', $raw, $secret), $signature)) return response('Forbidden', 403);
        if (strlen($raw) > 1048576) return response('Payload too large', 413);
        $payload = json_decode($raw, true, 64);
        if (!is_array($payload)) return response('Invalid payload', 400);
        try {
            foreach ($parser->messages($payload) as $event) {
                try { $ingress->accept($event); }
                catch (\InvalidArgumentException) { /* Reused message ID/content mismatch: no domain processing. */ }
            }
            foreach ($parser->values($payload) as $value) $statuses->handle($value);
        } catch (\Throwable) {
            // Encourage Meta retry on persistence outage, without rendering debug traces or signed body.
            return response('Temporarily unavailable', 503);
        }
        return response('EVENT_RECEIVED', 200);
    }
}
