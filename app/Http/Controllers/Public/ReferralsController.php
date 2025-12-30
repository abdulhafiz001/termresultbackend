<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralsController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'referrer_name' => ['required', 'string', 'max:255'],
            'referrer_phone' => ['nullable', 'string', 'max:50'],
            'referrer_email' => ['nullable', 'email', 'max:255'],

            'school_name' => ['required', 'string', 'max:255'],
            'school_address' => ['nullable', 'string', 'max:255'],
            'school_city' => ['nullable', 'string', 'max:255'],
            'school_state' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Generate a short code for WhatsApp verification.
        $code = null;
        for ($i = 0; $i < 5; $i++) {
            $candidate = strtoupper(Str::random(8));
            $exists = DB::table('referrals')->where('referral_code', $candidate)->exists();
            if (! $exists) {
                $code = $candidate;
                break;
            }
        }
        if (! $code) {
            return response()->json(['message' => 'Failed to generate referral code. Please try again.'], 500);
        }

        DB::table('referrals')->insert([
            'referrer_name' => trim((string) $data['referrer_name']),
            'referrer_phone' => isset($data['referrer_phone']) ? trim((string) $data['referrer_phone']) : null,
            'referrer_email' => isset($data['referrer_email']) ? strtolower(trim((string) $data['referrer_email'])) : null,
            'school_name' => trim((string) $data['school_name']),
            'school_address' => isset($data['school_address']) ? trim((string) $data['school_address']) : null,
            'school_city' => isset($data['school_city']) ? trim((string) $data['school_city']) : null,
            'school_state' => isset($data['school_state']) ? trim((string) $data['school_state']) : null,
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
            'referral_code' => $code,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Referral recorded successfully.',
            'data' => [
                'referral_code' => $code,
            ],
        ]);
    }
}


