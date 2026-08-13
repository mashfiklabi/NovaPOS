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
        // 1. Categories Table
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); // BIGINT primary key
            $table->uuid('uuid')->unique();
            $table->string('name'); // Scoped unique validator handles it
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();
            $table->string('status')->default('active'); // active, inactive

            // Audit Columns
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->index()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });

        // 2. Brands Table
        Schema::create('brands', function (Blueprint $table) {
            $table->id(); // BIGINT primary key
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('status')->default('active'); // active, inactive

            // Audit Columns
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->index()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });

        // 3. Units Table
        Schema::create('units', function (Blueprint $table) {
            $table->id(); // BIGINT primary key
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('short_name')->unique();
            $table->string('allow_decimal')->default('disallowed'); // allowed, disallowed

            // Audit Columns
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->index()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });

        // 4. Products Table
        Schema::create('products', function (Blueprint $table) {
            $table->id(); // BIGINT primary key
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->text('description')->nullable();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();
            $table->foreignId('unit_id')
                ->constrained('units')
                ->cascadeOnDelete();
            $table->decimal('cost_price', 18, 2);
            $table->decimal('selling_price', 18, 2);
            $table->decimal('stock_alert_threshold', 18, 3)->default(0.000);
            $table->decimal('current_stock', 18, 3)->default(0.000);
            $table->string('image')->nullable();
            $table->string('status')->default('active'); // active, inactive, out_of_stock, discontinued

            // New Sprint 3 Requested Product Columns
            $table->boolean('track_stock')->default(true);
            $table->boolean('allow_decimal')->default(false);
            $table->enum('tax_type', ['exclusive', 'inclusive', 'none'])->default('none');
            $table->decimal('tax_rate', 18, 2)->nullable()->default(0.00);

            // Audit Columns
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->index()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
