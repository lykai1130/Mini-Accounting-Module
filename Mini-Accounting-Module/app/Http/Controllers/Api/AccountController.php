<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = Account::query()
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $accounts,
        ]);
    }
}

