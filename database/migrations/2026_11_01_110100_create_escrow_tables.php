<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('description', 300)->nullable();
            $table->unsignedBigInteger('amount'); // kobo held in escrow
            $table->unsignedBigInteger('fee');    // kobo platform fee, snapshotted at creation
            $table->unsignedSmallInteger('fee_bps'); // rate used, for the audit trail
            $table->string('status', 20)->index();
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('auto_release_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('open')->index();
            $table->string('outcome', 20)->nullable(); // release | refund
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // At most one open dispute per escrow is enforced in the service under a row lock.
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->nullableMorphs('subject');
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Pooled customer-funds account; per-escrow attribution is the ledger transaction subject.
        DB::table('ledger_accounts')->insert([
            'type' => 'escrow_holding', 'user_id' => null, 'currency' => 'NGN',
            'balance' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ledger_accounts')->where('type', 'escrow_holding')->whereNull('user_id')->delete();
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('escrows');
    }
};
