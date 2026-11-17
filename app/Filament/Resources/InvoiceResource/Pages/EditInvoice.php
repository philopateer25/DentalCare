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
