<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['subtotal'] = $data['subtotal'] ?? $data['total_amount'] ?? 0;
        $data['balance_due'] = max(0, (float)($data['total_amount'] ?? 0) - (float)($data['paid_amount'] ?? 0));
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
