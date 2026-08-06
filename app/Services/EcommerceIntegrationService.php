<?php

namespace App\Services;

use App\Models\OrderChannel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EcommerceIntegrationService
{
    protected OrderChannel $channel;

    public function __construct(OrderChannel $channel)
    {
        $this->channel = $channel;
    }

    /**
     * Sync products from external platform
     */
    public function syncProducts(): array
    {
        match($this->channel->integration_type) {
            OrderChannel::INTEGRATION_SHOPIFY => $this->syncShopifyProducts(),
            OrderChannel::INTEGRATION_BIGCOMMERCE => $this->syncBigCommerceProducts(),
            default => throw new \Exception('Integration type not supported'),
        };
    }

    /**
     * Sync orders from external platform
     */
    public function syncOrders(): array
    {
        match($this->channel->integration_type) {
            OrderChannel::INTEGRATION_SHOPIFY => $this->syncShopifyOrders(),
            OrderChannel::INTEGRATION_BIGCOMMERCE => $this->syncBigCommerceOrders(),
            default => throw new \Exception('Integration type not supported'),
        };
    }

    /**
     * Push order status to external platform
     */
    public function pushOrderStatus(SalesOrder $order): bool
    {
        match($this->channel->integration_type) {
            OrderChannel::INTEGRATION_SHOPIFY => $this->pushShopifyOrderStatus($order),
            OrderChannel::INTEGRATION_BIGCOMMERCE => $this->pushBigCommerceOrderStatus($order),
            default => throw new \Exception('Integration type not supported'),
        };
    }

    /**
     * Sync inventory to external platform
     */
    public function syncInventory(Product $product, int $quantity): bool
    {
        match($this->channel->integration_type) {
            OrderChannel::INTEGRATION_SHOPIFY => $this->syncShopifyInventory($product, $quantity),
            OrderChannel::INTEGRATION_BIGCOMMERCE => $this->syncBigCommerceInventory($product, $quantity),
            default => throw new \Exception('Integration type not supported'),
        };
    }

    /**
     * Shopify Integration Methods
     */
    protected function syncShopifyProducts(): array
    {
        $apiKey = $this->channel->getConfigValue('shopify_api_key');
        $apiSecret = $this->channel->getConfigValue('shopify_api_secret');
        $shopDomain = $this->channel->getConfigValue('shopify_domain');

        if (!$apiKey || !$apiSecret || !$shopDomain) {
            throw new \Exception('Shopify credentials not configured');
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $apiSecret,
        ])->get("https://{$shopDomain}/admin/api/2024-01/products.json");

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch Shopify products');
        }

        $products = $response->json('products', []);
        $synced = 0;

        foreach ($products as $shopifyProduct) {
            $this->createOrUpdateShopifyProduct($shopifyProduct);
            $synced++;
        }

        $this->channel->markAsSynced();

        return [
            'synced' => $synced,
            'total' => count($products),
        ];
    }

    protected function syncShopifyOrders(): array
    {
        $apiKey = $this->channel->getConfigValue('shopify_api_key');
        $apiSecret = $this->channel->getConfigValue('shopify_api_secret');
        $shopDomain = $this->channel->getConfigValue('shopify_domain');
        $lastSync = $this->channel->last_synced_at?->format('Y-m-d\TH:i:s');

        if (!$apiKey || !$apiSecret || !$shopDomain) {
            throw new \Exception('Shopify credentials not configured');
        }

        $url = "https://{$shopDomain}/admin/api/2024-01/orders.json";
        if ($lastSync) {
            $url .= "?created_at_min={$lastSync}";
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $apiSecret,
        ])->get($url);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch Shopify orders');
        }

        $orders = $response->json('orders', []);
        $synced = 0;

        foreach ($orders as $shopifyOrder) {
            $this->createShopifyOrder($shopifyOrder);
            $synced++;
        }

        $this->channel->markAsSynced();

        return [
            'synced' => $synced,
            'total' => count($orders),
        ];
    }

    protected function pushShopifyOrderStatus(SalesOrder $order): bool
    {
        $apiKey = $this->channel->getConfigValue('shopify_api_key');
        $apiSecret = $this->channel->getConfigValue('shopify_api_secret');
        $shopDomain = $this->channel->getConfigValue('shopify_domain');

        if (!$order->external_order_id) {
            return false;
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $apiSecret,
        ])->put("https://{$shopDomain}/admin/api/2024-01/orders/{$order->external_order_id}.json", [
            'order' => [
                'id' => $order->external_order_id,
                'fulfillment_status' => match($order->status) {
                    SalesOrder::STATUS_SHIPPED => 'fulfilled',
                    SalesOrder::STATUS_DELIVERED => 'fulfilled',
                    SalesOrder::STATUS_CANCELLED => 'restocked',
                    default => null,
                },
                'tracking_numbers' => $order->tracking_number ? [$order->tracking_number] : [],
                'tracking_company' => $order->carrier,
            ],
        ]);

        return $response->successful();
    }

    protected function syncShopifyInventory(Product $product, int $quantity): bool
    {
        $apiKey = $this->channel->getConfigValue('shopify_api_key');
        $apiSecret = $this->channel->getConfigValue('shopify_api_secret');
        $shopDomain = $this->channel->getConfigValue('shopify_domain');
        $shopifyProductId = $product->metadata['shopify_product_id'] ?? null;

        if (!$shopifyProductId) {
            return false;
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $apiSecret,
        ])->put("https://{$shopDomain}/admin/api/2024-01/variants/{$shopifyProductId}.json", [
            'variant' => [
                'id' => $shopifyProductId,
                'inventory_quantity' => $quantity,
            ],
        ]);

        return $response->successful();
    }

    protected function createOrUpdateShopifyProduct(array $shopifyProduct): void
    {
        $existingProduct = Product::where('metadata->shopify_product_id', $shopifyProduct['id'])->first();

        $productData = [
            'name' => $shopifyProduct['title'],
            'name_en' => $shopifyProduct['title'],
            'description' => $shopifyProduct['body_html'] ?? '',
            'description_en' => $shopifyProduct['body_html'] ?? '',
            'sku' => $shopifyProduct['variants'][0]['sku'] ?? null,
            'price' => (float) ($shopifyProduct['variants'][0]['price'] ?? 0),
            'image_main' => $shopifyProduct['image']['src'] ?? null,
            'is_active' => $shopifyProduct['status'] === 'active',
            'metadata' => array_merge($existingProduct?->metadata ?? [], [
                'shopify_product_id' => $shopifyProduct['id'],
                'shopify_handle' => $shopifyProduct['handle'],
            ]),
        ];

        if ($existingProduct) {
            $existingProduct->update($productData);
        } else {
            Product::create($productData);
        }

        // Sync variants
        foreach ($shopifyProduct['variants'] as $variant) {
            $this->createOrUpdateShopifyVariant($variant, $existingProduct ?? Product::where('metadata->shopify_product_id', $shopifyProduct['id'])->first());
        }
    }

    protected function createOrUpdateShopifyVariant(array $variant, Product $product): void
    {
        $existingVariant = ProductVariant::where('metadata->shopify_variant_id', $variant['id'])->first();

        $variantData = [
            'product_id' => $product->id,
            'sku' => $variant['sku'],
            'barcode' => $variant['barcode'],
            'price' => (float) $variant['price'],
            'cost_price' => (float) ($variant['compare_at_price'] ?? 0),
            'stock_quantity' => $variant['inventory_quantity'] ?? 0,
            'metadata' => array_merge($existingVariant?->metadata ?? [], [
                'shopify_variant_id' => $variant['id'],
            ]),
        ];

        if ($existingVariant) {
            $existingVariant->update($variantData);
        } else {
            ProductVariant::create($variantData);
        }
    }

    protected function createShopifyOrder(array $shopifyOrder): void
    {
        $existingOrder = SalesOrder::where('external_order_id', $shopifyOrder['id'])
            ->where('channel_id', $this->channel->id)
            ->first();

        if ($existingOrder) {
            return;
        }

        $customer = $this->findOrCreateShopifyCustomer($shopifyOrder['customer']);

        $order = SalesOrder::create([
            'order_number' => $shopifyOrder['order_number'],
            'external_order_id' => $shopifyOrder['id'],
            'customer_id' => $customer->id,
            'channel_id' => $this->channel->id,
            'status' => match($shopifyOrder['fulfillment_status']) {
                'fulfilled' => SalesOrder::STATUS_DELIVERED,
                'partial' => SalesOrder::STATUS_SHIPPED,
                'restocked' => SalesOrder::STATUS_CANCELLED,
                default => SalesOrder::STATUS_CONFIRMED,
            },
            'order_date' => $shopifyOrder['created_at'],
            'subtotal' => (float) $shopifyOrder['subtotal_price'],
            'tax' => (float) $shopifyOrder['total_tax'],
            'total' => (float) $shopifyOrder['total_price'],
            'shipping_address' => $shopifyOrder['shipping_address'],
            'billing_address' => $shopifyOrder['billing_address'],
            'currency' => $shopifyOrder['currency'],
            'paid_amount' => (float) $shopifyOrder['total_price'],
            'due_amount' => 0,
        ]);

        foreach ($shopifyOrder['line_items'] as $lineItem) {
            $product = Product::where('metadata->shopify_product_id', $lineItem['product_id'])->first();
            $variant = ProductVariant::where('metadata->shopify_variant_id', $lineItem['variant_id'])->first();

            $order->items()->create([
                'product_id' => $product?->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $lineItem['quantity'],
                'unit_price' => (float) $lineItem['price'],
                'total' => (float) $lineItem['price'] * $lineItem['quantity'],
            ]);
        }
    }

    protected function findOrCreateShopifyCustomer(array $shopifyCustomer): \App\Models\Customer
    {
        $customer = \App\Models\Customer::where('metadata->shopify_customer_id', $shopifyCustomer['id'])->first();

        if (!$customer) {
            $customer = \App\Models\Customer::create([
                'name' => $shopifyCustomer['first_name'] . ' ' . $shopifyCustomer['last_name'],
                'email' => $shopifyCustomer['email'],
                'phone' => $shopifyCustomer['phone'],
                'metadata' => [
                    'shopify_customer_id' => $shopifyCustomer['id'],
                ],
            ]);
        }

        return $customer;
    }

    /**
     * BigCommerce Integration Methods
     */
    protected function syncBigCommerceProducts(): array
    {
        $apiKey = $this->channel->getConfigValue('bigcommerce_api_key');
        $apiSecret = $this->channel->getConfigValue('bigcommerce_api_secret');
        $storeId = $this->channel->getConfigValue('bigcommerce_store_id');

        if (!$apiKey || !$apiSecret || !$storeId) {
            throw new \Exception('BigCommerce credentials not configured');
        }

        $response = Http::withBasicAuth($apiKey, $apiSecret)
            ->get("https://api.bigcommerce.com/stores/{$storeId}/v3/catalog/products");

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch BigCommerce products');
        }

        $products = $response->json('data', []);
        $synced = 0;

        foreach ($products as $bcProduct) {
            $this->createOrUpdateBigCommerceProduct($bcProduct);
            $synced++;
        }

        $this->channel->markAsSynced();

        return [
            'synced' => $synced,
            'total' => count($products),
        ];
    }

    protected function syncBigCommerceOrders(): array
    {
        $apiKey = $this->channel->getConfigValue('bigcommerce_api_key');
        $apiSecret = $this->channel->getConfigValue('bigcommerce_api_secret');
        $storeId = $this->channel->getConfigValue('bigcommerce_store_id');

        if (!$apiKey || !$apiSecret || !$storeId) {
            throw new \Exception('BigCommerce credentials not configured');
        }

        $response = Http::withBasicAuth($apiKey, $apiSecret)
            ->get("https://api.bigcommerce.com/stores/{$storeId}/v2/orders");

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch BigCommerce orders');
        }

        $orders = $response->json();
        $synced = 0;

        foreach ($orders as $bcOrder) {
            $this->createBigCommerceOrder($bcOrder);
            $synced++;
        }

        $this->channel->markAsSynced();

        return [
            'synced' => $synced,
            'total' => count($orders),
        ];
    }

    protected function pushBigCommerceOrderStatus(SalesOrder $order): bool
    {
        $apiKey = $this->channel->getConfigValue('bigcommerce_api_key');
        $apiSecret = $this->channel->getConfigValue('bigcommerce_api_secret');
        $storeId = $this->channel->getConfigValue('bigcommerce_store_id');

        if (!$order->external_order_id) {
            return false;
        }

        $response = Http::withBasicAuth($apiKey, $apiSecret)
            ->put("https://api.bigcommerce.com/stores/{$storeId}/v2/orders/{$order->external_order_id}", [
                'status_id' => match($order->status) {
                    SalesOrder::STATUS_SHIPPED => 10,
                    SalesOrder::STATUS_DELIVERED => 11,
                    SalesOrder::STATUS_CANCELLED => 5,
                    default => 9,
                },
            ]);

        return $response->successful();
    }

    protected function syncBigCommerceInventory(Product $product, int $quantity): bool
    {
        $apiKey = $this->channel->getConfigValue('bigcommerce_api_key');
        $apiSecret = $this->channel->getConfigValue('bigcommerce_api_secret');
        $storeId = $this->channel->getConfigValue('bigcommerce_store_id');
        $bcProductId = $product->metadata['bigcommerce_product_id'] ?? null;

        if (!$bcProductId) {
            return false;
        }

        $response = Http::withBasicAuth($apiKey, $apiSecret)
            ->put("https://api.bigcommerce.com/stores/{$storeId}/v3/catalog/products/{$bcProductId}", [
                'inventory_level' => $quantity,
            ]);

        return $response->successful();
    }

    protected function createOrUpdateBigCommerceProduct(array $bcProduct): void
    {
        $existingProduct = Product::where('metadata->bigcommerce_product_id', $bcProduct['id'])->first();

        $productData = [
            'name' => $bcProduct['name'],
            'name_en' => $bcProduct['name'],
            'description' => $bcProduct['description'] ?? '',
            'description_en' => $bcProduct['description'] ?? '',
            'sku' => $bcProduct['sku'],
            'price' => (float) $bcProduct['price'],
            'is_active' => $bcProduct['is_visible'],
            'metadata' => array_merge($existingProduct?->metadata ?? [], [
                'bigcommerce_product_id' => $bcProduct['id'],
            ]),
        ];

        if ($existingProduct) {
            $existingProduct->update($productData);
        } else {
            Product::create($productData);
        }
    }

    protected function createBigCommerceOrder(array $bcOrder): void
    {
        $existingOrder = SalesOrder::where('external_order_id', $bcOrder['id'])
            ->where('channel_id', $this->channel->id)
            ->first();

        if ($existingOrder) {
            return;
        }

        $customer = $this->findOrCreateBigCommerceCustomer($bcOrder);

        $order = SalesOrder::create([
            'order_number' => $bcOrder['id'],
            'external_order_id' => $bcOrder['id'],
            'customer_id' => $customer->id,
            'channel_id' => $this->channel->id,
            'status' => match($bcOrder['status_id']) {
                10 => SalesOrder::STATUS_SHIPPED,
                11 => SalesOrder::STATUS_DELIVERED,
                5 => SalesOrder::STATUS_CANCELLED,
                default => SalesOrder::STATUS_CONFIRMED,
            },
            'order_date' => $bcOrder['date_created'],
            'subtotal' => (float) $bcOrder['subtotal_ex_tax'],
            'tax' => (float) $bcOrder['total_tax'],
            'total' => (float) $bcOrder['total_inc_tax'],
            'shipping_address' => [
                'first_name' => $bcOrder['shipping_address']['first_name'],
                'last_name' => $bcOrder['shipping_address']['last_name'],
                'street_1' => $bcOrder['shipping_address']['street_1'],
                'city' => $bcOrder['shipping_address']['city'],
                'state' => $bcOrder['shipping_address']['state'],
                'zip' => $bcOrder['shipping_address']['zip'],
                'country' => $bcOrder['shipping_address']['country'],
            ],
            'currency' => $bcOrder['currency_code'],
            'paid_amount' => (float) $bcOrder['total_inc_tax'],
            'due_amount' => 0,
        ]);

        foreach ($bcOrder['products'] as $product) {
            $productModel = Product::where('metadata->bigcommerce_product_id', $product['product_id'])->first();

            $order->items()->create([
                'product_id' => $productModel?->id,
                'quantity' => $product['quantity'],
                'unit_price' => (float) $product['price_ex_tax'],
                'total' => (float) $product['total_ex_tax'],
            ]);
        }
    }

    protected function findOrCreateBigCommerceCustomer(array $bcCustomer): \App\Models\Customer
    {
        $customer = \App\Models\Customer::where('metadata->bigcommerce_customer_id', $bcCustomer['id'])->first();

        if (!$customer) {
            $customer = \App\Models\Customer::create([
                'name' => $bcCustomer['first_name'] . ' ' . $bcCustomer['last_name'],
                'email' => $bcCustomer['email'],
                'phone' => $bcCustomer['phone'],
                'metadata' => [
                    'bigcommerce_customer_id' => $bcCustomer['id'],
                ],
            ]);
        }

        return $customer;
    }

    /**
     * Test connection to external platform
     */
    public function testConnection(): bool
    {
        try {
            match($this->channel->integration_type) {
                OrderChannel::INTEGRATION_SHOPIFY => $this->testShopifyConnection(),
                OrderChannel::INTEGRATION_BIGCOMMERCE => $this->testBigCommerceConnection(),
                default => throw new \Exception('Integration type not supported'),
            };
            return true;
        } catch (\Exception $e) {
            Log::error('Ecommerce connection test failed', [
                'channel_id' => $this->channel->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function testShopifyConnection(): bool
    {
        $apiKey = $this->channel->getConfigValue('shopify_api_key');
        $apiSecret = $this->channel->getConfigValue('shopify_api_secret');
        $shopDomain = $this->channel->getConfigValue('shopify_domain');

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $apiSecret,
        ])->get("https://{$shopDomain}/admin/api/2024-01/shop.json");

        return $response->successful();
    }

    protected function testBigCommerceConnection(): bool
    {
        $apiKey = $this->channel->getConfigValue('bigcommerce_api_key');
        $apiSecret = $this->channel->getConfigValue('bigcommerce_api_secret');
        $storeId = $this->channel->getConfigValue('bigcommerce_store_id');

        $response = Http::withBasicAuth($apiKey, $apiSecret)
            ->get("https://api.bigcommerce.com/stores/{$storeId}/v2/store");

        return $response->successful();
    }
}
