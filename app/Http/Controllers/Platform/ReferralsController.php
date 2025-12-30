<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferralsController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:pending,completed,failed'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $q = isset($data['q']) ? trim(strtolower((string) $data['q'])) : null;

        $rows = DB::table('referrals')
            ->when(! empty($data['status']), fn ($qq) => $qq->where('status', $data['status']))
            ->when($q, function ($qq) use ($q) {
                $like = '%'.$q.'%';
                $qq->whereRaw('lower(referrer_name) like ?', [$like])
                    ->orWhereRaw('lower(school_name) like ?', [$like])
                    ->orWhereRaw('lower(referral_code) like ?', [$like]);
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,completed,failed'],
        ]);

        $row = DB::table('referrals')->where('id', $id)->first();
        if (! $row) return response()->json(['message' => 'Referral not found.'], 404);

        $payload = [
            'status' => $data['status'],
            'updated_at' => now(),
        ];
        if ($data['status'] === 'completed') {
            $payload['completed_at'] = now();
            $payload['failed_at'] = null;
        } elseif ($data['status'] === 'failed') {
            $payload['failed_at'] = now();
            $payload['completed_at'] = null;
        } else {
            $payload['completed_at'] = null;
            $payload['failed_at'] = null;
        }

        DB::table('referrals')->where('id', $id)->update($payload);

        return response()->json(['message' => 'Referral status updated.']);
    }
}


