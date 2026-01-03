<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeeRulesController extends Controller
{
    public function index()
    {
        $rules = TenantDB::table('fee_rules')
            ->join('classes', function ($j) {
                $j->on('classes.id', '=', 'fee_rules.class_id')
                    ->on('classes.tenant_id', '=', 'fee_rules.tenant_id');
            })
            ->select([
                'fee_rules.*',
                'classes.name as class_name',
            ])
            ->orderBy('classes.name')
            ->get();

        return response()->json(['data' => $rules]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'class_id' => ['nullable', 'integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'class_ids' => ['nullable', 'array', 'min:1'],
            'class_ids.*' => ['integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'amount_naira' => ['required', 'numeric', 'min:0'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $classIds = [];
        if (! empty($data['class_ids'])) {
            $classIds = array_values(array_unique(array_map('intval', $data['class_ids'])));
        } elseif (! empty($data['class_id'])) {
            $classIds = [(int) $data['class_id']];
        } else {
            return response()->json(['message' => 'Please select at least one class.'], 422);
        }

        $amountKobo = (int) round(((float) $data['amount_naira']) * 100);
        $label = trim((string) $data['label']);

        $currentSessionId = TenantDB::table('academic_sessions')->where('is_current', true)->value('id');

        $createdIds = [];
        $skipped = 0;
        foreach ($classIds as $cid) {
            // Respect unique(class_id, label); skip duplicates gracefully
            $exists = TenantDB::table('fee_rules')->where('class_id', $cid)->where('label', $label)->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            $createdIds[] = DB::table('fee_rules')->insertGetId([
                'tenant_id' => $tenantId,
                'class_id' => (int) $cid,
                'academic_session_id' => $currentSessionId ?: null,
                'amount_kobo' => $amountKobo,
                'currency' => 'NGN',
                'label' => $label,
                'description' => $data['description'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (count($createdIds) === 0) {
            return response()->json(['message' => 'No fee rule was created (duplicates).'], 422);
        }

        return response()->json([
            'data' => ['ids' => $createdIds, 'skipped' => $skipped],
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'amount_naira' => ['nullable', 'numeric', 'min:0'],
            'label' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $update = ['updated_at' => now()];
        if (array_key_exists('amount_naira', $data)) {
            $update['amount_kobo'] = (int) round(((float) $data['amount_naira']) * 100);
        }
        if (array_key_exists('label', $data)) $update['label'] = $data['label'];
        if (array_key_exists('description', $data)) $update['description'] = $data['description'];

        $updated = TenantDB::table('fee_rules')->where('id', $id)->update($update);
        if (! $updated) {
            return response()->json(['message' => 'Fee rule not found.'], 404);
        }

        return response()->json(['message' => 'Fee rule updated.']);
    }

    public function destroy(int $id)
    {
        $deleted = TenantDB::table('fee_rules')->where('id', $id)->delete();
        if (! $deleted) {
            return response()->json(['message' => 'Fee rule not found.'], 404);
        }
        return response()->json(['message' => 'Fee rule deleted.']);
    }
}


