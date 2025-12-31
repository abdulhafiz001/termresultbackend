<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SchoolPaymentReceived;
use App\Support\TenantCache;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PaymentsController extends Controller
{
    private function notifySchoolIfNeeded(string $reference): void
    {
        $school = app('tenant.school');
        if (! $school || empty($school->contact_email)) return;
        if (! \Schema::hasColumn('payments', 'school_notified_at')) return;

        $payment = TenantDB::table('payments')->where('reference', $reference)->first();
        if (! $payment || ($payment->status ?? null) !== 'success') return;
        if (! empty($payment->school_notified_at)) return;

        try {
            $student = TenantDB::table('users')->where('id', (int) $payment->student_id)->first();
            $profile = TenantDB::table('student_profiles')->where('user_id', (int) $payment->student_id)->first();
            $class = $payment->class_id ? TenantDB::table('classes')->where('id', (int) $payment->class_id)->first() : null;

            Mail::to((string) $school->contact_email)->queue(new SchoolPaymentReceived($school, $payment, $student, $profile, $class));

            TenantDB::table('payments')->where('id', (int) $payment->id)->update([
                'school_notified_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to send school payment notification email (manual record).', [
                'reference' => $reference,
                'school_id' => $school?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function index(Request $request)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'academic_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = DB::table('payments as p')
            ->where('p.tenant_id', $tenantId)
            ->leftJoin('users as u', function ($j) {
                $j->on('u.id', '=', 'p.student_id')
                    ->on('u.tenant_id', '=', 'p.tenant_id');
            })
            ->leftJoin('student_profiles as sp', function ($j) {
                $j->on('sp.user_id', '=', 'u.id')
                    ->on('sp.tenant_id', '=', 'p.tenant_id');
            })
            ->leftJoin('classes as c', function ($j) {
                $j->on('c.id', '=', 'p.class_id')
                    ->on('c.tenant_id', '=', 'p.tenant_id');
            })
            ->leftJoin('fee_rules as fr', function ($j) {
                $j->on('fr.id', '=', 'p.fee_rule_id')
                    ->on('fr.tenant_id', '=', 'p.tenant_id');
            })
            ->where('p.status', 'success')
            ->select([
                'p.id',
                'p.reference',
                'p.amount_kobo',
                'p.currency',
                'p.label',
                'p.method',
                'p.provider',
                'p.paid_at',
                'p.receipt_number',
                'p.academic_session_id',
                'p.class_id',
                'c.name as class_name',
                'p.student_id',
                'u.admission_number',
                'sp.first_name',
                'sp.last_name',
                'fr.id as fee_rule_id',
                'fr.label as fee_rule_label',
            ])
            ->orderByDesc('p.paid_at')
            ->orderByDesc('p.id');

        if (! empty($data['academic_session_id'])) {
            $query->where('p.academic_session_id', (int) $data['academic_session_id']);
        }
        if (! empty($data['term_id']) && \Schema::hasColumn('payments', 'term_id')) {
            $query->where('p.term_id', (int) $data['term_id']);
        }
        if (! empty($data['class_id'])) {
            $query->where('p.class_id', (int) $data['class_id']);
        }
        if (! empty($data['q'])) {
            $q = '%'.strtolower(trim((string) $data['q'])).'%';
            $query->where(function ($w) use ($q) {
                $w->whereRaw('lower(sp.first_name) like ?', [$q])
                    ->orWhereRaw('lower(sp.last_name) like ?', [$q])
                    ->orWhereRaw('lower(u.admission_number) like ?', [$q])
                    ->orWhereRaw('lower(p.reference) like ?', [$q])
                    ->orWhereRaw('lower(p.receipt_number) like ?', [$q]);
            });
        }

        return response()->json([
            'data' => $query->limit(500)->get(),
        ]);
    }

    public function stats(Request $request)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'academic_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
        ]);

        $query = TenantDB::table('payments')->where('status', 'success');
        if (! empty($data['academic_session_id'])) {
            $query->where('academic_session_id', (int) $data['academic_session_id']);
        }
        if (! empty($data['term_id']) && \Schema::hasColumn('payments', 'term_id')) {
            $query->where('term_id', (int) $data['term_id']);
        }
        if (! empty($data['class_id'])) {
            $query->where('class_id', (int) $data['class_id']);
        }

        $totalKobo = (int) $query->sum('amount_kobo');
        $count = (int) $query->count();
        $byMethod = DB::table('payments')
            ->where('tenant_id', $tenantId)
            ->select(['method', DB::raw('count(*) as cnt'), DB::raw('sum(amount_kobo) as total_kobo')])
            ->where('status', 'success')
            ->when(! empty($data['academic_session_id']), fn ($q) => $q->where('academic_session_id', (int) $data['academic_session_id']))
            ->when(! empty($data['term_id']) && \Schema::hasColumn('payments', 'term_id'), fn ($q) => $q->where('term_id', (int) $data['term_id']))
            ->when(! empty($data['class_id']), fn ($q) => $q->where('class_id', (int) $data['class_id']))
            ->groupBy('method')
            ->get();

        return response()->json([
            'data' => [
                'total_kobo' => $totalKobo,
                'count' => $count,
                'by_method' => $byMethod,
            ],
        ]);
    }

    public function recordManual(Request $request)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'fee_rule_id' => ['nullable', 'integer', 'exists:fee_rules,id'],
            'academic_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'amount_naira' => ['required', 'numeric', 'min:1'],
            'reference' => ['required', 'string', 'max:100'],
        ]);

        $student = TenantDB::table('users')->where('id', (int) $data['student_id'])->where('role', 'student')->first();
        if (! $student) return response()->json(['message' => 'Student not found.'], 404);

        $profile = TenantDB::table('student_profiles')->where('user_id', (int) $student->id)->first();
        if (! $profile || (int) ($profile->current_class_id ?? 0) !== (int) $data['class_id']) {
            return response()->json(['message' => 'Student is not in the selected class.'], 422);
        }

        $rule = null;
        if (! empty($data['fee_rule_id'])) {
            $rule = TenantDB::table('fee_rules')->where('id', (int) $data['fee_rule_id'])->where('class_id', (int) $data['class_id'])->first();
            if (! $rule) return response()->json(['message' => 'Fee rule not found for this class.'], 404);
        } else {
            $rule = TenantDB::table('fee_rules')->where('class_id', (int) $data['class_id'])->orderBy('id')->first();
        }

        if (! $rule) {
            return response()->json(['message' => 'No fee rule exists for this class.'], 422);
        }

        $amountKobo = (int) round(((float) $data['amount_naira']) * 100);

        // Prevent overpayment
        $alreadyPaid = (int) TenantDB::table('payments')
            ->where('student_id', (int) $student->id)
            ->where('fee_rule_id', (int) $rule->id)
            ->where('status', 'success')
            ->sum('amount_kobo');
        $remaining = max(0, (int) $rule->amount_kobo - $alreadyPaid);
        if ($remaining <= 0) {
            return response()->json(['message' => 'This fee is already fully paid.'], 422);
        }
        if ($amountKobo > $remaining) {
            return response()->json(['message' => 'Amount exceeds remaining balance.'], 422);
        }

        $ref = trim((string) $data['reference']);
        if (TenantDB::table('payments')->where('reference', $ref)->exists()) {
            return response()->json(['message' => 'Reference already exists.'], 422);
        }

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $sessionId = $data['academic_session_id'] ?? ($currentSession?->id ?: null);
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;
        $termId = $data['term_id'] ?? ($currentTerm?->id ?: null);
        $receipt = 'MAN-'.Str::upper(Str::random(6)).'-'.time();

        $id = DB::table('payments')->insertGetId([
            'tenant_id' => $tenantId,
            'student_id' => (int) $student->id,
            'class_id' => (int) $data['class_id'],
            'fee_rule_id' => (int) $rule->id,
            'academic_session_id' => $sessionId ? (int) $sessionId : null,
            'term_id' => $termId ? (int) $termId : null,
            'amount_kobo' => $amountKobo,
            'currency' => (string) ($rule->currency ?? 'NGN'),
            'label' => (string) ($rule->label ?? 'School Fees'),
            'reference' => $ref,
            'status' => 'success',
            'provider' => 'manual',
            'method' => 'manual',
            'recorded_by_user_id' => (int) $request->user()->id,
            'provider_transaction_id' => null,
            'provider_payload' => null,
            'paid_at' => now(),
            'receipt_number' => $receipt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Bust student fee cache for this session
        $school = app('tenant.school');
        $studentId = (int) $student->id;
        $sessionKey = (int) ($sessionId ?? 0);
        if ($school && $sessionKey) {
            TenantCache::forgetStudentFees($school, $studentId, $sessionKey);
        }

        // Notify school (fee amount only), once per reference
        $this->notifySchoolIfNeeded($ref);

        return response()->json(['data' => ['id' => $id], 'message' => 'Manual payment recorded.'], 201);
    }
}


