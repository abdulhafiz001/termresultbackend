<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimetableController extends Controller
{
    private function normalizeTime(?string $t): ?string
    {
        if (! is_string($t) || $t === '') return $t;
        return strlen($t) >= 5 ? substr($t, 0, 5) : $t;
    }

    public function index(Request $request)
    {
        $teacherId = $request->user()->id;

        $timetables = DB::table('timetables')
            ->join('classes', 'timetables.class_id', '=', 'classes.id')
            ->join('subjects', 'timetables.subject_id', '=', 'subjects.id')
            ->where('timetables.teacher_id', $teacherId)
            ->select([
                'timetables.id',
                'timetables.class_id',
                'classes.name as class_name',
                'timetables.subject_id',
                'subjects.name as subject_name',
                'timetables.day_of_week',
                'timetables.start_time',
                'timetables.end_time',
                'timetables.venue',
                'timetables.is_combined',
                'timetables.combined_class_ids',
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

