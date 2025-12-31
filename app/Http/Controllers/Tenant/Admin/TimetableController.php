<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class TimetableController extends Controller
{
    private function normalizeTime(?string $t): ?string
    {
        if (! is_string($t) || $t === '') return $t;
        // MySQL TIME often returns HH:MM:SS; frontend expects HH:MM
        return strlen($t) >= 5 ? substr($t, 0, 5) : $t;
    }

    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        $classId = $request->query('class_id');
        
        $query = TenantDB::table('timetables')
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
                'timetables.is_combined',
                'timetables.combined_class_ids',
                'timetables.notes',
            ])
            ->orderByRaw("FIELD(timetables.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday')")
            ->orderBy('timetables.start_time');

        if ($classId) {
            $query->where('timetables.class_id', (int) $classId);
        }

        $rows = $query->get()->map(function ($row) {
            $row->start_time = $this->normalizeTime($row->start_time ?? null);
            $row->end_time = $this->normalizeTime($row->end_time ?? null);
            return $row;
        });

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'teacher_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'day_of_week' => ['required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday'],
            // Accept HH:MM and HH:MM:SS (we normalize to HH:MM)
            'start_time' => ['required', 'string', 'regex:/^\\d{2}:\\d{2}(:\\d{2})?$/'],
            'end_time' => ['required', 'string', 'regex:/^\\d{2}:\\d{2}(:\\d{2})?$/'],
            'venue' => ['nullable', 'string', 'max:255'],
            'is_combined' => ['boolean'],
            'combined_class_ids' => ['nullable', 'array'],
            'combined_class_ids.*' => ['integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string'],
        ]);

        $data['start_time'] = $this->normalizeTime($data['start_time']);
        $data['end_time'] = $this->normalizeTime($data['end_time']);
        if ($data['end_time'] <= $data['start_time']) {
            throw ValidationException::withMessages([
                'end_time' => ['End time must be after start time.'],
            ]);
        }

        // Check for teacher clash (unless it's a combined class)
        if (!$data['is_combined']) {
            $teacherClash = TenantDB::table('timetables')
                ->where('teacher_id', $data['teacher_id'])
                ->where('day_of_week', $data['day_of_week'])
                ->where(function ($query) use ($data) {
                    $query->where(function ($q) use ($data) {
                        // Check if start_time falls within existing slot
                        $q->where('start_time', '<=', $data['start_time'])
                          ->where('end_time', '>', $data['start_time']);
                    })->orWhere(function ($q) use ($data) {
                        // Check if end_time falls within existing slot
                        $q->where('start_time', '<', $data['end_time'])
                          ->where('end_time', '>=', $data['end_time']);
                    })->orWhere(function ($q) use ($data) {
                        // Check if existing slot falls within new slot
                        $q->where('start_time', '>=', $data['start_time'])
                          ->where('end_time', '<=', $data['end_time']);
                    });
                })
                ->where('is_combined', false)
                ->first();

            if ($teacherClash) {
                throw ValidationException::withMessages([
                    'teacher_id' => ['This teacher already has a class scheduled at this time.'],
                ]);
            }
        }

        // Check for class clash (same class can't have multiple subjects at same time)
        $classClash = TenantDB::table('timetables')
            ->where('class_id', $data['class_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where(function ($query) use ($data) {
                $query->where(function ($q) use ($data) {
                    $q->where('start_time', '<=', $data['start_time'])
                      ->where('end_time', '>', $data['start_time']);
                })->orWhere(function ($q) use ($data) {
                    $q->where('start_time', '<', $data['end_time'])
                      ->where('end_time', '>=', $data['end_time']);
                })->orWhere(function ($q) use ($data) {
                    $q->where('start_time', '>=', $data['start_time'])
                      ->where('end_time', '<=', $data['end_time']);
                });
            })
            ->first();

        if ($classClash) {
            throw ValidationException::withMessages([
                'class_id' => ['This class already has a subject scheduled at this time.'],
            ]);
        }

        // Check if teacher is assigned to this subject
        $teacherSubject = TenantDB::table('teacher_subject')
            ->where('teacher_id', $data['teacher_id'])
            ->where('subject_id', $data['subject_id'])
            ->exists();

        if (!$teacherSubject) {
            throw ValidationException::withMessages([
                'teacher_id' => ['This teacher is not assigned to teach this subject.'],
            ]);
        }

        // Check teacher is assigned to the main class (and combined classes if any)
        $classIdsToCheck = array_merge([(int) $data['class_id']], (array) ($data['combined_class_ids'] ?? []));
        $missing = [];
        foreach (array_unique($classIdsToCheck) as $cid) {
            $ok = TenantDB::table('teacher_class')
                ->where('teacher_id', $data['teacher_id'])
                ->where('class_id', (int) $cid)
                ->exists();
            if (! $ok) $missing[] = (int) $cid;
        }
        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'teacher_id' => ['This teacher is not assigned to the selected class(es).'],
            ]);
        }

        $timetableId = DB::table('timetables')->insertGetId([
            'tenant_id' => $tenantId,
            'class_id' => $data['class_id'],
            'subject_id' => $data['subject_id'],
            'teacher_id' => $data['teacher_id'],
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'venue' => $data['venue'] ?? null,
            'is_combined' => $data['is_combined'] ?? false,
            'combined_class_ids' => $data['combined_class_ids'] ? json_encode($data['combined_class_ids']) : null,
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $timetableId]], 201);
    }

    public function update(Request $request, int $id)
    {
        $tenantId = TenantContext::id();
        $timetable = TenantDB::table('timetables')->where('id', $id)->first();
        if (!$timetable) {
            return response()->json(['message' => 'Timetable not found.'], 404);
        }

        $data = $request->validate([
            'class_id' => ['sometimes', 'required', 'integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'subject_id' => ['sometimes', 'required', 'integer', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'teacher_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'day_of_week' => ['sometimes', 'required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday'],
            'start_time' => ['sometimes', 'required', 'string', 'regex:/^\\d{2}:\\d{2}(:\\d{2})?$/'],
            'end_time' => ['sometimes', 'required', 'string', 'regex:/^\\d{2}:\\d{2}(:\\d{2})?$/'],
            'venue' => ['nullable', 'string', 'max:255'],
            'is_combined' => ['boolean'],
            'combined_class_ids' => ['nullable', 'array'],
            'combined_class_ids.*' => ['integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string'],
        ]);

        // Merge with existing data
        $updateData = array_merge([
            'class_id' => $timetable->class_id,
            'subject_id' => $timetable->subject_id,
            'teacher_id' => $timetable->teacher_id,
            'day_of_week' => $timetable->day_of_week,
            'start_time' => $this->normalizeTime($timetable->start_time),
            'end_time' => $this->normalizeTime($timetable->end_time),
            'is_combined' => $timetable->is_combined,
        ], $data);

        $updateData['start_time'] = $this->normalizeTime($updateData['start_time']);
        $updateData['end_time'] = $this->normalizeTime($updateData['end_time']);
        if ($updateData['end_time'] <= $updateData['start_time']) {
            throw ValidationException::withMessages([
                'end_time' => ['End time must be after start time.'],
            ]);
        }

        // Check for clashes (excluding current timetable)
        if (!$updateData['is_combined']) {
            $teacherClash = TenantDB::table('timetables')
                ->where('teacher_id', $updateData['teacher_id'])
                ->where('day_of_week', $updateData['day_of_week'])
                ->where('id', '!=', $id)
                ->where(function ($query) use ($updateData) {
                    $query->where(function ($q) use ($updateData) {
                        $q->where('start_time', '<=', $updateData['start_time'])
                          ->where('end_time', '>', $updateData['start_time']);
                    })->orWhere(function ($q) use ($updateData) {
                        $q->where('start_time', '<', $updateData['end_time'])
                          ->where('end_time', '>=', $updateData['end_time']);
                    })->orWhere(function ($q) use ($updateData) {
                        $q->where('start_time', '>=', $updateData['start_time'])
                          ->where('end_time', '<=', $updateData['end_time']);
                    });
                })
                ->where('is_combined', false)
                ->first();

            if ($teacherClash) {
                throw ValidationException::withMessages([
                    'teacher_id' => ['This teacher already has a class scheduled at this time.'],
                ]);
            }
        }

        $classClash = TenantDB::table('timetables')
            ->where('class_id', $updateData['class_id'])
            ->where('day_of_week', $updateData['day_of_week'])
            ->where('id', '!=', $id)
            ->where(function ($query) use ($updateData) {
                $query->where(function ($q) use ($updateData) {
                    $q->where('start_time', '<=', $updateData['start_time'])
                      ->where('end_time', '>', $updateData['start_time']);
                })->orWhere(function ($q) use ($updateData) {
                    $q->where('start_time', '<', $updateData['end_time'])
                      ->where('end_time', '>=', $updateData['end_time']);
                })->orWhere(function ($q) use ($updateData) {
                    $q->where('start_time', '>=', $updateData['start_time'])
                      ->where('end_time', '<=', $updateData['end_time']);
                });
            })
            ->first();

        if ($classClash) {
            throw ValidationException::withMessages([
                'class_id' => ['This class already has a subject scheduled at this time.'],
            ]);
        }

        TenantDB::table('timetables')
            ->where('id', $id)
            ->update([
                'class_id' => $updateData['class_id'],
                'subject_id' => $updateData['subject_id'],
                'teacher_id' => $updateData['teacher_id'],
                'day_of_week' => $updateData['day_of_week'],
                'start_time' => $updateData['start_time'],
                'end_time' => $updateData['end_time'],
                'venue' => $updateData['venue'] ?? null,
                'is_combined' => $updateData['is_combined'] ?? false,
                'combined_class_ids' => isset($updateData['combined_class_ids']) ? json_encode($updateData['combined_class_ids']) : $timetable->combined_class_ids,
                'notes' => $updateData['notes'] ?? null,
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Timetable updated successfully.']);
    }

    public function destroy(int $id)
    {
        TenantContext::id();
        $timetable = TenantDB::table('timetables')->where('id', $id)->first();
        if (!$timetable) {
            return response()->json(['message' => 'Timetable not found.'], 404);
        }

        TenantDB::table('timetables')->where('id', $id)->delete();

        return response()->json(['message' => 'Timetable deleted successfully.']);
    }
}

