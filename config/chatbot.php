<?php

return [
    // --- Anthropic ---
    'api_key'             => env('ANTHROPIC_API_KEY'),
    'model'               => env('CHATBOT_MODEL', 'claude-sonnet-4-6'), // verify current IDs at platform.claude.com/docs
    'max_tokens'          => 1024,
    'max_tool_rounds'     => 6,
    'history_messages'    => 20,
    'renewal_window_days' => 30,
    'snapshot_cache_ttl'  => 600,

    // --- Acinforce integrator (HTTP API) ---
    'integrator' => [
        'base_url'    => env('ACINFORCE_BASE_URL', 'https://salesforcetestes.acin.pt/public/api/v1'),
        'public_key'  => env('ACINFORCE_PUBLIC_KEY'),
        'private_key' => env('ACINFORCE_PRIVATE_KEY'),
        'verify_ssl'  => env('ACINFORCE_VERIFY_SSL', false),   // the SDK also skips verification on staging
        // route behind $integrator->createProduct(...) — confirm in the integrator's routes/api.php
        'create_opportunity_endpoint' => env('ACINFORCE_CREATE_OPPORTUNITY', '/products'),
        'pricebook_id' => env('ACINFORCE_PRICEBOOK_ID'),   // optional: a dedicated chatbot price book; empty = standard
    ],
];