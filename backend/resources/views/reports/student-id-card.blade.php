<!DOCTYPE html>
<html lang="{{ $language }}">
<head>
<meta charset="utf-8">
@php
    $cardStyle = $school['student_id_card_settings'] ?? [
        'background_color' => '#FFFFFF', 'border_color' => $school['primary_color'],
        'accent_color' => $school['primary_color'], 'text_color' => '#111827',
        'border_width' => 3, 'corner_style' => 'soft', 'spacing' => 'standard',
        'header_height' => 16, 'header_image_width' => 100, 'year_gap' => 1, 'font_scale' => 100, 'photo_size' => 'standard',
        'show_title' => true, 'title_fr' => 'Carte d’identité scolaire', 'title_en' => 'Student Identity Card',
        'show_motto' => true, 'show_cameroon_flag' => true, 'flag_size' => 10,
        'show_stamp' => true, 'stamp_label' => 'School stamp',
        'show_signature' => true, 'signature_label' => 'Authorized signature',
    ];
    $borderMm = [1 => 0.25, 2 => 0.5, 3 => 0.9, 4 => 1.4][$cardStyle['border_width']] ?? 0.9;
    $cardWidthMm = 85.6 - (2 * $borderMm);
    $cardHeightMm = 54 - (2 * $borderMm);
    $cornerRadius = ['square' => '0', 'soft' => '1.2mm', 'rounded' => '3mm'][$cardStyle['corner_style']] ?? '1.2mm';
    $headerHeight = $cardStyle['header_height'] ?? 16;
    $bodyTop = $headerHeight + ($cardStyle['year_gap'] ?? 1);
    $fontSize = 5.7 * (($cardStyle['font_scale'] ?? 100) / 100);
    $photoHeight = ['small' => 18.8, 'standard' => 21.8, 'large' => 24.2][$cardStyle['photo_size'] ?? 'standard'];
    $headerImageMaxHeight = max(5, $headerHeight - ($cardStyle['show_title'] ? 4 : 1.2));
@endphp
<style>
@page{margin:0;size:85.6mm 54mm}*{box-sizing:border-box}body{margin:0;font-family:DejaVu Sans,sans-serif;color:#111827}.card{position:relative;width:85.6mm;height:54mm;overflow:hidden;background:#fff;border:1.7mm solid {{ $school['primary_color'] }}}.inner{position:absolute;inset:1mm;border:.25mm solid {{ $school['primary_color'] }};overflow:hidden}.accent{position:absolute;top:0;right:0;width:22mm;height:22mm;background:{{ $school['primary_color'] }};opacity:.07;transform:rotate(45deg) translate(7mm,-11mm)}.header{height:13.6mm;padding:.8mm 1.4mm .4mm;border-bottom:.45mm solid {{ $school['primary_color'] }};text-align:center;overflow:hidden}.official-school-header{margin:0!important}.header-table{width:100%;height:9.5mm;border-collapse:collapse}.logo{width:13mm;text-align:center;vertical-align:middle}.logo img{max-width:11mm;max-height:9mm}.school{text-align:center;vertical-align:middle;line-height:1.05}.country{font-size:5.4pt;font-weight:700;text-transform:uppercase}.school-name{margin-top:.4mm;font-size:9.2pt;font-weight:800;text-transform:uppercase;color:{{ $school['primary_color'] }}}.motto{font-size:4.7pt;color:#4b5563}.card-title{margin-top:.1mm;font-size:7.3pt;font-weight:800;letter-spacing:.35pt;text-transform:uppercase}.content{display:table;width:100%;height:34.8mm;padding:1.3mm 1.4mm .9mm}.photo-side,.info-side{display:table-cell;vertical-align:top}.photo-side{width:24mm}.photo-frame{width:22.5mm;height:26.7mm;padding:.55mm;border:.35mm solid {{ $school['primary_color'] }};background:#fff}.photo{width:100%;height:100%;object-fit:cover}.photo-placeholder{width:100%;height:100%;background:#e5e7eb;color:{{ $school['primary_color'] }};text-align:center;font-size:22pt;font-weight:700;line-height:25.2mm}.matricule{width:22.5mm;margin-top:.7mm;padding:.55mm .6mm;background:{{ $school['primary_color'] }};color:#fff;font-size:5.7pt;font-weight:800;text-align:center;white-space:nowrap;overflow:hidden}.info-side{position:relative;padding-left:1.6mm;font-size:6.35pt;line-height:1.32}.year{display:inline-block;margin-bottom:.9mm;padding:.55mm 1.2mm;border-radius:2mm;background:{{ $school['primary_color'] }};color:#fff;font-size:6.2pt;font-weight:800}.row{margin-bottom:.55mm;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.label{display:inline-block;min-width:13.5mm;color:#4b5563}.value{font-weight:800}.birth{display:table;width:100%;margin-bottom:.55mm}.birth-cell{display:table-cell;white-space:nowrap}.class-row{margin-top:.6mm;padding:.8mm 1.1mm;border-left:.8mm solid {{ $school['primary_color'] }};background:#f3f4f6;font-size:6.6pt;line-height:1.2}.class-value{font-weight:800;color:{{ $school['primary_color'] }}}.security-mark{position:absolute;right:1.5mm;bottom:1.4mm;width:8.5mm;height:8.5mm;border:.35mm solid {{ $school['primary_color'] }};color:{{ $school['primary_color'] }};text-align:center;font-size:4.2pt;font-weight:800;line-height:8.5mm;transform:rotate(-7deg)}.footer{position:absolute;left:2mm;right:2mm;bottom:.6mm;text-align:center;font-size:3.8pt;color:#6b7280}
.inner{position:relative;left:auto;top:auto;margin:1mm;width:80.2mm;height:48.6mm}
.header{height:12mm;padding:.5mm 1.4mm .2mm}.header-table{height:8.2mm}.school-name{font-size:8.4pt}.card-title{font-size:6.7pt}.content-table{position:absolute;left:1.3mm;top:13mm;width:77.4mm;height:29.5mm;margin:0;border-collapse:collapse}.content-table td{vertical-align:top}.photo-cell{width:24mm}.info-cell{padding-left:1.6mm;font-size:6.1pt;line-height:1.27}.photo-frame{height:24.5mm}.photo-placeholder{line-height:23mm}.security-mark{bottom:.5mm}
.content-safe{position:absolute;left:1.3mm;top:13mm;width:77.4mm;height:29.5mm}.content-safe .photo-cell{float:left;width:24mm}.content-safe .info-cell{display:block;margin-left:24mm;padding-left:1.6mm;font-size:6.1pt;line-height:1.27}
.card{width:82.2mm;height:50.6mm}.inner{width:76.8mm;height:45.2mm}.content-table{left:1.3mm;top:13mm;width:74mm;height:29mm}
.header{height:14.2mm}.content-table{top:14.5mm;height:27.5mm}.class-row{margin-right:9mm;font-size:5.7pt}.class-row .label{min-width:9mm}.class-value{font-size:5.35pt}.security-mark{right:.5mm;width:7mm;height:7mm;line-height:7mm;font-size:3.5pt}
.footer{left:27mm;right:2mm;text-align:left;font-size:3.5pt}
.details-grid{width:100%;border-collapse:collapse;table-layout:fixed}.details-grid td{padding:.45mm 0;vertical-align:top}.detail-label{width:14mm;color:#4b5563;white-space:nowrap}.detail-value{font-weight:800;overflow:hidden}.details-grid .class-label,.details-grid .class-detail{padding:.7mm .8mm;background:#f3f4f6;border-top:.25mm solid #e5e7eb;border-bottom:.25mm solid #e5e7eb}.details-grid .class-label{border-left:.8mm solid {{ $school['primary_color'] }};color:#4b5563}.details-grid .class-detail{height:5.8mm;color:{{ $school['primary_color'] }};font-size:5.35pt;font-weight:800;line-height:1.15;word-wrap:break-word}.footer{left:26mm}
.info-cell{font-size:5.7pt;line-height:1.12}.year{margin-bottom:.45mm;padding:.4mm 1mm;font-size:5.8pt}.details-grid td{padding:.23mm 0}.details-grid .class-label,.details-grid .class-detail{padding:.45mm .65mm}.details-grid .class-detail{height:4.9mm;font-size:5.15pt;line-height:1.08}
.header{height:16.4mm}.content-table{top:16.7mm;height:27.5mm}
.photo-frame{height:21.8mm}.photo-placeholder{line-height:20.3mm}
.card{width:{{ $cardWidthMm }}mm;height:{{ $cardHeightMm }}mm;border-width:{{ $borderMm }}mm;border-color:{{ $cardStyle['border_color'] }};border-radius:{{ $cornerRadius }};background:{{ $cardStyle['background_color'] }};color:{{ $cardStyle['text_color'] }}}
.inner{border-color:{{ $cardStyle['border_color'] }};border-radius:{{ $cornerRadius }}}
.accent{background:{{ $cardStyle['accent_color'] }}}
.header{border-color:{{ $cardStyle['border_color'] }}}
.school-name,.class-value,.details-grid .class-detail{color:{{ $cardStyle['accent_color'] }}}
.photo-frame{border-color:{{ $cardStyle['border_color'] }}}
.matricule,.year{background:{{ $cardStyle['accent_color'] }}}
.details-grid .class-label{border-left-color:{{ $cardStyle['accent_color'] }}}
@if($cardStyle['spacing'] === 'compact')
.info-cell{font-size:5.35pt}.details-grid td{padding:.12mm 0}.details-grid .class-label,.details-grid .class-detail{padding:.3mm .55mm}
@endif
.inner{position:relative;left:auto;top:auto;width:100%;height:100%;margin:0;border:0;border-radius:0}
.header{height:{{ $headerHeight }}mm}
.content-table{top:{{ $bodyTop }}mm}
.info-cell{font-size:{{ $fontSize }}pt}
.photo-frame{height:{{ $photoHeight }}mm}.photo-placeholder{line-height:{{ $photoHeight - 1.5 }}mm}
.photo{object-fit:contain;background:#FFFFFF}
.id-header-image{display:block;margin:0 auto;max-width:{{ $cardStyle['header_image_width'] }}%;max-height:{{ $headerImageMaxHeight }}mm;width:auto;height:auto}
.cameroon-flag-svg{position:absolute;z-index:5;top:1mm;left:1mm;width:{{ $cardStyle['flag_size'] }}mm;height:{{ $cardStyle['flag_size'] }}mm}
.cameroon-flag{position:absolute;z-index:5;top:0;left:0;width:{{ $cardStyle['flag_size'] }}mm;height:{{ $cardStyle['flag_size'] }}mm;overflow:hidden}.cameroon-flag span{position:absolute;display:block;top:0;left:0;width:0;height:0;background:transparent}.flag-green{border-top:{{ $cardStyle['flag_size'] }}mm solid #007A5E;border-right:{{ $cardStyle['flag_size'] }}mm solid transparent}.flag-red{border-top:{{ $cardStyle['flag_size'] * 0.68 }}mm solid #CE1126;border-right:{{ $cardStyle['flag_size'] * 0.68 }}mm solid transparent}.flag-yellow{border-top:{{ $cardStyle['flag_size'] * 0.35 }}mm solid #FCD116;border-right:{{ $cardStyle['flag_size'] * 0.35 }}mm solid transparent}
.authorization{position:absolute;right:1mm;bottom:.8mm;width:18mm;height:8mm;text-align:center;font-size:3.3pt;color:{{ $cardStyle['text_color'] }}}.stamp-image{float:right;width:7mm;height:7mm;object-fit:contain}.stamp-placeholder{float:right;width:7mm;height:7mm;padding-top:2.1mm;border:.25mm solid {{ $cardStyle['accent_color'] }};border-radius:50%;color:{{ $cardStyle['accent_color'] }};font-size:2.6pt;line-height:1.05;overflow:hidden}.signature{float:left;width:10mm;margin-top:4.5mm;border-top:.2mm solid {{ $cardStyle['text_color'] }};padding-top:.3mm;line-height:1.05}.details-grid .class-detail{padding-right:.65mm}
</style>
</head>
<body><div class="card"><div class="inner"><div class="accent"></div>
@if($cardStyle['show_cameroon_flag'])<img class="cameroon-flag-svg" src="{{ public_path('images/cameroon-corner-flag.svg') }}" alt="">@endif
<div class="header">
@if($school['header_image_url'])
<img class="id-header-image" src="{{ $school['header_image_url'] }}" alt="">
@if($cardStyle['show_title'])<div class="card-title">{{ $language === 'fr' ? $cardStyle['title_fr'] : $cardStyle['title_en'] }}</div>@endif
@else
<table class="header-table"><tr><td class="logo">@if($school['logo_url'])<img src="{{ $school['logo_url'] }}">@endif</td><td class="school"><div class="country">{{ $language === 'fr' ? 'République du Cameroun · Paix – Travail – Patrie' : 'Republic of Cameroon · Peace – Work – Fatherland' }}</div><div class="school-name">{{ $school['name'] }}</div>@if($cardStyle['show_motto'] && $school['motto'])<div class="motto">{{ $school['motto'] }}</div>@endif</td><td class="logo">@if($school['secondary_logo_url'])<img src="{{ $school['secondary_logo_url'] }}">@endif</td></tr></table>
@if($cardStyle['show_title'])<div class="card-title">{{ $language === 'fr' ? $cardStyle['title_fr'] : $cardStyle['title_en'] }}</div>@endif
@endif
</div>
<table class="content-table"><tr><td class="photo-cell"><div class="photo-frame">
@if($student['photo_path'])<img class="photo" src="{{ $student['photo_path'] }}" alt="">@else<div class="photo-placeholder">{{ mb_strtoupper(mb_substr($student['first_name'],0,1).mb_substr($student['last_name'],0,1)) }}</div>@endif
</div><div class="matricule">{{ $language === 'fr' ? 'MAT.' : 'ID' }} {{ $student['official_matricule'] ?: $student['admission_number'] }}</div></td>
<td class="info-cell"><div class="year">{{ $language === 'fr' ? 'ANNÉE SCOLAIRE' : 'ACADEMIC YEAR' }} · {{ $academic_year }}</div>
<table class="details-grid">
<tr><td class="detail-label">{{ $language === 'fr' ? 'Nom :' : 'Last name:' }}</td><td class="detail-value">{{ mb_strtoupper($student['last_name']) }}</td></tr>
<tr><td class="detail-label">{{ $language === 'fr' ? 'Prénom(s) :' : 'First name(s):' }}</td><td class="detail-value">{{ mb_strtoupper(trim($student['first_name'].' '.$student['middle_name'])) }}</td></tr>
<tr><td class="detail-label">{{ $language === 'fr' ? 'Né(e) le :' : 'Born on:' }}</td><td class="detail-value">{{ $student['date_of_birth'] ?: '—' }}</td></tr>
<tr><td class="detail-label">{{ $language === 'fr' ? 'À :' : 'At:' }}</td><td class="detail-value">{{ mb_strtoupper($student['place_of_birth'] ?: '—') }}</td></tr>
<tr><td class="detail-label">{{ $language === 'fr' ? 'Sexe / Âge :' : 'Gender / Age:' }}</td><td class="detail-value">{{ $student['gender'] === 'female' ? 'F' : ($student['gender'] === 'male' ? 'M' : '—') }} / {{ $student['age'] ?? '—' }}{{ $language === 'fr' ? ' ans' : ' yrs' }}</td></tr>
<tr><td class="class-label">{{ $language === 'fr' ? 'Classe :' : 'Class:' }}</td><td class="class-detail">{{ $student['class_label'] }}</td></tr>
</table></td></tr></table>
@if($cardStyle['show_stamp'] || $cardStyle['show_signature'])<div class="authorization">
@if($cardStyle['show_signature'])<div class="signature">{{ $cardStyle['signature_label'] }}</div>@endif
@if($cardStyle['show_stamp'])
    @if($school['student_id_card_stamp_url'] ?? null)<img class="stamp-image" src="{{ $school['student_id_card_stamp_url'] }}" alt="">@else<div class="stamp-placeholder">{{ $cardStyle['stamp_label'] }}</div>@endif
@endif
</div>@endif
</div></div></body></html>
