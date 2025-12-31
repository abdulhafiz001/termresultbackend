<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;

class ComplaintsController extends Controller
{
    public function index(Request $request)
    {
        TenantContext::id();
        $studentId = $request->user()->id;

        $items = TenantDB::table('complaints')
            ->where('student_id', $studentId)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        $data = $request->validate([
            'type' => ['required', 'in:complaint,suggestion'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $id = TenantDB::table('complaints')->insertGetId([
            'tenant_id' => $tenantId,
            'student_id' => $request->user()->id,
            'type' => $data['type'],
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $id]], 201);
    }
}


