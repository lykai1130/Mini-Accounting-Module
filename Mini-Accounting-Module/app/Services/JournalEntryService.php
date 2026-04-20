<?php

namespace App\Services;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    /**
     * @param  array{
     *     entry_date: string,
     *     description?: string|null,
     *     lines: array<int, array{
     *         account_id: int,
     *         type: string,
     *         amount: numeric-string|int|float,
     *         line_description?: string|null
     *     }>
     * }  $payload
     */
    public function create(array $payload): JournalEntry
    {
        return DB::transaction(function () use ($payload): JournalEntry {
            $entry = JournalEntry::query()->create([
                'entry_date' => $payload['entry_date'],
                'description' => $payload['description'] ?? null,
            ]);

            $entry->lines()->createMany(
                collect($payload['lines'])
                    ->map(fn (array $line): array => [
                        'account_id' => $line['account_id'],
                        'type' => $this->normalizeLineType($line['type']),
                        'amount' => $line['amount'],
                        'line_description' => $line['line_description'] ?? null,
                    ])
                    ->all()
            );

            return $entry->load('lines.account');
        });
    }

    private function normalizeLineType(string $type): string
    {
        $normalized = strtolower(trim($type));

        return in_array($normalized, ['dr', 'debit'], true) ? 'dr' : 'cr';
    }
}
