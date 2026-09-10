<?php

namespace Tests\Feature;

use App\Models\Practice;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportTicketModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Practice $practiceA;
    protected Practice $practiceB;
    protected User $userA;
    protected User $userB;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'clinic_admin']);

        $this->practiceA = Practice::create(['name' => 'Practice Alpha', 'is_active' => true]);
        $this->practiceB = Practice::create(['name' => 'Practice Beta', 'is_active' => true]);

        $this->userA = User::create([
            'name' => 'User Alpha',
            'email' => 'alpha@support.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceA->id,
            'role' => 'clinic_admin',
        ]);
        $this->userA->assignRole('clinic_admin');

        $this->userB = User::create([
            'name' => 'User Beta',
            'email' => 'beta@support.test',
            'password' => bcrypt('password'),
            'practice_id' => $this->practiceB->id,
            'role' => 'clinic_admin',
        ]);
        $this->userB->assignRole('clinic_admin');

        $this->superAdmin = User::create([
            'name' => 'Support SuperAdmin',
            'email' => 'super@support.test',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);
        $this->superAdmin->assignRole('super_admin');
    }

    /** @test */
    public function ticket_creation_and_validation()
    {
        $this->actingAs($this->userA);

        // Blank subject & message should fail validation
        $responseFail = $this->postJson('/api/support/tickets', [
            'subject' => '',
            'message' => '',
        ]);
        $responseFail->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'message']);

        // Valid ticket creation
        $response = $this->postJson('/api/support/tickets', [
            'subject' => 'Printer Integration Issue',
            'message' => 'Prescription printing alignment is off by 1cm.',
            'priority' => 'high',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('ticket.subject', 'Printer Integration Issue')
            ->assertJsonPath('ticket.status', 'open');

        $this->assertDatabaseHas('support_tickets', [
            'practice_id' => $this->practiceA->id,
            'subject' => 'Printer Integration Issue',
            'priority' => 'high',
        ]);
    }

    /** @test */
    public function tenant_isolation_for_tickets()
    {
        $ticketA = SupportTicket::create([
            'ticket_number' => 'TICK-A1',
            'practice_id' => $this->practiceA->id,
            'user_id' => $this->userA->id,
            'subject' => 'Ticket A Subject',
            'message' => 'Message A',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        // User B attempts to view Ticket A details -> 403 Forbidden
        $this->actingAs($this->userB);
        $response = $this->getJson("/api/support/tickets/{$ticketA->id}");
        $response->assertStatus(403);

        // User B attempts to reply to Ticket A -> 403 Forbidden
        $replyResponse = $this->postJson("/api/support/tickets/{$ticketA->id}/reply", [
            'message' => 'Cross-tenant reply attempt',
        ]);
        $replyResponse->assertStatus(403);
    }

    /** @test */
    public function status_transitions_and_authorized_admin_response()
    {
        $ticketA = SupportTicket::create([
            'ticket_number' => 'TICK-A2',
            'practice_id' => $this->practiceA->id,
            'user_id' => $this->userA->id,
            'subject' => 'Billing Query',
            'message' => 'How do I add custom tax rates?',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        // Super Admin replies to ticket
        $this->actingAs($this->superAdmin);
        $replyResponse = $this->postJson("/api/support/tickets/{$ticketA->id}/reply", [
            'message' => 'Go to Filament Settings -> Tax Rates.',
        ]);

        $replyResponse->assertStatus(200)
            ->assertJsonPath('ticket.status', 'waiting');

        $ticketA->refresh();
        $this->assertEquals('waiting', $ticketA->status);

        // Status transition to resolved by Super Admin
        $statusResponse = $this->patchJson("/api/support/tickets/{$ticketA->id}/status", [
            'status' => 'resolved',
        ]);
        $statusResponse->assertStatus(200);

        $this->assertEquals('resolved', $ticketA->fresh()->status);
    }
}
