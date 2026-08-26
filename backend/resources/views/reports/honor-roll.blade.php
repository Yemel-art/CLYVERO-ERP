<!DOCTYPE html>
<html lang="{{ $language }}"><head><meta charset="utf-8"><style>
@page{margin:12mm}body{font-family:DejaVu Sans,sans-serif;font-size:10pt;color:#172033}.header{display:table;width:100%;border-bottom:2px solid {{ $school['primary_color'] ?? '#1D4ED8' }};padding-bottom:8px}.logo,.school{display:table-cell;vertical-align:middle}.logo{width:70px}.logo.right{text-align:right}.logo img{height:50px;max-width:65px;object-fit:contain}.school{text-align:center;font-size:18pt;font-weight:bold;color:{{ $school['primary_color'] ?? '#1D4ED8' }}}h1{text-align:center;font-size:16pt;margin:18px}table{width:100%;border-collapse:collapse}th{background:{{ $school['primary_color'] ?? '#1D4ED8' }};color:white;padding:7px}td{border:1px solid #cbd5e1;padding:6px}.num{text-align:right}.footer{text-align:right;margin-top:20px;color:#64748b}
</style></head><body>
@include('reports.partials.watermark')
@if($school['header_image_url'])
@include('reports.partials.school-header-image', ['maxHeight' => '85px'])
@else
<div class="header"><div class="logo">@if($school['logo_url'])<img src="{{ $school['logo_url'] }}">@endif</div><div class="school">{{ $school['name'] }}</div><div class="logo right">@if($school['secondary_logo_url'])<img src="{{ $school['secondary_logo_url'] }}">@endif</div></div>
@endif
<h1>{{ $language==='fr'?'Tableau d’honneur':'Honor Roll' }} · {{ $term }} · {{ $academic_year }}</h1>
<table><thead><tr><th>#</th><th>{{ $language==='fr'?'Élève':'Student' }}</th><th>{{ $language==='fr'?'Matricule':'Registration #' }}</th><th>{{ $language==='fr'?'Classe':'Class' }}</th><th>{{ $language==='fr'?'Moyenne':'Average' }}</th><th>{{ $language==='fr'?'Rang':'Rank' }}</th><th>{{ $language==='fr'?'Catégorie':'Category' }}</th></tr></thead><tbody>
@forelse($rows as $index=>$row)<tr><td>{{ $index+1 }}</td><td>{{ $row['student']['full_name'] }}</td><td>{{ $row['student']['admission_number'] }}</td><td>{{ $row['class'] }}</td><td class="num">{{ number_format($row['average'],2) }}/20</td><td class="num">{{ $row['rank'] }}</td><td>{{ $row['category'] }}</td></tr>@empty<tr><td colspan="7" style="text-align:center">{{ $language==='fr'?'Aucun élève ne satisfait aux critères.':'No student meets the configured criteria.' }}</td></tr>@endforelse
</tbody></table><div class="footer">{{ $language==='fr'?'Généré le':'Generated on' }} {{ $generated_at }}</div>
</body></html>
