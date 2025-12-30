<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaystackService
{
    private function secret(?string $secretKey = null): string
    {
        $secret = $secretKey ?: env('PAYSTACK_SECRET_KEY');
        if (! $secret) {
            throw new \RuntimeException('PAYSTACK_SECRET_KEY is not configured.');
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
            ->get('https://api.paystack.co/bank', [
                'country' => $country,
                'perPage' => 200,
            ]);

        if (! $res->successful()) {
            throw new \RuntimeException('Paystack list banks failed: '.$res->body());
        }

        return $res->json();
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


