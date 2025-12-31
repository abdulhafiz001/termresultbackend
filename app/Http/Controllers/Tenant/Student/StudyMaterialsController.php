<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudyMaterialsController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        $student = $request->user();

        $data = $request->validate([
            'subject_id' => ['required', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'academic_session_id' => ['nullable', 'integer'],
            'term_id' => ['nullable', 'integer'],
        ]);

        $studentClassId = (int) (TenantDB::table('student_profiles')->where('user_id', $student->id)->value('current_class_id') ?? 0);
        if (! $studentClassId) {
            return response()->json(['data' => []]);
        }

        // Default to current session/term when no filter provided.
        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        $sessionId = isset($data['academic_session_id']) ? (int) $data['academic_session_id'] : (int) ($currentSession?->id ?? 0);
        $termId = isset($data['term_id']) ? (int) $data['term_id'] : (int) ($currentTerm?->id ?? 0);

        $items = DB::table('study_materials as m')
            ->where('m.tenant_id', $tenantId)
            ->where('m.class_id', $studentClassId)
            ->where('m.subject_id', $data['subject_id'])
            ->when($sessionId > 0, fn ($q) => $q->where('m.academic_session_id', $sessionId))
            ->when($termId > 0, fn ($q) => $q->where('m.term_id', $termId))
            ->leftJoin('users as u', function ($j) {
                $j->on('u.id', '=', 'm.uploaded_by')->on('u.tenant_id', '=', 'm.tenant_id');
            })
            ->orderByDesc('m.id')
            ->get([
                'm.id',
                'm.title',
                'm.description',
                'm.file_original_name',
                'm.file_mime',
                'm.file_size',
                'm.created_at',
                'm.academic_session_id',
                'm.term_id',
                'u.name as teacher_name',
            ])
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'title' => $m->title,
                'description' => $m->description,
                'teacher_name' => $m->teacher_name,
                'created_at' => $m->created_at,
                'academic_session_id' => $m->academic_session_id,
                'term_id' => $m->term_id,
                'file' => [
                    'name' => $m->file_original_name,
                    'mime' => $m->file_mime,
                    'size' => $m->file_size,
                ],
                'download_url' => url("/api/tenant/student/study-materials/{$m->id}/download"),
            ]);

        return response()->json([
            'meta' => [
                'current_session_id' => $currentSession?->id,
                'current_term_id' => $currentTerm?->id,
                'filtered_session_id' => $sessionId ?: null,
                'filtered_term_id' => $termId ?: null,
            ],
            'data' => $items,
        ]);
    }

    public function download(Request $request, int $id)
    {
        $tenantId = TenantContext::id();
        $student = $request->user();

        $studentClassId = (int) (TenantDB::table('student_profiles')->where('user_id', $student->id)->value('current_class_id') ?? 0);
        if (! $studentClassId) return response()->json(['message' => 'Not eligible.'], 403);

        $m = TenantDB::table('study_materials')
            ->where('id', $id)
            ->where('class_id', $studentClassId)
            ->first();

        if (! $m) return response()->json(['message' => 'Material not found.'], 404);

        $disk = Storage::disk('public');
        $filePath = (string) ($m->file_path ?? '');
        if ($filePath === '') {
            return response()->json(['message' => 'File not available.'], 404);
        }

        if (! $disk->exists($filePath)) {
            $wasDeletedByBackup = DB::table('file_deletions')
                ->where('tenant_id', (string) $tenantId)
                ->where('file_path', $filePath)
                ->exists();

            if ($wasDeletedByBackup) {
                return response()->json([
                    'code' => 'FILE_ARCHIVED',
                    'message' => 'This file was archived/deleted during school backup & cleanup. Please meet the school admin for the backup copy.',
                ], 410);
            }

            return response()->json(['message' => 'File not found.'], 404);
        }

        $path = $disk->path($filePath);
        $name = $m->file_original_name ?: basename((string) $m->file_path);

        return response()->download($path, $name);
    }
}


