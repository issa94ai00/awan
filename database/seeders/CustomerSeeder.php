<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'id' => 1,
                'name' => 'Ahmed Al-Rashid',
                'email' => 'ahmed@example.com',
                'tax_number' => null,
                'phone' => '+966501234567',
                'company' => null,
                'address' => 'Riyadh, Saudi Arabia',
                'city' => 'Riyadh',
                'state' => null,
                'country' => 'Saudi Arabia',
                'postal_code' => null,
                'source' => null,
                'status' => 'active',
                'notes' => null,
                'balance' => 0.00,
                'credit_limit' => 0.00,
                'currency' => 'SAR',
                'total_purchases' => 0.00,
                'last_purchase_at' => null,
                'password' => null,
                'auth_token' => null,
            ],
            [
                'id' => 2,
                'name' => 'Sarah Mohammed',
                'email' => 'sarah@example.com',
                'tax_number' => null,
                'phone' => '+966502345678',
                'company' => null,
                'address' => 'Jeddah, Saudi Arabia',
                'city' => 'Jeddah',
                'state' => null,
                'country' => 'Saudi Arabia',
                'postal_code' => null,
                'source' => null,
                'status' => 'active',
                'notes' => null,
                'balance' => 0.00,
                'credit_limit' => 0.00,
                'currency' => 'SAR',
                'total_purchases' => 0.00,
                'last_purchase_at' => null,
                'password' => null,
                'auth_token' => null,
            ],
            [
                'id' => 3,
                'name' => 'Khalid Al-Otaibi',
                'email' => 'khalid@example.com',
                'tax_number' => null,
                'phone' => '+966503456789',
                'company' => null,
                'address' => 'Dammam, Saudi Arabia',
                'city' => 'Dammam',
                'state' => null,
                'country' => 'Saudi Arabia',
                'postal_code' => null,
                'source' => null,
                'status' => 'active',
                'notes' => null,
                'balance' => 0.00,
                'credit_limit' => 0.00,
                'currency' => 'SAR',
                'total_purchases' => 0.00,
                'last_purchase_at' => null,
                'password' => null,
                'auth_token' => null,
            ],
            [
                'id' => 4,
                'name' => 'Fatima Al-Qahtani',
                'email' => 'fatima@example.com',
                'tax_number' => null,
                'phone' => '+966504567890',
                'company' => null,
                'address' => 'Mecca, Saudi Arabia',
                'city' => 'Mecca',
                'state' => null,
                'country' => 'Saudi Arabia',
                'postal_code' => null,
                'source' => null,
                'status' => 'active',
                'notes' => null,
                'balance' => 0.00,
                'credit_limit' => 0.00,
                'currency' => 'SAR',
                'total_purchases' => 0.00,
                'last_purchase_at' => null,
                'password' => null,
                'auth_token' => null,
            ],
            [
                'id' => 5,
                'name' => 'Omar Al-Harbi',
                'email' => 'omar@example.com',
                'tax_number' => null,
                'phone' => '+966505678901',
                'company' => null,
                'address' => 'Medina, Saudi Arabia',
                'city' => 'Medina',
                'state' => null,
                'country' => 'Saudi Arabia',
                'postal_code' => null,
                'source' => null,
                'status' => 'active',
                'notes' => null,
                'balance' => 0.00,
                'credit_limit' => 0.00,
                'currency' => 'SAR',
                'total_purchases' => 0.00,
                'last_purchase_at' => null,
                'password' => null,
                'auth_token' => null,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(
                ['id' => $customer['id']],
                $customer
            );
        }

        $this->command->info('Created sample customers from database.sql');
    }
}
