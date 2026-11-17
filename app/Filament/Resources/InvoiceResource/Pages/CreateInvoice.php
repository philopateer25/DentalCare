<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $total = (float)($data['total_amount'] ?? 0);
        $paid = (float)($data['paid_amount'] ?? 0);
        $data['subtotal'] = $data['subtotal'] ?? $total;
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
