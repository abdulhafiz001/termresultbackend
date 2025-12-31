<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class GradingConfigsController extends Controller
{
    public function index()
    {
        TenantContext::id();
        $configs = TenantDB::table('grading_configs')->orderByDesc('id')->get();
        $ranges = TenantDB::table('grading_config_ranges')->get()->groupBy('grading_config_id');
        // Use an explicit alias so TenantDB's where(tenant_id=...) is qualified (avoids "tenant_id is ambiguous").
        $classes = TenantDB::table('grading_config_classes as gcc', 'gcc.tenant_id')
            ->join('classes', function ($j) {
                $j->on('classes.id', '=', 'gcc.class_id')
                    ->on('classes.tenant_id', '=', 'gcc.tenant_id');
            })
            ->select(['gcc.grading_config_id', 'classes.id', 'classes.name'])
            ->get()
            ->groupBy('grading_config_id');

        $data = $configs->map(function ($c) use ($ranges, $classes) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'is_active' => (bool) $c->is_active,
                'ranges' => ($ranges[$c->id] ?? collect())
                    ->sortByDesc('min_score')
                    ->values()
                    ->map(fn ($r) => ['grade' => $r->grade, 'min_score' => (int) $r->min_score, 'max_score' => (int) $r->max_score]),
                'classes' => ($classes[$c->id] ?? collect())
                    ->values()
                    ->map(fn ($x) => ['id' => $x->id, 'name' => $x->name]),
                'created_at' => $c->created_at,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'ranges' => ['required', 'array', 'min:1'],
            'ranges.*.grade' => ['required', 'string', 'max:2'],
            'ranges.*.min_score' => ['required', 'integer', 'min:0', 'max:100'],
            'ranges.*.max_score' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $ranges = $this->normalizeRanges($data['ranges']);
        $this->validateRanges($ranges);

        return DB::transaction(function () use ($request, $data, $ranges) {
            $tenantId = TenantContext::id();
            $id = DB::table('grading_configs')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (array_values(array_unique($data['class_ids'])) as $classId) {
                DB::table('grading_config_classes')->insert([
                    'tenant_id' => $tenantId,
                    'grading_config_id' => $id,
                    'class_id' => (int) $classId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($ranges as $r) {
                DB::table('grading_config_ranges')->insert([
                    'tenant_id' => $tenantId,
                    'grading_config_id' => $id,
                    'grade' => $r['grade'],
                    'min_score' => $r['min_score'],
                    'max_score' => $r['max_score'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json(['data' => ['id' => $id]], 201);
        });
    }

    public function update(Request $request, int $id)
    {
        $tenantId = TenantContext::id();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'ranges' => ['required', 'array', 'min:1'],
            'ranges.*.grade' => ['required', 'string', 'max:2'],
            'ranges.*.min_score' => ['required', 'integer', 'min:0', 'max:100'],
            'ranges.*.max_score' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $ranges = $this->normalizeRanges($data['ranges']);
        $this->validateRanges($ranges);

        return DB::transaction(function () use ($id, $data, $ranges) {
            $tenantId = TenantContext::id();
            $exists = TenantDB::table('grading_configs')->where('id', $id)->exists();
            if (! $exists) {
                return response()->json(['message' => 'Grading config not found.'], 404);
            }

            TenantDB::table('grading_configs')->where('id', $id)->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'updated_at' => now(),
            ]);

            TenantDB::table('grading_config_classes')->where('grading_config_id', $id)->delete();
            foreach (array_values(array_unique($data['class_ids'])) as $classId) {
                DB::table('grading_config_classes')->insert([
                    'tenant_id' => $tenantId,
                    'grading_config_id' => $id,
                    'class_id' => (int) $classId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            TenantDB::table('grading_config_ranges')->where('grading_config_id', $id)->delete();
            foreach ($ranges as $r) {
                DB::table('grading_config_ranges')->insert([
                    'tenant_id' => $tenantId,
                    'grading_config_id' => $id,
                    'grade' => $r['grade'],
                    'min_score' => $r['min_score'],
                    'max_score' => $r['max_score'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json(['message' => 'Grading config updated.']);
        });
    }

    public function destroy(int $id)
    {
        TenantContext::id();
        $deleted = TenantDB::table('grading_configs')->where('id', $id)->delete();
        if (! $deleted) {
            return response()->json(['message' => 'Grading config not found.'], 404);
        }
        return response()->json(['message' => 'Grading config deleted.']);
    }

    public static function gradeForClassTotal(int $classId, ?int $total): ?string
    {
        if ($total === null) return null;

        $tenantId = TenantContext::id();

        $cfg = DB::table('grading_configs as gc')
            ->where('gc.tenant_id', $tenantId)
            ->join('grading_config_classes as gcc', function ($j) {
                $j->on('gcc.grading_config_id', '=', 'gc.id')
                    ->on('gcc.tenant_id', '=', 'gc.tenant_id');
            })
            ->where('gc.is_active', true)
            ->where('gcc.class_id', $classId)
            ->orderByDesc('gc.id')
            ->select(['gc.id'])
            ->first();

        if (! $cfg) return null;

        $range = DB::table('grading_config_ranges')
            ->where('tenant_id', $tenantId)
            ->where('grading_config_id', $cfg->id)
            ->where('min_score', '<=', $total)
            ->where('max_score', '>=', $total)
            ->orderByDesc('min_score')
            ->first();

        return $range ? (string) $range->grade : null;
    }

    private function normalizeRanges(array $ranges): array
    {
        return collect($ranges)
            ->map(function ($r) {
                return [
                    'grade' => strtoupper(trim((string) $r['grade'])),
                    'min_score' => (int) $r['min_score'],
                    'max_score' => (int) $r['max_score'],
                ];
            })
            ->values()
            ->all();
    }

    private function validateRanges(array $ranges): void
    {
        foreach ($ranges as $r) {
            if ($r['min_score'] > $r['max_score']) {
                throw new HttpResponseException(
                    response()->json(['message' => 'Invalid range: min_score cannot be greater than max_score.'], 422)
                );
            }
        }

        // Check overlaps.
        $sorted = collect($ranges)->sortBy('min_score')->values();
        for ($i = 0; $i < $sorted->count(); $i++) {
            for ($j = $i + 1; $j < $sorted->count(); $j++) {
                $a = $sorted[$i];
                $b = $sorted[$j];
                $overlap = ! ($a['max_score'] < $b['min_score'] || $b['max_score'] < $a['min_score']);
                if ($overlap) {
                    throw new HttpResponseException(
                        response()->json(['message' => 'Grade ranges overlap. Please adjust your min/max scores.'], 422)
                    );
                }
            }
        }
    }
}


