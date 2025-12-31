<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // During development you can allow your Vite dev origin.
    // In production, set CORS_ALLOWED_ORIGINS to your domains.
    'allowed_methods' => ['*'],

    /**
     * CORS_ALLOWED_ORIGINS supports comma-separated values like:
     * - https://termresult.com
     * - https://demo.termresult.com
     * - https://*.termresult.com   (wildcards are converted into regex patterns)
     *
     * NOTE: Laravel's CORS config treats `allowed_origins` as exact matches.
     * For wildcards we must use `allowed_origins_patterns`.
     */
    'allowed_origins' => (function () {
        $raw = env('CORS_ALLOWED_ORIGINS');

        if ($raw === null || trim((string) $raw) === '') {
            return ['*'];
        }

        $raw = trim((string) $raw);
        if ($raw === '*') {
            return ['*'];
        }

        $items = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $exact = [];

        foreach ($items as $item) {
            if ($item === '*') {
                return ['*'];
            }

            // Wildcards belong in allowed_origins_patterns, not allowed_origins.
            if (str_contains($item, '*')) {
                continue;
            }

            $exact[] = $item;
        }

        return array_values(array_unique($exact));
    })(),

    'allowed_origins_patterns' => (function () {
        $raw = env('CORS_ALLOWED_ORIGINS');

        if ($raw === null || trim((string) $raw) === '') {
            return [];
        }

        $raw = trim((string) $raw);
        if ($raw === '*' || $raw === '') {
            return [];
        }

        $items = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $patterns = [];

        $toPattern = function (string $originWithWildcard): string {
            // Escape, then turn '*' into '.*' for regex.
            $quoted = preg_quote($originWithWildcard, '#');
            $quoted = str_replace('\*', '.*', $quoted);
            return '#^' . $quoted . '$#';
        };

        foreach ($items as $item) {
            if (! str_contains($item, '*')) {
                continue;
            }

            $patterns[] = $toPattern($item);

            // Common production setup: you might serve demo tenants over HTTP.
            // If someone whitelists `https://*.termresult.com` we also allow the HTTP variant.
            if (str_starts_with($item, 'https://')) {
                $httpVariant = 'http://' . substr($item, strlen('https://'));
                $patterns[] = $toPattern($httpVariant);
            }
        }

        return array_values(array_unique($patterns));
    })(),

    'allowed_headers' => ['*'],

    // Needed so the frontend can read `Content-Disposition` for blob downloads (answer slips, etc.)
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,
];


