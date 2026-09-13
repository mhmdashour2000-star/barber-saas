<?php

namespace App\Whatsapp\Meta;

use App\Whatsapp\ConversationResponse;

class ResponseRenderer
{
    /** Deterministic ordered message groups: every option is included, never silently discarded. */
    public function render(ConversationResponse $response): array
    {
        $text = $response->text;
        foreach (['summary', 'appointment'] as $key) {
            if (is_array($response->data[$key] ?? null)) $text .= "\n".$this->summary($response->data[$key]);
        }
        foreach ($response->data['appointments'] ?? [] as $appointment) $text .= "\n\n".$this->summary($appointment);
        $choices = array_values($response->choices);
        $messages = [];
        if (!$choices) return $this->texts($text);
        // Full labels in a numbered text legend keep long/truncated titles unambiguous.
        if (count($choices) > 3 || collect($choices)->contains(fn ($c) => mb_strlen($c['label']) > 20)) {
            $text .= "\n".implode("\n", array_map(fn ($c, $i) => ($i + 1).'. '.$c['label'], $choices, array_keys($choices)));
        }
        $body = $text;
        if (mb_strlen($body) > 1024) {
            $messages = $this->texts($body);
            $body = 'Choose an option below. The numbered options above show full labels.';
        }
        foreach ($choices as $choice) {
            if (!is_string($choice['id']) || strlen($choice['id']) > 200 || trim($choice['id']) !== $choice['id']) {
                throw new \InvalidArgumentException('Invalid interactive identifier.');
            }
        }
        if (count($choices) <= 3) {
            $titles = array_column($choices, 'label');
            $unique = count(array_unique($titles)) === count($titles);
            $buttons = [];
            foreach ($choices as $i => $choice) {
                $title = $unique && mb_strlen($choice['label']) <= 20 ? $choice['label'] : ($i + 1).'. '.mb_substr($choice['label'], 0, 16);
                $buttons[] = ['type' => 'reply', 'reply' => ['id' => $choice['id'], 'title' => $title]];
            }
            $messages[] = ['type' => 'interactive', 'interactive' => ['type' => 'button', 'body' => ['text' => $body], 'action' => ['buttons' => $buttons]]];
        } else {
            $groups = array_chunk($choices, 10);
            foreach ($groups as $group => $items) {
                $rows = [];
                foreach ($items as $index => $choice) {
                    $number = $group * 10 + $index + 1;
                    $rows[] = ['id' => $choice['id'], 'title' => mb_substr($number.'. '.$choice['label'], 0, 24)];
                }
                $groupText = count($groups) > 1 ? 'Options '.($group * 10 + 1).'–'.($group * 10 + count($items)).' of '.count($choices).'.' : $body;
                if ($group === 0 && count($groups) > 1 && !$messages) $messages = $this->texts($text);
                $messages[] = ['type' => 'interactive', 'interactive' => ['type' => 'list', 'body' => ['text' => $groupText],
                    'action' => ['button' => 'Choose an option', 'sections' => [['title' => 'Options', 'rows' => $rows]]]]];
            }
        }
        return $messages;
    }

    private function texts(string $text): array
    {
        $parts = [];
        do {
            $parts[] = ['type' => 'text', 'text' => ['preview_url' => false, 'body' => mb_substr($text, 0, 4096)]];
            $text = mb_substr($text, 4096);
        } while ($text !== '');
        return $parts;
    }

    private function summary(array $data): string
    {
        $labels = ['booking_code' => 'Booking', 'customer_name' => 'Customer', 'service' => 'Service', 'barber' => 'Barber',
            'date' => 'Date', 'time' => 'Time', 'timezone' => 'Timezone', 'duration_minutes' => 'Minutes', 'status' => 'Status'];
        $lines = [];
        foreach ($labels as $key => $label) if (isset($data[$key])) $lines[] = $label.': '.$data[$key];
        if (isset($data['price_minor_units'])) $lines[] = 'Price: '.number_format($data['price_minor_units'] / 100, 2, '.', '');
        return implode("\n", $lines);
    }
}
