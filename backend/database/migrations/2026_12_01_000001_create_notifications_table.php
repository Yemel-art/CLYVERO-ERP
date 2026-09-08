<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications — in-app messages delivered to one or many users.
 *
 * Kept simple by design (no separate channel/template machinery yet):
 * a row per recipient with a title, body, and read_at timestamp.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('info');               // info | success | warning | danger
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();                      // free-form context (e.g. ['link' => '/admin/finance/invoices/xxx'])
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
