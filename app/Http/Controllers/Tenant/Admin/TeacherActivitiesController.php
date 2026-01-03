<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherActivitiesController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        $data = $request->validate([
            'teacher_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $q = TenantDB::table('teacher_activities as ta', 'ta.tenant_id')
            ->join('users as u', function ($j) {
                $j->on('u.id', '=', 'ta.teacher_id')
                    ->on('u.tenant_id', '=', 'ta.tenant_id');
            })
            ->where('u.role', 'teacher')
            ->where('ta.tenant_id', $tenantId);

        if (! empty($data['teacher_id'])) $q->where('ta.teacher_id', (int) $data['teacher_id']);
        if (! empty($data['action'])) $q->where('ta.action', $data['action']);
        if (! empty($data['from'])) $q->whereDate('ta.created_at', '>=', $data['from']);
        if (! empty($data['to'])) $q->whereDate('ta.created_at', '<=', $data['to']);

        $limit = (int) ($data['limit'] ?? 200);

        $items = $q
            ->orderByDesc('ta.id')
            ->limit($limit)
            ->get([
                'ta.id',
                'ta.teacher_id',
                'u.name as teacher_name',
                'u.username as teacher_username',
                'ta.action',
                'ta.metadata',
                'ta.ip',
                'ta.user_agent',
                'ta.created_at',
            ])
            ->map(function ($x) {
                $meta = $x->metadata;
                if (is_string($meta)) $meta = json_decode($meta, true) ?? null;
                return [
                    'id' => $x->id,
                    'teacher_id' => $x->teacher_id,
                    'teacher_name' => $x->teacher_name,
                    'teacher_username' => $x->teacher_username,
                    'action' => $x->action,
                    'metadata' => $meta,
                    'ip' => $x->ip,
                    'created_at' => $x->created_at,
                ];
            });

        $stats = [
            'total' => (int) TenantDB::table('teacher_activities')->count(),
            'logins' => (int) TenantDB::table('teacher_activities')->where('action', 'teacher_login')->count(),
            'score_entries' => (int) TenantDB::table('teacher_activities')->where('action', 'score_saved')->count(),
            'attendance' => (int) TenantDB::table('teacher_activities')->where('action', 'attendance_saved')->count(),
        ];

        return response()->json(['data' => $items, 'stats' => $stats]);
    }
}


