<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudyMaterialsController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        $q = DB::table('study_materials as m')
            ->where('m.tenant_id', $tenantId)
            ->where('m.uploaded_by', $teacherId)
            ->leftJoin('classes as c', function ($j) {
                $j->on('c.id', '=', 'm.class_id')->on('c.tenant_id', '=', 'm.tenant_id');
            })
            ->leftJoin('subjects as s', function ($j) {
                $j->on('s.id', '=', 'm.subject_id')->on('s.tenant_id', '=', 'm.tenant_id');
            })
            ->orderByDesc('m.id');

        // Default to current session/term if available (keeps list focused)
        if ($currentSession && $currentTerm) {
            $q->where('m.academic_session_id', $currentSession->id)
                ->where('m.term_id', $currentTerm->id);
        }

        $items = $q->get([
            'm.id',
            'm.title',
            'm.description',
            'm.file_path',
            'm.file_original_name',
            'm.file_mime',
            'm.file_size',
            'm.class_id',
            'm.subject_id',
            'm.academic_session_id',
            'm.term_id',
            'm.created_at',
            'c.name as class_name',
            's.name as subject_name',
            's.code as subject_code',
        ])->map(fn ($m) => [
            'id' => (int) $m->id,
            'title' => $m->title,
            'description' => $m->description,
            'class' => [
                'id' => $m->class_id ? (int) $m->class_id : null,
                'name' => $m->class_name,
            ],
            'subject' => [
                'id' => $m->subject_id ? (int) $m->subject_id : null,
                'name' => $m->subject_name,
                'code' => $m->subject_code,
            ],
            'file' => [
                'path' => $m->file_path,
                'name' => $m->file_original_name ?: basename((string) $m->file_path),
                'mime' => $m->file_mime,
                'size' => $m->file_size,
            ],
            'created_at' => $m->created_at,
            'download_url' => url("/api/tenant/teacher/study-materials/{$m->id}/download"),
        ]);

        return response()->json([
            'meta' => [
                'academic_session_id' => $currentSession?->id,
                'term_id' => $currentTerm?->id,
            ],
            'data' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;

        $data = $request->validate([
            'class_id' => ['required', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'subject_id' => ['required', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            // Allow common study material formats; keep size safe for shared hosting.
            'file' => ['required', 'file', 'max:5120'], // 5MB
        ]);

        $teachesClass = TenantDB::table('teacher_class')
            ->where('teacher_id', $teacherId)
            ->where('class_id', $data['class_id'])
            ->exists();

        $teachesSubject = TenantDB::table('teacher_subject')
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $data['subject_id'])
            ->exists();

        if (! $teachesClass || ! $teachesSubject) {
            return response()->json(['message' => 'You are not assigned to teach this class/subject.'], 403);
        }

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $file = $request->file('file');
        $ext = $file?->getClientOriginalExtension() ?: 'bin';
        $safeName = now()->format('YmdHis') . '-' . Str::random(10) . '.' . strtolower($ext);

        $dir = "tenants/{$tenantId}/study-materials/class-{$data['class_id']}/subject-{$data['subject_id']}";
        $path = $file->storeAs($dir, $safeName, 'public');

        $id = DB::table('study_materials')->insertGetId([
            'tenant_id' => $tenantId,
            'uploaded_by' => $teacherId,
            'class_id' => $data['class_id'],
            'subject_id' => $data['subject_id'],
            'academic_session_id' => $currentSession->id,
            'term_id' => $currentTerm->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'file_original_name' => $file->getClientOriginalName(),
            'file_mime' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Study material uploaded.', 'id' => $id], 201);
    }

    public function download(Request $request, int $id)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;

        $m = TenantDB::table('study_materials')
            ->where('id', $id)
            ->where('uploaded_by', $teacherId)
            ->first();

        if (! $m) return response()->json(['message' => 'Material not found.'], 404);

        $path = Storage::disk('public')->path($m->file_path);
        $name = $m->file_original_name ?: basename((string) $m->file_path);

        return response()->download($path, $name);
    }

    public function destroy(Request $request, int $id)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;

        $m = TenantDB::table('study_materials')
            ->where('id', $id)
            ->where('uploaded_by', $teacherId)
            ->first();

        if (! $m) return response()->json(['message' => 'Material not found.'], 404);

        DB::table('study_materials')->where('tenant_id', $tenantId)->where('id', $id)->delete();
        if (! empty($m->file_path)) {
            Storage::disk('public')->delete($m->file_path);
        }

        return response()->json(['message' => 'Deleted.']);
    }
}


