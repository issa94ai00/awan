<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;

/**
 * The sales orders and invoices lists name the rep each sale is credited to.
 * An invoice raised from an order carries no rep of its own, so it names the
 * order's.
 */
function listRep(string $email, string $phone): Employee
{
    return Employee::create([
        'first_name' => 'مندوب',
        'last_name' => $email,
        'email' => $email,
        'phone' => $phone,
        'position' => 'مندوب مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'status' => 'نشط',
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->customer = Customer::create(['name' => 'عميل', 'phone' => '0999000000']);
});

function listOrder(Customer $customer, ?Employee $rep): SalesOrder
{
    return SalesOrder::create([
        'order_number' => 'SO-REP-'.uniqid(),
        'customer_id' => $customer->id,
        'assigned_employee_id' => $rep?->id,
        'status' => SalesOrder::STATUS_PENDING,
        'subtotal' => 100, 'tax' => 0, 'discount' => 0, 'total' => 100,
        'currency' => 'SYP',
    ]);
}

function listInvoice(Customer $customer, ?Employee $rep, ?SalesOrder $order = null): Invoice
{
    return Invoice::create([
        'invoice_number' => 'INV-REP-'.uniqid(),
        'customer_id' => $customer->id,
        'sales_order_id' => $order?->id,
        'assigned_employee_id' => $rep?->id,
        'subtotal' => 100, 'tax' => 0, 'discount' => 0, 'total' => 100,
        'paid_amount' => 0, 'due_amount' => 100,
        'status' => Invoice::STATUS_CONFIRMED,
    ]);
}

it('names the rep and their phone on the sales orders list', function () {
    $rep = listRep('so-rep@example.test', '0933111222');
    $order = listOrder($this->customer, $rep);

    $row = collect($this->getJson('/api/v1/sales-orders')->assertOk()->json('data.sales_orders'))
        ->firstWhere('id', $order->id);

    expect($row['assigned_employee']['name'])->toBe($rep->name)
        ->and($row['assigned_employee']['phone'])->toBe('0933111222');
});

it('names the invoice\'s own rep, else its order\'s, on the invoices list', function () {
    $own = listRep('own@example.test', '0933000001');
    $orderRep = listRep('order@example.test', '0933000002');

    $direct = listInvoice($this->customer, $own);
    $fromOrder = listInvoice($this->customer, null, listOrder($this->customer, $orderRep));
    $none = listInvoice($this->customer, null);

    $rows = collect($this->getJson('/api/v1/invoices?lean=1')->assertOk()->json('data.invoices'))->keyBy('id');

    expect($rows[$direct->id]['sales_rep'])->toMatchArray(['id' => $own->id, 'phone' => '0933000001', 'from_order' => false])
        ->and($rows[$fromOrder->id]['sales_rep'])->toMatchArray(['id' => $orderRep->id, 'from_order' => true])
        ->and($rows[$none->id]['sales_rep'])->toBeNull();
});

it('credits an order\'s invoice to the order\'s rep when it is raised', function () {
    $rep = listRep('raised@example.test', '0933000003');
    $order = listOrder($this->customer, $rep);
    $order->items()->create([
        'product_id' => \App\Models\Product::factory()->create()->id,
        'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'tax' => 0,
    ]);

    $invoice = app(\App\Services\Sales\SalesOrderWorkflowService::class)->ensureInvoice($order);

    expect((int) $invoice->assigned_employee_id)->toBe($rep->id);
});
