<?php

namespace Database\Seeders;

use App\Models\ClinicExpense;
use App\Models\DentalLab;
use App\Models\DoctorCommission;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Practice;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DentalFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $practice = Practice::firstOrCreate(
            ['name' => 'Main Dental Clinic'],
            ['currency' => 'USD', 'timezone' => 'UTC', 'is_active' => true]
        );

        $doctor = User::firstOrCreate(
            ['email' => 'doctor@dentalcare.com'],
            ['name' => 'Dr. Sarah Jenkins DDS', 'password' => bcrypt('password')]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@dentalcare.com'],
            ['name' => 'Dr. Michael Admin', 'password' => bcrypt('password')]
        );

        $patients = Patient::all();
        if ($patients->isEmpty()) {
            return;
        }

        $labs = DentalLab::all();
        $suppliers = Supplier::all();

        // 1. Seed Invoices & Items & Payments & Installments
        // Case 1: Comprehensive Smile Makeover (6-Unit Veneers)
        $inv1 = Invoice::firstOrCreate(
            ['invoice_number' => 'INV-2026-00101'],
            [
                'practice_id' => $practice->id,
                'patient_id' => $patients[1]->id ?? $patients[0]->id,
                'subtotal' => 7200.00,
                'discount_amount' => 200.00,
                'tax_amount' => 0.00,
                'total_amount' => 7000.00,
                'paid_amount' => 3500.00,
                'balance_due' => 3500.00,
                'status' => 'partially_paid',
                'issue_date' => Carbon::now()->subDays(15),
                'due_date' => Carbon::now()->addDays(45),
                'terms_and_conditions' => '50% deposit upon preparation, 50% financed across 6 monthly installments.',
                'notes' => 'Aesthetic 6-unit e.max veneer reconstruction teeth #13-#23.',
            ]
        );

        InvoiceItem::firstOrCreate(
            ['invoice_id' => $inv1->id, 'description' => 'Porcelain Laminate Veneer IPS e.max CAD (Tooth #13 to #23, 6 Units)'],
            ['quantity' => 6, 'unit_price' => 1200.00, 'total_price' => 7200.00]
        );

        $p1 = Payment::firstOrCreate(
            ['transaction_reference' => 'POS-CARD-99301'],
            [
                'practice_id' => $practice->id,
                'invoice_id' => $inv1->id,
                'patient_id' => $inv1->patient_id,
                'amount' => 3500.00,
                'payment_method' => 'card_pos',
                'logged_by_user_id' => $admin->id,
                'paid_at' => Carbon::now()->subDays(15),
                'notes' => '50% initial surgical & preparation deposit.',
            ]
        );

        // Doctor Commission for Case 1 (40% of net after $1,140 lab fee)
        DoctorCommission::firstOrCreate(
            ['payment_id' => $p1->id],
            [
                'doctor_id' => $doctor->id,
                'gross_amount' => 3500.00,
                'lab_deduction_amount' => 570.00, // 50% of lab fee allocated to deposit
                'commission_percentage' => 40.00,
                'commission_amount' => 1172.00,
                'status' => 'accrued',
            ]
        );

        // Installment Plan for Remaining $3,500
        $plan1 = InstallmentPlan::firstOrCreate(
            ['invoice_id' => $inv1->id],
            [
                'total_funded_amount' => 7000.00,
                'down_payment' => 3500.00,
                'number_of_installments' => 6,
                'frequency' => 'monthly',
                'status' => 'active',
                'notes' => 'Financing contract 6 monthly payments of $583.33',
            ]
        );
        $plan1->generateSchedules(Carbon::now()->addDays(15));

        // Case 2: Implant Custom Abutment & Crown (Tooth #21)
        $inv2 = Invoice::firstOrCreate(
            ['invoice_number' => 'INV-2026-00102'],
            [
                'practice_id' => $practice->id,
                'patient_id' => $patients[2]->id ?? $patients[0]->id,
                'subtotal' => 2400.00,
                'discount_amount' => 0.00,
                'tax_amount' => 0.00,
                'total_amount' => 2400.00,
                'paid_amount' => 2400.00,
                'balance_due' => 0.00,
                'status' => 'paid',
                'issue_date' => Carbon::now()->subDays(10),
                'due_date' => Carbon::now()->subDays(5),
                'notes' => 'Straumann BLX custom titanium abutment & screw-retained zirconia crown.',
            ]
        );

        InvoiceItem::firstOrCreate(
            ['invoice_id' => $inv2->id, 'description' => 'Titanium Custom Milled Abutment & Screw-Retained Zirconia Crown (#21)'],
            ['quantity' => 1, 'unit_price' => 2400.00, 'total_price' => 2400.00]
        );

        $p2 = Payment::firstOrCreate(
            ['transaction_reference' => 'IP-WIRE-449102'],
            [
                'practice_id' => $practice->id,
                'invoice_id' => $inv2->id,
                'patient_id' => $inv2->patient_id,
                'amount' => 2400.00,
                'payment_method' => 'instapay',
                'logged_by_user_id' => $admin->id,
                'paid_at' => Carbon::now()->subDays(10),
                'notes' => 'Paid in full via InstaPay transfer.',
            ]
        );

        DoctorCommission::firstOrCreate(
            ['payment_id' => $p2->id],
            [
                'doctor_id' => $doctor->id,
                'gross_amount' => 2400.00,
                'lab_deduction_amount' => 380.00,
                'commission_percentage' => 45.00,
                'commission_amount' => 909.00,
                'status' => 'settled',
                'settled_at' => Carbon::now()->subDays(2),
            ]
        );

        // Case 3: Clear Orthodontic Aligners (Full 20-Stage Package)
        $inv3 = Invoice::firstOrCreate(
            ['invoice_number' => 'INV-2026-00103'],
            [
                'practice_id' => $practice->id,
                'patient_id' => $patients[4]->id ?? $patients[0]->id,
                'subtotal' => 4500.00,
                'discount_amount' => 300.00,
                'tax_amount' => 0.00,
                'total_amount' => 4200.00,
                'paid_amount' => 1200.00,
                'balance_due' => 3000.00,
                'status' => 'partially_paid',
                'issue_date' => Carbon::now()->subDays(5),
                'due_date' => Carbon::now()->addDays(60),
                'notes' => 'Comprehensive Clear Aligner therapy kit.',
            ]
        );

        InvoiceItem::firstOrCreate(
            ['invoice_id' => $inv3->id, 'description' => 'Clear Aligner Comprehensive Orthodontic Kit (Upper & Lower 20-Stages)'],
            ['quantity' => 1, 'unit_price' => 4500.00, 'total_price' => 4500.00]
        );

        Payment::firstOrCreate(
            ['transaction_reference' => 'CASH-REC-00214'],
            [
                'practice_id' => $practice->id,
                'invoice_id' => $inv3->id,
                'patient_id' => $inv3->patient_id,
                'amount' => 1200.00,
                'payment_method' => 'cash',
                'logged_by_user_id' => $admin->id,
                'paid_at' => Carbon::now()->subDays(5),
                'notes' => 'Cash down payment upon digital impression.',
            ]
        );

        // Case 4: Overdue Root Canal & Core Buildup Invoice
        $inv4 = Invoice::firstOrCreate(
            ['invoice_number' => 'INV-2026-00104'],
            [
                'practice_id' => $practice->id,
                'patient_id' => $patients[0]->id,
                'subtotal' => 1150.00,
                'discount_amount' => 0.00,
                'tax_amount' => 0.00,
                'total_amount' => 1150.00,
                'paid_amount' => 0.00,
                'balance_due' => 1150.00,
                'status' => 'overdue',
                'issue_date' => Carbon::now()->subDays(45),
                'due_date' => Carbon::now()->subDays(15), // Overdue!
                'notes' => 'Molar Root Canal Therapy (Tooth #46) + Fiber Post & Core Buildup.',
            ]
        );

        InvoiceItem::firstOrCreate(
            ['invoice_id' => $inv4->id, 'description' => 'Molar Endodontic Therapy (3-Canals, Tooth #46)'],
            ['quantity' => 1, 'unit_price' => 850.00, 'total_price' => 850.00]
        );
        InvoiceItem::firstOrCreate(
            ['invoice_id' => $inv4->id, 'description' => 'Glass Fiber Post & Core Composite Reconstruction'],
            ['quantity' => 1, 'unit_price' => 300.00, 'total_price' => 300.00]
        );

        // 2. Seed Operating Expenses & Practice Overhead
        $expenses = [
            [
                'expense_number' => 'EXP-2026-00001',
                'category' => 'Facility Rent & Lease',
                'payee' => 'Metropolitan Medical Plaza LLC',
                'amount' => 4500.00,
                'expense_date' => Carbon::now()->startOfMonth(),
                'payment_method' => 'auto_debit',
                'reference_number' => 'LEASE-AUG-2026',
                'tax_deductible' => true,
                'is_recurring' => true,
                'recurring_frequency' => 'monthly',
                'notes' => 'Monthly clinic facility lease (Suite 400 - 2,500 sq ft).',
            ],
            [
                'expense_number' => 'EXP-2026-00002',
                'category' => 'Staff Payroll & Salaries',
                'payee' => 'DentalCare Staff Payroll Services (ADP)',
                'amount' => 8200.00,
                'expense_date' => Carbon::now()->subDays(10),
                'payment_method' => 'bank_transfer',
                'reference_number' => 'PAYROLL-0826',
                'tax_deductible' => true,
                'is_recurring' => true,
                'recurring_frequency' => 'monthly',
                'notes' => 'Salaries for 2 Dental Assistants, 1 Hygienist, and 1 Front Desk Receptionist.',
            ],
            [
                'expense_number' => 'EXP-2026-00003',
                'category' => 'Clinical Supplies & Materials',
                'payee' => 'Henry Schein Dental Supply',
                'supplier_id' => $suppliers->firstWhere('name', 'Henry Schein Dental Supplies')?->id ?? $suppliers->first()?->id,
                'amount' => 1640.50,
                'expense_date' => Carbon::now()->subDays(12),
                'payment_method' => 'credit_card',
                'reference_number' => 'INV-HS-992140',
                'tax_deductible' => true,
                'notes' => 'Restorative composites, dental burs, barrier film, and nitrile gloves replenishment.',
            ],
            [
                'expense_number' => 'EXP-2026-00004',
                'category' => 'Dental Lab Fees',
                'payee' => 'Glidewell Dental Laboratories',
                'dental_lab_id' => $labs->firstWhere('name', 'Glidewell Dental Laboratories')?->id ?? $labs->first()?->id,
                'amount' => 980.00,
                'expense_date' => Carbon::now()->subDays(7),
                'payment_method' => 'bank_transfer',
                'reference_number' => 'STMT-GLIDE-8819',
                'tax_deductible' => true,
                'notes' => 'Statement for 4 BruxZir crowns and 1 posterior bridge case.',
            ],
            [
                'expense_number' => 'EXP-2026-00005',
                'category' => 'Utilities & Electricity',
                'payee' => 'Consolidated Energy & Medical Biohazard Waste',
                'amount' => 620.00,
                'expense_date' => Carbon::now()->subDays(14),
                'payment_method' => 'auto_debit',
                'reference_number' => 'UTIL-AUG-2026',
                'tax_deductible' => true,
                'notes' => 'High-load clinic electricity, suction vacuum compressor power, and sharps bio-waste pickup.',
            ],
            [
                'expense_number' => 'EXP-2026-00006',
                'category' => 'Marketing & Patient Acquisition',
                'payee' => 'Google Ads & Dental SEO Marketing',
                'amount' => 1200.00,
                'expense_date' => Carbon::now()->subDays(8),
                'payment_method' => 'credit_card',
                'reference_number' => 'GADS-66381',
                'tax_deductible' => true,
                'is_recurring' => true,
                'recurring_frequency' => 'monthly',
                'notes' => 'Targeted local search ads for "Emergency Dentist" and "Invisalign Provider".',
            ],
        ];

        foreach ($expenses as $e) {
            ClinicExpense::firstOrCreate(
                ['expense_number' => $e['expense_number']],
                array_merge($e, ['practice_id' => $practice->id, 'logged_by_user_id' => $admin->id])
            );
        }
    }
}
