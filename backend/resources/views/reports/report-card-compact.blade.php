<!DOCTYPE html>
<html lang="{{ $language }}">
<head>
<meta charset="utf-8">
<title>{{ $labels['title'] }} - {{ $student['full_name'] }} - {{ $term['name'] }}</title>
@php
    $subjectCount = count($grades);
    $rowFont = $subjectCount > 20 ? 5.1 : ($subjectCount > 14 ? 5.5 : 6.1);
    $rowPadding = $subjectCount > 20 ? 1.0 : ($subjectCount > 14 ? 1.35 : 1.8);
    $weightedGrandTotal = collect($grades)->sum(fn ($grade) =>
        $grade['average'] !== null ? $grade['average'] * $grade['coefficient'] : 0
    );
@endphp
<style>
@page{margin:6mm 7mm 6mm}*{box-sizing:border-box}body{margin:0;font-family:DejaVu Sans,sans-serif;font-size:6.2pt;color:#111;line-height:1.12}table{border-collapse:collapse;width:100%}.no-break{page-break-inside:avoid}.official-school-header{margin-bottom:1mm!important}.header-fallback td{vertical-align:middle;text-align:center}.header-logo{width:18mm}.header-logo img{max-width:15mm;max-height:15mm}.school-name{font-size:11pt;font-weight:800;text-transform:uppercase;color:{{ $school['primary_color'] }}}.motto{font-size:5.5pt}.title-band{margin:.8mm 0 .7mm;padding:.65mm;border-top:.35mm solid #111;border-bottom:.35mm solid #111;background:#ececec;text-align:center;font-weight:800;text-transform:uppercase}.title-main{font-size:10.5pt}.title-sub{font-size:6.3pt}.identity{margin-bottom:.8mm}.identity td{padding:.45mm 1mm;border:0}.identity .label{width:19%;font-size:5.4pt}.identity .value{width:31%;font-weight:800;font-size:6pt}.grades{table-layout:fixed;border:.35mm solid #111}.grades th,.grades td{border:.2mm solid #111;padding:{{ $rowPadding }}px 2px;font-size:{{ $rowFont }}pt;vertical-align:middle}.grades th{background:#e5e7eb;text-align:center;font-weight:800}.grades .subject{width:31%;font-weight:700}.grades .code{width:8%;text-align:center}.grades .number{width:8%;text-align:center}.grades .appreciation{width:21%;font-size:{{ max(4.8, $rowFont - .3) }}pt}.total-row td{font-weight:800;background:#ededed}.summary-grid{margin-top:.8mm;table-layout:fixed}.summary-grid td{border:.2mm solid #111;padding:1mm;vertical-align:top}.summary-title{display:block;margin-bottom:.5mm;font-size:5.2pt;font-weight:800;text-align:center;text-transform:uppercase;border-bottom:.15mm solid #777}.big{font-size:7.2pt;font-weight:800}.decision{margin-top:.8mm;table-layout:fixed}.decision td{border:.2mm solid #111;padding:.8mm;vertical-align:top;height:12mm}.decision .heading{display:block;font-size:5.2pt;font-weight:800;text-align:center;text-transform:uppercase;border-bottom:.15mm solid #777;margin-bottom:.6mm}.decision .principal{text-align:center}.decision-label{font-size:8pt;font-weight:800;color:{{ $school['primary_color'] }}}.signatures{margin-top:.8mm;table-layout:fixed}.signatures td{position:relative;width:33.33%;height:19mm;border:.2mm solid #111;padding:.8mm;text-align:center;vertical-align:top}.signature-title{font-size:5.4pt;font-weight:800}.signature-line{position:absolute;left:5mm;right:5mm;bottom:2.5mm;border-top:.2mm solid #111;font-size:4.6pt;padding-top:.4mm}.stamp{max-width:15mm;max-height:14mm;margin-top:1mm}.principal-name{margin-top:1mm;font-weight:700}.footer{margin-top:.7mm;text-align:center;font-size:4.5pt;color:#555}.muted{color:#555}.watermark-layer{opacity:.035!important}
</style>
</head>
<body>
@include('reports.partials.watermark')

<div class="no-break">
@if($school['header_image_url'])
    @include('reports.partials.school-header-image', ['maxHeight' => '72px', 'bottomMargin' => '1px'])
@else
    @if($school['header'])<div style="text-align:center;font-size:5.5pt">{{ $school['header'] }}</div>@endif
    <table class="header-fallback"><tr>
        <td class="header-logo">@if($school['logo_url'])<img src="{{ $school['logo_url'] }}" alt="">@endif</td>
        <td><div class="school-name">{{ $school['name'] }}</div>@if($school['motto'])<div class="motto">{{ $school['motto'] }}</div>@endif</td>
        <td class="header-logo">@if($school['secondary_logo_url'])<img src="{{ $school['secondary_logo_url'] }}" alt="">@endif</td>
    </tr></table>
@endif
<div class="title-band"><div class="title-main">{{ $labels['title'] }}</div><div class="title-sub">{{ $term['name'] }}</div></div>

<table class="identity">
<tr><td class="label">{{ $labels['student'] }}:</td><td class="value">{{ mb_strtoupper($student['full_name']) }}</td><td class="label">{{ $labels['class'] }}:</td><td class="value">{{ $student['class'] ?? '-' }}</td></tr>
<tr><td class="label">{{ $labels['admission'] }}:</td><td class="value">{{ $student['admission_number'] }}</td><td class="label">{{ $labels['academic_year'] }}:</td><td class="value">{{ $student['academic_year'] ?? '-' }}</td></tr>
<tr><td class="label">{{ $labels['date_of_birth'] }}:</td><td class="value">{{ $student['date_of_birth'] ?? '-' }}</td><td class="label">{{ $labels['form_master'] }}:</td><td class="value">{{ $student['form_master'] ?? '-' }}</td></tr>
<tr><td class="label">{{ $labels['term'] }}:</td><td class="value">{{ $term['name'] }}</td><td class="label">{{ $labels['parent'] }}:</td><td class="value">{{ $student['parent'] ?? '-' }}</td></tr>
</table>
</div>

<table class="grades">
<thead><tr><th class="subject">{{ $labels['subject'] }}</th><th class="code">{{ $labels['code'] }}</th><th class="number">{{ $labels['average'] }}</th><th class="number">{{ $labels['coefficient'] }}</th><th class="number">{{ $labels['weighted'] }}</th><th class="appreciation">{{ $labels['remark'] }}</th></tr></thead>
<tbody>
@forelse($grades as $grade)
@php
    $weighted = $grade['average'] !== null ? $grade['average'] * $grade['coefficient'] : null;
    if ($grade['average'] === null) {
        $subjectRemark = '-';
    } elseif ($grade['average'] >= 16) {
        $subjectRemark = 'Excellent';
    } elseif ($grade['average'] >= 14) {
        $subjectRemark = $language === 'fr' ? 'Très bien' : 'Very good';
    } elseif ($grade['average'] >= 12) {
        $subjectRemark = $language === 'fr' ? 'Bien' : 'Good';
    } elseif ($grade['average'] >= 10) {
        $subjectRemark = $language === 'fr' ? 'Assez bien' : 'Fair';
    } else {
        $subjectRemark = $language === 'fr' ? 'Insuffisant' : 'Needs improvement';
    }
@endphp
<tr><td class="subject">{{ $grade['name'] }}</td><td class="code">{{ $grade['code'] }}</td><td class="number">{{ $grade['average'] !== null ? number_format($grade['average'],2) : '-' }}</td><td class="number">{{ number_format($grade['coefficient'],1) }}</td><td class="number">{{ $weighted !== null ? number_format($weighted,2) : '-' }}</td><td class="appreciation">{{ $subjectRemark }}</td></tr>
@empty
<tr><td colspan="6" style="text-align:center;padding:2mm">{{ $labels['no_grades'] }}</td></tr>
@endforelse
<tr class="total-row"><td colspan="3" style="text-align:right">{{ $language === 'fr' ? 'TOTAUX' : 'TOTALS' }}</td><td class="number">{{ number_format($overall['total_coefficient'],1) }}</td><td class="number">{{ $overall['average'] !== null ? number_format($weightedGrandTotal,2) : '-' }}</td><td>{{ $overall['remark'] }}</td></tr>
</tbody></table>

<table class="summary-grid no-break"><tr>
<td><span class="summary-title">{{ $language === 'fr' ? 'Résultat de l’élève' : 'Student result' }}</span><span class="big">{{ $overall['average'] !== null ? number_format($overall['average'],2).' / 20' : '-' }}</span><br>{{ $labels['class_rank'] }}: <strong>{{ $overall['rank'] ? $overall['rank']['position'].' / '.$overall['rank']['total'] : '-' }}</strong></td>
<td><span class="summary-title">{{ $language === 'fr' ? 'Résultats de la classe' : 'Class results' }}</span>{{ $labels['class_average'] }}: <strong>{{ $overall['class_average'] !== null ? number_format($overall['class_average'],2).' / 20' : '-' }}</strong><br>{{ $labels['student_average'] }}: <strong>{{ $overall['average'] !== null ? number_format($overall['average'],2) : '-' }}</strong></td>
<td><span class="summary-title">{{ $labels['attendance'] }}</span>{{ $labels['present'] }}: <strong>{{ $attendance['present'] }}</strong> &nbsp; {{ $labels['absent'] }}: <strong>{{ $attendance['absent'] }}</strong><br>{{ $labels['late'] }}: <strong>{{ $attendance['late'] }}</strong> &nbsp; {{ $labels['attendance_rate'] }}: <strong>{{ number_format($attendance['rate'],1) }}%</strong></td>
<td><span class="summary-title">{{ $language === 'fr' ? 'Travail' : 'Work' }}</span>{{ $labels['remark'] }}: <strong>{{ $overall['remark'] }}</strong><br>{{ $language === 'fr' ? 'Séances' : 'Sessions' }}: <strong>{{ $attendance['total'] }}</strong></td>
</tr></table>

<table class="decision no-break"><tr>
<td><span class="heading">{{ $labels['teacher_appreciation'] }}</span>{{ $academic_decision['teacher_appreciation'] ?? $overall['remark'] ?? '-' }}</td>
<td><span class="heading">{{ $labels['class_council'] }}</span>@if($academic_decision)<div class="decision-label">{{ $academic_decision['label'] }}</div>{{ $academic_decision['class_council_recommendation'] ?? '' }}@else<span class="muted">-</span>@endif</td>
<td class="principal"><span class="heading">{{ $language === 'fr' ? 'Visa du chef d’établissement' : 'Principal’s approval' }}</span>@if($school['principal_name'])<div class="principal-name">{{ $school['principal_title'] }}<br>{{ $school['principal_name'] }}</div>@endif</td>
</tr></table>

<table class="signatures no-break"><tr>
<td><div class="signature-title">{{ $labels['parent_signature'] }}</div><div class="signature-line">{{ $language === 'fr' ? 'Signature' : 'Signature' }}</div></td>
<td><div class="signature-title">{{ $labels['form_master'] }}</div><div class="signature-line">{{ $student['form_master'] ?? '' }}</div></td>
<td><div class="signature-title">{{ $labels['principal'] }}</div>@if($school['stamp_url'])<img class="stamp" src="{{ $school['stamp_url'] }}" alt="">@endif<div class="signature-line">{{ $school['principal_name'] ?? ($language === 'fr' ? 'Cachet et signature' : 'Stamp and signature') }}</div></td>
</tr></table>

<div class="footer">{{ $labels['generated'] }} {{ $school['name'] }} - {{ $generated_at }}@if($school['footer']) - {{ $school['footer'] }}@endif</div>
</body></html>
