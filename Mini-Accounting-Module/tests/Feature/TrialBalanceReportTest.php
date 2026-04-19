<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialBalanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_trial_balance_grouped_by_account(): void
    {
        $cash = Account::query()->create([
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
        ]);

        $income = Account::query()->create([
            'code' => '4000',
            'name' => 'Service Income',
            'type' => 'income',
        ]);

        $expense = Account::query()->create([
            'code' => '5000',
            'name' => 'Office Expense',
            'type' => 'expense',
        ]);

        $this->postJson('/api/journal-entries', [
            'entry_date' => '2026-04-10',
            'description' => 'Sale entry',
            'lines' => [
                ['account_id' => $cash->id, 'type' => 'debit', 'amount' => 100],
                ['account_id' => $income->id, 'type' => 'credit', 'amount' => 100],
            ],
        ])->assertCreated();

        $this->postJson('/api/journal-entries', [
            'entry_date' => '2026-04-15',
            'description' => 'Office purchase',
            'lines' => [
                ['account_id' => $expense->id, 'type' => 'debit', 'amount' => 40],
                ['account_id' => $cash->id, 'type' => 'credit', 'amount' => 40],
            ],
        ])->assertCreated();

        $response = $this->getJson('/api/reports/trial-balance?from=2026-04-01&to=2026-04-30');
        $response->assertOk();

        $rows = collect($response->json('data'));

        $cashRow = $rows->firstWhere('code', '1000');
        $incomeRow = $rows->firstWhere('code', '4000');
        $expenseRow = $rows->firstWhere('code', '5000');

        $this->assertNotNull($cashRow);
        $this->assertNotNull($incomeRow);
        $this->assertNotNull($expenseRow);

        $this->assertSame(100.0, (float) $cashRow['total_debit']);
        $this->assertSame(40.0, (float) $cashRow['total_credit']);
        $this->assertSame(60.0, (float) $cashRow['balance']);

        $this->assertSame(0.0, (float) $incomeRow['total_debit']);
        $this->assertSame(100.0, (float) $incomeRow['total_credit']);
        $this->assertSame(-100.0, (float) $incomeRow['balance']);

        $this->assertSame(40.0, (float) $expenseRow['total_debit']);
        $this->assertSame(0.0, (float) $expenseRow['total_credit']);
        $this->assertSame(40.0, (float) $expenseRow['balance']);

        $this->assertSame(140.0, (float) $response->json('summary.total_debit'));
        $this->assertSame(140.0, (float) $response->json('summary.total_credit'));
    }
}

