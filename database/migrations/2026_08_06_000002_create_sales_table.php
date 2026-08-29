<?php

declare(strict_types=1);

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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->date('sale_date');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0.00);
            $table->decimal('discount_amount', 18, 2)->default(0.00);
            $table->decimal('tax_amount', 18, 2)->default(0.00);
            $table->decimal('shipping_cost', 18, 2)->default(0.00);
            $table->decimal('grand_total', 18, 2)->default(0.00);
            $table->decimal('paid_amount', 18, 2)->default(0.00);
            $table->decimal('due_amount', 18, 2)->default(0.00);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
