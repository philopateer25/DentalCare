<?php

namespace App\Filament\Resources\PayrollSlipResource\Pages;

use App\Filament\Resources\PayrollSlipResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollSlip extends CreateRecord
{
    protected static string $resource = PayrollSlipResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['base_salary'] = !empty($data['base_salary']) ? (float)$data['base_salary'] : 0.00;
        $data['overtime_amount'] = !empty($data['overtime_amount']) ? (float)$data['overtime_amount'] : 0.00;
        $data['bonus_amount'] = !empty($data['bonus_amount']) ? (float)$data['bonus_amount'] : 0.00;
        $data['allowance_amount'] = !empty($data['allowance_amount']) ? (float)$data['allowance_amount'] : 0.00;
        $data['tax_deduction'] = !empty($data['tax_deduction']) ? (float)$data['tax_deduction'] : 0.00;
        $data['insurance_deduction'] = !empty($data['insurance_deduction']) ? (float)$data['insurance_deduction'] : 0.00;
        $data['other_deductions'] = !empty($data['other_deductions']) ? (float)$data['other_deductions'] : 0.00;
        $data['net_salary'] = !empty($data['net_salary']) ? (float)$data['net_salary'] : 0.00;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
