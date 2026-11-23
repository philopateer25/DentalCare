<?php

namespace Tests\Feature;

use App\Filament\Resources\PatientResource\Pages\ListPatients;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientFile;
use App\Models\Practice;
use App\Models\User;
use App\Services\PaperRecords\PaperCardScan;
use App\Services\PaperRecords\PatientImport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Phase 4 — switching from paper: import with opening balances, phone scan of paper cards. */
class PaperRecordsTest extends TestCase
{
    use RefreshDatabase;

    private Practice $practice;

    private Practice $other;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'clinic_admin', 'guard_name' => 'web']);
        $this->practice = $this->makePractice('Smile');
        $this->other = $this->makePractice('Other');
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@smile.test', 'password' => bcrypt('x'),
            'practice_id' => $this->practice->id, 'role' => 'clinic_admin',
        ]);
        $this->admin->assignRole('clinic_admin');
    }

    private function makePractice(string $name): Practice
    {
        return Practice::create(['name' => $name, 'currency' => 'EGP', 'is_active' => true, 'onboarding_completed_at' => now(), 'onboarding_step' => 6]);
    }

    private function patient(Practice $practice, string $phone, string $first = 'Mona'): Patient
    {
        return Patient::create(['practice_id' => $practice->id, 'first_name' => $first, 'last_name' => 'Adel', 'phone' => $phone, 'gender' => 'female']);
    }

    /** @param list<list<mixed>> $rows */
    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    // ── Import ──────────────────────────────────

    #[Test]
    public function arabic_excel_with_debts_and_installments_is_checked_then_imported(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 22));
        $this->patient($this->practice, '01011111111', 'Existing');
        $this->patient($this->other, '01033333333', 'OtherClinic');

        $path = $this->xlsx([
            ['الاسم', 'الموبايل', 'النوع', 'السن', 'المبلغ المستحق', 'عدد الأقساط', 'تاريخ أول قسط', 'ملاحظات'],
            ['محمد أحمد', '٠١٠٢٢٢٢٢٢٢٢', 'ذكر', '٤٥', '1,500', 3, '1/12/2026', 'تقويم'],
            ['Mona Ali', '+20 115 555 5555', 'female', 30, '', '', '', ''],
            ['', '01044444444', '', '', '', '', '', ''],                 // no name
            ['Dup Existing', '01011111111', '', '', '', '', '', ''],    // already a patient here
            ['Twice', '01022222222', '', '', '', '', '', ''],           // same phone as row 2
            ['Bad Amount', '01066666666', 'x', '', 'abc', '', '', ''],  // gender + amount
            ['Other clinic phone ok', '01033333333', '', '', 200, '', '', ''], // other clinic's phone is not a duplicate
        ]);

        $import = app(PatientImport::class);
        $read = $import->read($path);
        $this->assertNull($read['error']);
        $checked = $import->check($read['rows'], $this->practice->id);

        $byLine = collect($checked)->keyBy('line');
        $this->assertTrue($byLine[2]['ok'], implode(' ', $byLine[2]['errors']));
        $this->assertSame('01022222222', $byLine[2]['data']['phone'], 'Arabic digits are converted.');
        $this->assertSame(1500.0, $byLine[2]['data']['owed']);
        $this->assertSame('01155555555', $byLine[3]['data']['phone'], '+20 prefix is converted.');
        $this->assertFalse($byLine[4]['ok']);
        $this->assertStringContainsString('Already a patient', implode(' ', $byLine[5]['errors']));
        $this->assertStringContainsString('row 2', implode(' ', $byLine[6]['errors']));
        $this->assertCount(2, $byLine[7]['errors']);
        $this->assertTrue($byLine[8]['ok']);

        $this->assertSame(1, Patient::where('practice_id', $this->practice->id)->count(), 'Checking saves nothing.');

        $counts = $import->import($checked, $this->practice->id);

        $this->assertSame(['patients' => 3, 'invoices' => 2, 'plans' => 1, 'owed' => 1700.0], $counts);

        $mohamed = Patient::where('practice_id', $this->practice->id)->where('phone', '01022222222')->firstOrFail();
        $this->assertSame('محمد', $mohamed->first_name);
        $this->assertSame('male', $mohamed->gender);
        $this->assertSame('1981-07-01', $mohamed->dob->toDateString());

        $invoice = Invoice::where('patient_id', $mohamed->id)->firstOrFail();
        $this->assertSame($this->practice->id, (int) $invoice->practice_id);
        $this->assertEquals(1500, (float) $invoice->balance_due);
        $this->assertCount(1, $invoice->items);

        $plan = InstallmentPlan::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(['2026-12-01', '2027-01-01', '2027-02-01'], $plan->schedules->map(fn ($s) => $s->due_date->toDateString())->all());
        $this->assertEquals(1500, (float) $plan->schedules->sum('amount'));

        // Importing the same file again creates nothing new.
        $again = $import->check($import->read($path)['rows'], $this->practice->id);
        $this->assertSame(0, $import->import($again, $this->practice->id)['patients']);
    }

    #[Test]
    public function a_file_without_name_or_phone_columns_is_refused(): void
    {
        $path = $this->xlsx([['Patient ID', 'Visits'], [1, 2]]);

        $read = app(PatientImport::class)->read($path);

        $this->assertSame(['name', 'phone'], $read['missing']);
        $this->assertNotNull($read['error']);
        $this->assertSame([], $read['rows']);
    }

    #[Test]
    public function csv_files_and_the_bilingual_template_headers_work(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, "\xEF\xBB\xBF".implode(',', PatientImport::TEMPLATE_HEADERS)."\nSara Nabil,1012345678,F,28,300,2,2026-12-15,weekly,\n");

        $import = app(PatientImport::class);
        $checked = $import->check($import->read($path)['rows'], $this->practice->id);

        $this->assertTrue($checked[0]['ok'], implode(' ', $checked[0]['errors']));
        $this->assertSame('01012345678', $checked[0]['data']['phone'], 'Lost leading zero is restored.');
        $this->assertSame('weekly', $checked[0]['data']['frequency']);
        $this->assertSame('2026-12-15', $checked[0]['data']['first_due']);
    }

    #[Test]
    public function the_template_downloads_and_reads_back(): void
    {
        $response = $this->actingAs($this->admin)->get(route('patients.import.template'));
        $response->assertOk();

        $import = app(PatientImport::class);
        $read = $import->read($response->getFile()->getPathname(), 'template.xlsx');
        $checked = $import->check($read['rows'], $this->practice->id);

        $this->assertCount(2, $checked);
        $this->assertTrue($checked[0]['ok'] && $checked[1]['ok']);
    }

    // ── Scan paper card ─────────────────────────

    #[Test]
    public function phone_upload_saves_photos_once_to_the_right_patient(): void
    {
        Storage::fake('local');
        $patient = $this->patient($this->practice, '01011111111');

        $scan = app(PaperCardScan::class)->start($patient, $this->admin->id);

        $this->get($scan['path'])->assertOk()->assertSee('Mona');

        $this->post(route('paper-card.store', ['token' => $scan['token']]), [
            'photos' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.jpg')],
        ])->assertOk()->assertSee('2 photos saved');

        $files = PatientFile::where('patient_id', $patient->id)->get();
        $this->assertCount(2, $files);
        $this->assertSame('paper_record', $files[0]->type);
        $this->assertStringStartsWith("patient-files/{$this->practice->id}/{$patient->id}/", $files[0]->file_path);
        Storage::disk('local')->assertExists($files[0]->file_path);

        // One-time link.
        $this->post(route('paper-card.store', ['token' => $scan['token']]), [
            'photos' => [UploadedFile::fake()->image('again.jpg')],
        ])->assertSee('expired or was already used');
        $this->assertSame(2, PatientFile::where('patient_id', $patient->id)->count());
    }

    #[Test]
    public function unknown_or_expired_links_and_non_images_are_refused(): void
    {
        Storage::fake('local');
        $patient = $this->patient($this->practice, '01011111111');

        $this->get('/scan/'.str_repeat('a', 32))->assertOk()->assertSee('expired or was already used');

        $scan = app(PaperCardScan::class)->start($patient, $this->admin->id);
        $this->post(route('paper-card.store', ['token' => $scan['token']]), [
            'photos' => [UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertSessionHasErrors('photos.0');

        $this->travel(11)->minutes();
        $this->get($scan['path'])->assertSee('expired or was already used');
        $this->assertSame(0, PatientFile::count());
    }

    #[Test]
    public function photos_download_only_for_the_same_clinic(): void
    {
        Storage::fake('local');
        $patient = $this->patient($this->practice, '01011111111');
        $scan = app(PaperCardScan::class)->start($patient, $this->admin->id);
        $this->post(route('paper-card.store', ['token' => $scan['token']]), ['photos' => [UploadedFile::fake()->image('front.jpg')]]);
        $file = PatientFile::firstOrFail();

        $this->get(route('patient-files.download', $file))->assertRedirect(); // guests → login
        $this->actingAs($this->admin)->get(route('patient-files.download', $file))->assertOk();

        $outsider = User::create(['name' => 'Out', 'email' => 'out@other.test', 'password' => bcrypt('x'), 'practice_id' => $this->other->id]);
        $this->actingAs($outsider)->get(route('patient-files.download', $file))->assertForbidden();
    }

    #[Test]
    public function phone_link_uses_the_clinic_network_address(): void
    {
        $patient = $this->patient($this->practice, '01011111111');
        $scan = app(PaperCardScan::class);

        config(['app.lan_url' => null]);
        $this->assertNull($scan->start($patient, $this->admin->id)['url'], 'localhost cannot be opened by a phone.');

        config(['app.lan_url' => 'http://192.168.1.10']);
        $this->assertStringStartsWith('http://192.168.1.10/scan/', $scan->start($patient, $this->admin->id)['url']);
    }

    #[Test]
    public function returning_paper_patient_is_created_in_one_step_and_lands_on_the_scan(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->practice);
        $this->patient($this->practice, '01011111111', 'Existing');

        // Same phone → refused, nothing created.
        Livewire::test(ListPatients::class)
            ->callAction('fromPaperCard', data: ['name' => 'Someone Else', 'phone' => '010 1111 1111'])
            ->assertNotified();
        $this->assertSame(1, Patient::count());

        Livewire::test(ListPatients::class)
            ->callAction('fromPaperCard', data: ['name' => 'Hassan  Mahmoud Ali', 'phone' => '+201277777777'])
            ->assertRedirectContains('?scan=1');

        $patient = Patient::where('phone', '01277777777')->firstOrFail();
        $this->assertSame($this->practice->id, (int) $patient->practice_id);
        $this->assertSame(['Hassan', 'Mahmoud Ali'], [$patient->first_name, $patient->last_name]);
    }
}
