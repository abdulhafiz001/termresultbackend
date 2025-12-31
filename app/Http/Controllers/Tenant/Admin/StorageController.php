<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class StorageController extends Controller
{
    private function tenantDir(string $tenantId): string
    {
        return "tenants/{$tenantId}";
    }

    /**
     * @return array<int,string>
     */
    private function filePathsForSessionTerm(string $tenantId, int $sessionId, int $termId): array
    {
        $paths = [];

        // Study materials
        $materials = TenantDB::table('study_materials')
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->pluck('file_path')
            ->all();
        $paths = array_merge($paths, array_filter($materials));

        // Exam question submissions (papers + optional sources)
        $examPapers = TenantDB::table('exam_question_submissions')
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->pluck('paper_pdf_path')
            ->all();
        $paths = array_merge($paths, array_filter($examPapers));

        $examSources = TenantDB::table('exam_question_submissions')
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->pluck('source_file_path')
            ->all();
        $paths = array_merge($paths, array_filter($examSources));

        // Assignments: teacher-uploaded question images
        if (\Illuminate\Support\Facades\Schema::hasColumn('assignments', 'image_path')) {
            $assignmentImages = TenantDB::table('assignments')
                ->where('academic_session_id', $sessionId)
                ->where('term_id', $termId)
                ->pluck('image_path')
                ->all();
            $paths = array_merge($paths, array_filter($assignmentImages));
        }

        // Assignment submissions: student-uploaded files (if used)
        $submissionFiles = TenantDB::table('assignment_submissions as sub')
            ->join('assignments as a', function ($j) {
                $j->on('a.id', '=', 'sub.assignment_id')
                    ->on('a.tenant_id', '=', 'sub.tenant_id');
            })
            ->where('a.academic_session_id', $sessionId)
            ->where('a.term_id', $termId)
            ->pluck('sub.file_path')
            ->all();
        $paths = array_merge($paths, array_filter($submissionFiles));

        // Safety: only delete tenant-scoped paths
        $prefix = $this->tenantDir($tenantId) . '/';
        $paths = array_values(array_unique(array_filter(array_map(function ($p) use ($prefix) {
            $p = trim((string) $p);
            if ($p === '') return null;
            // Normalize backslashes (windows dev) to slashes
            $p = str_replace('\\', '/', $p);
            if (! str_starts_with($p, $prefix)) return null;
            return $p;
        }, $paths))));

        return $paths;
    }

    private function bytesToHuman(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        $kb = $bytes / 1024;
        if ($kb < 1024) return number_format($kb, 1) . ' KB';
        $mb = $kb / 1024;
        if ($mb < 1024) return number_format($mb, 1) . ' MB';
        $gb = $mb / 1024;
        return number_format($gb, 2) . ' GB';
    }

    private function computeTenantUsageBytes(string $tenantId): int
    {
        $disk = Storage::disk('public');
        $base = $disk->path($this->tenantDir($tenantId));

        if (! is_dir($base)) return 0;

        $total = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            if ($file->isFile()) {
                $total += (int) $file->getSize();
            }
        }

        return $total;
    }

    public function usage(Request $request)
    {
        $tenantId = TenantContext::id();
        $school = app('tenant.school');

        // Central value (defaults to 200MB if not set)
        $quotaMb = (int) ($school->storage_quota_mb ?? 200);
        $quotaBytes = $quotaMb * 1024 * 1024;

        $usedBytes = $this->computeTenantUsageBytes($tenantId);
        $percent = $quotaBytes > 0 ? (int) floor(($usedBytes / $quotaBytes) * 100) : 0;
        $percent = max(0, min(100, $percent));

        $status = 'green';
        if ($percent >= 90) $status = 'red';
        elseif ($percent >= 70) $status = 'yellow';

        $warnings = [
            'warn80' => $percent >= 80,
            'warn95' => $percent >= 95,
        ];

        return response()->json([
            'data' => [
                'used_bytes' => $usedBytes,
                'quota_bytes' => $quotaBytes,
                'used_human' => $this->bytesToHuman($usedBytes),
                'quota_human' => $this->bytesToHuman($quotaBytes),
                'percent' => $percent,
                'status' => $status, // green|yellow|red
                'warnings' => $warnings,
            ],
        ]);
    }

    public function backupInit(Request $request)
    {
        $tenantId = TenantContext::id();
        $school = app('tenant.school');

        $quotaMb = (int) ($school->storage_quota_mb ?? 200);
        $quotaBytes = $quotaMb * 1024 * 1024;

        $usedBytes = $this->computeTenantUsageBytes($tenantId);
        if ($usedBytes <= 0) {
            return response()->json(['message' => 'No files to back up.'], 422);
        }
        if ($quotaBytes > 0 && $usedBytes > $quotaBytes) {
            return response()->json(['message' => 'Storage usage exceeds quota. Please contact support.'], 422);
        }

        // Create temp zip on disk (shared hosting friendly)
        $token = Str::random(32);
        $tmpDir = storage_path("app/tmp/tenants/{$tenantId}");
        if (! is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

        $slug = Str::slug((string) ($school->name ?? 'school')) ?: 'school';
        $filename = "{$slug}-backup-{$tenantId}-" . now()->format('Y-m-d_His') . ".zip";
        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Could not create ZIP file.'], 500);
        }

        $disk = Storage::disk('public');
        $prefix = $this->tenantDir($tenantId);
        $base = $disk->path($prefix);

        // Add files with relative paths inside zip
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            if (! $file->isFile()) continue;
            $abs = $file->getPathname();
            $rel = ltrim(str_replace($base, '', $abs), DIRECTORY_SEPARATOR);
            $zip->addFile($abs, $prefix . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $rel));
        }

        $zip->close();

        if (! file_exists($zipPath) || filesize($zipPath) <= 0) {
            @unlink($zipPath);
            return response()->json(['message' => 'ZIP creation failed.'], 500);
        }

        // Store in cache for later download + cleanup authorization
        Cache::put("tenant_storage_backup:{$tenantId}:{$token}", [
            'zip_path' => $zipPath,
            'filename' => $filename,
            'created_at' => now()->toISOString(),
        ], now()->addMinutes(30));

        return response()->json([
            'data' => [
                'token' => $token,
                'filename' => $filename,
                'download_url' => url("/api/tenant/admin/storage/backup/{$token}/download"),
                'used_bytes' => $usedBytes,
            ],
            'message' => 'Backup ZIP created. Please download it before cleaning up.',
        ]);
    }

    public function backupSessionTerm(Request $request)
    {
        $tenantId = TenantContext::id();
        $school = app('tenant.school');
        $data = $request->validate([
            'academic_session_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
        ]);

        $quotaMb = (int) ($school->storage_quota_mb ?? 200);
        $quotaBytes = $quotaMb * 1024 * 1024;

        // Block current session/term (this is intended for past cleanup)
        $isCurrent = TenantDB::table('academic_sessions')
            ->where('id', (int) $data['academic_session_id'])
            ->where('is_current', true)
            ->exists();
        $isCurrentTerm = TenantDB::table('terms')
            ->where('id', (int) $data['term_id'])
            ->where('is_current', true)
            ->exists();
        if ($isCurrent || $isCurrentTerm) {
            return response()->json(['message' => 'You cannot clean up the current academic session/term. Select a past term.'], 422);
        }

        $paths = $this->filePathsForSessionTerm($tenantId, (int) $data['academic_session_id'], (int) $data['term_id']);
        if (count($paths) === 0) {
            return response()->json(['message' => 'No files found for the selected session/term.'], 422);
        }

        $token = Str::random(32);
        $tmpDir = storage_path("app/tmp/tenants/{$tenantId}");
        if (! is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

        $slug = Str::slug((string) ($school->name ?? 'school')) ?: 'school';
        $filename = "{$slug}-backup-{$tenantId}-session{$data['academic_session_id']}-term{$data['term_id']}-" . now()->format('Y-m-d_His') . ".zip";
        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Could not create ZIP file.'], 500);
        }

        $disk = Storage::disk('public');
        foreach ($paths as $p) {
            try {
                $abs = $disk->path($p);
                if (is_file($abs)) {
                    $zip->addFile($abs, $p);
                }
            } catch (\Throwable $e) {
                // Ignore missing files
            }
        }
        $zip->close();

        if (! file_exists($zipPath) || filesize($zipPath) <= 0) {
            @unlink($zipPath);
            return response()->json(['message' => 'ZIP creation failed.'], 500);
        }

        // Safety: do not generate ZIPs larger than the school's quota (shared-hosting friendly)
        if ($quotaBytes > 0 && filesize($zipPath) > $quotaBytes) {
            @unlink($zipPath);
            return response()->json([
                'message' => "Backup ZIP is too large for your quota ({$quotaMb} MB). Please choose a smaller/older term or clean up in parts.",
            ], 422);
        }

        Cache::put("tenant_storage_backup_scope:{$tenantId}:{$token}", [
            'zip_path' => $zipPath,
            'filename' => $filename,
            'paths' => $paths,
            'scope' => [
                'academic_session_id' => (int) $data['academic_session_id'],
                'term_id' => (int) $data['term_id'],
            ],
            'created_at' => now()->toISOString(),
        ], now()->addMinutes(30));

        return response()->json([
            'data' => [
                'token' => $token,
                'filename' => $filename,
                'download_url' => url("/api/tenant/admin/storage/backup-scope/{$token}/download"),
                'file_count' => count($paths),
            ],
            'message' => 'Backup ZIP created for the selected session/term. Download it before cleanup.',
        ]);
    }

    public function backupScopeDownload(Request $request, string $token)
    {
        $tenantId = TenantContext::id();
        $payload = Cache::get("tenant_storage_backup_scope:{$tenantId}:{$token}");
        if (! $payload || empty($payload['zip_path']) || ! file_exists($payload['zip_path'])) {
            return response()->json(['message' => 'Backup not found or expired. Please create a new backup.'], 404);
        }

        return response()->download($payload['zip_path'], $payload['filename'], [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function backupDownload(Request $request, string $token)
    {
        $tenantId = TenantContext::id();
        $payload = Cache::get("tenant_storage_backup:{$tenantId}:{$token}");
        if (! $payload || empty($payload['zip_path']) || ! file_exists($payload['zip_path'])) {
            return response()->json(['message' => 'Backup not found or expired. Please create a new backup.'], 404);
        }

        return response()->download($payload['zip_path'], $payload['filename'], [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function cleanupScope(Request $request)
    {
        $tenantId = TenantContext::id();
        $adminId = $request->user()?->id;
        $data = $request->validate([
            'token' => ['required', 'string', 'size:32'],
        ]);

        $payload = Cache::get("tenant_storage_backup_scope:{$tenantId}:{$data['token']}");
        if (! $payload) {
            return response()->json(['message' => 'Backup token not found or expired. Please backup again before cleanup.'], 422);
        }

        $paths = $payload['paths'] ?? [];
        if (! is_array($paths) || count($paths) === 0) {
            return response()->json(['message' => 'No file list found for this backup.'], 422);
        }

        $disk = Storage::disk('public');
        // Delete only the files in the scope (leave DB records intact)
        $existingPaths = [];
        foreach ($paths as $p) {
            try {
                if ($disk->exists($p)) {
                    $existingPaths[] = $p;
                }
                $disk->delete($p); // safe even if missing
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Track deletions so we can show a friendly message only when file existed and was deleted via cleanup.
        if (! empty($existingPaths)) {
            $scope = $payload['scope'] ?? [];
            $sessionId = isset($scope['academic_session_id']) ? (int) $scope['academic_session_id'] : null;
            $termId = isset($scope['term_id']) ? (int) $scope['term_id'] : null;

            $rows = array_map(fn ($p) => [
                'tenant_id' => (string) $tenantId,
                'file_path' => (string) $p,
                'deleted_at' => now(),
                'deleted_by' => $adminId ? (int) $adminId : null,
                'academic_session_id' => $sessionId,
                'term_id' => $termId,
                'reason' => 'backup_cleanup',
                'created_at' => now(),
                'updated_at' => now(),
            ], $existingPaths);

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('file_deletions')->upsert(
                    $chunk,
                    ['tenant_id', 'file_path'],
                    ['deleted_at', 'deleted_by', 'academic_session_id', 'term_id', 'reason', 'updated_at']
                );
            }
        }

        if (! empty($payload['zip_path']) && file_exists($payload['zip_path'])) {
            @unlink($payload['zip_path']);
        }

        Cache::forget("tenant_storage_backup_scope:{$tenantId}:{$data['token']}");

        return response()->json(['message' => 'Cleanup completed for the selected session/term.']);
    }

    public function cleanup(Request $request)
    {
        $tenantId = TenantContext::id();
        $adminId = $request->user()?->id;
        $data = $request->validate([
            'token' => ['required', 'string', 'size:32'],
        ]);

        $payload = Cache::get("tenant_storage_backup:{$tenantId}:{$data['token']}");
        if (! $payload) {
            return response()->json(['message' => 'Backup token not found or expired. Please backup again before cleanup.'], 422);
        }

        $disk = Storage::disk('public');
        $prefix = $this->tenantDir($tenantId);

        // Track deletions before deleting (only what exists right now)
        try {
            $existing = $disk->allFiles($prefix);
            if (! empty($existing)) {
                $rows = array_map(fn ($p) => [
                    'tenant_id' => (string) $tenantId,
                    'file_path' => (string) str_replace('\\', '/', $p),
                    'deleted_at' => now(),
                    'deleted_by' => $adminId ? (int) $adminId : null,
                    'academic_session_id' => null,
                    'term_id' => null,
                    'reason' => 'backup_cleanup',
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $existing);

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('file_deletions')->upsert(
                        $chunk,
                        ['tenant_id', 'file_path'],
                        ['deleted_at', 'deleted_by', 'reason', 'updated_at']
                    );
                }
            }
        } catch (\Throwable $e) {
            // ignore tracking failures; cleanup must still proceed
        }

        // Delete tenant files
        $disk->deleteDirectory($prefix);

        // Cleanup zip file
        if (! empty($payload['zip_path']) && file_exists($payload['zip_path'])) {
            @unlink($payload['zip_path']);
        }

        Cache::forget("tenant_storage_backup:{$tenantId}:{$data['token']}");

        return response()->json(['message' => 'Cleanup completed.']);
    }
}


