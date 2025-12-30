<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Report Card - {{ $student->admission_number }}</title>
    <style>
        @page {
            margin: 12mm;
            size: A4;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.5;
            background-color: #fff;
        }

        /* --- Refined Single Watermark --- */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            opacity: 0.04;
            z-index: 0;
            pointer-events: none;
            width: 80%;
            text-align: center;
        }
        .watermark-text {
            font-size: 80px;
            font-weight: 900;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 10px;
        }

        /* --- Layout Containers --- */
        .container {
            position: relative;
            z-index: 1;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #1f2937;
        }

        .logo-container {
            margin-bottom: 10px;
        }

        .logo, .logo-fallback {
            max-width: 70px;
            margin: 0 auto;
            display: block;
            margin-bottom: 10px;
        }

        .logo-fallback {
            width: 70px;
            height: 70px;
            background: #1f2937;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }

        .school-name {
            font-size: 22px;
            font-weight: 800;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .school-contact {
            font-size: 9px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .school-contact span {
            margin: 0 5px;
        }

        .report-title {
            display: inline-block;
            background: #1f2937;
            color: #ffffff;
            padding: 5px 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
            border-radius: 2px;
            text-transform: uppercase;
        }

        /* --- Student Info Grid --- */
        .student-info {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
            border-radius: 4px;
        }
        .info-column {
            display: table-cell;
            width: 50%;
            padding: 10px;
        }
        .info-item {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            color: #4b5563;
            width: 120px;
            display: inline-block;
        }
        .info-value {
            color: #111827;
            font-weight: 600;
        }

        .promotion-status {
            display: table-row;
            width: 100%;
        }
        .promotion-cell {
            display: table-cell;
            padding: 10px;
            border-top: 1px solid #e5e7eb;
        }
        .promotion-approved { color: #059669; }
        .promotion-graduated { color: #3b82f6; }
        .promotion-repeated { color: #dc2626; }

        /* --- Table Design --- */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #1f2937;
            color: white;
            padding: 8px 5px;
            font-size: 10px;
            text-transform: uppercase;
            border: 1px solid #1f2937;
        }
        td {
            border: 1px solid #d1d5db;
            padding: 7px 5px;
            text-align: center;
        }
        tr:nth-child(even) {
            background-color: #f3f4f6;
        }
        .text-left { text-align: left; padding-left: 10px; }

        /* --- Summary Section --- */
        .summary-container {
            display: table;
            width: 100%;
            border-spacing: 10px 0;
            margin-bottom: 20px;
        }
        .summary-box {
            display: table-cell;
            background: #f9fafb;
            border: 1px solid #d1d5db;
            padding: 10px;
            text-align: center;
        }
        .summary-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .summary-value {
            font-size: 16px;
            font-weight: 800;
            color: #111827;
        }

        /* --- Third Term Final Average --- */
        .final-average {
            margin: 15px 0;
            padding: 12px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .final-average h4 {
            font-size: 10px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .calc-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 9px;
        }
        .calc-label { color: #4b5563; }
        .calc-value { font-weight: 500; color: #111827; }
        .calc-divider {
            text-align: center;
            color: #9ca3af;
            margin: 2px 0;
            font-size: 9px;
        }
        .final-row {
            display: flex;
            justify-content: space-between;
            padding-top: 6px;
            margin-top: 6px;
            border-top: 1px solid #d1d5db;
        }
        .final-label {
            font-size: 10px;
            font-weight: 600;
            color: #111827;
        }
        .final-value {
            font-size: 12px;
            font-weight: bold;
            color: #dc2626;
        }

        /* --- Remarks --- */
        .remarks-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .remark-card {
            display: table-cell;
            width: 50%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
            background-color: #ffffff;
        }
        .remark-title {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 8px;
            padding-bottom: 4px;
        }
        .remark-content {
            font-size: 9.5px;
            line-height: 1.5;
            color: #4b5563;
        }

        /* --- Grade Scale --- */
        .grade-scale-wrapper {
            margin-bottom: 20px;
        }
        .grade-scale-title {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 8px;
            text-transform: uppercase;
            color: #2d3748;
        }
        .grade-table {
            font-size: 9px;
        }
        .grade-table th {
            background-color: #4b5563;
            font-size: 9px;
            padding: 5px;
        }
        .grade-table td {
            padding: 5px;
            font-size: 9px;
        }

        /* --- Modern Verification Badge --- */
       
        .verification-footer {
            margin-top: 30px;
            display: table;
            width: 100%;
        }
        .verification-text {
            display: table-cell;
            width: 65%;
            vertical-align: middle;
        }
        .badge-cell {
            display: table-cell;
            width: 35%;
            text-align: right;
            vertical-align: middle;
        }
        
        .modern-badge {
            display: inline-block;
            width: 140px;
            height: 140px;
            background: #ffffff;
            border: 4px double #1f2937;
            border-radius: 50%;
            position: relative;
            text-align: center;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .badge-inner {
            position: absolute;
            inset: 5px;
            border: 1px solid #9ca3af;
            border-radius: 50%;
        }
        .badge-content {
            padding-top: 25px;
        }
        .badge-school-name {
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            padding: 0 10px;
            color: #374151;
        }
        .badge-check {
            font-size: 32px;
            color: #111827;
            line-height: 1;
            margin: 5px 0;
        }
        .badge-status {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #111827;
        }
        .badge-code {
            font-family: monospace;
            font-size: 8px;
            color: #6b7280;
            margin-top: 5px;
        }
    </style>
</head>
<body>

    <div class="watermark">
        <div class="watermark-text">OFFICIAL</div>
    </div>

    <div class="container">
        <div class="header">
            <div class="logo-container">
                @if(!empty($schoolInfo['logo_path']))
                    <img src="{{ $schoolInfo['logo_path'] }}" alt="Logo" class="logo" />
                @else
                    @php
                        $parts = preg_split('/\s+/', trim((string) ($schoolInfo['name'] ?? 'SCHOOL')));
                        $initials = strtoupper(substr($parts[0] ?? 'S', 0, 1) . substr($parts[1] ?? 'C', 0, 1));
                    @endphp
                    <div class="logo-fallback">{{ $initials }}</div>
                @endif
            </div>
            <div class="school-name">{{ $schoolInfo['name'] }}</div>
            <div style="font-size: 10px; color: #6b7280;">{{ $schoolInfo['address'] }}</div>
            @if($schoolInfo['phone'] || $schoolInfo['email'])
                <div class="school-contact">
                    @if($schoolInfo['phone']) Tel: {{ $schoolInfo['phone'] }} @endif
                    @if($schoolInfo['phone'] && $schoolInfo['email']) <span>•</span> @endif
                    @if($schoolInfo['email']) Email: {{ $schoolInfo['email'] }} @endif
                </div>
            @endif
            <div class="report-title">Academic Report Card</div>
        </div>

        <div class="student-info">
            <div class="info-column">
                <div class="info-item"><span class="info-label">Student:</span> <span class="info-value">{{ $student->first_name }} {{ $student->middle_name ?? '' }} {{ $student->last_name }}</span></div>
                <div class="info-item"><span class="info-label">Admission No:</span> <span class="info-value">{{ $student->admission_number }}</span></div>
            </div>
            <div class="info-column" style="border-left: 1px solid #e5e7eb;">
                <div class="info-item"><span class="info-label">Class:</span> <span class="info-value">{{ $className ?? 'N/A' }}</span></div>
                <div class="info-item"><span class="info-label">Session/Term:</span> <span class="info-value">{{ $academicSessionName }} / {{ $termName }}</span></div>
            </div>
            
            @if($isThirdTerm && $promotionStatus)
                <div class="promotion-status">
                    <div class="promotion-cell" style="width: 100%;">
                        <span class="info-label">Promotion Status:</span>
                        <span class="info-value 
                            @if($promotionStatus === 'promoted') promotion-approved
                            @elseif($promotionStatus === 'graduated') promotion-graduated
                            @elseif($promotionStatus === 'repeated') promotion-repeated @endif">
                            @if($promotionStatus === 'promoted')
                                ✓ PROMOTED TO NEXT CLASS
                            @elseif($promotionStatus === 'graduated')
                                🎓 GRADUATED
                            @elseif($promotionStatus === 'repeated')
                                ⚠ REPEATED - TO REPEAT CURRENT CLASS
                            @endif
                        </span>
                    </div>
                </div>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 5%">S/N</th>
                    <th class="text-left" style="width: 25%">Subject</th>
                    <th>1st CA</th>
                    <th>2nd CA</th>
                    <th>Exam</th>
                    <th>Total</th>
                    @if(($positionsPolicy ?? 'all') !== 'none')
                        <th>Position</th>
                    @endif
                    <th>Grade</th>
                    <th class="text-left">Remark</th>
                </tr>
            </thead>
            <tbody>
                @if($scores->count() > 0)
                    @foreach($scores as $index => $score)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="text-left"><strong>{{ $score->subject_name ?? $score->subject ?? 'N/A' }}</strong></td>
                        <td>{{ $score->first_ca ?? '-' }}</td>
                        <td>{{ $score->second_ca ?? '-' }}</td>
                        <td>{{ $score->exam_score ?? '-' }}</td>
                        <td><strong>{{ is_numeric($score->total_score) ? number_format((float) $score->total_score, 1) : '-' }}</strong></td>
                        @if(($positionsPolicy ?? 'all') !== 'none')
                            <td><strong>{{ $score->subject_position_formatted ?? '-' }}</strong></td>
                        @endif
                        <td><strong>{{ $score->grade ?? '-' }}</strong></td>
                        <td class="text-left" style="font-size: 9px;">{{ $score->remark ?? '-' }}</td>
                    </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="{{ (($positionsPolicy ?? 'all') !== 'none') ? 9 : 8 }}" style="text-align: center; padding: 15px; color: #666;">
                            No scores recorded for this term.
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="summary-container">
            <div class="summary-box">
                <div class="summary-label">Total Score</div>
                <div class="summary-value">{{ number_format($totalScore, 1) }}</div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Average %</div>
                <div class="summary-value">{{ number_format($averageScore, 1) }}%</div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Class Position</div>
                <div class="summary-value">{{ (($positionsPolicy ?? 'all') !== 'none') ? ($overallPositionFormatted ?? 'N/A') : 'N/A' }}</div>
            </div>
        </div>

        @if($isThirdTerm && $thirdTermFinalAverage)
        <div class="final-average">
            <h4>Final Average Calculation</h4>
            <div style="font-size: 9px;">
                <div class="calc-row">
                    <span class="calc-label">First Term Average:</span>
                    <span class="calc-value">{{ number_format($thirdTermFinalAverage['first_term_average'], 2) }}%</span>
                </div>
                <div class="calc-divider">+</div>
                <div class="calc-row">
                    <span class="calc-label">Second Term Average:</span>
                    <span class="calc-value">{{ number_format($thirdTermFinalAverage['second_term_average'], 2) }}%</span>
                </div>
                <div class="calc-divider">+</div>
                <div class="calc-row">
                    <span class="calc-label">Third Term Average:</span>
                    <span class="calc-value">{{ number_format($thirdTermFinalAverage['third_term_average'], 2) }}%</span>
                </div>
                <div class="calc-divider" style="border-top: 1px solid #e5e7eb; padding-top: 4px; margin-top: 4px;">÷ 3</div>
                <div class="final-row">
                    <span class="final-label">Final Average for this Class:</span>
                    <span class="final-value">{{ number_format($thirdTermFinalAverage['final_average'], 2) }}%</span>
                </div>
                <p style="font-size: 7.5px; color: #6b7280; margin-top: 6px; text-align: center;">
                    (First Term Average + Second Term Average + Third Term Average) ÷ 3
                </p>
            </div>
        </div>
        @endif

        <div class="remarks-section">
            <div class="remark-card">
                <div class="remark-title">Teacher's Remark</div>
                <div class="remark-content">
                    @if($averageScore >= 80)
                        Excellent performance! {{ $student->first_name }} has demonstrated exceptional understanding across all subjects and consistently delivers work of the highest quality. This level of academic excellence is truly commendable.
                    @elseif($averageScore >= 70)
                        Very impressive academic performance! {{ $student->first_name }} displays good grasp of concepts and shows consistent effort in all subject areas. With continued focus, even higher achievements are definitely within reach.
                    @elseif($averageScore >= 60)
                        Good academic progress! {{ $student->first_name }} shows understanding in most areas but there's room for improvement in consistency and depth of work. Focus on strengthening weaker subjects while maintaining performance in stronger areas.
                    @elseif($averageScore >= 50)
                        Average performance. {{ $student->first_name }} grasps some concepts but struggles with consistency and depth. Recommend developing better study routines and working more closely with subject teachers to identify and address specific weaknesses.
                    @elseif($averageScore >= 40)
                        Below average results. {{ $student->first_name }} requires substantial academic support to catch up with peers. Work on strengthening basic skills and seeking help immediately when concepts are unclear.
                    @else
                        Poor academic performance. {{ $student->first_name }} requires immediate and intensive intervention. The performance suggests fundamental gaps in understanding that need urgent attention through remedial work and additional tutoring.
                    @endif
                </div>
            </div>
            <div style="display: table-cell; width: 2%;"></div>
            <div class="remark-card">
                <div class="remark-title">Principal's Remark</div>
                <div class="remark-content">
                    @if($isThirdTerm)
                        @if($promotionStatus === 'promoted')
                            Approved for promotion to next class. {{ $student->first_name }} has demonstrated consistent academic performance throughout the session and is ready to advance. Congratulations on your promotion!
                        @elseif($promotionStatus === 'graduated')
                            Congratulations! {{ $student->first_name }} has successfully completed this level and is hereby graduated. We wish you success in your future endeavors.
                        @elseif($promotionStatus === 'repeated')
                            {{ $student->first_name }} has not met the minimum requirements for promotion. The student is required to repeat the current class to strengthen academic performance. We encourage continued effort and improvement.
                        @else
                            @if($averageScore >= 70)
                                Approved for promotion to next class. {{ $student->first_name }} has demonstrated consistent academic performance throughout the session and is ready to advance.
                            @else
                                Requires improvement before promotion. {{ $student->first_name }} needs to strengthen academic performance to meet promotion requirements.
                            @endif
                        @endif
                    @else
                        @if($averageScore >= 70)
                            Good performance this term. Continue to maintain this standard throughout the session.
                        @elseif($averageScore >= 60)
                            Fair performance. More effort is needed to improve overall academic standing.
                        @else
                            Requires significant improvement. Focus on developing better study habits and seeking additional support.
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="grade-scale-wrapper">
            <div class="grade-scale-title">
                GRADING SCALE
                @if(!empty($gradingConfig?->name))
                    <span style="font-weight: normal; color: #6b7280;">— {{ $gradingConfig->name }}</span>
                @endif
            </div>
            <table class="grade-table">
                <thead>
                    <tr>
                        <th>Grade</th>
                        <th>Score Range</th>
                        <th>Interpretation</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $gradeRemarks = [
                            'A' => 'Excellent',
                            'B' => 'Very Good',
                            'C' => 'Good',
                            'D' => 'Fair',
                            'E' => 'Pass',
                            'F' => 'Fail',
                        ];
                    @endphp

                    @if(isset($gradingRanges) && $gradingRanges && count($gradingRanges) > 0)
                        @foreach($gradingRanges as $r)
                            <tr>
                                <td><strong>{{ strtoupper((string) $r->grade) }}</strong></td>
                                <td>{{ (int) $r->min_score }} - {{ (int) $r->max_score }}</td>
                                <td>{{ $gradeRemarks[strtoupper((string) $r->grade)] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr><td><strong>A</strong></td><td>80 - 100</td><td>Excellent</td></tr>
                        <tr><td><strong>B</strong></td><td>70 - 79</td><td>Very Good</td></tr>
                        <tr><td><strong>C</strong></td><td>60 - 69</td><td>Good</td></tr>
                        <tr><td><strong>D</strong></td><td>50 - 59</td><td>Fair</td></tr>
                        <tr><td><strong>E</strong></td><td>40 - 49</td><td>Pass</td></tr>
                        <tr><td><strong>F</strong></td><td>0 - 39</td><td>Fail</td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="verification-footer">
            <div class="verification-text">
                <div style="font-weight: bold; text-transform: uppercase; font-size: 10px; margin-bottom: 4px;">Digital Verification</div>
                <div style="font-size: 9px; color: #4b5563; max-width: 300px;">
                    This document is an official record of <strong>{{ $schoolInfo['name'] }}</strong>. 
                    The authenticity of this report can be verified using the digital mark and unique ID.
                </div>
            </div>
            <div class="badge-cell">
                <div class="modern-badge">
                    <div class="badge-inner"></div>
                    <div class="badge-content">
                        <div class="badge-school-name">{{ substr($schoolInfo['name'], 0, 35) }}</div>
                        <div class="badge-check">✓</div>
                        <div class="badge-status">VERIFIED</div>
                        <div class="badge-code">{{ $verificationCode ?? 'REF-'.rand(1000,9999) }}</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>