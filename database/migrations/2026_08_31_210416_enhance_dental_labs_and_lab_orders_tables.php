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
        Schema::table('dental_labs', function (Blueprint $table) {
            $table->string('lab_type')->default('Commercial Lab')->after('name');
            $table->string('account_number')->nullable()->after('lab_type');
            $table->string('portal_url')->nullable()->after('email');
            $table->integer('standard_turnaround_days')->default(7)->after('address');
            $table->decimal('rating', 2, 1)->default(5.0)->after('standard_turnaround_days');
            $table->string('pricing_tier')->default('Standard')->after('rating');
            $table->string('courier_service')->nullable()->after('pricing_tier');
            $table->text('notes')->nullable()->after('is_active');
        });

        Schema::table('lab_orders', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->unique()->after('id');
            $table->string('order_type')->default('Crown')->after('dental_lab_id');
            $table->string('teeth_fdi')->nullable()->after('tooth_number_fdi');
            $table->string('sub_type')->nullable()->after('order_type');
            $table->string('shade_system')->default('VITA Classical')->after('shade');
            $table->string('stump_shade')->nullable()->after('shade_system');
            $table->string('translucency')->nullable()->after('stump_shade');
            $table->string('surface_texture')->nullable()->after('translucency');
            $table->string('occlusal_staining')->nullable()->after('surface_texture');
            $table->string('margin_design')->nullable()->after('occlusal_staining');
            $table->string('impression_type')->default('digital_scan')->after('margin_design');
            $table->text('digital_scan_url')->nullable()->after('impression_type');
            $table->foreignId('fitting_appointment_id')->nullable()->after('delivered_at')->constrained('appointments')->nullOnDelete();
            $table->dateTime('fitting_date')->nullable()->after('fitting_appointment_id');
            $table->string('lab_invoice_number')->nullable()->after('cost');
            $table->decimal('patient_charge', 10, 2)->nullable()->after('cost');
            $table->string('payment_status')->default('pending')->after('lab_invoice_number');
            $table->string('redo_reason')->nullable()->after('status');
            $table->integer('redo_count')->default(0)->after('redo_reason');
            $table->integer('warranty_years')->default(5)->after('redo_count');
            $table->boolean('qc_passed')->default(false)->after('warranty_years');
            $table->string('lab_box_number')->nullable()->after('qc_passed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropForeign(['fitting_appointment_id']);
            $table->dropColumn([
                'tracking_number',
                'order_type',
                'teeth_fdi',
                'sub_type',
                'shade_system',
                'stump_shade',
                'translucency',
                'surface_texture',
                'occlusal_staining',
                'margin_design',
                'impression_type',
                'digital_scan_url',
                'fitting_appointment_id',
                'fitting_date',
                'lab_invoice_number',
                'patient_charge',
                'payment_status',
                'redo_reason',
                'redo_count',
                'warranty_years',
                'qc_passed',
                'lab_box_number',
            ]);
        });

        Schema::table('dental_labs', function (Blueprint $table) {
            $table->dropColumn([
                'lab_type',
                'account_number',
                'portal_url',
                'standard_turnaround_days',
                'rating',
                'pricing_tier',
                'courier_service',
                'notes',
            ]);
        });
    }
};
