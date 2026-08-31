<?php

namespace Database\Seeders;

use App\Models\InsuranceClaim;
use App\Models\InsuranceProvider;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientInsurancePolicy;
use App\Models\PayrollSlip;
use App\Models\Practice;
use App\Models\StaffMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DentalStaffAndInsuranceSeeder extends Seeder
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

        // 1. Seed Staff Members
        $staffData = [
            [
                'employee_id' => 'EMP-001',
                'first_name' => 'Sarah',
                'last_name' => 'Jenkins',
                'role' => 'Lead Dentist',
                'email' => 'doctor@dentalcare.com',
                'phone' => '+1 (555) 101-2001',
                'employment_type' => 'Full-Time',
                'hire_date' => '2022-03-01',
                'base_salary' => 12500.00,
                'bank_name' => 'JPMorgan Chase',
                'bank_account_number' => 'US99-CHAS-10293847',
                'tax_id' => 'W4-99201',
                'user_id' => $doctor->id,
            ],
            [
                'employee_id' => 'EMP-002',
                'first_name' => 'David',
                'last_name' => 'Miller',
                'role' => 'Associate Dentist',
                'email' => 'david.miller@dentalcare.com',
                'phone' => '+1 (555) 101-2002',
                'employment_type' => 'Full-Time',
                'hire_date' => '2023-06-15',
                'base_salary' => 9500.00,
                'bank_name' => 'Bank of America',
                'bank_account_number' => 'US88-BOFA-99182341',
                'tax_id' => 'W4-88312',
            ],
            [
                'employee_id' => 'EMP-003',
                'first_name' => 'Jessica',
                'last_name' => 'Taylor',
                'role' => 'Dental Hygienist (RDH)',
                'email' => 'jessica.rdh@dentalcare.com',
                'phone' => '+1 (555) 101-2003',
                'employment_type' => 'Full-Time',
                'hire_date' => '2023-01-10',
                'base_salary' => 5200.00,
                'hourly_rate' => 45.00,
                'bank_name' => 'Wells Fargo',
                'bank_account_number' => 'US77-WELL-44910283',
                'tax_id' => 'W4-77192',
            ],
            [
                'employee_id' => 'EMP-004',
                'first_name' => 'Amanda',
                'last_name' => 'Rodriguez',
                'role' => 'Dental Assistant (CDA)',
                'email' => 'amanda.cda@dentalcare.com',
                'phone' => '+1 (555) 101-2004',
                'employment_type' => 'Full-Time',
                'hire_date' => '2024-02-01',
                'base_salary' => 3800.00,
                'hourly_rate' => 26.00,
                'bank_name' => 'Citibank',
                'bank_account_number' => 'US66-CITI-10293845',
                'tax_id' => 'W4-66381',
            ],
            [
                'employee_id' => 'EMP-005',
                'first_name' => 'Marcus',
                'last_name' => 'Vance',
                'role' => 'Practice Manager',
                'email' => 'marcus.mgr@dentalcare.com',
                'phone' => '+1 (555) 101-2005',
                'employment_type' => 'Full-Time',
                'hire_date' => '2021-08-01',
                'base_salary' => 6500.00,
                'bank_name' => 'JPMorgan Chase',
                'bank_account_number' => 'US99-CHAS-88392019',
                'tax_id' => 'W4-55102',
            ],
            [
                'employee_id' => 'EMP-006',
                'first_name' => 'Ashley',
                'last_name' => 'Brooks',
                'role' => 'Front Desk / Patient Coordinator',
                'email' => 'ashley.reception@dentalcare.com',
                'phone' => '+1 (555) 101-2006',
                'employment_type' => 'Full-Time',
                'hire_date' => '2024-05-15',
                'base_salary' => 3400.00,
                'hourly_rate' => 22.00,
                'bank_name' => 'PNC Bank',
                'bank_account_number' => 'US55-PNCB-77281934',
                'tax_id' => 'W4-44192',
            ],
            [
                'employee_id' => 'EMP-007',
                'first_name' => 'Carlos',
                'last_name' => 'Santana',
                'role' => 'Sterilization Technician',
                'email' => 'carlos.steril@dentalcare.com',
                'phone' => '+1 (555) 101-2007',
                'employment_type' => 'Full-Time',
                'hire_date' => '2024-08-01',
                'base_salary' => 2900.00,
                'hourly_rate' => 19.00,
                'bank_name' => 'Capital One',
                'bank_account_number' => 'US44-CAPO-33928104',
                'tax_id' => 'W4-33109',
            ],
        ];

        $staffModels = [];
        foreach ($staffData as $s) {
            $staffModels[] = StaffMember::firstOrCreate(
                ['employee_id' => $s['employee_id']],
                array_merge($s, ['practice_id' => $practice->id, 'is_active' => true])
            );
        }

        // 2. Seed Monthly Payroll Payslips
        $currentMonth = (int) date('m');
        $currentYear = (int) date('Y');

        foreach ($staffModels as $staff) {
            $base = (float) $staff->base_salary;
            $overtime = in_array($staff->role, ['Dental Assistant (CDA)', 'Dental Hygienist (RDH)']) ? 250.00 : 0.00;
            $bonus = in_array($staff->role, ['Practice Manager', 'Lead Dentist']) ? 500.00 : 150.00;
            $allowance = 150.00;
            $tax = round($base * 0.15, 2);
            $insurance = 120.00;
            $net = PayrollSlip::calculateNet($base, $overtime, $bonus, $allowance, $tax, $insurance, 0.00);

            PayrollSlip::firstOrCreate(
                [
                    'staff_member_id' => $staff->id,
                    'pay_period_month' => $currentMonth,
                    'pay_period_year' => $currentYear,
                ],
                [
                    'practice_id' => $practice->id,
                    'base_salary' => $base,
                    'overtime_amount' => $overtime,
                    'bonus_amount' => $bonus,
                    'allowance_amount' => $allowance,
                    'tax_deduction' => $tax,
                    'insurance_deduction' => $insurance,
                    'other_deductions' => 0.00,
                    'net_salary' => $net,
                    'payment_method' => 'bank_direct_deposit',
                    'status' => 'disbursed',
                    'disbursed_at' => Carbon::now()->subDays(5),
                    'notes' => 'Monthly payroll direct deposit processed via ACH.',
                ]
            );
        }

        // 3. Seed Insurance Providers
        $providers = [
            [
                'name' => 'Delta Dental Premier & PPO',
                'payer_id' => 'DELTA-01',
                'contact_person' => 'Rachel Miller',
                'phone' => '+1 (800) 521-2651',
                'claims_email' => 'claims@deltadental.com',
                'portal_url' => 'https://provider.deltadental.com',
                'standard_reimbursement_days' => 10,
            ],
            [
                'name' => 'MetLife Dental Solutions',
                'payer_id' => 'METLIFE-99',
                'contact_person' => 'Arthur Vance',
                'phone' => '+1 (800) 942-0854',
                'claims_email' => 'dentalclaims@metlife.com',
                'portal_url' => 'https://metdental.com',
                'standard_reimbursement_days' => 14,
            ],
            [
                'name' => 'Cigna Dental Total Care',
                'payer_id' => 'CIGNA-62',
                'contact_person' => 'Elena Rostova',
                'phone' => '+1 (800) 244-6224',
                'claims_email' => 'cignadental@cigna.com',
                'portal_url' => 'https://cignaforhcp.cigna.com',
                'standard_reimbursement_days' => 12,
            ],
            [
                'name' => 'Aetna Dental PPO Network',
                'payer_id' => 'AETNA-33',
                'contact_person' => 'Jason Cole',
                'phone' => '+1 (800) 445-3344',
                'claims_email' => 'dentalclaims@aetna.com',
                'portal_url' => 'https://navinet.aetna.com',
                'standard_reimbursement_days' => 15,
            ],
            [
                'name' => 'Bupa Global Private Healthcare',
                'payer_id' => 'BUPA-GLOBAL',
                'contact_person' => 'Claire Hamilton',
                'phone' => '+44 1273 208181',
                'claims_email' => 'claims@bupaglobal.com',
                'portal_url' => 'https://bupaglobal.com/providers',
                'standard_reimbursement_days' => 21,
            ],
        ];

        $providerModels = [];
        foreach ($providers as $p) {
            $providerModels[] = InsuranceProvider::firstOrCreate(
                ['payer_id' => $p['payer_id']],
                array_merge($p, ['practice_id' => $practice->id, 'is_active' => true])
            );
        }

        // 4. Seed Patient Insurance Policies
        if ($patients->isNotEmpty()) {
            $policy1 = PatientInsurancePolicy::firstOrCreate(
                ['policy_number' => 'DEL-994012'],
                [
                    'practice_id' => $practice->id,
                    'patient_id' => $patients[0]->id,
                    'insurance_provider_id' => $providerModels[0]->id,
                    'group_number' => 'GRP-GOOGLE-881',
                    'subscriber_name' => $patients[0]->first_name . ' ' . $patients[0]->last_name,
                    'subscriber_relationship' => 'self',
                    'plan_type' => 'PPO',
                    'annual_maximum' => 2000.00,
                    'annual_deductible' => 50.00,
                    'deductible_met' => 50.00,
                    'preventive_coverage_pct' => 100.00,
                    'basic_coverage_pct' => 80.00,
                    'major_coverage_pct' => 50.00,
                    'ortho_coverage_pct' => 50.00,
                    'ortho_lifetime_max' => 2000.00,
                    'effective_date' => Carbon::now()->startOfYear(),
                    'is_active' => true,
                ]
            );

            $policy2 = PatientInsurancePolicy::firstOrCreate(
                ['policy_number' => 'MET-338291'],
                [
                    'practice_id' => $practice->id,
                    'patient_id' => $patients[1]->id ?? $patients[0]->id,
                    'insurance_provider_id' => $providerModels[1]->id,
                    'group_number' => 'GRP-MICROSOFT-44',
                    'subscriber_name' => ($patients[1]->first_name ?? 'Emily') . ' ' . ($patients[1]->last_name ?? 'Rodriguez'),
                    'subscriber_relationship' => 'self',
                    'plan_type' => 'PPO',
                    'annual_maximum' => 2500.00,
                    'annual_deductible' => 50.00,
                    'deductible_met' => 50.00,
                    'preventive_coverage_pct' => 100.00,
                    'basic_coverage_pct' => 80.00,
                    'major_coverage_pct' => 50.00,
                    'ortho_coverage_pct' => 60.00,
                    'ortho_lifetime_max' => 2500.00,
                    'effective_date' => Carbon::now()->startOfYear(),
                    'is_active' => true,
                ]
            );

            // 5. Seed Claims & Pre-Authorizations
            InsuranceClaim::firstOrCreate(
                ['claim_number' => 'CLM-2026-00001'],
                [
                    'practice_id' => $practice->id,
                    'patient_id' => $patients[0]->id,
                    'insurance_provider_id' => $providerModels[0]->id,
                    'patient_insurance_policy_id' => $policy1->id,
                    'doctor_id' => $doctor->id,
                    'claim_type' => 'standard_claim',
                    'total_claimed_amount' => 1150.00,
                    'estimated_insurance_amount' => 650.00,
                    'patient_copay_amount' => 500.00,
                    'actual_paid_amount' => 650.00,
                    'eob_reference_number' => 'EOB-DEL-993810',
                    'status' => 'approved_paid',
                    'submitted_at' => Carbon::now()->subDays(20),
                    'adjudicated_at' => Carbon::now()->subDays(5),
                    'treatment_summary' => 'D3330 Molar Root Canal Therapy (Tooth #46) + D2950 Core Buildup Composite.',
                    'notes' => 'Claim approved and settled via direct ACH deposit.',
                ]
            );

            InsuranceClaim::firstOrCreate(
                ['claim_number' => 'CLM-2026-00002'],
                [
                    'practice_id' => $practice->id,
                    'patient_id' => $patients[1]->id ?? $patients[0]->id,
                    'insurance_provider_id' => $providerModels[1]->id,
                    'patient_insurance_policy_id' => $policy2->id,
                    'doctor_id' => $doctor->id,
                    'claim_type' => 'pre_authorization',
                    'total_claimed_amount' => 7000.00,
                    'estimated_insurance_amount' => 1500.00,
                    'patient_copay_amount' => 5500.00,
                    'actual_paid_amount' => 0.00,
                    'status' => 'under_review',
                    'submitted_at' => Carbon::now()->subDays(3),
                    'treatment_summary' => 'Pre-authorization for 6-unit anterior ceramic veneers (#13-#23) and periodontal stabilization.',
                    'notes' => 'Awaiting dental consultant clinical review and X-ray evaluation.',
                ]
            );
        }
    }
}
