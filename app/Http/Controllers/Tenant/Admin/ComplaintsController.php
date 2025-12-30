<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplaintsController extends Controller
{
    public function index(Request $request)
    {
        $items = DB::table('complaints')
            ->join('users', 'users.id', '=', 'complaints.student_id')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->select([
                'complaints.*',
                'users.admission_number',
                'student_profiles.first_name',
                'student_profiles.last_name',
            ])
            ->orderByDesc('complaints.created_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $items]);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:open,in_review,resolved'],
            'admin_response' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::table('complaints')->where('id', $id)->update([
            'status' => $data['status'] ?? DB::raw('status'),
            'admin_response' => array_key_exists('admin_response', $data) ? $data['admin_response'] : DB::raw('admin_response'),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Complaint updated.']);
    }
}


