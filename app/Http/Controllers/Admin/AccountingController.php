<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function index()
    {
        $accountsCount = LedgerAccount::count();
        $entriesCount = JournalEntry::count();
        $balanceSum = LedgerAccount::sum('balance');

        return view('admin.accounting.index', compact('accountsCount', 'entriesCount', 'balanceSum'));
    }

    public function journal(Request $request)
    {
        $entries = JournalEntry::with('ledgerAccount')
            ->latest('entry_date')
            ->paginate(20);

        return view('admin.accounting.journal', compact('entries'));
    }

    public function ledger(Request $request)
    {
        $accounts = LedgerAccount::orderBy('code')->paginate(20);

        return view('admin.accounting.ledger', compact('accounts'));
    }

    public function trialBalance()
    {
        $accounts = LedgerAccount::orderBy('type')->get();
        $totalDebit = JournalEntry::sum('debit');
        $totalCredit = JournalEntry::sum('credit');

        return view('admin.accounting.trial-balance', compact('accounts', 'totalDebit', 'totalCredit'));
    }
}
