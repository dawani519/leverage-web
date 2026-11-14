<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // All amounts are integer kobo. Never floats.
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('currency', 3)->default('NGN');
            // Cached running balance, only ever changed by LedgerService inside a locked transaction.
            $table->bigInteger('balance')->default(0);
            $table->timestamps();

            $table->unique(['type', 'user_id']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('idempotency_key')->unique();
            $table->string('type', 40)->index();
            $table->nullableMorphs('subject');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Append-only: no updated_at, rows are never updated or deleted.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('direction', 6);
            $table->unsignedBigInteger('amount');
            $table->bigInteger('balance_after');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ledger_account_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
    }
};
