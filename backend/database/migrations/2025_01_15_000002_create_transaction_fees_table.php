<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
            $table->enum('fee_type', ['platform', 'processing', 'stripe'])->default('platform');
            $table->decimal('amount', 8, 2);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'fee_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_fees');
    }
};
