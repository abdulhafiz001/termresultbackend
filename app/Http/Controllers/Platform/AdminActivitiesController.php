<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminActivitiesController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            // If true, show only support (customer_service) admin activities
            'support_only' => ['nullable', 'boolean'],
        ]);

        $limit = (int) ($data['limit'] ?? 200);
        $supportOnly = (bool) ($data['support_only'] ?? true);

        $q = DB::table('platform_admin_activities as a')
            ->join('platform_admins as pa', 'pa.id', '=', 'a.platform_admin_id');

        if ($supportOnly) {
            $q->where('pa.role', 'customer_service');
        }

        $items = $q->orderByDesc('a.id')
            ->limit($limit)
            ->get([
                'a.id',
                'a.action',
                'a.metadata',
                'a.ip',
                'a.created_at',
                'pa.full_name as admin_name',
                'pa.email as admin_email',
                'pa.role as admin_role',
            ]);

        return response()->json(['data' => $items]);
    }
}


