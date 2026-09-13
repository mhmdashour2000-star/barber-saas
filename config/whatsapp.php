<?php

return [
    'meta' => [
        'graph_version' => env('META_WHATSAPP_GRAPH_VERSION', ''),
        'verify_token' => env('META_WHATSAPP_VERIFY_TOKEN', ''),
        'app_secret' => env('META_WHATSAPP_APP_SECRET', ''),
        // Secret references keyed by the trusted destination phone-number ID. Never expose this map.
        'access_tokens' => json_decode(env('META_WHATSAPP_ACCESS_TOKENS', '{}'), true) ?: [],
        'timeout' => (int) env('META_WHATSAPP_TIMEOUT', 20),
        'connect_timeout' => (int) env('META_WHATSAPP_CONNECT_TIMEOUT', 5),
    ],
    'conversation_idle_hours' => 24,
    'slot_interval_minutes' => 15,
];
