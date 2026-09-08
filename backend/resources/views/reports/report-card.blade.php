<!DOCTYPE html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <title>{{ $labels['title'] }} · {{ $student['full_name'] }} · {{ $term['name'] }}</title>
    <style>
        @page { margin: 14mm 14mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #0f172a; }
        h1, h2 { margin: 0; }
        .header { display: table; width: 100%; border-bottom: 2px solid {{ $school['primary_color'] }}; padding-bottom: 8px; margin-bottom: 10px; }
        .header-logo, .header-school, .student-photo { display: table-cell; vertical-align: middle; }
        .header-logo { width: 62px; }
        .header-logo img { width: 52px; height: 52px; object-fit: contain; }
        .header-school { text-align: center; }
        .school-name { font-size: 17pt; font-weight: 700; color: {{ $school['primary_color'] }}; }
        .school-motto { color: #64748b; font-style: italic; }
        .report-title { margin-top: 4px; font-size: 12pt; font-weight: 700; text-transform: uppercase; }
        .student-photo { width: 128px; text-align: right; white-space: nowrap; }
        .secondary-logo { width: 52px; height: 52px; object-fit: contain; margin-right: 7px; vertical-align: middle; }
        .student-photo img, .photo-placeholder { width: 62px; height: 72px; border: 1px solid #cbd5e1; border-radius: 4px; object-fit: cover; }
        .photo-placeholder { display: inline-block; line-height: 72px; text-align: center; background: #f1f5f9; color: #64748b; font-size: 18pt; font-weight: 700; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta td { padding: 3px 5px; }
        .meta .label { width: 18%; color: #64748b; }
        .meta .value { width: 32%; font-weight: 600; }
        .panel { border: 1px solid #dbe3ef; padding: 8px 10px; margin-bottom: 9px; }
        .panel h2 { color: {{ $school['primary_color'] }}; font-size: 10.5pt; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 5px; }
        .grades { width: 100%; border-collapse: collapse; }
        .grades th { background: #eff6ff; color: #334155; padding: 5px 6px; text-align: left; }
        .grades td { padding: 4px 6px; border-bottom: 1px solid #eef2f7; }
        .grades .num { text-align: right; }
        .summary { display: table; width: 100%; }
        .metric { display: table-cell; width: 33.33%; padding: 6px; text-align: center; }
        .metric-value { color: {{ $school['primary_color'] }}; font-size: 13pt; font-weight: 700; }
        .metric-label { color: #64748b; font-size: 8pt; }
        .remark { margin: 6px 0 0; padding: 6px; background: #f8fafc; text-align: center; }
        .signatures { display: table; width: 100%; margin-top: 28px; }
        .signature { display: table-cell; width: 33.33%; padding-top: 5px; border-top: 1px solid #94a3b8; text-align: center; }
        .small { color: #64748b; font-size: 8pt; }
    </style>
</head>
<body>
@include('reports.partials.watermark')
    @if($school['header_image_url'])
        @include('reports.partials.school-header-image', ['maxHeight' => '92px'])
        <div class="header">
            <div class="header-logo"></div>
            <div class="header-school">
                <div class="report-title">{{ $labels['title'] }} · {{ $term['name'] }}</div>
            </div>
            <div class="student-photo">
                @if ($student['photo_url'])
                    <img src="{{ $student['photo_url'] }}" alt="{{ $student['full_name'] }}">
                @else
                    <span class="photo-placeholder">{{ mb_strtoupper(mb_substr($student['full_name'], 0, 1)) }}</span>
                @endif
            </div>
        </div>
    @else
    @if($school['header'])<div style="text-align:center;color:#64748b;font-size:8pt">{{ $school['header'] }}</div>@endif
    <div class="header">
        <div class="header-logo">
            @if ($school['logo_url'])
                <img src="{{ $school['logo_url'] }}" alt="">
            @endif
        </div>
        <div class="header-school">
            <div class="school-name">{{ $school['name'] }}</div>
            <div class="school-motto">{{ $school['motto'] }}</div>
            <div class="report-title">{{ $labels['title'] }} · {{ $term['name'] }}</div>
        </div>
        <div class="student-photo">
            @if ($school['secondary_logo_url'])
                <img class="secondary-logo" src="{{ $school['secondary_logo_url'] }}" alt="">
            @endif
            @if ($student['photo_url'])
                <img src="{{ $student['photo_url'] }}" alt="{{ $student['full_name'] }}">
            @else
                <span class="photo-placeholder">{{ mb_strtoupper(mb_substr($student['full_name'], 0, 1)) }}</span>
            @endif
        </div>
    </div>
    @endif

    <table class="meta">
        <tr>
            <td class="label">{{ $labels['student'] }}</td><td class="value">{{ $student['full_name'] }}</td>
            <td class="label">{{ $labels['class'] }}</td><td class="value">{{ $student['class'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">{{ $labels['admission'] }}</td><td class="value">{{ $student['admission_number'] }}</td>
            <td class="label">{{ $labels['academic_year'] }}</td><td class="value">{{ $student['academic_year'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">{{ $labels['date_of_birth'] }}</td><td class="value">{{ $student['date_of_birth'] ?? '—' }}</td>
            <td class="label">{{ $labels['form_master'] }}</td><td class="value">{{ $student['form_master'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">{{ $labels['term'] }}</td><td class="value">{{ $term['name'] }}</td>
            <td class="label">{{ $labels['parent'] }}</td><td class="value">{{ $student['parent'] ?? '—' }}</td>
        </tr>
    </table>

    <div class="panel">
        <h2>{{ $labels['performance'] }}</h2>
        <table class="grades">
            <thead>
                <tr>
                    <th>{{ $labels['subject'] }}</th>
                    <th>{{ $labels['code'] }}</th>
                    <th class="num">{{ $labels['coefficient'] }}</th>
                    <th class="num">{{ $labels['average'] }}</th>
                    <th class="num">{{ $labels['weighted'] }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($grades as $grade)
                    @php($weighted = $grade['average'] !== null ? $grade['average'] * $grade['coefficient'] : null)
                    <tr>
                        <td>{{ $grade['name'] }}</td>
                        <td>{{ $grade['code'] }}</td>
                        <td class="num">{{ number_format($grade['coefficient'], 1) }}</td>
                        <td class="num">{{ $grade['average'] !== null ? number_format($grade['average'], 2) : '—' }}</td>
                        <td class="num">{{ $weighted !== null ? number_format($weighted, 2) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="small" style="text-align:center">{{ $labels['no_grades'] }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h2>{{ $labels['overall'] }}</h2>
        <div class="summary">
            <div class="metric">
                <div class="metric-value">{{ $overall['average'] !== null ? number_format($overall['average'], 2) . ' / 20' : '—' }}</div>
                <div class="metric-label">{{ $labels['student_average'] }}</div>
            </div>
            <div class="metric">
                <div class="metric-value">{{ $overall['class_average'] !== null ? number_format($overall['class_average'], 2) . ' / 20' : '—' }}</div>
                <div class="metric-label">{{ $labels['class_average'] }}</div>
            </div>
            <div class="metric">
                <div class="metric-value">{{ $overall['rank'] ? $overall['rank']['position'] . ' / ' . $overall['rank']['total'] : '—' }}</div>
                <div class="metric-label">{{ $labels['class_rank'] }}</div>
            </div>
        </div>
        <p class="remark"><strong>{{ $labels['remark'] }}:</strong> {{ $overall['remark'] }}</p>
    </div>

    <div class="panel">
        <h2>{{ $labels['attendance'] }}</h2>
        <div class="summary">
            <div class="metric"><div class="metric-value">{{ $attendance['present'] }}</div><div class="metric-label">{{ $labels['present'] }}</div></div>
            <div class="metric"><div class="metric-value">{{ $attendance['absent'] }}</div><div class="metric-label">{{ $labels['absent'] }}</div></div>
            <div class="metric"><div class="metric-value">{{ $attendance['late'] }}</div><div class="metric-label">{{ $labels['late'] }}</div></div>
        </div>
        <p class="small" style="text-align:center">{{ $labels['attendance_rate'] }}: {{ number_format($attendance['rate'], 1) }}% · {{ $attendance['total'] }} {{ $labels['sessions'] }}</p>
    </div>

    @if ($academic_decision)
        <div class="panel">
            <h2>{{ $labels['academic_decision'] }}</h2>
            <p class="remark"><strong>{{ $academic_decision['label'] }}</strong></p>
            <p><strong>{{ $labels['teacher_appreciation'] }}:</strong> {{ $academic_decision['teacher_appreciation'] ?? '—' }}</p>
            <p><strong>{{ $labels['class_council'] }}:</strong> {{ $academic_decision['class_council_recommendation'] ?? '—' }}</p>
        </div>
    @endif

    <div class="signatures">
        <div class="signature">{{ $labels['form_master'] }}</div>
        <div class="signature">{{ $labels['principal'] }}</div>
        <div class="signature">{{ $labels['parent_signature'] }}</div>
    </div>
    <p class="small" style="text-align:center; margin-top:10px">{{ $labels['generated'] }} {{ $school['name'] }} · {{ $generated_at }}</p>
    @if($school['principal_name'])<p class="small" style="text-align:right"><strong>{{ $school['principal_title'] }}</strong><br>{{ $school['principal_name'] }}</p>@endif
    @if($school['footer'])<p class="small" style="text-align:center">{{ $school['footer'] }}</p>@endif
</body>
</html>
