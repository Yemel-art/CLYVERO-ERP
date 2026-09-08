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
        $this->assertStringContainsString('max-height:105px', $html);
    }

    public function test_student_id_card_is_bilingual_and_uses_the_official_header(): void
    {
        $base = [
            'school' => [
                'name' => 'Clyvero Test School', 'motto' => 'Learn and serve',
                'address' => 'Yaoundé', 'phone' => '600000000',
                'logo_url' => null, 'secondary_logo_url' => null,
                'header_image_url' => '/var/www/html/storage/app/public/schools/header.png',
                'header_image_settings' => ['mode' => 'fit', 'width' => 100, 'max_height' => 105, 'alignment' => 'center'],
                'primary_color' => '#1D4ED8', 'header' => null,
            ],
            'student' => [
                'first_name' => 'Jordan', 'last_name' => 'Kenfack', 'middle_name' => 'Junior',
                'admission_number' => 'CLY-2026-0001', 'official_matricule' => 'FFRR0001AB',
                'date_of_birth' => '11-05-2010', 'place_of_birth' => 'Douala',
                'age' => 16, 'gender' => 'male', 'class_label' => 'Form 3 Electrical Engineering', 'photo_path' => null,
            ],
            'academic_year' => '2026-2027',
        ];

        $french = view('reports.student-id-card', $base + ['language' => 'fr'])->render();
        $english = view('reports.student-id-card', $base + ['language' => 'en'])->render();

        $this->assertStringContainsString('Carte d’identité scolaire', $french);
        $this->assertStringContainsString('Student Identity Card', $english);
        $this->assertStringContainsString('/var/www/html/storage/app/public/schools/header.png', $french);
        $this->assertStringContainsString('FFRR0001AB', $french);
        $this->assertStringContainsString('2026-2027', $english);
        $this->assertStringContainsString('Form 3 Electrical Engineering', $english);

        $pdf = Pdf::loadView('reports.student-id-card', $base + ['language' => 'fr'])
            ->setPaper([0, 0, 242.65, 153.07])
            ->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
