<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaystackService
{
    private function secret(?string $secretKey = null): string
    {
        $secret = $secretKey ?: config('services.paystack.secret');
        if (! $secret) {
            throw new \RuntimeException('PAYSTACK_SECRET_KEY is not configured. Please set it in your .env file and run php artisan config:cache.');
        }
        if (strlen($secret) < 10) {
            throw new \RuntimeException('PAYSTACK_SECRET_KEY appears to be invalid (too short).');
        }
        return $secret;
    }

    public function initialize(array $payload, ?string $secretKey = null): array
    {
        $secret = $this->secret($secretKey);

        $res = Http::withToken($secret)
            ->acceptJson()
            ->post('https://api.paystack.co/transaction/initialize', $payload);

        if (! $res->successful()) {
            throw new \RuntimeException('Paystack initialize failed: '.$res->body());
        }

        return $res->json();
    }

    public function verify(string $reference, ?string $secretKey = null): array
    {
        $secret = $this->secret($secretKey);

        $res = Http::withToken($secret)
            ->acceptJson()
            ->get('https://api.paystack.co/transaction/verify/'.urlencode($reference));

        if (! $res->successful()) {
            throw new \RuntimeException('Paystack verify failed: '.$res->body());
        }

        return $res->json();
    }

    public function listBanks(string $country = 'nigeria', ?string $secretKey = null): array
    {
        $secret = $this->secret($secretKey);

        $res = Http::withToken($secret)
            ->acceptJson()
            ->timeout(30) // Add timeout to prevent hanging
            ->get('https://api.paystack.co/bank', [
                'country' => $country,
                'perPage' => 200,
            ]);

        if (! $res->successful()) {
            $errorBody = $res->body();
            $statusCode = $res->status();
            throw new \RuntimeException("Paystack list banks failed (HTTP {$statusCode}): {$errorBody}");
        }

        $json = $res->json();
        
        // Handle case where response might not be valid JSON
        if ($json === null) {
            throw new \RuntimeException('Paystack API returned invalid JSON response. Please check your API key and network connection.');
        }
        
        // Check if Paystack returned an error
        if (!isset($json['status']) || $json['status'] !== true) {
            $message = $json['message'] ?? 'Unknown error from Paystack';
            $data = $json['data'] ?? null;
            throw new \RuntimeException("Paystack list banks failed: {$message}" . ($data ? " (Data: " . json_encode($data) . ")" : ""));
        }

        return $json;
    }

    public function resolveBankAccount(string $accountNumber, string $bankCode, ?string $secretKey = null): array
    {
        $secret = $this->secret($secretKey);

        $res = Http::withToken($secret)
            ->acceptJson()
            ->get('https://api.paystack.co/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

        if (! $res->successful()) {
            throw new \RuntimeException('Paystack resolve account failed: '.$res->body());
        }

        return $res->json();
    }

    public function createSubaccount(array $payload, ?string $secretKey = null): array
    {
        $secret = $this->secret($secretKey);

        $res = Http::withToken($secret)
            ->acceptJson()
            ->post('https://api.paystack.co/subaccount', $payload);

        if (! $res->successful()) {
            throw new \RuntimeException('Paystack create subaccount failed: '.$res->body());
        }

        return $res->json();
    }
}


