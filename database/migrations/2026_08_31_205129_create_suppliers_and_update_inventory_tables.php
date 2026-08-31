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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_number')->nullable();
            $table->text('address')->nullable();
            $table->string('payment_terms')->nullable()->default('Net 30');
            $table->integer('lead_time_days')->default(3);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('practice_id')->constrained('suppliers')->nullOnDelete();
            $table->string('brand')->nullable()->after('name');
            $table->string('sub_category')->nullable()->after('category');
            $table->decimal('unit_price', 10, 2)->default(0.00)->after('unit');
            $table->decimal('selling_price', 10, 2)->nullable()->after('unit_price');
            $table->string('storage_location')->nullable()->after('reorder_quantity');
            $table->boolean('has_expiration')->default(false)->after('storage_location');
            $table->string('barcode')->nullable()->after('sku');
            $table->text('description')->nullable()->after('barcode');
            $table->boolean('is_active')->default(true)->after('description');
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('inventory_item_id')->constrained('suppliers')->nullOnDelete();
            $table->date('received_date')->nullable()->after('quantity_remaining');
            $table->string('notes')->nullable()->after('received_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'received_date', 'notes']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn([
                'supplier_id',
                'brand',
                'sub_category',
                'unit_price',
                'selling_price',
                'storage_location',
                'has_expiration',
                'barcode',
                'description',
                'is_active',
            ]);
        });

        Schema::dropIfExists('suppliers');
    }
};
