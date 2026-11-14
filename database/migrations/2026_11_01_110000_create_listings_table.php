<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('category', 60)->index();
            $table->string('condition', 20)->default('new');
            $table->string('listing_type', 20)->default('single');
            $table->unsignedBigInteger('price'); // kobo
            $table->string('location')->nullable();
            $table->boolean('offers_shipping')->default(false);
            $table->boolean('leverage_fee_supported')->default(false);
            $table->json('images')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
