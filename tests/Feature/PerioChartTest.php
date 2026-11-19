<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PerioChartController;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerioChartTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'doctor']);
        $practice = Practice::create(['name' => 'Perio Clinic', 'currency' => 'EGP', 'is_active' => true]);
        $this->doctor = User::create(['practice_id' => $practice->id, 'name' => 'Dr P', 'email' => 'p@perio.test', 'password' => bcrypt('x'), 'role' => 'doctor']);
        $this->doctor->assignRole('doctor');
        $this->patient = Patient::create(['practice_id' => $practice->id, 'file_number' => 'PR-1', 'first_name' => 'Hana', 'last_name' => 'Said', 'phone' => '01', 'gender' => 'female']);
    }

    #[Test]
    public function a_gum_chart_can_be_started_saved_and_summarised(): void
    {
        $chart = $this->actingAs($this->doctor)->postJson("/api/patients/{$this->patient->id}/perio")->assertCreated()->json();

        $this->actingAs($this->doctor)->putJson("/api/perio/{$chart['id']}", [
            'probe_depths' => ['16' => [3, 6, 4, 2, 3, 2], '11' => [2, 2, 2, 2, 2, 2]],
            'gingival_margins' => ['16' => [0, -2, 0, 0, 0, 0]],
            'bleeding' => ['16' => [false, true, true, false, false, false], '11' => [false, false, false, false, false, false]],
            'plaque' => ['16' => [true, false, false, false, false, false]],
            'mobility' => ['16' => 1],
            'furcation' => ['16' => 2],
        ])->assertOk()
            ->assertJsonPath('summary.sites_recorded', 12)
            ->assertJsonPath('summary.sites_6_plus', 1)
            ->assertJsonPath('summary.sites_4_5', 1)
            ->assertJsonPath('summary.max_cal', 8)          // 6 mm pocket with 2 mm recession
            ->assertJsonPath('summary.bop_percent', 17)     // 2 of 12 sites
            ->assertJsonPath('mobility.16', 1)
            ->assertJsonPath('furcation.16', 2);

        $this->actingAs($this->doctor)->getJson("/api/patients/{$this->patient->id}/perio")
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.probe_depths.16.1', 6);
    }

    #[Test]
    public function invalid_perio_values_are_rejected(): void
    {
        $chart = $this->actingAs($this->doctor)->postJson("/api/patients/{$this->patient->id}/perio")->json();

        $this->actingAs($this->doctor)->putJson("/api/perio/{$chart['id']}", ['probe_depths' => ['16' => [3, 3, 3]]])->assertUnprocessable();
        $this->actingAs($this->doctor)->putJson("/api/perio/{$chart['id']}", ['probe_depths' => ['19' => [1, 1, 1, 1, 1, 1]]])->assertUnprocessable();
        $this->actingAs($this->doctor)->putJson("/api/perio/{$chart['id']}", ['probe_depths' => ['16' => [3, 3, 30, 3, 3, 3]]])->assertUnprocessable();
        $this->actingAs($this->doctor)->putJson("/api/perio/{$chart['id']}", ['mobility' => ['16' => 5]])->assertUnprocessable();
    }

    #[Test]
    public function gum_charts_are_tenant_scoped_and_need_login(): void
    {
        $this->getJson("/api/patients/{$this->patient->id}/perio")->assertUnauthorized();

        $other = Practice::create(['name' => 'Other', 'currency' => 'EGP', 'is_active' => true]);
        $outsider = User::create(['practice_id' => $other->id, 'name' => 'O', 'email' => 'o@o.test', 'password' => bcrypt('x'), 'role' => 'doctor']);
        $this->actingAs($outsider)->getJson("/api/patients/{$this->patient->id}/perio")->assertForbidden();

        $chart = $this->actingAs($this->doctor)->postJson("/api/patients/{$this->patient->id}/perio")->json();
        $this->actingAs($outsider)->putJson("/api/perio/{$chart['id']}", ['notes' => 'x'])->assertForbidden();
    }

    #[Test]
    public function summary_handles_an_empty_chart(): void
    {
        $s = PerioChartController::summary([], [], [], []);
        $this->assertSame(0, $s['sites_recorded']);
        $this->assertNull($s['mean_pd']);
        $this->assertNull($s['bop_percent']);
    }
}
