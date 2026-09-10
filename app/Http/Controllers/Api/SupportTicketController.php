<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public const VALID_STATUSES = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];
    public const VALID_PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /**
     * List support tickets for tenant or super admin.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('super_admin') || $user->role === 'super_admin' || $user->role === 'developer';

        $query = SupportTicket::query()->with(['creator:id,name,email', 'practice:id,name']);

        if (!$isSuperAdmin) {
            if (!$user->practice_id) {
                return response()->json(['message' => 'No practice context found.'], 400);
            }
            $query->where('practice_id', $user->practice_id);
        }

        if ($request->filled('status') && in_array($request->input('status'), self::VALID_STATUSES, true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority') && in_array($request->input('priority'), self::VALID_PRIORITIES, true)) {
            $query->where('priority', $request->input('priority'));
        }

        $tickets = $query->latest()->get();

        return response()->json([
            'tickets' => $tickets,
            'statuses' => self::VALID_STATUSES,
            'priorities' => self::VALID_PRIORITIES,
        ]);
    }

    /**
     * Create a new support ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $practiceId = $user->practice_id;

        if (!$practiceId) {
            return response()->json(['message' => 'No practice context found.'], 400);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'priority' => 'nullable|string|in:' . implode(',', self::VALID_PRIORITIES),
        ]);

        $ticketNumber = 'TICK-' . strtoupper(substr(uniqid(), -6));

        $ticket = SupportTicket::create([
            'ticket_number' => $ticketNumber,
            'practice_id' => $practiceId,
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'open',
            'priority' => $validated['priority'] ?? 'medium',
        ]);

        return response()->json([
            'message' => 'Support ticket created successfully.',
            'ticket' => $ticket->load(['creator', 'practice']),
        ], 201);
    }

    /**
     * View ticket details and replies.
     */
    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('super_admin') || $user->role === 'super_admin' || $user->role === 'developer';

        if (!$isSuperAdmin && (int) $user->practice_id !== (int) $ticket->practice_id) {
            return response()->json(['message' => 'Unauthorized cross-tenant access to support ticket.'], 403);
        }

        return response()->json([
            'ticket' => $ticket->load(['creator:id,name,email', 'practice:id,name', 'replies.user:id,name,role']),
        ]);
    }

    /**
     * Add reply to ticket.
     */
    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('super_admin') || $user->role === 'super_admin' || $user->role === 'developer';

        if (!$isSuperAdmin && (int) $user->practice_id !== (int) $ticket->practice_id) {
            return response()->json(['message' => 'Unauthorized cross-tenant access to support ticket.'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $reply = $ticket->replies()->create([
            'user_id' => $user->id,
            'message' => $validated['message'],
            'is_staff_reply' => $isSuperAdmin,
        ]);

        if ($isSuperAdmin) {
            $ticket->update(['status' => 'waiting']);
            NotificationService::notifySystemAlert(
                $ticket->practice_id,
                "Support Reply on Ticket #{$ticket->ticket_number}",
                "Support team replied: " . substr($validated['message'], 0, 100) . "..."
            );
        } else {
            if ($ticket->status === 'waiting' || $ticket->status === 'resolved') {
                $ticket->update(['status' => 'in_progress']);
            }
        }

        return response()->json([
            'message' => 'Reply submitted successfully.',
            'reply' => $reply->load('user:id,name,role'),
            'ticket' => $ticket->fresh(['replies.user']),
        ]);
    }

    /**
     * Update ticket status or priority.
     */
    public function updateStatus(Request $request, SupportTicket $ticket): JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('super_admin') || $user->role === 'super_admin' || $user->role === 'developer';

        if (!$isSuperAdmin && (int) $user->practice_id !== (int) $ticket->practice_id) {
            return response()->json(['message' => 'Unauthorized cross-tenant access to support ticket.'], 403);
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:' . implode(',', self::VALID_STATUSES),
            'priority' => 'nullable|string|in:' . implode(',', self::VALID_PRIORITIES),
        ]);

        $updates = [];
        if (isset($validated['status'])) {
            $updates['status'] = $validated['status'];
        }
        if (isset($validated['priority'])) {
            $updates['priority'] = $validated['priority'];
        }

        $ticket->update($updates);

        return response()->json([
            'message' => 'Support ticket updated.',
            'ticket' => $ticket->fresh(),
        ]);
    }
}
