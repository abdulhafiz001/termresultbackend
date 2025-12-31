<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    private function normalizeTime(?string $t): ?string
    {
        if (! is_string($t) || $t === '') return $t;
        return strlen($t) >= 5 ? substr($t, 0, 5) : $t;
    }

    public function index(Request $request)
    {
        TenantContext::id();
        $studentId = $request->user()->id;

        // Get student's current class
        $studentProfile = TenantDB::table('student_profiles')
            ->where('user_id', $studentId)
            ->first();

        if (!$studentProfile || !$studentProfile->current_class_id) {
            return response()->json(['message' => 'Student class not found.'], 404);
        }

        $classId = (int) $studentProfile->current_class_id;

        $timetables = TenantDB::table('timetables')
            ->join('classes', function ($j) {
                $j->on('timetables.class_id', '=', 'classes.id')
                    ->on('timetables.tenant_id', '=', 'classes.tenant_id');
            })
            ->join('subjects', function ($j) {
                $j->on('timetables.subject_id', '=', 'subjects.id')
                    ->on('timetables.tenant_id', '=', 'subjects.tenant_id');
            })
            ->join('users', function ($j) {
                $j->on('timetables.teacher_id', '=', 'users.id')
                    ->on('timetables.tenant_id', '=', 'users.tenant_id');
            })
            ->where(function ($q) use ($classId) {
                $q->where('timetables.class_id', $classId)
                  ->orWhere(function ($q) use ($classId) {
                      $q->where('timetables.is_combined', true)
                        ->whereJsonContains('timetables.combined_class_ids', $classId);
                  });
            })
            ->select([
                'timetables.id',
                'timetables.class_id',
                'classes.name as class_name',
                'timetables.subject_id',
                'subjects.name as subject_name',
                'timetables.teacher_id',
                'users.name as teacher_name',
                'timetables.day_of_week',
                'timetables.start_time',
                'timetables.end_time',
                'timetables.venue',
                'timetables.notes',
            ])
            ->orderByRaw("FIELD(timetables.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday')")
            ->orderBy('timetables.start_time')
            ->get()
            ->map(function ($row) {
                $row->start_time = $this->normalizeTime($row->start_time ?? null);
                $row->end_time = $this->normalizeTime($row->end_time ?? null);
                return $row;
            });

        return response()->json(['data' => $timetables]);
    }
}

