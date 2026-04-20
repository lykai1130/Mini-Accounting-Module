<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJournalEntryRequest;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $entries = JournalEntry::query()
            ->with(['lines.account'])
            ->when(
                $request->filled('from'),
                fn ($query) => $query->whereDate('entry_date', '>=', $request->string('from')->toString())
            )
            ->when(
                $request->filled('to'),
                fn ($query) => $query->whereDate('entry_date', '<=', $request->string('to')->toString())
            )
            ->when(
                $request->filled('account_id'),
                fn ($query) => $query->whereHas(
                    'lines',
                    fn ($lineQuery) => $lineQuery->where('account_id', $request->integer('account_id'))
                )
            )
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $entries,
        ]);
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $entry = $this->journalEntryService->create(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'message' => 'Journal entry saved successfully.',
            'data' => $entry,
        ], 201);
    }

    public function destroy(JournalEntry $journalEntry): JsonResponse
    {
        $journalEntry->delete();

        return response()->json([
            'message' => 'Journal entry deleted successfully.',
        ]);
    }
}
