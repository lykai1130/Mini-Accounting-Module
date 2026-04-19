<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrialBalanceReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $lineTotalsSubQuery = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when(
                $request->filled('from'),
                fn ($query) => $query->whereDate('journal_entries.entry_date', '>=', $request->string('from')->toString())
            )
            ->when(
                $request->filled('to'),
                fn ($query) => $query->whereDate('journal_entries.entry_date', '<=', $request->string('to')->toString())
            )
            ->groupBy('journal_lines.account_id')
            ->select([
                'journal_lines.account_id',
                DB::raw("SUM(CASE WHEN journal_lines.type = 'debit' THEN journal_lines.amount ELSE 0 END) as total_debit"),
                DB::raw("SUM(CASE WHEN journal_lines.type = 'credit' THEN journal_lines.amount ELSE 0 END) as total_credit"),
            ]);

        $rows = Account::query()
            ->leftJoinSub($lineTotalsSubQuery, 'totals', function ($join): void {
                $join->on('accounts.id', '=', 'totals.account_id');
            })
            ->orderBy('accounts.code')
            ->get([
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('COALESCE(totals.total_debit, 0) as total_debit'),
                DB::raw('COALESCE(totals.total_credit, 0) as total_credit'),
                DB::raw('COALESCE(totals.total_debit, 0) - COALESCE(totals.total_credit, 0) as balance'),
            ]);

        return response()->json([
            'data' => $rows,
            'summary' => [
                'total_debit' => $rows->sum('total_debit'),
                'total_credit' => $rows->sum('total_credit'),
            ],
        ]);
    }
}

