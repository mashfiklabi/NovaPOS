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
        Schema::create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('original_payment_id')->nullable()->constrained('sale_payments')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('refund_method')->default('cash');
            $table->string('reason')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('status')->default('completed');
            $table->foreignId('processed_by')->constrained('users');
            $table->timestamp('processed_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('customer_credit_ledgers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('type'); // credit or debit
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->string('reference_number')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credit_ledgers');
        Schema::dropIfExists('sale_refunds');
    }
};
