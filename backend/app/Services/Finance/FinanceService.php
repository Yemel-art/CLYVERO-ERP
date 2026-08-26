<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Student;
use App\Services\BaseService;
use App\Services\Notification\NotificationService;
use App\Services\TenantContext;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceService extends BaseService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly TenantContext $tenant,
    )
    {
    }
    // ─── Fee Structures ─────────────────────────────────────────────

    public function createFee(array $attributes): FeeStructure
    {
        return $this->transaction(fn () => FeeStructure::create($attributes));
    }

    public function updateFee(FeeStructure $fee, array $attributes): FeeStructure
    {
        $fee->update($attributes);
        return $fee->fresh() ?? $fee;
    }

    public function deleteFee(FeeStructure $fee): void
    {
        $fee->delete();
    }

    // ─── Invoices ───────────────────────────────────────────────────

    /**
     * Generate an invoice for a student in an academic year, based on
     * the fee structures that apply to their class (and any school-wide
     * required fees).
     */
    public function generateInvoiceForStudent(Student $student, AcademicYear $year, ?CarbonImmutable $dueAt = null): Invoice
    {
        return $this->transaction(function () use ($student, $year, $dueAt): Invoice {
            $existing = Invoice::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $year->id)
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing->load(['items', 'student', 'payments']);
            }

            $applicable = FeeStructure::query()
                ->where('academic_year_id', $year->id)
                ->where(function ($q) use ($student): void {
                    $q->whereNull('class_id');
                    if ($student->class_id) $q->orWhere('class_id', $student->class_id);
                })
                ->where('is_required', true)
                ->get();

            $invoice = Invoice::create([
                'invoice_number'   => $this->nextInvoiceNumber(),
                'student_id'       => $student->id,
                'academic_year_id' => $year->id,
                'issued_at'        => now()->toDateString(),
                'due_at'           => $dueAt?->toDateString() ?? now()->addDays(30)->toDateString(),
                'status'           => Invoice::STATUS_ISSUED,
                'created_by'       => Auth::id(),
            ]);

            $subtotal = 0.0;
            if ($applicable->isEmpty()) {
                throw ValidationException::withMessages([
                    'fee_structure' => ['Configure at least one required fee for this academic year or class before generating an invoice.'],
                ]);
            }
            foreach ($applicable as $fee) {
                $line = (float) $fee->amount;
                InvoiceItem::create([
                    'invoice_id'       => $invoice->id,
                    'fee_structure_id' => $fee->id,
                    'description'      => $fee->name,
                    'quantity'         => 1,
                    'unit_amount'      => $line,
                    'line_total'       => $line,
                ]);
                $subtotal += $line;
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'total'    => $subtotal,
                'balance'  => $subtotal,
            ]);

            return $invoice->fresh()?->load(['items', 'student']) ?? $invoice;
        });
    }

    /**
     * Add a custom line item to an existing invoice.
     *
     * @param array{description: string, quantity?: int, unit_amount: float} $item
     */
    public function addInvoiceItem(Invoice $invoice, array $item): Invoice
    {
        return $this->transaction(function () use ($invoice, $item): Invoice {
            $qty = (int) ($item['quantity'] ?? 1);
            $unit = (float) $item['unit_amount'];
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => $item['description'],
                'quantity'    => $qty,
                'unit_amount' => $unit,
                'line_total'  => $qty * $unit,
            ]);
            return $this->recalculate($invoice);
        });
    }

    public function cancelInvoice(Invoice $invoice): Invoice
    {
        return $this->transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->payments()->whereNull('voided_at')->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => ['An invoice with collected payments cannot be cancelled. Void the payments first.'],
                ]);
            }
            $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

            return $invoice->fresh() ?? $invoice;
        });
    }

    // ─── Payments ───────────────────────────────────────────────────

    /**
     * Record a payment against an invoice.
     *
     * @param array{paid_at: string, amount: float, method: string, reference?: ?string, notes?: ?string} $data
     */
    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        return $this->transaction(function () use ($invoice, $data): Payment {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $this->recalculate($lockedInvoice);
            $lockedInvoice->refresh();

            if ($lockedInvoice->status === Invoice::STATUS_CANCELLED) {
                throw new \DomainException('Cannot record a payment on a cancelled invoice.');
            }
            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new \DomainException('Payment amount must be greater than 0.');
            }
            if ($amount > (float) $lockedInvoice->balance + 0.01) {
                throw new \DomainException('Payment amount cannot exceed the outstanding invoice balance.');
            }

            $payment = Payment::create([
                'receipt_number' => $this->nextReceiptNumber(),
                'invoice_id'     => $lockedInvoice->id,
                'student_id'     => $lockedInvoice->student_id,
                'paid_at'        => $data['paid_at'],
                'amount'         => $amount,
                'method'         => $data['method'],
                'reference'      => $data['reference'] ?? null,
                'notes'          => $data['notes'] ?? null,
                'received_by'    => Auth::id(),
            ]);

            $this->recalculate($lockedInvoice);
            $studentName = $lockedInvoice->student()->value(DB::raw("CONCAT(first_name, ' ', last_name)"));
            $this->notifications->broadcastToRole(
                UserRole::Administrator->value,
                'New payment recorded',
                sprintf('%s paid %.0f XAF. Receipt %s.', $studentName, $amount, $payment->receipt_number),
                'success',
            );
            return $payment;
        });
    }

    public function voidPayment(Payment $payment, string $reason): Payment
    {
        return $this->transaction(function () use ($payment, $reason): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->voided_at !== null) {
                return $payment;
            }
            $invoice = Invoice::query()->whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();
            $payment->update([
                'voided_at' => now(),
                'voided_by' => Auth::id(),
                'void_reason' => $reason,
            ]);
            $this->recalculate($invoice);

            return $payment->fresh() ?? $payment;
        });
    }

    // ─── Helpers ────────────────────────────────────────────────────

    /**
     * Recompute totals on an invoice from items and payments. Updates status accordingly.
     */
    public function recalculate(Invoice $invoice): Invoice
    {
        $subtotal = (float) $invoice->items()->sum('line_total');
        $paid     = (float) $invoice->payments()->whereNull('voided_at')->sum('amount');
        $total    = $subtotal - (float) $invoice->discount;
        $balance  = max(0, $total - $paid);

        $status = match (true) {
            $invoice->status === Invoice::STATUS_CANCELLED         => Invoice::STATUS_CANCELLED,
            $balance <= 0.01                                       => Invoice::STATUS_PAID,
            $paid > 0                                              => Invoice::STATUS_PARTIALLY_PAID,
            $invoice->due_at && $invoice->due_at->isPast()         => Invoice::STATUS_OVERDUE,
            default                                                => Invoice::STATUS_ISSUED,
        };

        $invoice->update([
            'subtotal' => $subtotal,
            'total'    => $total,
            'paid'     => $paid,
            'balance'  => $balance,
            'status'   => $status,
        ]);

        return $invoice->fresh() ?? $invoice;
    }

    public function studentBalance(string $studentId): float
    {
        return (float) Invoice::where('student_id', $studentId)
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED])
            ->sum('balance');
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $today = now()->toDateString();
        $payments = Payment::query()->whereNull('voided_at');
        $todayPayments = (clone $payments)
            ->where('paid_at', $today)
            ->with(['student:id,first_name,last_name,admission_number', 'invoice:id,balance'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return [
            'date' => $today,
            'collected_today' => (float) (clone $payments)->where('paid_at', $today)->sum('amount'),
            'total_collected' => (float) (clone $payments)->sum('amount'),
            'total_outstanding' => (float) Invoice::query()->where('status', '!=', Invoice::STATUS_CANCELLED)->sum('balance'),
            'total_overdue' => (float) Invoice::query()->where('status', Invoice::STATUS_OVERDUE)->sum('balance'),
            'students_registered_today' => Student::query()->whereDate('created_at', $today)->count(),
            'payments_received_today' => $todayPayments->count(),
            'recent_payments' => $todayPayments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'amount' => (float) $payment->amount,
                'paid_at' => $payment->paid_at->toDateString(),
                'invoice_id' => $payment->invoice_id,
                'invoice_balance' => (float) ($payment->invoice?->balance ?? 0),
                'student' => $payment->student ? [
                    'id' => $payment->student->id,
                    'full_name' => $payment->student->full_name,
                    'admission_number' => $payment->student->admission_number,
                ] : null,
            ])->values()->all(),
        ];
    }

    private function nextInvoiceNumber(): string
    {
        $year = (int) now()->year;
        $prefix = sprintf('%s-INV-%d-', $this->tenant->schoolCode(), $year);
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["invoice-number:{$prefix}"]);
        $last = Invoice::where('invoice_number', 'like', $prefix . '%')->orderByDesc('invoice_number')->value('invoice_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return sprintf('%s%05d', $prefix, $next);
    }

    private function nextReceiptNumber(): string
    {
        $year = (int) now()->year;
        $prefix = sprintf('%s-RCT-%d-', $this->tenant->schoolCode(), $year);
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["receipt-number:{$prefix}"]);
        $last = Payment::where('receipt_number', 'like', $prefix . '%')->orderByDesc('receipt_number')->value('receipt_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return sprintf('%s%05d', $prefix, $next);
    }
}
