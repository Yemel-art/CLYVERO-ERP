<!DOCTYPE html>
<html lang="{{ $language }}"><head><meta charset="utf-8"><style>
@page{margin:18mm}body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:12pt}.header{display:table;width:100%;border-bottom:3px double {{ $school['primary_color'] }};padding-bottom:12px}.brand-logo,.brand-identity{display:table-cell;vertical-align:middle}.brand-logo{width:85px}.brand-logo.right{text-align:right}.brand-logo img{height:70px;max-width:80px;object-fit:contain}.brand-identity{text-align:center}.school{font-size:20pt;font-weight:bold;color:{{ $school['primary_color'] }}}.title{text-align:center;margin:35px 0 25px;font-size:18pt;text-transform:uppercase;text-decoration:underline}.identity{display:table;width:100%;margin:20px 0}.photo,.text{display:table-cell;vertical-align:top}.photo{width:110px}.photo img,.placeholder{width:90px;height:110px;border:1px solid #aaa;object-fit:cover}.placeholder{text-align:center;line-height:110px;background:#eee;font-size:24pt}.text{line-height:2}.signatures{display:table;width:100%;margin-top:70px}.signature{display:table-cell;width:50%;text-align:center}.stamp{height:80px;border:1px dashed #999;margin:auto;width:120px;padding-top:45px;color:#888;font-size:9pt}
</style></head><body>
@include('reports.partials.watermark')
@if($school['header_image_url'])
@include('reports.partials.school-header-image', ['maxHeight' => '110px'])
@else
@if($school['header'])<div style="text-align:center;color:#64748b;font-size:9pt">{{ $school['header'] }}</div>@endif
<div class="header"><div class="brand-logo">@if($school['logo_url'])<img src="{{ $school['logo_url'] }}">@endif</div><div class="brand-identity"><div class="school">{{ $school['name'] }}</div><div>{{ $school['motto'] }}</div><div>{{ $school['address'] }} · {{ $school['phone'] }}</div></div><div class="brand-logo right">@if($school['secondary_logo_url'])<img src="{{ $school['secondary_logo_url'] }}">@endif</div></div>
@endif
<h1 class="title">{{ $language==='fr'?'Certificat de scolarité':'School Enrollment Certificate' }}</h1>
<div class="identity"><div class="photo">@if($student['photo_url'])<img src="{{ $student['photo_url'] }}">@else<div class="placeholder">{{ mb_substr($student['full_name'],0,1) }}</div>@endif</div><div class="text">
@if($language==='fr')
Je soussigné(e), Chef d’établissement de <strong>{{ $school['name'] }}</strong>, certifie que l’élève <strong>{{ $student['full_name'] }}</strong>, matricule <strong>{{ $student['admission_number'] }}</strong>, né(e) le {{ $student['date_of_birth'] }}, est régulièrement inscrit(e) en classe de <strong>{{ $student['class'] }}</strong> pour l’année scolaire <strong>{{ $academic_year }}</strong>.
@else
I, the undersigned Principal of <strong>{{ $school['name'] }}</strong>, certify that <strong>{{ $student['full_name'] }}</strong>, registration number <strong>{{ $student['admission_number'] }}</strong>, born on {{ $student['date_of_birth'] }}, is officially enrolled in <strong>{{ $student['class'] }}</strong> for the <strong>{{ $academic_year }}</strong> academic year.
@endif
</div></div>
<p>{{ $language==='fr'?'Le présent certificat est délivré pour servir et valoir ce que de droit.':'This certificate is issued for all legal and administrative purposes.' }}</p>
<div class="signatures"><div class="signature">{{ $language==='fr'?'Fait le':'Issued on' }} {{ $issued_at }}<br><br><strong>{{ $language==='fr'?'Le Chef d’établissement':'Principal' }}</strong></div><div class="signature"><div class="stamp">{{ $language==='fr'?'Cachet officiel':'Official stamp' }}</div></div></div>
@if($school['principal_name'])<p style="text-align:right"><strong>{{ $school['principal_title'] }}</strong><br>{{ $school['principal_name'] }}</p>@endif
@if($school['footer'])<p style="position:fixed;bottom:9mm;left:0;right:0;text-align:center;color:#64748b;font-size:8pt">{{ $school['footer'] }}</p>@endif
</body></html>
