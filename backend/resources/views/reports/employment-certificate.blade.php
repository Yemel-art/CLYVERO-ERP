<!DOCTYPE html>
<html lang="{{ $language }}"><head><meta charset="utf-8"><style>
@page{margin:18mm}body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:12pt}.header{display:table;width:100%;border-bottom:3px double {{ $school['primary_color'] }};padding-bottom:12px}.brand-logo,.brand-identity{display:table-cell;vertical-align:middle}.brand-logo{width:85px}.brand-logo.right{text-align:right}.brand-logo img{height:70px;max-width:80px;object-fit:contain}.brand-identity{text-align:center}.school{font-size:20pt;font-weight:bold;color:{{ $school['primary_color'] }}}.title{text-align:center;margin:38px 0 30px;font-size:18pt;text-transform:uppercase;text-decoration:underline}.content{line-height:2;text-align:justify}.details{margin:24px 40px;border-collapse:collapse}.details td{padding:5px 10px;border-bottom:1px solid #ddd}.label{font-weight:bold}.signatures{display:table;width:100%;margin-top:70px}.signature{display:table-cell;width:50%;text-align:center}.stamp{height:80px;border:1px dashed #999;margin:10px auto;width:120px;color:#888;font-size:9pt;padding-top:45px}
</style></head><body>
@include('reports.partials.watermark')
@if($school['header_image_url'])
@include('reports.partials.school-header-image', ['maxHeight' => '110px'])
@else
@if($school['header'])<div style="text-align:center;color:#64748b;font-size:9pt">{{ $school['header'] }}</div>@endif
<div class="header"><div class="brand-logo">@if($school['logo_url'])<img src="{{ $school['logo_url'] }}">@endif</div><div class="brand-identity"><div class="school">{{ $school['name'] }}</div><div>{{ $school['motto'] }}</div><div>{{ $school['address'] }} · {{ $school['phone'] }} · {{ $school['email'] }}</div></div><div class="brand-logo right">@if($school['secondary_logo_url'])<img src="{{ $school['secondary_logo_url'] }}">@endif</div></div>
@endif
<h1 class="title">{{ $language==='fr' ? 'Attestation de travail' : 'Certificate of Employment' }}</h1>
<div class="content">
@if($language==='fr')
Je soussigné(e), Chef d’établissement de <strong>{{ $school['name'] }}</strong>, atteste par la présente que <strong>{{ $teacher['full_name'] }}</strong>, matricule <strong>{{ $teacher['employee_number'] }}</strong>, travaille effectivement au sein de notre établissement.
@else
I, the undersigned Principal of <strong>{{ $school['name'] }}</strong>, hereby certify that <strong>{{ $teacher['full_name'] }}</strong>, employee ID <strong>{{ $teacher['employee_number'] }}</strong>, is effectively employed by our institution.
@endif
</div>
<table class="details">
<tr><td class="label">{{ $language==='fr'?'Fonction':'Position' }}</td><td>{{ $teacher['position'] }}</td></tr>
<tr><td class="label">{{ $language==='fr'?'Département':'Department' }}</td><td>{{ $teacher['department'] ?? '—' }}</td></tr>
<tr><td class="label">{{ $language==='fr'?'Statut':'Employment status' }}</td><td>{{ $teacher['status'] }}</td></tr>
<tr><td class="label">{{ $language==='fr'?'Date de prise de service':'Employment start date' }}</td><td>{{ $teacher['hire_date'] }}</td></tr>
<tr><td class="label">{{ $language==='fr'?'Ancienneté':'Years of service' }}</td><td>{{ $teacher['years_of_service'] }} {{ $language==='fr'?'ans':'years' }}</td></tr>
</table>
<p>{{ $language==='fr' ? 'La présente attestation est délivrée à l’intéressé(e) pour servir et valoir ce que de droit.' : 'This certificate is issued to the employee for all legal and administrative purposes.' }}</p>
<div class="signatures"><div class="signature">{{ $language==='fr'?'Fait le':'Issued on' }} {{ $issued_at }}<br><br><strong>{{ $language==='fr'?'Le Chef d’établissement':'Principal' }}</strong></div><div class="signature"><div class="stamp">{{ $language==='fr'?'Cachet officiel':'Official stamp' }}</div></div></div>
@if($school['principal_name'])<p style="text-align:right"><strong>{{ $school['principal_title'] }}</strong><br>{{ $school['principal_name'] }}</p>@endif
@if($school['footer'])<p style="position:fixed;bottom:9mm;left:0;right:0;text-align:center;color:#64748b;font-size:8pt">{{ $school['footer'] }}</p>@endif
</body></html>
