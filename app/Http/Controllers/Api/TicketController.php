<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with('customer');

        if ($request->filled('search')) {
            $searchTerm = '%'.$request->search.'%';
            $query->where(function ($query) use ($searchTerm) {
                $query->where('subject', 'like', $searchTerm)
                    ->orWhere('message', 'like', $searchTerm)
                    ->orWhere('status', 'like', $searchTerm)
                    ->orWhere('priority', 'like', $searchTerm)
                    ->orWhereHas('customer', function ($query) use ($searchTerm) {
                        $query->where('name', 'like', $searchTerm)
                            ->orWhere('email', 'like', $searchTerm)
                            ->orWhere('phone', 'like', $searchTerm);
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $perPage = min(max((int) $request->get('per_page', 20), 1), 100);
        $tickets = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Tickets retrieved successfully',
            'data' => [
                'tickets' => $tickets->items(),
                'pagination' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                    'has_more_pages' => $tickets->hasMorePages(),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'status' => 'required|in:open,pending,closed,resolved',
            'priority' => 'required|in:low,medium,high',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $validated['created_by'] = auth()->id();

        $ticket = Ticket::create($validated);
        $ticket->load('customer');

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'data' => $ticket,
        ], 201);
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('customer');

        return response()->json([
            'success' => true,
            'message' => 'Ticket retrieved successfully',
            'data' => $ticket,
        ]);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'status' => 'required|in:open,pending,closed,resolved',
            'priority' => 'required|in:low,medium,high',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $ticket->update($validated);
        $ticket->load('customer');

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully',
            'data' => $ticket,
        ]);
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ticket deleted successfully',
            'data' => null,
        ]);
    }
}
