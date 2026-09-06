<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\Invoice;

class AppointmentObserver
{
    /**
     * When an appointment is created, automatically generate an invoice
     * with the consultation fee as a line item.
     */
    public function created(Appointment $appointment): void
    {
        // Only auto-invoice if there's a consultation fee > 0
        if (($appointment->consultation_fee ?? 0) <= 0) {
            return;
        }

        $invoice = Invoice::create([
            'patient_id' => $appointment->patient_id,
            'practice_id' => $appointment->practice_id ?? \App\Models\Practice::first()?->id,
            'invoice_number' => 'INV-' . strtoupper(uniqid()),
            'issue_date' => now(),
            'total_amount' => $appointment->consultation_fee,
            'paid_amount' => 0,
            'remaining_balance' => $appointment->consultation_fee,
            'status' => 'unpaid',
        ]);

        $invoice->items()->create([
            'invoiceable_type' => Appointment::class,
            'invoiceable_id' => $appointment->id,
            'procedure_name' => 'Consultation Fee - ' . ($appointment->chief_complaint ?: 'General Consultation'),
            'tooth_number' => null,
            'quantity' => 1,
            'unit_price' => $appointment->consultation_fee,
            'total' => $appointment->consultation_fee,
        ]);
    }

    /**
     * If the consultation fee is updated on an existing appointment,
     * update the related invoice too.
     */
    public function updated(Appointment $appointment): void
    {
        if ($appointment->isDirty('consultation_fee')) {
            $newFee = $appointment->consultation_fee;
            
            // Find existing invoice item linked to this appointment
            $item = \App\Models\InvoiceItem::where('invoiceable_type', Appointment::class)
                ->where('invoiceable_id', $appointment->id)
                ->first();

            if ($item) {
                $oldFee = $item->unit_price;
                $diff = $newFee - $oldFee;

                $item->update([
                    'unit_price' => $newFee,
                    'total' => $newFee,
                ]);

                // Update invoice totals
                $invoice = $item->invoice;
                if ($invoice) {
                    $invoice->total_amount += $diff;
                    $invoice->remaining_balance += $diff;
                    $invoice->save();
                }
            } elseif ($newFee > 0) {
                // No existing item — create a new invoice
                $invoice = Invoice::create([
                    'patient_id' => $appointment->patient_id,
                    'practice_id' => $appointment->practice_id ?? \App\Models\Practice::first()?->id,
                    'invoice_number' => 'INV-' . strtoupper(uniqid()),
                    'issue_date' => now(),
                    'total_amount' => $newFee,
                    'paid_amount' => 0,
                    'remaining_balance' => $newFee,
                    'status' => 'unpaid',
                ]);

                $invoice->items()->create([
                    'invoiceable_type' => Appointment::class,
                    'invoiceable_id' => $appointment->id,
                    'procedure_name' => 'Consultation Fee - ' . ($appointment->chief_complaint ?: 'General Consultation'),
                    'tooth_number' => null,
                    'quantity' => 1,
                    'unit_price' => $newFee,
                    'total' => $newFee,
                ]);
            }
        }
    }
}
