<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment received</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;">
  <div style="max-width:680px;margin:0 auto;padding:18px;">
    <div style="background:#111827;color:#fff;border-radius:14px;padding:18px 18px;">
      <div style="font-size:14px;opacity:.9;">TermResult</div>
      <div style="font-size:20px;font-weight:700;margin-top:4px;">Payment received</div>
      <div style="font-size:13px;opacity:.9;margin-top:6px;">
        {{ $school->name ?? 'School' }}
      </div>
    </div>

    <div style="background:#fff;border-radius:14px;padding:18px;margin-top:14px;border:1px solid #eef0f5;">
      <div style="font-size:14px;color:#111827;line-height:1.6;">
        A payment has been recorded successfully for your school.
        <br>
        <strong>Note:</strong> This email shows the <strong>fee amount received by the school</strong>. TermResult service charges (if any) are excluded.
      </div>

      <div style="margin-top:14px;border-radius:12px;border:1px solid #eef0f5;overflow:hidden;">
        <table cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
          <tr style="background:#f9fafb;">
            <td style="padding:12px 14px;font-size:13px;color:#6b7280;">Reference</td>
            <td style="padding:12px 14px;font-size:13px;color:#111827;font-weight:700;">{{ $payment->reference ?? '-' }}</td>
          </tr>
          <tr>
            <td style="padding:12px 14px;font-size:13px;color:#6b7280;">Receipt</td>
            <td style="padding:12px 14px;font-size:13px;color:#111827;">{{ $payment->receipt_number ?? '-' }}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:12px 14px;font-size:13px;color:#6b7280;">Fee amount (school)</td>
            <td style="padding:12px 14px;font-size:13px;color:#111827;font-weight:700;">
              {{ number_format(((int) ($payment->amount_kobo ?? 0)) / 100, 0) }} {{ $payment->currency ?? 'NGN' }}
            </td>
          </tr>
          <tr>
            <td style="padding:12px 14px;font-size:13px;color:#6b7280;">Label</td>
            <td style="padding:12px 14px;font-size:13px;color:#111827;">{{ $payment->label ?? 'School Fees' }}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:12px 14px;font-size:13px;color:#6b7280;">Method</td>
            <td style="padding:12px 14px;font-size:13px;color:#111827;">{{ strtoupper((string) ($payment->method ?? '')) }}</td>
          </tr>
          <tr>
            <td style="padding:12px 14px;font-size:13px;color:#6b7280;">Paid at</td>
            <td style="padding:12px 14px;font-size:13px;color:#111827;">
              {{ !empty($payment->paid_at) ? \Carbon\Carbon::parse($payment->paid_at)->format('D, M j, Y g:i A') : '-' }}
            </td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:12px 14px;font-size:13px;color:#6b7280;">Student</td>
            <td style="padding:12px 14px;font-size:13px;color:#111827;">
              @php
                $studentName = null;
                if (!empty($profile)) {
                  $studentName = trim(($profile->first_name ?? '').' '.($profile->middle_name ?? '').' '.($profile->last_name ?? ''));
                }
                if (!$studentName && !empty($student)) {
                  $studentName = $student->name ?? null;
                }
              @endphp
              {{ $studentName ?: '-' }}
              @if (!empty($student) && !empty($student->admission_number))
                <span style="color:#6b7280;">(Adm: {{ $student->admission_number }})</span>
              @endif
            </td>
          </tr>
          <tr>
            <td style="padding:12px 14px;font-size:13px;color:#6b7280;">Class</td>
            <td style="padding:12px 14px;font-size:13px;color:#111827;">{{ $class->name ?? '-' }}</td>
          </tr>
        </table>
      </div>

      <div style="margin-top:16px;font-size:12px;color:#6b7280;line-height:1.6;">
        If you did not expect this payment, please contact TermResult support.
      </div>
    </div>

    <div style="text-align:center;margin-top:14px;color:#9ca3af;font-size:12px;">
      © {{ date('Y') }} TermResult. All rights reserved.
    </div>
  </div>
</body>
</html>


