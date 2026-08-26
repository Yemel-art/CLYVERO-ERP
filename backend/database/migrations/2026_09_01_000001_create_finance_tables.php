<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance — fee structures, invoices, payments.
 *
 * Workflow:
 *   1. Admin defines a fee_structure for an academic year (e.g.
 *      "Tuition – Form 1", amount XAF 250,000). Cafeteria, uniforms,
 *      transport, etc. are fee_categories on the same structure.
 *   2. When a student is enrolled, an invoice is generated with one or
 *      more invoice_items pulled from applicable fee_structures.
 *   3. Parents make payments (cash, bank, Mobile Money). Each payment
 *      references the invoice and reduces the balance.
 *
 * Source: SRS Finance Module + Business Workflow §7.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignUuid('class_id')->nullable()->constrained('school_classes')->nullOnDelete(); // optional, per-class fee
            $table->string('name');                                         // "Tuition", "Cafeteria", "Uniform"
            $table->enum('category', ['tuition', 'cafeteria', 'uniform', 'transport', 'exam', 'other'])->default('tuition');
            $table->decimal('amount', 12, 2);                               // XAF
            $table->enum('frequency', ['one_time', 'monthly', 'termly', 'annual'])->default('annual');
            $table->boolean('is_required')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'category']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();                     // TL-YYYY-NNNNN
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->date('issued_at');
            $table->date('due_at');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->enum('status', ['draft', 'issued', 'partially_paid', 'paid', 'cancelled', 'overdue'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index('due_at');
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('fee_structure_id')->nullable()->constrained('fee_structures')->nullOnDelete();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('receipt_number')->unique();                     // RCT-YYYY-NNNNN
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('paid_at');
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'bank_transfer', 'mtn_momo', 'orange_money', 'cheque', 'other'])->default('cash');
            $table->string('reference')->nullable();                        // mobile money txn id, cheque number, etc.
            $table->text('notes')->nullable();
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'paid_at']);
            $table->index('method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fee_structures');
    }
};
