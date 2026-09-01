<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Finance\GenerateInvoiceRequest;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Http\Requests\Finance\ReceiptRequest;
use App\Http\Requests\Finance\StoreFeeRequest;
use App\Http\Resources\FeeStructureResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Services\Finance\FinanceService;
use App\Services\Finance\ReceiptService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FinanceController extends ApiController
{
    public function __construct(
        private readonly FinanceService $finance,
        private readonly ReceiptService $receipts,
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('finance.view'), 403);
        abort_if($request->user()?->isParent(), 403);

        return $this->ok($this->finance->summary(), 'Finance summary retrieved.');
    }

    // ─── Fee Structures ─────────────────────────────────────────────

    public function listFees(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('fee.view'), 403);
        $q = FeeStructure::query()->with('schoolClass');
        if ($request->filled('academic_year_id')) $q->where('academic_year_id', $request->input('academic_year_id'));
        if ($request->filled('category'))         $q->where('category', $request->input('category'));
        return $this->ok(FeeStructureResource::collection($q->orderBy('category')->get()), 'Fee structures retrieved.');
    }

    public function storeFee(StoreFeeRequest $request): JsonResponse
    {
        return $this->created(new FeeStructureResource($this->finance->createFee($request->validated())), 'Fee created.');
    }

    public function updateFee(StoreFeeRequest $request, FeeStructure $fee): JsonResponse
    {
        return $this->ok(new FeeStructureResource($this->finance->updateFee($fee, $request->validated())), 'Fee updated.');
    }

    public function destroyFee(Request $request, FeeStructure $fee): JsonResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $this->finance->deleteFee($fee);
        return $this->ok(null, 'Fee deleted.');
    }

    // ─── Invoices ───────────────────────────────────────────────────

    public function listInvoices(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('invoice.view'), 403);
        // Payments are included so the finance dashboard can present an
        // auditable daily collection ledger without issuing one query per invoice.
        $q = Invoice::query()->with(['student', 'payments']);
        if ($request->user()?->isParent()) {
            $q->whereHas('student.parents', fn ($parents) => $parents
                ->where('parents.user_id', $request->user()->id)
                ->where('parents.is_active', true));
        }
        if ($request->filled('student_id')) $q->where('student_id', $request->input('student_id'));
        if ($request->filled('status'))     $q->where('status', $request->input('status'));
        if ($request->filled('q')) {
            $like = '%' . $request->input('q') . '%';
            $q->where(fn ($q) => $q->where('invoice_number', 'ilike', $like)
                ->orWhereHas('student', fn ($s) => $s->where('first_name', 'ilike', $like)->orWhere('last_name', 'ilike', $like)));
        }
        $page = $q->orderByDesc('issued_at')->paginate((int) $request->integer('per_page', 25));
        return $this->ok(
            InvoiceResource::collection($page->items()),
            'Invoices retrieved.',
            meta: ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        );
    }

    public function showInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('invoice.view'), 403);
        $this->authorize('view', $invoice->student);
        return $this->ok(
            new InvoiceResource($invoice->load(['student', 'items', 'payments'])),
            'Invoice retrieved.',
        );
    }

    public function generateInvoice(GenerateInvoiceRequest $request): JsonResponse
    {
        /** @var Student $student */
        $student = Student::findOrFail($request->input('student_id'));
        /** @var AcademicYear $year */
        $year = AcademicYear::findOrFail($request->input('academic_year_id'));
        $dueAt = $request->filled('due_at') ? CarbonImmutable::parse($request->input('due_at')) : null;
        $invoice = $this->finance->generateInvoiceForStudent($student, $year, $dueAt);
        return $this->created(new InvoiceResource($invoice->load(['items', 'student'])), 'Invoice generated.');
    }

    public function cancelInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        return $this->ok(new InvoiceResource($this->finance->cancelInvoice($invoice)), 'Invoice cancelled.');
    }

    // ─── Payments ───────────────────────────────────────────────────

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $payment = $this->finance->recordPayment($invoice, $request->validated());
        return $this->created(new PaymentResource($payment), 'Payment recorded.');
    }

    public function voidPayment(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return $this->ok(
            new PaymentResource($this->finance->voidPayment($payment, $validated['reason'])),
            'Payment voided.',
        );
    }

    public function receipt(ReceiptRequest $request, Payment $payment): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $payment->student);
        abort_if($payment->voided_at !== null, 409, 'A voided payment cannot produce a valid receipt.');

        return $this->receipts->download(
            $payment,
            $request->integer('copies', 1),
            $request->string('language', 'fr')->toString(),
        );
    }

    public function studentBalance(Request $request, string $studentId): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('invoice.view'), 403);
        $student = Student::findOrFail($studentId);
        $this->authorize('view', $student);
        return $this->ok(['balance' => $this->finance->studentBalance($studentId)], 'Balance retrieved.');
    }
}
