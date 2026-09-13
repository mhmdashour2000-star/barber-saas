<?php

namespace App\Whatsapp\Meta;

use App\Whatsapp\{CompanyResolver, InboundMessage};
use Carbon\CarbonImmutable;

class PayloadParser
{
    public function __construct(private CompanyResolver $companies) {}

    /** Called only after authenticity validation. Malformed members do not discard other valid entries. */
    public function messages(array $payload): array
    {
        $result = [];
        foreach ($this->values($payload) as $value) {
            $destination = $value['metadata']['phone_number_id'] ?? null;
            if (!is_string($destination) || !preg_match('/^\d{1,100}$/D', $destination)) continue;
            try { $company = $this->companies->byPhoneNumberId($destination); }
            catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { continue; }
            foreach (is_array($value['messages'] ?? null) ? $value['messages'] : [] as $message) {
                if (!is_array($message)) continue;
                $from = $message['from'] ?? null;
                $id = $message['id'] ?? null;
                $timestamp = $message['timestamp'] ?? null;
                if (!is_string($from) || !preg_match('/^[1-9]\d{7,14}$/D', $from)
                    || !is_string($id) || !is_string($timestamp) || !preg_match('/^\d{1,11}$/D', $timestamp)) continue;
                $kind = $message['type'] ?? null;
                $unsupported = false;
                if ($kind === 'text') {
                    $type = 'text'; $selection = $message['text']['body'] ?? null;
                } elseif ($kind === 'interactive') {
                    $interactive = $message['interactive'] ?? [];
                    $subtype = $interactive['type'] ?? null;
                    if (!in_array($subtype, ['button_reply', 'list_reply'], true)) continue;
                    $type = $subtype === 'button_reply' ? 'button' : 'list';
                    $selection = $interactive[$subtype]['id'] ?? null; // Titles are display-only, never authority.
                } else {
                    $type = 'text'; $selection = '[unsupported]'; $unsupported = true;
                }
                if (!is_string($selection)) continue;
                if (mb_strlen($selection) > 2000) {
                    $selection = '[unsupported]'; $type = 'text'; $unsupported = true;
                }
                try {
                    $input = new InboundMessage('meta', $id, $company, '+'.$from, $type, $selection, CarbonImmutable::createFromTimestampUTC($timestamp));
                    $result[] = ['input' => $input, 'unsupported' => $unsupported, 'destination_id' => $destination];
                } catch (\InvalidArgumentException) { continue; }
            }
        }
        return $result;
    }

    public function values(array $payload): array
    {
        if (($payload['object'] ?? null) !== 'whatsapp_business_account') return [];
        $values = [];
        foreach (is_array($payload['entry'] ?? null) ? $payload['entry'] : [] as $entry) {
            foreach (is_array($entry['changes'] ?? null) ? $entry['changes'] : [] as $change) {
                if (($change['field'] ?? null) === 'messages' && is_array($change['value'] ?? null)) $values[] = $change['value'];
            }
        }
        return $values;
    }
}
