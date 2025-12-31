<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplaintsController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();

        $items = DB::table('complaints as c')
            ->where('c.tenant_id', $tenantId)
            ->join('users as u', function ($j) {
                $j->on('u.id', '=', 'c.student_id')
                    ->on('u.tenant_id', '=', 'c.tenant_id');
            })
            ->leftJoin('student_profiles as sp', function ($j) {
                $j->on('sp.user_id', '=', 'u.id')
                    ->on('sp.tenant_id', '=', 'c.tenant_id');
            })
            ->select([
                'c.*',
                'u.admission_number',
                'sp.first_name',
                'sp.last_name',
            ])
            ->orderByDesc('c.created_at')
            ->limit(200)
            ->get();

        // Mark unread complaints as viewed when admin opens the page.
        DB::table('complaints')
            ->where('tenant_id', $tenantId)
            ->whereNull('admin_viewed_at')
            ->update([
                'admin_viewed_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['data' => $items]);
    }

    public function unreadCount(Request $request)
    {
        $tenantId = TenantContext::id();

        $count = (int) TenantDB::table('complaints')
            ->whereNull('admin_viewed_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function update(Request $request, int $id)
    {
        $tenantId = TenantContext::id();
        $data = $request->validate([
            'status' => ['nullable', 'in:open,in_review,resolved'],
            'admin_response' => ['nullable', 'string', 'max:5000'],
        ]);

        $updated = DB::table('complaints')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update([
            'status' => $data['status'] ?? DB::raw('status'),
            'admin_response' => array_key_exists('admin_response', $data) ? $data['admin_response'] : DB::raw('admin_response'),
            'updated_at' => now(),
        ]);

        if (! $updated) {
            return response()->json(['message' => 'Complaint not found.'], 404);
        }

        return response()->json(['message' => 'Complaint updated.']);
    }
}


