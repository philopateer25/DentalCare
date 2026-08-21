<?php

namespace Tests\Unit;

use App\Models\ToothRecord;
use PHPUnit\Framework\TestCase;

class ToothRecordTest extends TestCase
{
    public function test_fdi_to_universal_adult_teeth_mapping(): void
    {
        $this->assertEquals('1', ToothRecord::fdiToUniversal(18));
        $this->assertEquals('8', ToothRecord::fdiToUniversal(11));
        $this->assertEquals('9', ToothRecord::fdiToUniversal(21));
        $this->assertEquals('16', ToothRecord::fdiToUniversal(28));
        $this->assertEquals('17', ToothRecord::fdiToUniversal(38));
        $this->assertEquals('24', ToothRecord::fdiToUniversal(31));
        $this->assertEquals('25', ToothRecord::fdiToUniversal(41));
        $this->assertEquals('32', ToothRecord::fdiToUniversal(48));
    }

    public function test_fdi_to_universal_pediatric_teeth_mapping(): void
    {
        $this->assertEquals('A', ToothRecord::fdiToUniversal(55));
        $this->assertEquals('E', ToothRecord::fdiToUniversal(51));
        $this->assertEquals('F', ToothRecord::fdiToUniversal(61));
        $this->assertEquals('J', ToothRecord::fdiToUniversal(65));
        $this->assertEquals('K', ToothRecord::fdiToUniversal(75));
        $this->assertEquals('O', ToothRecord::fdiToUniversal(71));
        $this->assertEquals('P', ToothRecord::fdiToUniversal(81));
        $this->assertEquals('T', ToothRecord::fdiToUniversal(85));
    }
}
