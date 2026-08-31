<?php

namespace App\Filament\Resources\InsuranceClaimResource\Pages;

use App\Filament\Resources\InsuranceClaimResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInsuranceClaim extends CreateRecord
{
    protected static string $resource = InsuranceClaimResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['total_claimed_amount'] = !empty($data['total_claimed_amount']) ? (float)$data['total_claimed_amount'] : 0.00;
        $data['estimated_insurance_amount'] = !empty($data['estimated_insurance_amount']) ? (float)$data['estimated_insurance_amount'] : 0.00;
        $data['patient_copay_amount'] = !empty($data['patient_copay_amount']) ? (float)$data['patient_copay_amount'] : 0.00;
        $data['actual_paid_amount'] = !empty($data['actual_paid_amount']) ? (float)$data['actual_paid_amount'] : 0.00;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
