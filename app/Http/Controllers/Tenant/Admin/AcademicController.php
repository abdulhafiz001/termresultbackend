<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicController extends Controller
{
    public function status()
    {
        TenantContext::id();
        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = null;

        if ($currentSession) {
            $currentTerm = TenantDB::table('terms')
                ->where('academic_session_id', $currentSession->id)
                ->where('is_current', true)
                ->first();
        }

        return response()->json([
            'has_current_session' => (bool) $currentSession,
            'has_current_term' => (bool) $currentTerm,
            'current_session' => $currentSession,
            'current_term' => $currentTerm,
        ]);
    }

    public function list()
    {
        TenantContext::id();
        $sessions = TenantDB::table('academic_sessions')->orderByDesc('start_date')->get();
        $terms = TenantDB::table('terms')->get()->groupBy('academic_session_id');

        $data = $sessions->map(function ($s) use ($terms) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'start_date' => $s->start_date,
                'end_date' => $s->end_date,
                'is_current' => (bool) $s->is_current,
                'terms' => ($terms[$s->id] ?? collect())->values(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function createSession(Request $request)
    {
        $tenantId = TenantContext::id();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $id = DB::table('academic_sessions')->insertGetId([
            'tenant_id' => $tenantId,
            ...$data,
            'is_current' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function setCurrentSession(int $id)
    {
        TenantContext::id();
        DB::transaction(function () use ($id) {
            TenantDB::table('academic_sessions')->update(['is_current' => false, 'updated_at' => now()]);
            TenantDB::table('academic_sessions')->where('id', $id)->update(['is_current' => true, 'updated_at' => now()]);

            // If terms exist for this session, keep only one current term.
            $hasTerm = TenantDB::table('terms')->where('academic_session_id', $id)->exists();
            if ($hasTerm) {
                // Do not force current term; admin can set explicitly.
                TenantDB::table('terms')->where('academic_session_id', $id)->update(['updated_at' => now()]);
            }
        });

        return response()->json(['message' => 'Current academic session updated.']);
    }

    public function upsertTerm(Request $request, int $sessionId)
    {
        $tenantId = TenantContext::id();
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $sessionId) {
            if (! empty($data['is_current'])) {
                TenantDB::table('terms')
                    ->where('academic_session_id', $sessionId)
                    ->update(['is_current' => false, 'updated_at' => now()]);
            }

            if (! empty($data['id'])) {
                TenantDB::table('terms')->where('id', (int) $data['id'])->update([
                    'name' => $data['name'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'is_current' => (bool) ($data['is_current'] ?? false),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('terms')->insert([
                    'tenant_id' => TenantContext::id(),
                    'academic_session_id' => $sessionId,
                    'name' => $data['name'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'is_current' => (bool) ($data['is_current'] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['message' => 'Term saved.']);
    }

    public function setCurrentTerm(int $termId)
    {
        TenantContext::id();
        $term = TenantDB::table('terms')->where('id', $termId)->first();
        if (! $term) {
            return response()->json(['message' => 'Term not found.'], 404);
        }

        $sessionId = (int) $term->academic_session_id;
        if (! $sessionId) {
            return response()->json(['message' => 'Invalid term session.'], 422);
        }

        DB::transaction(function () use ($sessionId, $termId) {
            // Make this term's session the current academic session.
            TenantDB::table('academic_sessions')->update(['is_current' => false, 'updated_at' => now()]);
            TenantDB::table('academic_sessions')->where('id', $sessionId)->update(['is_current' => true, 'updated_at' => now()]);

            // Ensure only one current term within the session.
            TenantDB::table('terms')
                ->where('academic_session_id', $sessionId)
                ->update(['is_current' => false, 'updated_at' => now()]);

            TenantDB::table('terms')
                ->where('id', $termId)
                ->update(['is_current' => true, 'updated_at' => now()]);
        });

        return response()->json(['message' => 'Current term updated successfully.']);
    }
}


