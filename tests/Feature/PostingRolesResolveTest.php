<?php

use App\Models\LedgerAccount;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;

/**
 * Every role the posting service asks for must resolve to an account.
 *
 * `LedgerPostingService` names accounts by role rather than by code, so that
 * renumbering the chart cannot silently redirect a posting. The weakness of
 * that indirection is the one this guards: a role that resolves to nothing
 * raises no error until somebody tries to post a real document, and then the
 * whole operation fails at the worst possible moment.
 *
 * It has already happened. A migration adding input VAT found account 1006
 * free of *that* role, claimed it, and overwrote the `inventory_in_transit`
 * role the account actually held — so warehouse transfers could no longer be
 * shipped, and nothing said so until one was. This test reads the roles out of
 * the service itself, so a role added there without an account to back it
 * fails here rather than in production.
 */
function rolesUsedByPostingService(): array
{
    $source = file_get_contents(app_path('Services/Accounting/LedgerPostingService.php'));

    preg_match_all("/'role'\s*=>\s*'([a-z_]+)'/", $source, $literal);

    // `$cashRole` and friends are chosen at runtime from a small set of
    // literals; those appear in the same file as plain strings.
    preg_match_all("/=\s*'(cash|bank)'\s*:/", $source, $ternary);

    return array_values(array_unique(array_merge(
        $literal[1] ?? [],
        $ternary[1] ?? [],
        // Resolved through a variable, so the pattern above cannot see them.
        ['cash', 'bank', 'inventory']
    )));
}

it('finds a plausible number of roles, so the parser has not stopped matching', function () {
    expect(count(rolesUsedByPostingService()))->toBeGreaterThan(10);
});

it('resolves every role the posting service uses to a real account', function () {
    $missing = [];

    foreach (rolesUsedByPostingService() as $role) {
        if (! LedgerAccount::where('posting_role', $role)->exists()) {
            $missing[] = $role;
        }
    }

    expect($missing)->toBe([], 'أدوار لا يقابلها حساب في دليل الحسابات: '.implode(', ', $missing));
});

it('keeps goods-in-transit and input VAT on separate accounts', function () {
    $inTransit = LedgerAccount::where('posting_role', 'inventory_in_transit')->first();
    $inputVat = LedgerAccount::where('posting_role', 'input_vat')->first();

    expect($inTransit)->not->toBeNull();
    expect($inputVat)->not->toBeNull();
    expect($inTransit->id)->not->toBe($inputVat->id);

    // The names are what an accountant reads on the statement; a role pointing
    // at an account that describes something else is the failure this test was
    // written for.
    expect($inTransit->name)->toContain('الطريق');
    expect($inputVat->name)->toContain('ضريبة');
});

it('can actually post a transfer shipment', function () {
    $from = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    // The end-to-end proof: this is the posting that stopped working, and no
    // test exercised it.
    $entry = app(LedgerPostingService::class)->postTransferShipment(
        key: 'transfer_test:1',
        fromWarehouseId: $from->id,
        cost: 250,
        label: 'مناقلة تجريبية',
    );

    expect($entry)->not->toBeNull();
    expect(round((float) $entry->total_debit, 2))->toBe(250.0);

    $inTransitId = LedgerAccount::where('posting_role', 'inventory_in_transit')->value('id');
    expect((int) $entry->lines->firstWhere('debit', '>', 0)->account_id)->toBe($inTransitId);
});
