<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Decline School</title>
  </head>
  <body style="font-family: Arial, sans-serif; max-width: 720px; margin: 40px auto; padding: 0 16px;">
    <h2>Decline School Registration</h2>
    <p><strong>School:</strong> {{ $school->name }} ({{ $school->subdomain }}.termresult.com)</p>

    @if ($errors->any())
      <div style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:10px; border-radius:6px; margin: 12px 0;">
        {{ $errors->first() }}
      </div>
    @endif

    <form method="POST" action="{{ route('platform.approvals.decline', ['token' => $token, 'signature' => request('signature'), 'expires' => request('expires')]) }}">
      @csrf
      <label for="reason" style="display:block; margin-bottom:8px;">Reason</label>
      <textarea id="reason" name="reason" rows="6" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px;" required></textarea>
      <button type="submit" style="margin-top:12px; background:#dc2626; color:#fff; border:none; padding:10px 14px; border-radius:6px; cursor:pointer;">
        Submit Decline Reason
      </button>
    </form>
  </body>
</html>


