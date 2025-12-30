<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplaintsController extends Controller
{
    public function index(Request $request)
    {
        $studentId = $request->user()->id;

        $items = DB::table('complaints')
            ->where('student_id', $studentId)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:complaint,suggestion'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $id = DB::table('complaints')->insertGetId([
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


