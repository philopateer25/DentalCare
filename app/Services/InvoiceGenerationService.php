<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentProcedure;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceGenerationService
{
    /**
     * Generate an invoice from completed, un-invoiced procedures in a treatment plan.
     *
     * @param TreatmentPlan $plan
     * @param array|Collection|null $procedureIds Specific procedure IDs to invoice (optional)
     * @param User|null $user
     * @return Invoice
     */
    public function generateInvoiceFromPlan(
        TreatmentPlan $plan,
        $procedureIds = null,
        ?User $user = null
    ): Invoice {
        $user = $user ?? auth()->user();

        $patient = $plan->patient;
        if (! $patient) {
            throw new InvalidArgumentException('Treatment plan must belong to a patient.');
        }

        // Tenant isolation check
        if ($user && ! $user->hasRole('developer') && (int) $user->practice_id !== (int) $patient->practice_id) {
            throw new InvalidArgumentException('Unauthorized cross-practice invoice generation.');
        }

        // Policy authorization check
        if ($user && ! $user->can('create', Invoice::class)) {
            throw new InvalidArgumentException('User is not authorized to generate invoices.');
        }

        return DB::transaction(function () use ($plan, $patient, $procedureIds) {
            // Fetch completed procedures for this plan
            $query = TreatmentProcedure::whereHas('phase', function ($q) use ($plan) {
                $q->where('treatment_plan_id', $plan->id);
            })->where('status', 'completed');

            if (! empty($procedureIds)) {
                $ids = is_array($procedureIds) ? $procedureIds : $procedureIds->toArray();
                $query->whereIn('id', $ids);
            }

            $completedProcedures = $query->get();

            if ($completedProcedures->isEmpty()) {
                throw new InvalidArgumentException('No completed procedures found for invoicing.');
            }

            // Find procedure IDs that are already invoiced
            $alreadyInvoicedIds = InvoiceItem::where(function ($q) use ($completedProcedures) {
                $q->whereIn('treatment_procedure_id', $completedProcedures->pluck('id'))
                    ->orWhere(function ($q2) use ($completedProcedures) {
                        $q2->where('invoiceable_type', TreatmentProcedure::class)
                            ->whereIn('invoiceable_id', $completedProcedures->pluck('id'));
                    });
            })->pluck('treatment_procedure_id')
                ->merge(
                    InvoiceItem::where('invoiceable_type', TreatmentProcedure::class)
                        ->whereIn('invoiceable_id', $completedProcedures->pluck('id'))
                        ->pluck('invoiceable_id')
                )
                ->unique()
                ->toArray();

            $billableProcedures = $completedProcedures->reject(fn ($p) => in_array($p->id, $alreadyInvoicedIds));

            if ($billableProcedures->isEmpty()) {
                throw new InvalidArgumentException('All selected completed procedures have already been invoiced.');
            }

            // Create Invoice record
            $invoice = Invoice::create([
                'practice_id' => $patient->practice_id,
                'patient_id' => $patient->id,
                'treatment_plan_id' => $plan->id,
                'issue_date' => now(),
                'due_date' => now()->addDays(30),
                'subtotal' => 0.00,
                'discount_amount' => 0.00,
                'tax_amount' => 0.00,
                'total_amount' => 0.00,
                'paid_amount' => 0.00,
                'balance_due' => 0.00,
                'status' => 'unpaid',
            ]);

            $subtotal = 0.00;
            $discountTotal = 0.00;

            foreach ($billableProcedures as $procedure) {
                $description = ($procedure->procedureCode?->code ? "{$procedure->procedureCode->code} - " : '') .
                    ($procedure->procedureCode?->title ?? 'Dental Procedure');
                if ($procedure->tooth_number_fdi) {
                    $description .= " (Tooth #{$procedure->tooth_number_fdi})";
                }

                $unitPrice = (float) $procedure->fee;
                $discount = (float) $procedure->discount;
                $netPrice = (float) $procedure->net_amount;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'treatment_procedure_id' => $procedure->id,
                    'invoiceable_type' => TreatmentProcedure::class,
                    'invoiceable_id' => $procedure->id,
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'total_price' => $netPrice,
                ]);

                $subtotal += $unitPrice;
                $discountTotal += $discount;
            }

            $totalAmount = max(0, $subtotal - $discountTotal);

            $invoice->updateQuietly([
                'subtotal' => $subtotal,
                'discount_amount' => $discountTotal,
                'total_amount' => $totalAmount,
                'balance_due' => $totalAmount,
            ]);

            return $invoice;
        });
    }
}
