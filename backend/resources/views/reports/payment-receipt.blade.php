<!DOCTYPE html>
<html lang="{{ $language }}"><head><meta charset="utf-8"><style>
@page{margin:8mm}body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:{{ $copies===3?'7.5pt':($copies===2?'9pt':'11pt') }};margin:0}.receipt{border:1.5px solid {{ $school['primary_color'] }};padding:{{ $copies===3?'6px':'10px' }};margin-bottom:6px;page-break-inside:avoid}.header{display:table;width:100%;border-bottom:1px solid #94a3b8;padding-bottom:5px}.logo,.identity,.copy{display:table-cell;vertical-align:middle}.logo{width:55px}.logo img,.copy img{height:42px;max-width:50px;object-fit:contain}.identity{text-align:center}.school{font-size:1.5em;font-weight:bold;color:{{ $school['primary_color'] }}}.copy{width:90px;text-align:right;font-weight:bold;color:#64748b}.copy-label{margin-top:2px}.title{text-align:center;font-size:1.3em;text-transform:uppercase;margin:6px}.grid{width:100%;border-collapse:collapse}.grid td{padding:2px 5px;border-bottom:1px dotted #cbd5e1}.label{font-weight:bold;width:18%}.amount{font-size:1.25em;font-weight:bold;color:{{ $school['primary_color'] }}}.signatures{display:table;width:100%;margin-top:{{ $copies===3?'12px':'22px' }};}.signature{display:table-cell;width:50%;text-align:center;border-top:1px solid #64748b;padding-top:3px}.footer{text-align:center;color:#64748b;font-size:.85em;margin-top:4px}
</style></head><body>
@include('reports.partials.watermark')
@for($copy=1;$copy<=$copies;$copy++)
<section class="receipt">
@if($school['header_image_url'])
@include('reports.partials.school-header-image', ['maxHeight' => $copies === 3 ? '44px' : ($copies === 2 ? '58px' : '85px'), 'bottomMargin' => '3px'])
<div class="copy-label" style="text-align:right;font-weight:bold;color:#64748b">{{ $copy===1?($language==='fr'?'ORIGINAL':'ORIGINAL'):($copy===2?($language==='fr'?'DUPLICATA':'DUPLICATE'):($language==='fr'?'TRIPLICATA':'TRIPLICATE')) }}</div>
@else
@if($school['header'])<div style="text-align:center;font-size:.8em;color:#64748b">{{ $school['header'] }}</div>@endif
<div class="header"><div class="logo">@if($school['logo_url'])<img src="{{ $school['logo_url'] }}">@endif</div><div class="identity"><div class="school">{{ $school['name'] }}</div><div>{{ $school['motto'] }}</div><div>{{ $school['address'] }} · {{ $school['phone'] }}</div></div><div class="copy">@if($school['secondary_logo_url'])<img src="{{ $school['secondary_logo_url'] }}">@endif<div class="copy-label">{{ $copy===1?($language==='fr'?'ORIGINAL':'ORIGINAL'):($copy===2?($language==='fr'?'DUPLICATA':'DUPLICATE'):($language==='fr'?'TRIPLICATA':'TRIPLICATE')) }}</div></div></div>
@endif
<div class="title">{{ $language==='fr'?'Reçu de paiement':'Payment Receipt' }} · {{ $receipt['number'] }}</div>
<table class="grid">
<tr><td class="label">{{ $language==='fr'?'Élève':'Student' }}</td><td>{{ $receipt['student'] }}</td><td class="label">{{ $language==='fr'?'Matricule':'Registration #' }}</td><td>{{ $receipt['registration_number'] }}</td></tr>
<tr><td class="label">{{ $language==='fr'?'Classe':'Class' }}</td><td>{{ $receipt['class'] }}</td><td class="label">{{ $language==='fr'?'Date':'Date' }}</td><td>{{ $receipt['date'] }}</td></tr>
<tr><td class="label">{{ $language==='fr'?'Motif':'Payment type' }}</td><td colspan="3">{{ $receipt['payment_type'] }}</td></tr>
<tr><td class="label">{{ $language==='fr'?'Montant':'Amount' }}</td><td class="amount">{{ number_format($receipt['amount'],0,',',' ') }} XAF</td><td class="label">{{ $language==='fr'?'Mode':'Method' }}</td><td>{{ str_replace('_',' ',$receipt['method']) }}</td></tr>
<tr><td class="label">{{ $language==='fr'?'En lettres':'In words' }}</td><td colspan="3">{{ ucfirst($receipt['amount_words']) }} {{ $language==='fr'?'francs CFA':'CFA francs' }}</td></tr>
<tr><td class="label">{{ $language==='fr'?'Frais totaux':'Total fees' }}</td><td>{{ number_format($receipt['invoice_total'],0,',',' ') }} XAF</td><td class="label">{{ $language==='fr'?'Total payé':'Total paid' }}</td><td>{{ number_format($receipt['total_paid'],0,',',' ') }} XAF</td></tr>
<tr><td class="label">{{ $language==='fr'?'Reste à payer':'Balance due' }}</td><td class="amount" colspan="3">{{ number_format($receipt['balance'],0,',',' ') }} XAF</td></tr>
@if($receipt['reference'])<tr><td class="label">{{ $language==='fr'?'Référence':'Reference' }}</td><td colspan="3">{{ $receipt['reference'] }}</td></tr>@endif
</table>
<div class="signatures"><div class="signature">{{ $language==='fr'?'Caissier(ère)':'Cashier' }}<br>{{ $receipt['cashier'] }}</div><div class="signature">{{ $language==='fr'?'Signature / Cachet':'Signature / Stamp' }}</div></div>
<div class="footer">{{ $language==='fr'?'Merci pour votre paiement. Conservez ce reçu.':'Thank you for your payment. Please keep this receipt.' }}</div>
@if($school['footer'])<div class="footer">{{ $school['footer'] }}</div>@endif
</section>
@endfor
</body></html>
