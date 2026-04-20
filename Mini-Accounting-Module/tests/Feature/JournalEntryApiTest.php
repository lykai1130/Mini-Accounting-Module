<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_a_balanced_journal_entry(): void
    {
        $cash = Account::query()->create([
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
        ]);

        $income = Account::query()->create([
            'code' => '4000',
            'name' => 'Sales Income',
            'type' => 'income',
        ]);

        $payload = [
            'entry_date' => '2026-04-19',
            'reference_no' => 'JV-001',
            'description' => 'Cash sale',
            'lines' => [
                [
                    'account_id' => $cash->id,
                    'type' => 'debit',
                    'amount' => 100.00,
                ],
                [
                    'account_id' => $income->id,
                    'type' => 'credit',
                    'amount' => 100.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/journal-entries', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Journal entry saved successfully.')
            ->assertJsonCount(2, 'data.lines');

        $this->assertDatabaseHas('journal_entries', [
            'reference_no' => 'JV-001',
        ]);

        $this->assertDatabaseCount('journal_lines', 2);
    }

    public function test_it_rejects_an_unbalanced_journal_entry(): void
    {
        $cash = Account::query()->create([
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
        ]);

        $income = Account::query()->create([
            'code' => '4000',
            'name' => 'Sales Income',
            'type' => 'income',
        ]);

        $payload = [
            'entry_date' => '2026-04-19',
            'description' => 'Unbalanced test',
            'lines' => [
                [
                    'account_id' => $cash->id,
                    'type' => 'debit',
                    'amount' => 150.00,
                ],
                [
                    'account_id' => $income->id,
                    'type' => 'credit',
                    'amount' => 100.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/journal-entries', $payload);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines']);

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
    }

    public function test_it_deletes_a_journal_entry_with_lines(): void
    {
        $cash = Account::query()->create([
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
        ]);

        $income = Account::query()->create([
            'code' => '4000',
            'name' => 'Sales Income',
            'type' => 'income',
        ]);

        $payload = [
            'entry_date' => '2026-04-19',
            'reference_no' => 'JV-DELETE-001',
            'description' => 'Delete me',
            'lines' => [
                [
                    'account_id' => $cash->id,
                    'type' => 'debit',
                    'amount' => 250.00,
                ],
                [
                    'account_id' => $income->id,
                    'type' => 'credit',
                    'amount' => 250.00,
                ],
            ],
        ];

        $created = $this->postJson('/api/journal-entries', $payload)->assertCreated();
        $entryId = $created->json('data.id');

        $this->deleteJson("/api/journal-entries/{$entryId}")
            ->assertOk()
            ->assertJsonPath('message', 'Journal entry deleted successfully.');

        $this->assertDatabaseMissing('journal_entries', [
            'id' => $entryId,
        ]);

        $this->assertDatabaseCount('journal_lines', 0);
    }
}
