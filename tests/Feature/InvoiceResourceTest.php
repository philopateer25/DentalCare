<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_refresh_financials_updates_paid_remaining_and_status(): void
    {
        $patient = Patient::factory()->create([
            'file_number' => 'PAT-123456',
            'full_name' => 'Dr. Ahmed Sameh',
            'phone' => '01012345678',
        ]);

        $invoice = Invoice::create([
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-TEST-01',
            'invoice_date' => now(),
            'subtotal' => 1000.00,
            'discount' => 100.00,
            'tax' => 50.00,
            'total_amount' => 950.00,
            'paid_amount' => 0.00,
            'remaining_balance' => 950.00,
            'status' => 'unpaid',
        ]);

        $this::assertEquals('unpaid', $invoice->status);
        $this::assertEquals(950.00, $invoice->remaining_balance);

        // Record Partial Payment of 450 EGP
        Payment::create([
            'invoice_id' => $invoice->id,
            'patient_id' => $patient->id,
            'amount' => 450.00,
            'payment_method' => 'instapay',
            'transaction_reference' => 'INSTA-998877',
            'paid_at' => now(),
        ]);

        $invoice->refreshFinancials();
        $invoice->refresh();

        $this::assertEquals(450.00, $invoice->paid_amount);
        $this::assertEquals(500.00, $invoice->remaining_balance);
        $this::assertEquals('partially_paid', $invoice->status);

        // Record Remaining Payment of 500 EGP
        Payment::create([
            'invoice_id' => $invoice->id,
            'patient_id' => $patient->id,
            'amount' => 500.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        $invoice->refreshFinancials();
        $invoice->refresh();

        $this::assertEquals(950.00, $invoice->paid_amount);
        $this::assertEquals(0.00, $invoice->remaining_balance);
        $this::assertEquals('paid', $invoice->status);
    }
}
