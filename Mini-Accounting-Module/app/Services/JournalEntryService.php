<?php

namespace App\Services;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    /**
     * @param  array{
     *     entry_date: string,
     *     reference_no?: string|null,
     *     description?: string|null,
     *     lines: array<int, array{
     *         account_id: int,
     *         type: string,
     *         amount: numeric-string|int|float,
     *         line_description?: string|null
     *     }>
     * }  $payload
     */
    public function create(array $payload, ?int $createdBy = null): JournalEntry
    {
        return DB::transaction(function () use ($payload, $createdBy): JournalEntry {
            $entry = JournalEntry::query()->create([
                'entry_date' => $payload['entry_date'],
                'reference_no' => $payload['reference_no'] ?? null,
                'description' => $payload['description'] ?? null,
                'created_by' => $createdBy,
            ]);

            $entry->lines()->createMany(
                collect($payload['lines'])
                    ->map(fn (array $line): array => [
                        'account_id' => $line['account_id'],
                        'type' => $line['type'],
                        'amount' => $line['amount'],
                        'line_description' => $line['line_description'] ?? null,
                    ])
                    ->all()
            );

            return $entry->load('lines.account');
        });
    }
}

