<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Exam Answer Slip</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { margin-bottom: 14px; }
        .meta { margin-top: 6px; font-size: 12px; color: #333; }
        .pill { display:inline-block; padding: 2px 8px; border-radius: 999px; background: #f3f4f6; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        th { background: #f9fafb; text-align: left; }
        .small { font-size: 11px; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($schoolName))
            <div style="font-size: 18px; font-weight: 800; margin-bottom: 2px;">{{ $schoolName }}</div>
        @endif
        <div style="font-size: 16px; font-weight: 700;">Exam Answer Slip</div>
        <div class="meta">
            <div><strong>Student:</strong> {{ $studentName }} <span class="small">({{ $attempt->admission_number ?? '' }})</span></div>
            <div><strong>Class:</strong> {{ $attempt->class_name }}</div>
            <div><strong>Subject:</strong> {{ $attempt->subject_name }}</div>
            <div><strong>Exam Code:</strong> <span class="pill">{{ $attempt->code }}</span></div>
            <div><strong>Type:</strong> {{ strtoupper($attempt->exam_type) }}</div>
            <div><strong>Status:</strong> {{ $attempt->status }}</div>
            <div><strong>Started:</strong> {{ $attempt->started_at }}</div>
            <div><strong>Submitted:</strong> {{ $attempt->submitted_at }}</div>
            @if(!is_null($attempt->objective_score))
                <div><strong>Objective Score:</strong> {{ $attempt->objective_score }}</div>
            @endif
            @if(!is_null($attempt->total_score))
                <div><strong>Total Score:</strong> {{ $attempt->total_score }}</div>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 70px;">Q No</th>
                <th>Answer</th>
                <th style="width: 80px;">Mark</th>
            </tr>
        </thead>
        <tbody>
        @foreach($answers as $a)
            <tr>
                <td>{{ $a->question_number }}</td>
                <td>
                    @if(!empty($a->objective_choice))
                        <strong>{{ $a->objective_choice }}</strong>
                    @endif
                    @if(!empty($a->answer_text))
                        <div style="white-space: pre-wrap;">{{ $a->answer_text }}</div>
                    @endif
                </td>
                <td>{{ $a->mark }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>


