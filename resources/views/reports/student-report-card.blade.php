<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Report Card - {{ $student->admission_number }}</title>
    <style>
        @page {
            margin: 15mm;
            size: A4;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
            position: relative;
        }
        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            opacity: 0.08;
            z-index: 0;
            text-align: center;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }
        .watermark-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .watermark-logo {
            width: 250px;
            height: 250px;
            margin-bottom: 30px;
            opacity: 0.12;
            object-fit: contain;
        }
        .watermark-text {
            font-size: 80px;
            font-weight: bold;
            color: #aecb1f;
            text-transform: uppercase;
            letter-spacing: 10px;
            white-space: nowrap;
        }
        /* Ensure content appears above watermark */
        .header, .student-info, table, .summary, .remarks, .grade-scale, .signature-section, .footer {
            position: relative;
            z-index: 1;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 3px solid #aecb1f;
        }
        .logo-container {
            margin-bottom: 8px;
        }
        .logo {
            max-width: 80px;
            max-height: 80px;
            margin: 0 auto;
            display: block;
        }
        .school-name {
            font-size: 18px;
            font-weight: bold;
            color: #1a202c;
            margin: 5px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .school-address {
            font-size: 10px;
            color: #666;
            margin: 3px 0;
        }
        .report-title {
            font-size: 14px;
            font-weight: bold;
            color: #aecb1f;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .student-info {
            margin: 15px 0;
            background-color: #f7fafc;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 6px;
            font-size: 10px;
        }
        .info-label {
            font-weight: bold;
            width: 140px;
            display: table-cell;
            color: #4a5568;
        }
        .info-value {
            display: table-cell;
            color: #2d3748;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10px;
        }
        th, td {
            border: 1px solid #2d3748;
            padding: 6px 4px;
            text-align: center;
        }
        th {
            background-color: #aecb1f;
            color: white;
            font-weight: bold;
            font-size: 9px;
        }
        td {
            color: #2d3748;
        }
        .text-left {
            text-align: left !important;
            padding-left: 6px;
        }
        .summary {
            margin-top: 15px;
            display: table;
            width: 100%;
        }
        .summary-box {
            display: table-cell;
            border: 2px solid #aecb1f;
            padding: 10px;
            text-align: center;
            width: 33.33%;
            background-color: #fff5f5;
        }
        .summary-label {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 5px;
            color: #4a5568;
            text-transform: uppercase;
        }
        .summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #aecb1f;
        }
        .remarks {
            margin-top: 15px;
            display: table;
            width: 100%;
        }
        .remark-box {
            display: table-cell;
            width: 48%;
            border: 1px solid #2d3748;
            padding: 10px;
            vertical-align: top;
            background-color: #ffffff;
        }
        .remark-box:first-child {
            margin-right: 2%;
        }
        .remark-title {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            color: #2d3748;
            text-transform: uppercase;
        }
        .remark-content {
            font-size: 9px;
            line-height: 1.5;
            color: #4a5568;
            text-align: justify;
        }
        .grade-scale {
            margin-top: 15px;
            font-size: 9px;
        }
        .grade-scale-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 10px;
            color: #2d3748;
        }
        .grade-table {
            font-size: 8px;
        }
        .grade-table th {
            background-color: #4a5568;
            font-size: 8px;
            padding: 4px;
        }
        .grade-table td {
            padding: 4px;
            font-size: 8px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        .stamp-section {
            margin-top: 20px;
            display: table;
            width: 100%;
        }
        .stamp-container {
            display: table-cell;
            width: 100%;
            text-align: center;
            padding: 10px;
            vertical-align: middle;
        }
        .school-stamp {
            display: inline-block;
            width: 180px;
            height: 180px;
            position: relative;
            margin: 0 auto;
        }
        .stamp-image {
            width: 180px;
            height: 180px;
            display: block;
            margin: 0 auto;
            object-fit: contain;
        }
        .stamp-circle-outer {
            width: 100%;
            height: 100%;
            border: 3px solid #000;
            border-radius: 50%;
            position: absolute;
            top: 0;
            left: 0;
        }
        .stamp-circle-inner {
            width: 75%;
            height: 75%;
            border: 1px dashed #000;
            border-radius: 50%;
            position: absolute;
            top: 12.5%;
            left: 12.5%;
        }
        .stamp-text-top {
            position: absolute;
            top: 15%;
            left: 50%;
            transform: translateX(-50%);
            font-size: 11px;
            font-weight: bold;
            color: #000;
            white-space: nowrap;
        }
        .stamp-text-left {
            position: absolute;
            left: 10%;
            top: 50%;
            transform: translateY(-50%) rotate(-90deg);
            font-size: 9px;
            color: #000;
            white-space: nowrap;
        }
        .stamp-date {
            position: absolute;
            top: 40%;
            left: 50%;
            transform: translateX(-50%);
            font-size: 9px;
            font-weight: bold;
            color: #000;
        }
        .stamp-date-line {
            position: absolute;
            top: 45%;
            left: 20%;
            width: 60%;
            border-top: 1px dashed #000;
        }
        .stamp-sign {
            position: absolute;
            top: 55%;
            left: 50%;
            transform: translateX(-50%);
            font-size: 9px;
            font-weight: bold;
            color: #000;
        }
        .stamp-signature {
            position: absolute;
            top: 60%;
            left: 30%;
            width: 40%;
            height: 15px;
            border-bottom: 2px solid #0066cc;
            transform: rotate(-5deg);
        }
    </style>
</head>
<body>
    <!-- Watermark -->
    <div class="watermark">
        <div class="watermark-content">
            @php
                $logoPath = public_path('images/G-LOVE ACADEMY.jpeg');
                $logoExists = file_exists($logoPath);
            @endphp
            @if($logoExists)
                <img src="{{ $logoPath }}" alt="School Logo" class="watermark-logo" />
            @endif
            <div class="watermark-text">{{ $schoolInfo['name'] }}</div>
        </div>
    </div>
    
    <!-- Header -->
    <div class="header">
        <div class="logo-container">
            @php
                $logoPath = public_path('images/G-LOVE ACADEMY.jpeg');
                $logoExists = file_exists($logoPath);
            @endphp
            @if($logoExists)
                <img src="{{ $logoPath }}" alt="School Logo" class="logo" />
            @else
                <div style="width: 80px; height: 80px; background: #aecb1f; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;">GLA</div>
            @endif
        </div>
        <div class="school-name">{{ $schoolInfo['name'] }}</div>
        @if($schoolInfo['address'])
            <div class="school-address">{{ $schoolInfo['address'] }}</div>
        @endif
        @if($schoolInfo['phone'] || $schoolInfo['email'])
            <div class="school-address">
                @if($schoolInfo['phone']) Tel: {{ $schoolInfo['phone'] }} @endif
                @if($schoolInfo['phone'] && $schoolInfo['email']) | @endif
                @if($schoolInfo['email']) Email: {{ $schoolInfo['email'] }} @endif
            </div>
        @endif
        <div class="report-title">
            STUDENT'S REPORT CARD
        </div>
    </div>

    <!-- Student Information -->
    <div class="student-info">
        <div class="info-row">
            <span class="info-label">Student Name:</span>
            <span class="info-value">{{ $student->first_name }} {{ $student->middle_name ?? '' }} {{ $student->last_name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Admission Number:</span>
            <span class="info-value">{{ $student->admission_number }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Class:</span>
            <span class="info-value">{{ $student->schoolClass->name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Academic Session:</span>
            <span class="info-value">{{ $academicSession->name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Term:</span>
            <span class="info-value">{{ $term }}</span>
        </div>
        @if($isThirdTerm && $promotionStatus)
            <div class="info-row" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e2e8f0;">
                <span class="info-label" style="font-size: 11px; color: #2d3748;">Promotion Status:</span>
                <span class="info-value" style="font-size: 11px; font-weight: bold; 
                    @if($promotionStatus === 'promoted') color: #22c55e; 
                    @elseif($promotionStatus === 'graduated') color: #3b82f6; 
                    @elseif($promotionStatus === 'repeated') color: #ef4444; 
                    @endif">
                    @if($promotionStatus === 'promoted')
                        ✓ PROMOTED TO NEXT CLASS
                    @elseif($promotionStatus === 'graduated')
                        🎓 GRADUATED
                    @elseif($promotionStatus === 'repeated')
                        ⚠ REPEATED - TO REPEAT CURRENT CLASS
                    @endif
                </span>
            </div>
        @endif
    </div>

    <!-- Results Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 4%;">S/N</th>
                <th class="text-left" style="width: 20%;">Subject</th>
                <th style="width: 8%;">1st CA</th>
                <th style="width: 8%;">2nd CA</th>
                <th style="width: 8%;">Exam</th>
                <th style="width: 8%;">Total</th>
                <th style="width: 8%;">Position</th>
                <th style="width: 8%;">Grade</th>
                <th style="width: 20%;">Remark</th>
            </tr>
        </thead>
        <tbody>
            @foreach($scores as $index => $score)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="text-left">{{ $score->subject->name ?? 'N/A' }}</td>
                    <td>{{ $score->first_ca ?? '-' }}</td>
                    <td>{{ $score->second_ca ?? '-' }}</td>
                    <td>{{ $score->exam_score ?? '-' }}</td>
                    <td><strong>{{ number_format($score->total_score, 1) }}</strong></td>
                    <td><strong>{{ $score->subject_position_formatted ?? '-' }}</strong></td>
                    <td><strong>{{ $score->grade ?? '-' }}</strong></td>
                    <td>{{ $score->remark ?? '-' }}</td>
                </tr>
            @endforeach
            @if($scores->isEmpty())
                <tr>
                    <td colspan="9" style="text-align: center; padding: 15px; color: #666;">No scores recorded for this term.</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Summary -->
    <div class="summary">
        <div class="summary-box">
            <div class="summary-label">Total Score</div>
            <div class="summary-value">{{ number_format($totalScore, 1) }}</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Average Score</div>
            <div class="summary-value">{{ number_format($averageScore, 1) }}%</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Overall Position</div>
            <div class="summary-value">{{ $overallPositionFormatted ?? 'N/A' }}</div>
        </div>
    </div>

    <!-- Third Term Final Average Calculation -->
    @if($isThirdTerm && $thirdTermFinalAverage)
    <div style="margin: 15px 0; padding: 12px; background: white; border: 1px solid #e5e7eb; border-radius: 6px;">
        <h4 style="font-size: 10px; font-weight: 600; color: #111827; margin-bottom: 10px;">Final Average Calculation</h4>
        <div style="font-size: 8px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="color: #4b5563;">First Term Average:</span>
                <span style="font-weight: 500; color: #111827;">{{ number_format($thirdTermFinalAverage['first_term_average'], 2) }}%</span>
            </div>
            <div style="text-align: center; color: #9ca3af; margin: 2px 0; font-size: 8px;">+</div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="color: #4b5563;">Second Term Average:</span>
                <span style="font-weight: 500; color: #111827;">{{ number_format($thirdTermFinalAverage['second_term_average'], 2) }}%</span>
            </div>
            <div style="text-align: center; color: #9ca3af; margin: 2px 0; font-size: 8px;">+</div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="color: #4b5563;">Third Term Average:</span>
                <span style="font-weight: 500; color: #111827;">{{ number_format($thirdTermFinalAverage['third_term_average'], 2) }}%</span>
            </div>
            <div style="text-align: center; color: #9ca3af; margin: 6px 0 4px 0; padding-top: 4px; border-top: 1px solid #e5e7eb; font-size: 8px;">÷ 3</div>
            <div style="display: flex; justify-content: space-between; padding-top: 6px; margin-top: 6px; border-top: 1px solid #d1d5db;">
                <span style="font-size: 9px; font-weight: 600; color: #111827;">Final Average for this Class:</span>
                <span style="font-size: 11px; font-weight: bold; color: #dc2626;">{{ number_format($thirdTermFinalAverage['final_average'], 2) }}%</span>
            </div>
            <p style="font-size: 7px; color: #6b7280; margin-top: 6px; text-align: center;">
                (First Term Average + Second Term Average + Third Term Average) ÷ 3
            </p>
        </div>
    </div>
    @endif

    <!-- Remarks -->
    <div class="remarks">
        <div class="remark-box">
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
        <div class="remark-box">
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

    <!-- Grade Scale -->
    <div class="grade-scale">
        <div class="grade-scale-title">GRADING SCALE</div>
        <table class="grade-table">
            <thead>
                <tr>
                    <th>Grade</th>
                    <th>Score Range</th>
                    <th>Remark</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>A</strong></td><td>80 - 100</td><td>Excellent</td></tr>
                <tr><td><strong>B</strong></td><td>70 - 79</td><td>Very Good</td></tr>
                <tr><td><strong>C</strong></td><td>60 - 69</td><td>Good</td></tr>
                <tr><td><strong>D</strong></td><td>50 - 59</td><td>Fair</td></tr>
                <tr><td><strong>E</strong></td><td>40 - 49</td><td>Pass</td></tr>
                <tr><td><strong>F</strong></td><td>0 - 39</td><td>Fail</td></tr>
            </tbody>
        </table>
    </div>

    <!-- School Stamp -->
    <div class="stamp-section">
        <div class="stamp-container">
            @php
                $stampPath = public_path('images/school-stamp.svg');
                $stampBase64 = file_exists($stampPath)
                    ? 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($stampPath))
                    : null;
            @endphp
            @if($stampBase64)
                <img src="{{ $stampBase64 }}" alt="School Stamp" class="stamp-image" />
            @else
                <div class="school-stamp">
                    <div class="stamp-circle-outer"></div>
                    <div class="stamp-circle-inner"></div>
                    <div class="stamp-text-top">G-LOVE ACADEMY</div>
                    <div class="stamp-text-left">LUGBE ABUJA</div>
                    <div class="stamp-date">DATE</div>
                    <div class="stamp-date-line"></div>
                    <div class="stamp-sign">SIGN.</div>
                    <div class="stamp-signature"></div>
                </div>
            @endif
        </div>
    </div>

    <!-- Footer -->
    <!-- <div class="footer">
        <p>This is a computer-generated report. Generated on {{ date('F j, Y') }} at {{ date('g:i A') }}</p>
    </div> -->
</body>
</html>
