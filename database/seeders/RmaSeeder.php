<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RmaItem;
use App\Models\RmaRequest;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class RmaSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing data to reference
        $customers = Customer::limit(5)->get();
        $products = Product::limit(10)->get();
        $salesOrders = SalesOrder::where('status', 'delivered')->limit(5)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('No customers found. Please run CustomerSeeder first.');
            return;
        }

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Please run ProductSeeder first.');
            return;
        }

        // Create users if none exist
        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->info('Creating sample users...');
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'is_admin' => true,
            ]);
            User::create([
                'name' => 'Staff User',
                'email' => 'staff@example.com',
                'password' => bcrypt('password'),
                'is_admin' => false,
            ]);
            User::create([
                'name' => 'Manager User',
                'email' => 'manager@example.com',
                'password' => bcrypt('password'),
                'is_admin' => true,
            ]);
            $users = User::all();
        }

        // Use specific customer data from database.sql
        $customerIds = [1, 2, 3, 4, 5]; // IDs from database.sql
        $customerNames = ['Ahmed Al-Rashid', 'Sarah Mohammed', 'Khalid Al-Otaibi', 'Fatima Al-Qahtani', 'Omar Al-Harbi'];
        
        // Use specific product data from database.sql
        $productIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]; // IDs from database.sql
        $productNames = [
            'Roof Hatch',
            'Handrail Systems', 
            'Garbage & Linen Chutes',
            'Access Raised Floor',
            'Lockers',
            'Ladders',
            'Gratings (Steel / Galvanized)',
            'Steel Bollards',
            'Fencing',
            'Metal Gates'
        ];

        if ($salesOrders->isEmpty()) {
            $this->command->warn('No delivered sales orders found. Creating sample sales orders for RMA testing...');
            
            // Create sample sales orders if none exist
            foreach ($customers->take(3) as $customer) {
                $salesOrder = SalesOrder::create([
                    'customer_id' => $customer->id,
                    'order_number' => 'SO-' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                    'status' => 'delivered',
                    'total' => rand(500, 5000),
                    'subtotal' => rand(450, 4500),
                    'tax' => rand(50, 500),
                    'shipping_cost' => rand(20, 100),
                    'order_date' => now()->subDays(rand(10, 30))->format('Y-m-d'),
                    'delivered_at' => now()->subDays(rand(1, 9))->format('Y-m-d'),
                ]);

                // Add items to the sales order
                foreach ($products->take(rand(2, 4)) as $product) {
                    SalesOrderItem::create([
                        'sales_order_id' => $salesOrder->id,
                        'product_id' => $product->id,
                        'description' => $product->name_en ?? $product->name,
                        'quantity' => rand(1, 5),
                        'unit_price' => $product->price ?? rand(50, 500),
                        'total' => ($product->price ?? rand(50, 500)) * rand(1, 5),
                    ]);
                }
            }
            
            $salesOrders = SalesOrder::where('status', 'delivered')->get();
        }

        $rmaRequests = [
            [
                'status' => 'pending',
                'type' => 'refund',
                'reason' => 'defective',
                'reason_description' => 'فتحة الاسطح للطوارئ والصيانة arrived with manufacturing defects',
                'days_ago' => 2,
                'customer_id' => 1,
                'product_id' => 1,
            ],
            [
                'status' => 'approved',
                'type' => 'exchange',
                'reason' => 'wrong_item',
                'reason_description' => 'Received wrong size/color for أنظمة حواجز الدرج',
                'days_ago' => 5,
                'customer_id' => 2,
                'product_id' => 2,
            ],
            [
                'status' => 'completed',
                'type' => 'refund',
                'reason' => 'damaged',
                'reason_description' => 'Package arrived damaged during shipping - نظام مرمى النفايات والبياضات',
                'days_ago' => 15,
                'customer_id' => 3,
                'product_id' => 3,
            ],
            [
                'status' => 'rejected',
                'type' => 'refund',
                'reason' => 'changed_mind',
                'reason_description' => 'Customer changed their mind about أرضية مرتفعة للوصول',
                'days_ago' => 20,
                'customer_id' => 4,
                'product_id' => 4,
            ],
            [
                'status' => 'pending',
                'type' => 'exchange',
                'reason' => 'not_as_described',
                'reason_description' => 'Product description did not match actual item - الخزائن',
                'days_ago' => 1,
                'customer_id' => 5,
                'product_id' => 5,
            ],
        ];

        foreach ($rmaRequests as $index => $rmaData) {
            $customer = Customer::find($rmaData['customer_id']);
            $salesOrder = $salesOrders->get($index % $salesOrders->count());
            $user = $users->get($index % $users->count());
            $product = Product::find($rmaData['product_id']);

            // Generate RMA number
            $rmaNumber = 'RMA-' . str_pad($index + 1, 6, '0', STR_PAD_LEFT);

            $rmaRequest = RmaRequest::create([
                'rma_number' => $rmaNumber,
                'customer_id' => $customer->id,
                'sales_order_id' => $salesOrder->id,
                'status' => $rmaData['status'],
                'type' => $rmaData['type'],
                'reason' => $rmaData['reason'],
                'reason_description' => $rmaData['reason_description'],
                'return_address' => [
                    'address_line1' => $customer->address,
                    'city' => $customer->city,
                    'country' => $customer->country,
                    'postal_code' => rand(10000, 99999),
                ],
                'requested_at' => now()->subDays($rmaData['days_ago']),
                'approved_at' => $rmaData['status'] === 'approved' || $rmaData['status'] === 'completed' ? now()->subDays($rmaData['days_ago'] - 1) : null,
                'completed_at' => $rmaData['status'] === 'completed' ? now()->subDays($rmaData['days_ago'] - 3) : null,
                'approved_by' => $rmaData['status'] === 'approved' || $rmaData['status'] === 'completed' ? $user->id : null,
                'completed_by' => $rmaData['status'] === 'completed' ? $user->id : null,
                'refund_amount' => rand(100, 1000),
                'refund_method' => $rmaData['status'] === 'completed' ? 'original' : null,
                'admin_notes' => $rmaData['status'] === 'rejected' ? 'Return rejected per customer request' : null,
                'resolution_type' => $rmaData['type'],
            ]);

            // Add RMA items
            $orderItems = SalesOrderItem::where('sales_order_id', $salesOrder->id)->get();
            
            if ($orderItems->isEmpty()) {
                // Create dummy order items if none exist using the specific product from RMA data
                $orderItem = SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => $product->id,
                    'description' => $product->name_en ?? $product->name,
                    'quantity' => rand(1, 5),
                    'unit_price' => $product->price ?? rand(50, 500),
                    'total' => ($product->price ?? rand(50, 500)) * rand(1, 5),
                ]);
                
                $quantity = min($orderItem->quantity, rand(1, 2));
                
                RmaItem::create([
                    'rma_request_id' => $rmaRequest->id,
                    'sales_order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'quantity_requested' => $quantity,
                    'quantity_received' => $rmaData['status'] === 'completed' ? $quantity : 0,
                    'condition' => ['new', 'used', 'damaged'][rand(0, 2)],
                    'resolution' => $rmaData['type'],
                    'exchange_product_id' => $rmaData['type'] === 'exchange' ? $productIds[array_rand($productIds)] : null,
                    'exchange_variant_id' => null,
                    'refund_amount' => $orderItem->unit_price * $quantity * 0.9, // 90% refund
                    'notes' => 'Item condition as reported by customer',
                ]);
            } else {
                foreach ($orderItems->take(rand(1, 3)) as $orderItem) {
                    $quantity = min($orderItem->quantity, rand(1, 2));
                    
                    RmaItem::create([
                        'rma_request_id' => $rmaRequest->id,
                        'sales_order_item_id' => $orderItem->id,
                        'product_id' => $orderItem->product_id,
                        'product_variant_id' => null,
                        'quantity_requested' => $quantity,
                        'quantity_received' => $rmaData['status'] === 'completed' ? $quantity : 0,
                        'condition' => ['new', 'used', 'damaged'][rand(0, 2)],
                        'resolution' => $rmaData['type'],
                        'exchange_product_id' => $rmaData['type'] === 'exchange' ? $productIds[array_rand($productIds)] : null,
                        'exchange_variant_id' => null,
                        'refund_amount' => $orderItem->unit_price * $quantity * 0.9, // 90% refund
                        'notes' => 'Item condition as reported by customer',
                    ]);
                }
            }
        }

        $this->command->info('Created ' . count($rmaRequests) . ' sample RMA requests with items');
    }
}
