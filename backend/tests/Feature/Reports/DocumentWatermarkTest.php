<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

final class DocumentWatermarkTest extends TestCase
{
    public function test_document_brand_uses_safe_footer_instead_of_an_overlay(): void
    {
        config(['app.name' => 'Clyvero ERP']);

        $watermark = view('reports.partials.watermark')->render();

        $this->assertStringContainsString('Clyvero ERP', $watermark);
        $this->assertStringContainsString('document-brand-footer', $watermark);
        $this->assertStringNotContainsString('opacity:', $watermark);
        $this->assertStringNotContainsString('data:image/svg+xml', $watermark);
        $this->assertStringNotContainsString('<svg', $watermark);
    }

    public function test_dompdf_can_generate_a_pdf_with_the_watermark(): void
    {
        $watermark = view('reports.partials.watermark')->render();
        $output = Pdf::loadHTML("<html><body>{$watermark}<h1>Document test</h1></body></html>")
            ->setPaper('a4')
            ->output();

        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertGreaterThan(500, strlen($output));
    }

    public function test_official_header_image_preserves_the_uploaded_asset_as_a_full_width_image(): void
    {
        $html = view('reports.partials.school-header-image', [
            'school' => ['header_image_url' => '/var/www/html/storage/app/public/schools/header.png'],
            'maxHeight' => '110px',
        ])->render();

        $this->assertStringContainsString('/var/www/html/storage/app/public/schools/header.png', $html);
        $this->assertStringContainsString('width:100%', $html);
        $this->assertStringContainsString('height:auto', $html);
        $this->assertStringContainsString('max-height:110px', $html);
    }
}
