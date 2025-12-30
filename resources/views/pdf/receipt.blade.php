<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>Receipt</title>
    <style>
      body { font-family: Arial, sans-serif; color: #111827; }
      .header { text-align: center; margin-bottom: 18px; }
      .box { border: 1px solid #e5e7eb; padding: 12px; border-radius: 6px; }
      table { width: 100%; border-collapse: collapse; margin-top: 12px; }
      td { padding: 6px 0; }
      .muted { color: #6b7280; font-size: 12px; }
    </style>
  </head>
  <body>
    <div class="header">
      <h2>{{ $school->name }} - Payment Receipt</h2>
      <div class="muted">{{ $school->subdomain }}.termresult.com</div>
    </div>

    <div class="box">
      <table>
        <tr><td><strong>Receipt No:</strong></td><td>{{ $payment->receipt_number }}</td></tr>
        <tr><td><strong>Reference:</strong></td><td>{{ $payment->reference }}</td></tr>
        <tr><td><strong>Status:</strong></td><td>{{ strtoupper($payment->status) }}</td></tr>
        <tr><td><strong>Paid At:</strong></td><td>{{ $payment->paid_at }}</td></tr>
        <tr><td><strong>Item:</strong></td><td>{{ $payment->label }}</td></tr>
        <tr><td><strong>Amount:</strong></td><td>{{ number_format($payment->amount_kobo / 100, 2) }} {{ $payment->currency }}</td></tr>
      </table>
    </div>

    <div class="box" style="margin-top: 12px;">
      <h3 style="margin: 0 0 8px 0;">Student</h3>
      <div><strong>Name:</strong> {{ $profile->last_name ?? '' }} {{ $profile->first_name ?? '' }}</div>
      <div><strong>Admission No:</strong> {{ $student->admission_number }}</div>
      <div><strong>Class:</strong> {{ $class->name ?? '-' }}</div>
    </div>

    <p class="muted" style="margin-top: 16px;">
      This receipt is generated electronically.
    </p>
  </body>
</html>


