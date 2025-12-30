<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // During development you can allow your Vite dev origin.
    // In production, set CORS_ALLOWED_ORIGINS to your domains.
    'allowed_methods' => ['*'],

    // If CORS_ALLOWED_ORIGINS is not set, default to allowing all origins.
    // Laravel's CORS config expects an array. Use ['*'] for wildcard.
    'allowed_origins' => (function () {
        $raw = env('CORS_ALLOWED_ORIGINS');

        if ($raw === null || trim((string) $raw) === '') {
            return ['*'];
        }

        $raw = trim((string) $raw);
        if ($raw === '*') {
            return ['*'];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    })(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Needed so the frontend can read `Content-Disposition` for blob downloads (answer slips, etc.)
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,
];


