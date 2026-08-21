<?php

namespace Tests\Unit;

use App\Models\DoctorCommission;
use PHPUnit\Framework\TestCase;

class DoctorCommissionTest extends TestCase
{
    public function test_calculate_commission_with_gross_production_and_lab_deduction(): void
    {
        // 1000 EGP procedure, 200 EGP lab fee, 40% commission rate
        // Net = 800, Commission = 320 EGP
        $commission = DoctorCommission::calculateCommission(1000.00, 200.00, 40.00);
        $this->assertEquals(320.00, $commission);
    }

    public function test_calculate_commission_when_lab_cost_exceeds_gross(): void
    {
        $commission = DoctorCommission::calculateCommission(500.00, 600.00, 40.00);
        $this->assertEquals(0.00, $commission);
    }
}
