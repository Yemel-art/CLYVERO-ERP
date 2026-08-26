<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use NumberFormatter;

class ReceiptService
{
    public function download(Payment $payment, int $copies = 1, string $language = 'fr'): Response
    {
        $payment->load(['student.school', 'student.schoolClass', 'invoice.items', 'receiver']);
        $school = $payment->student?->school;
        abort_if($school === null, 409, 'The payment is not linked to a valid school.');
        $formatter = class_exists(NumberFormatter::class)
            ? new NumberFormatter($language === 'fr' ? 'fr' : 'en', NumberFormatter::SPELLOUT)
            : null;
        $amount = (int) round((float) $payment->amount);
        $amountWords = $formatter?->format($amount) ?: (string) $amount;

        $data = [
            'language' => $language,
            'copies' => $copies,
            'school' => [
                'name' => $school->school_name,
                'motto' => $school->slogan,
                'address' => $school->address,
                'phone' => $school->phone,
                'email' => $school->email,
                'logo_url' => $school->logo && Storage::disk('public')->exists($school->logo)
                    ? Storage::disk('public')->path($school->logo)
                    : null,
                'secondary_logo_url' => $school->secondary_logo && Storage::disk('public')->exists($school->secondary_logo)
                    ? Storage::disk('public')->path($school->secondary_logo)
                    : null,
                'header_image_url' => $school->document_header_image && Storage::disk('public')->exists($school->document_header_image)
                    ? Storage::disk('public')->path($school->document_header_image)
                    : null,
                'header_image_settings' => $school->documentHeaderImageSettings(),
                'primary_color' => $school->primary_color ?: '#1D4ED8',
                'header' => $school->document_header,
                'footer' => $school->document_footer,
            ],
            'receipt' => [
                'number' => $payment->receipt_number,
                'student' => $payment->student?->full_name,
                'registration_number' => $payment->student?->admission_number,
                'class' => $payment->student?->schoolClass?->name,
                'payment_type' => $payment->invoice?->items->pluck('description')->join(', '),
                'amount' => (float) $payment->amount,
                'amount_words' => $amountWords,
                'invoice_total' => (float) $payment->invoice?->total,
                'total_paid' => (float) $payment->invoice?->paid,
                'balance' => (float) $payment->invoice?->balance,
                'date' => $payment->paid_at->locale($language)->translatedFormat('d F Y'),
                'method' => $this->paymentMethod($payment->method, $language),
                'cashier' => $payment->receiver?->full_name,
                'reference' => $payment->reference,
            ],
        ];

        return Pdf::loadView('reports.payment-receipt', $data)
            ->setPaper('a4')
            ->download("receipt-{$payment->receipt_number}.pdf");
    }

    private function paymentMethod(string $method, string $language): string
    {
        $labels = [
            'cash' => ['fr' => 'Espèces', 'en' => 'Cash'],
            'bank_transfer' => ['fr' => 'Virement bancaire', 'en' => 'Bank transfer'],
            'mtn_momo' => ['fr' => 'MTN MoMo', 'en' => 'MTN MoMo'],
            'orange_money' => ['fr' => 'Orange Money', 'en' => 'Orange Money'],
            'cheque' => ['fr' => 'Chèque', 'en' => 'Cheque'],
            'other' => ['fr' => 'Autre', 'en' => 'Other'],
        ];

        return $labels[$method][$language] ?? str_replace('_', ' ', $method);
    }
}
