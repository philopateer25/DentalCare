<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $total = (float)($data['total_amount'] ?? 0);
        $paid = (float)($data['paid_amount'] ?? 0);
        $data['balance_due'] = max(0, $total - $paid);

        if (($data['status'] ?? '') !== 'cancelled') {
            if ($paid >= $total && $total > 0) {
                $data['status'] = 'paid';
            } elseif ($paid > 0 && $paid < $total) {
                $data['status'] = 'partially_paid';
            } elseif ($paid == 0) {
                $data['status'] = 'unpaid';
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $invoice = $this->record;
        $paidAmount = (float) $invoice->paid_amount;
        $existingPaymentsSum = (float) $invoice->payments()->sum('amount');
        if ($paidAmount > $existingPaymentsSum) {
            $difference = $paidAmount - $existingPaymentsSum;
            \App\Models\Payment::create([
                'practice_id' => $invoice->practice_id ?? \Filament\Facades\Filament::getTenant()?->id ?? 1,
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
                'amount' => $difference,
                'payment_method' => 'cash',
                'transaction_reference' => 'PAY-' . date('Ymd') . '-' . str_pad((string) (\App\Models\Payment::max('id') + 1), 4, '0', STR_PAD_LEFT),
                'logged_by_user_id' => auth()->id() ?? 1,
                'paid_at' => now(),
                'notes' => 'Payment registered with invoice ' . $invoice->invoice_number,
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
