<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use App\Notifications\AppointmentReminderNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\LowInventoryNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationsSystemTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected User $userA;
    protected User $userB;
    protected Patient $patientA;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'doctor']);
        Role::firstOrCreate(['name' => 'clinic_admin']);

        $this->practiceA = Practice::create([
            'name' => 'Practice Alpha',
            'is_active' => true,
        ]);
        $this->practiceB = Practice::create([
            'name' => 'Practice Beta',
            'is_active' => true,
        ]);

        $this->userA = User::create([
            'name' => 'User Alpha',
            'email' => 'alpha@notifications.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceA->id,
            'role' => 'clinic_admin',
        ]);
        $this->userA->assignRole('clinic_admin');

        $this->userB = User::create([
            'name' => 'User Beta',
            'email' => 'beta@notifications.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceB->id,
            'role' => 'clinic_admin',
        ]);
        $this->userB->assignRole('clinic_admin');

        $this->patientA = Patient::create([
            'practice_id' => $this->practiceA->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'gender' => 'male',
        ]);
    }

    /** @test */
    public function correct_notification_created_and_received_by_recipient()
    {
        $branch = Branch::create(['practice_id' => $this->practiceA->id, 'name' => 'Main']);
        $operatory = Operatory::create(['branch_id' => $branch->id, 'name' => 'Op 1']);

        $appointment = Appointment::create([
            'practice_id' => $this->practiceA->id,
            'branch_id' => $branch->id,
            'operatory_id' => $operatory->id,
            'patient_id' => $this->patientA->id,
            'doctor_id' => $this->userA->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'booked',
        ]);

        NotificationService::notifyAppointmentReminder($appointment);

        $this->assertEquals(1, $this->userA->notifications()->count());
        $notification = $this->userA->notifications()->first();
        $this->assertEquals('appointment_reminder', $notification->data['type']);
        $this->assertEquals($appointment->id, $notification->data['appointment_id']);
    }

    /** @test */
    public function duplicate_notification_prevented_within_deduplication_window()
    {
        $item = InventoryItem::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Dental Needle 27G',
            'min_reorder_level' => 10,
        ]);

        // First notification send
        $sentFirst = NotificationService::notifyLowInventory($item, 3);
        $this->assertGreaterThan(0, $sentFirst);
        $this->assertEquals(1, $this->userA->notifications()->count());

        // Duplicate send attempt immediately afterwards
        $sentSecond = NotificationService::notifyLowInventory($item, 3);
        $this->assertEquals(0, $sentSecond);
        $this->assertEquals(1, $this->userA->notifications()->count());
    }

    /** @test */
    public function tenant_isolation_for_notification_retrieval()
    {
        $invoice = Invoice::create([
            'practice_id' => $this->practiceA->id,
            'patient_id' => $this->patientA->id,
            'invoice_number' => 'INV-OVERDUE-01',
            'issue_date' => now()->subDays(30),
            'total_amount' => 500,
            'paid_amount' => 0,
            'balance_due' => 500,
            'status' => 'unpaid',
        ]);

        NotificationService::notifyInvoiceOverdue($invoice);

        // User A (Practice A) sees the notification
        $this->actingAs($this->userA);
        $responseA = $this->getJson('/api/notifications');
        $responseA->assertStatus(200)
            ->assertJsonPath('unread_count', 1);

        // User B (Practice B) sees zero notifications for Practice A
        $this->actingAs($this->userB);
        $responseB = $this->getJson('/api/notifications');
        $responseB->assertStatus(200)
            ->assertJsonPath('unread_count', 0);
    }

    /** @test */
    public function mark_as_read_and_mark_all_as_read_endpoints()
    {
        $item = InventoryItem::create([
            'practice_id' => $this->practiceA->id,
            'name' => 'Composite Shade A2',
            'min_reorder_level' => 5,
        ]);

        NotificationService::notifyLowInventory($item, 2);

        $this->actingAs($this->userA);
        $notificationId = $this->userA->unreadNotifications()->first()->id;

        // Mark single notification as read
        $response = $this->postJson("/api/notifications/{$notificationId}/read");
        $response->assertStatus(200)
            ->assertJsonPath('unread_count', 0);

        // Mark all as read
        NotificationService::notifySystemAlert($this->practiceA->id, 'Maintenance', 'Scheduled maintenance tonight.');
        $responseAll = $this->postJson('/api/notifications/read-all');
        $responseAll->assertStatus(200)
            ->assertJsonPath('unread_count', 0);
    }
}
