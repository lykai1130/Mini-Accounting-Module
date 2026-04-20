<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.type' => ['required', 'string', 'in:debit,credit,dr,cr'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.line_description' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lines = collect($request->input('lines', []));
            $debit = $lines
                ->filter(fn (array $line): bool => in_array(strtolower((string) ($line['type'] ?? '')), ['debit', 'dr'], true))
                ->sum(fn (array $line): float => (float) $line['amount']);
            $credit = $lines
                ->filter(fn (array $line): bool => in_array(strtolower((string) ($line['type'] ?? '')), ['credit', 'cr'], true))
                ->sum(fn (array $line): float => (float) $line['amount']);

            if (abs($debit - $credit) > 0.00001) {
                $validator->errors()->add(
                    'lines',
                    'Total debits must equal total credits.'
                );
            }
        });

        $entry = $this->journalEntryService->create($validator->validate());

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
