@if(!empty($school['header_image_url']))
@php
    $headerSettings = $school['header_image_settings'] ?? [];
    $headerMode = in_array($headerSettings['mode'] ?? null, ['fit', 'full_width'], true) ? $headerSettings['mode'] : 'fit';
    $headerWidth = min(100, max(30, (int) ($headerSettings['width'] ?? 100)));
    $configuredHeight = min(200, max(40, (int) ($headerSettings['max_height'] ?? 105)));
    $documentHeightCap = (int) ($maxHeight ?? 105);
    $headerMaxHeight = min($configuredHeight, $documentHeightCap > 0 ? $documentHeightCap : $configuredHeight);
    $headerAlignment = in_array($headerSettings['alignment'] ?? null, ['left', 'center', 'right'], true)
        ? $headerSettings['alignment']
        : 'center';
    $headerImageStyle = $headerMode === 'full_width'
        ? "display:inline-block;width:{$headerWidth}%;height:auto;"
        : "display:inline-block;max-width:{$headerWidth}%;max-height:{$headerMaxHeight}px;width:auto;height:auto;";
@endphp
<div class="official-school-header" style="width:100%;text-align:{{ $headerAlignment }};margin:0 0 {{ $bottomMargin ?? '8px' }} 0;">
    <img
        src="{{ $school['header_image_url'] }}"
        alt=""
        style="{{ $headerImageStyle }}"
    >
</div>
@endif
