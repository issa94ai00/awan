<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addColumnIfMissing(string $table, string $column, callable $definition): void
    {
        if (!Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $t) use ($definition) {
                $definition($t);
            });
        }
    }

    public function up(): void
    {
        // ========================================
        // PRODUCTS — remaining ERP fields
        // ========================================
        $this->addColumnIfMissing('products', 'short_description_ar', fn(Blueprint $t) => $t->text('short_description_ar')->nullable()->after('description_en'));
        $this->addColumnIfMissing('products', 'short_description_en', fn(Blueprint $t) => $t->text('short_description_en')->nullable()->after('short_description_ar'));
        $this->addColumnIfMissing('products', 'sort_order', fn(Blueprint $t) => $t->integer('sort_order')->default(0)->after('views_count'));
        $this->addColumnIfMissing('products', 'max_stock', fn(Blueprint $t) => $t->integer('max_stock')->nullable()->after('sort_order'));
        $this->addColumnIfMissing('products', 'weight', fn(Blueprint $t) => $t->decimal('weight', 8, 2)->nullable()->after('max_stock'));
        $this->addColumnIfMissing('products', 'length', fn(Blueprint $t) => $t->decimal('length', 10, 2)->nullable()->after('weight'));
        $this->addColumnIfMissing('products', 'width', fn(Blueprint $t) => $t->decimal('width', 10, 2)->nullable()->after('length'));
        $this->addColumnIfMissing('products', 'height', fn(Blueprint $t) => $t->decimal('height', 10, 2)->nullable()->after('width'));
        $this->addColumnIfMissing('products', 'color', fn(Blueprint $t) => $t->string('color')->nullable()->after('model'));
        $this->addColumnIfMissing('products', 'size', fn(Blueprint $t) => $t->string('size')->nullable()->after('color'));
        $this->addColumnIfMissing('products', 'variant_group', fn(Blueprint $t) => $t->string('variant_group')->nullable()->after('size'));

        // ========================================
        // CATEGORIES — hierarchy + translations
        // ========================================
        $this->addColumnIfMissing('categories', 'parent_id', fn(Blueprint $t) => $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete());
        $this->addColumnIfMissing('categories', 'description_en', fn(Blueprint $t) => $t->text('description_en')->nullable());
        $this->addColumnIfMissing('categories', 'meta_title', fn(Blueprint $t) => $t->string('meta_title')->nullable());
        $this->addColumnIfMissing('categories', 'meta_description', fn(Blueprint $t) => $t->string('meta_description')->nullable());
        $this->addColumnIfMissing('categories', 'is_featured', fn(Blueprint $t) => $t->boolean('is_featured')->default(false));

        // ========================================
        // CUSTOMERS — full address + tax
        // ========================================
        $this->addColumnIfMissing('customers', 'tax_number', fn(Blueprint $t) => $t->string('tax_number')->nullable()->after('email'));
        $this->addColumnIfMissing('customers', 'city', fn(Blueprint $t) => $t->string('city')->nullable()->after('address'));
        $this->addColumnIfMissing('customers', 'state', fn(Blueprint $t) => $t->string('state')->nullable()->after('city'));
        $this->addColumnIfMissing('customers', 'country', fn(Blueprint $t) => $t->string('country')->nullable()->after('state'));
        $this->addColumnIfMissing('customers', 'postal_code', fn(Blueprint $t) => $t->string('postal_code')->nullable()->after('country'));
        $this->addColumnIfMissing('customers', 'currency', fn(Blueprint $t) => $t->string('currency')->default('SAR'));
        $this->addColumnIfMissing('customers', 'total_purchases', fn(Blueprint $t) => $t->decimal('total_purchases', 14, 2)->default(0));
        $this->addColumnIfMissing('customers', 'last_purchase_at', fn(Blueprint $t) => $t->date('last_purchase_at')->nullable());

        // ========================================
        // SUPPLIERS — full address + tax
        // ========================================
        $this->addColumnIfMissing('suppliers', 'tax_number', fn(Blueprint $t) => $t->string('tax_number')->nullable()->after('email'));
        $this->addColumnIfMissing('suppliers', 'city', fn(Blueprint $t) => $t->string('city')->nullable()->after('address'));
        $this->addColumnIfMissing('suppliers', 'state', fn(Blueprint $t) => $t->string('state')->nullable()->after('city'));
        $this->addColumnIfMissing('suppliers', 'country', fn(Blueprint $t) => $t->string('country')->nullable()->after('state'));
        $this->addColumnIfMissing('suppliers', 'postal_code', fn(Blueprint $t) => $t->string('postal_code')->nullable()->after('country'));
        $this->addColumnIfMissing('suppliers', 'currency', fn(Blueprint $t) => $t->string('currency')->default('SAR'));
        $this->addColumnIfMissing('suppliers', 'lead_time_days', fn(Blueprint $t) => $t->string('lead_time_days')->nullable());
        $this->addColumnIfMissing('suppliers', 'total_purchases', fn(Blueprint $t) => $t->decimal('total_purchases', 14, 2)->default(0));

        // ========================================
        // EMPLOYEES — employment details
        // ========================================
        $this->addColumnIfMissing('employees', 'job_title', fn(Blueprint $t) => $t->string('job_title')->nullable()->after('position'));
        $this->addColumnIfMissing('employees', 'employment_type', fn(Blueprint $t) => $t->string('employment_type')->default('full_time'));
        $this->addColumnIfMissing('employees', 'contract_type', fn(Blueprint $t) => $t->string('contract_type')->default('permanent'));
        $this->addColumnIfMissing('employees', 'bonus', fn(Blueprint $t) => $t->decimal('bonus', 12, 2)->default(0));
        $this->addColumnIfMissing('employees', 'national_id', fn(Blueprint $t) => $t->string('national_id')->nullable());
        $this->addColumnIfMissing('employees', 'nationality', fn(Blueprint $t) => $t->string('nationality')->nullable());
        $this->addColumnIfMissing('employees', 'contract_start', fn(Blueprint $t) => $t->date('contract_start')->nullable());
        $this->addColumnIfMissing('employees', 'contract_end', fn(Blueprint $t) => $t->date('contract_end')->nullable());
        $this->addColumnIfMissing('employees', 'emergency_contact_name', fn(Blueprint $t) => $t->string('emergency_contact_name')->nullable());
        $this->addColumnIfMissing('employees', 'emergency_contact_phone', fn(Blueprint $t) => $t->string('emergency_contact_phone')->nullable());
        $this->addColumnIfMissing('employees', 'bank_name', fn(Blueprint $t) => $t->string('bank_name')->nullable());
        $this->addColumnIfMissing('employees', 'bank_account_number', fn(Blueprint $t) => $t->string('bank_account_number')->nullable());

        // ========================================
        // INVOICES — currency + tracking
        // ========================================
        $this->addColumnIfMissing('invoices', 'currency', fn(Blueprint $t) => $t->string('currency')->default('SAR'));
        $this->addColumnIfMissing('invoices', 'exchange_rate', fn(Blueprint $t) => $t->decimal('exchange_rate', 10, 4)->default(1));
        $this->addColumnIfMissing('invoices', 'due_date', fn(Blueprint $t) => $t->date('due_date')->nullable());
        $this->addColumnIfMissing('invoices', 'reference', fn(Blueprint $t) => $t->string('reference')->nullable());

        // ========================================
        // INVOICE ITEMS — discount + tax
        // ========================================
        $this->addColumnIfMissing('invoice_items', 'discount', fn(Blueprint $t) => $t->decimal('discount', 10, 2)->default(0));
        $this->addColumnIfMissing('invoice_items', 'tax_rate', fn(Blueprint $t) => $t->decimal('tax_rate', 5, 2)->default(0));
        $this->addColumnIfMissing('invoice_items', 'tax_amount', fn(Blueprint $t) => $t->decimal('tax_amount', 10, 2)->default(0));

        // ========================================
        // SALES ORDERS — currency + payment + dates
        // ========================================
        $this->addColumnIfMissing('sales_orders', 'currency', fn(Blueprint $t) => $t->string('currency')->default('SAR'));
        $this->addColumnIfMissing('sales_orders', 'paid_amount', fn(Blueprint $t) => $t->decimal('paid_amount', 12, 2)->default(0));
        $this->addColumnIfMissing('sales_orders', 'due_amount', fn(Blueprint $t) => $t->decimal('due_amount', 12, 2)->default(0));
        $this->addColumnIfMissing('sales_orders', 'confirmed_at', fn(Blueprint $t) => $t->date('confirmed_at')->nullable());
        $this->addColumnIfMissing('sales_orders', 'shipped_at', fn(Blueprint $t) => $t->date('shipped_at')->nullable());
        $this->addColumnIfMissing('sales_orders', 'delivered_at', fn(Blueprint $t) => $t->date('delivered_at')->nullable());

        // ========================================
        // PURCHASE ORDERS — subtotal + dates + currency
        // ========================================
        $this->addColumnIfMissing('purchase_orders', 'subtotal', fn(Blueprint $t) => $t->decimal('subtotal', 12, 2)->default(0));
        $this->addColumnIfMissing('purchase_orders', 'currency', fn(Blueprint $t) => $t->string('currency')->default('SAR'));
        $this->addColumnIfMissing('purchase_orders', 'order_date', fn(Blueprint $t) => $t->date('order_date')->nullable());
        $this->addColumnIfMissing('purchase_orders', 'received_date', fn(Blueprint $t) => $t->date('received_date')->nullable());
        $this->addColumnIfMissing('purchase_orders', 'paid_amount', fn(Blueprint $t) => $t->decimal('paid_amount', 12, 2)->default(0));
        $this->addColumnIfMissing('purchase_orders', 'due_amount', fn(Blueprint $t) => $t->decimal('due_amount', 12, 2)->default(0));

        // ========================================
        // PURCHASE RECEIPTS
        // ========================================
        $this->addColumnIfMissing('purchase_receipts', 'status', fn(Blueprint $t) => $t->string('status')->default('received'));
        $this->addColumnIfMissing('purchase_receipts', 'currency', fn(Blueprint $t) => $t->string('currency')->default('SAR'));
        $this->addColumnIfMissing('purchase_receipts', 'shipping_address', fn(Blueprint $t) => $t->text('shipping_address')->nullable());

        // ========================================
        // PAYMENTS — currency + reconciliation
        // ========================================
        $this->addColumnIfMissing('payments', 'currency', fn(Blueprint $t) => $t->string('currency')->default('SAR'));
        $this->addColumnIfMissing('payments', 'exchange_rate', fn(Blueprint $t) => $t->decimal('exchange_rate', 10, 4)->default(1));
        $this->addColumnIfMissing('payments', 'sales_order_id', fn(Blueprint $t) => $t->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete());
        $this->addColumnIfMissing('payments', 'purchase_order_id', fn(Blueprint $t) => $t->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete());

        // ========================================
        // STOCK MOVEMENTS — warehouse + cost
        // ========================================
        $this->addColumnIfMissing('stock_movements', 'warehouse_id', fn(Blueprint $t) => $t->foreignId('warehouse_id')->nullable());
        $this->addColumnIfMissing('stock_movements', 'unit_cost', fn(Blueprint $t) => $t->decimal('unit_cost', 12, 2)->default(0));
        $this->addColumnIfMissing('stock_movements', 'total_cost', fn(Blueprint $t) => $t->decimal('total_cost', 14, 2)->default(0));
        $this->addColumnIfMissing('stock_movements', 'batch_number', fn(Blueprint $t) => $t->string('batch_number')->nullable());
        $this->addColumnIfMissing('stock_movements', 'expiry_date', fn(Blueprint $t) => $t->date('expiry_date')->nullable());

        // ========================================
        // LEDGER ACCOUNTS — type + currency
        // ========================================
        $this->addColumnIfMissing('ledger_accounts', 'account_type', fn(Blueprint $t) => $t->string('account_type')->default('asset'));
        $this->addColumnIfMissing('ledger_accounts', 'currency', fn(Blueprint $t) => $t->string('currency')->default('SAR'));
        $this->addColumnIfMissing('ledger_accounts', 'opening_balance', fn(Blueprint $t) => $t->decimal('opening_balance', 14, 2)->default(0));
        $this->addColumnIfMissing('ledger_accounts', 'is_system', fn(Blueprint $t) => $t->boolean('is_system')->default(false));

        // ========================================
        // JOURNAL ENTRIES — amounts
        // ========================================
        $this->addColumnIfMissing('journal_entries', 'ledger_account_id', fn(Blueprint $t) => $t->foreignId('ledger_account_id')->nullable()->constrained()->nullOnDelete());
        $this->addColumnIfMissing('journal_entries', 'debit', fn(Blueprint $t) => $t->decimal('debit', 14, 2)->default(0));
        $this->addColumnIfMissing('journal_entries', 'credit', fn(Blueprint $t) => $t->decimal('credit', 14, 2)->default(0));
        $this->addColumnIfMissing('journal_entries', 'currency', fn(Blueprint $t) => $t->string('currency')->default('SAR'));

        // ========================================
        // NEW TABLES
        // ========================================
        if (!Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('manager_name')->nullable();
                $table->string('manager_phone')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('product_units')) {
            Schema::create('product_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('name_ar')->nullable();
                $table->integer('base_unit_multiplier')->default(1);
                $table->decimal('price_multiplier', 8, 4)->default(1);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $table) {
                $table->id();
                $table->string('contactable_type');
                $table->unsignedBigInteger('contactable_id');
                $table->string('name');
                $table->string('position')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('warehouses');

        $tables = [
            'products' => ['short_description_ar','short_description_en','sort_order','weight','length','width','height','color','size','variant_group'],
            'categories' => ['parent_id','description_en','meta_title','meta_description','is_featured'],
            'customers' => ['tax_number','city','state','country','postal_code','currency','total_purchases','last_purchase_at'],
            'suppliers' => ['tax_number','city','state','country','postal_code','currency','lead_time_days','total_purchases'],
            'employees' => ['job_title','employment_type','contract_type','bonus','national_id','nationality','contract_start','contract_end','emergency_contact_name','emergency_contact_phone','bank_name','bank_account_number'],
            'invoices' => ['currency','exchange_rate','due_date','reference'],
            'invoice_items' => ['discount','tax_rate','tax_amount'],
            'sales_orders' => ['currency','paid_amount','due_amount','confirmed_at','shipped_at','delivered_at'],
            'purchase_orders' => ['subtotal','currency','order_date','received_date','paid_amount','due_amount'],
            'purchase_receipts' => ['status','currency','shipping_address'],
            'payments' => ['currency','exchange_rate','sales_order_id','purchase_order_id'],
            'stock_movements' => ['warehouse_id','unit_cost','total_cost','batch_number','expiry_date'],
            'ledger_accounts' => ['account_type','currency','opening_balance','is_system'],
            'journal_entries' => ['ledger_account_id','debit','credit','currency'],
        ];

        foreach ($tables as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
