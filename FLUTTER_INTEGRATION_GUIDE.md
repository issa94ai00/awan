# Flutter E-commerce API Integration Guide

## Overview

This guide explains how the Flutter E-commerce API has been integrated into the Promax Laravel project.

## Integration Summary

The Flutter E-commerce API has been successfully integrated into the Promax Laravel project without breaking existing functionality. The integration uses a separate route prefix (`/api/flutter`) to avoid conflicts with existing Promax API routes.

## What Was Added

### New Controllers (8)
- `FlutterCartController` - Cart management for Flutter app
- `FlutterOrderController` - Order management for Flutter app
- `FlutterUserController` - User profile management for Flutter app
- `FlutterPaymentController` - Payment method management for Flutter app
- `FlutterWalletController` - Wallet management for Flutter app
- `FlutterReviewController` - Product reviews for Flutter app
- `FlutterWishlistController` - Wishlist management for Flutter app
- `FlutterNotificationController` - Notification management for Flutter app

### New Models (8)
- `Address` - User shipping addresses
- `PaymentMethod` - User payment methods
- `Order` - Flutter orders
- `OrderItem` - Flutter order items
- `WalletTransaction` - Wallet transactions
- `Review` - Product reviews
- `WishlistItem` - Wishlist items
- `Notification` - User notifications

### New Migrations (9)
- `create_addresses_table`
- `create_payment_methods_table`
- `create_orders_table`
- `create_order_items_table`
- `create_wallet_transactions_table`
- `create_reviews_table`
- `create_wishlist_items_table`
- `create_notifications_table`
- `add_flutter_fields_to_users_table`

### New Factories (4)
- `AddressFactory`
- `PaymentMethodFactory`
- `OrderFactory`
- `ReviewFactory`

### New Seeders (1)
- `FlutterEcommerceSeeder` - Seeds demo data for Flutter features

### Updated Files

#### Models
- `User` - Added relationships for addresses, payment methods, orders, wallet transactions, reviews, wishlist items, notifications, and wallet balance accessor
- `Product` - Added relationships for cart items, order items, reviews, wishlist items, and updateRating method
- `CartItem` - Added total attribute

#### Controllers
- `AuthController` - Added forgotPassword, verifyOtp, resetPassword methods
- `ProductController` - Added flutterSearch, popular, flashSale, bestSellers methods

#### Routes
- `routes/api.php` - Added Flutter-specific routes under `/api/flutter` prefix

#### Config
- `config/cors.php` - Created CORS configuration for API access

## Installation Steps

### 1. Run Migrations

```bash
cd C:\Users\Damatech\Desktop\promax
php artisan migrate
```

This will create the new tables:
- addresses
- payment_methods
- orders
- order_items
- wallet_transactions
- reviews
- wishlist_items
- notifications

And add new fields to the users table:
- avatar
- is_pro
- pro_label
- notifications_enabled

### 2. Seed Demo Data (Optional)

```bash
php artisan db:seed --class=FlutterEcommerceSeeder
```

This will create:
- A demo user (flutter@demo.com / password123!)
- 3 addresses for the demo user
- 2 payment methods for the demo user
- Reviews for 10 products
- 5 wishlist items

### 3. Configure CORS

The `config/cors.php` file has been created with permissive settings for development. For production, update the `allowed_origins` array with your Flutter app's domain.

### 4. Test the API

Start the Laravel development server:

```bash
php artisan serve
```

Test the Flutter API endpoints:

```bash
# Register
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com","password":"password123!","password_confirmation":"password123!"}'

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password123!"}'

# Get popular products
curl http://localhost:8000/api/flutter/products/popular
```

## API Endpoints Structure

### Existing Promax Endpoints (Unchanged)
- `/api/v1/auth/*` - Authentication
- `/api/v1/products` - Products
- `/api/v1/categories` - Categories
- `/api/v1/search` - Search
- `/api/v1/admin/*` - Admin endpoints
- `/api/v1/pos/*` - POS endpoints
- `/api/v1/invoices/*` - Invoices
- etc.

### New Flutter Endpoints
- `/api/flutter/auth/forgot-password` - Forgot password
- `/api/flutter/auth/verify-otp` - Verify OTP
- `/api/flutter/auth/reset-password` - Reset password
- `/api/flutter/products/search` - Advanced search
- `/api/flutter/products/popular` - Popular products
- `/api/flutter/products/flash-sale` - Flash sale products
- `/api/flutter/products/best-sellers` - Best sellers
- `/api/flutter/cart/*` - Cart management
- `/api/flutter/orders/*` - Order management
- `/api/flutter/user/profile` - User profile
- `/api/flutter/user/addresses` - Address management
- `/api/flutter/user/payment-methods` - Payment methods
- `/api/flutter/user/wallet` - Wallet management
- `/api/flutter/products/{id}/reviews` - Product reviews
- `/api/flutter/user/wishlist` - Wishlist
- `/api/flutter/user/notifications` - Notifications

## Database Schema Changes

### New Tables

#### addresses
```sql
- id
- user_id (foreign key to users)
- street
- city
- country
- zip
- phone
- is_default (boolean)
- timestamps
```

#### payment_methods
```sql
- id
- user_id (foreign key to users)
- card_number_last4
- cardholder_name
- expiry_date
- card_type
- is_default (boolean)
- timestamps
```

#### orders
```sql
- id
- user_id (foreign key to users)
- order_number (unique)
- status (enum: pending, processing, shipped, delivered, canceled, returned)
- subtotal (decimal)
- shipping_cost (decimal)
- tax (decimal)
- total (decimal)
- shipping_address_id (foreign key to addresses)
- payment_method_id (foreign key to payment_methods)
- payment_method_type
- shipped_at (timestamp)
- delivered_at (timestamp)
- notes (text)
- timestamps
```

#### order_items
```sql
- id
- order_id (foreign key to orders)
- product_id (foreign key to products)
- product_title
- product_image
- product_brand
- price (decimal)
- price_after_discount (decimal)
- quantity
- size
- color
- timestamps
```

#### wallet_transactions
```sql
- id
- user_id (foreign key to users)
- type (enum: credit, debit)
- amount (decimal)
- description
- products (json)
- timestamps
```

#### reviews
```sql
- id
- user_id (foreign key to users)
- product_id (foreign key to products)
- rating (integer)
- comment (text)
- images (json)
- timestamps
- unique (user_id, product_id)
```

#### wishlist_items
```sql
- id
- user_id (foreign key to users)
- product_id (foreign key to products)
- timestamps
- unique (user_id, product_id)
```

#### notifications
```sql
- id
- user_id (foreign key to users)
- title
- message
- type
- is_read (boolean)
- read_at (timestamp)
- data (json)
- timestamps
```

### Modified Tables

#### users
Added columns:
- avatar (nullable string)
- is_pro (boolean, default false)
- pro_label (nullable string)
- notifications_enabled (boolean, default true)

## Flutter App Integration

### Base URL
```
http://localhost:8000/api/flutter
```

### Authentication Flow

1. **Register/Login**: Use existing Promax endpoints
   - POST `/api/v1/auth/register`
   - POST `/api/v1/auth/login`

2. **Store Token**: Save the returned token for authenticated requests

3. **Use Token**: Include in Authorization header
   ```
   Authorization: Bearer {token}
   ```

### Product Browsing

1. **Get Products**: Use existing Promax endpoint
   - GET `/api/v1/products`

2. **Search Products**: Use Flutter-specific endpoint
   - GET `/api/flutter/products/search?query=shirt&size=M&color=Red`

3. **Get Featured**: Use Flutter-specific endpoints
   - GET `/api/flutter/products/popular`
   - GET `/api/flutter/products/flash-sale`
   - GET `/api/flutter/products/best-sellers`

### Cart Management

All cart endpoints are under `/api/flutter/cart/*` and require authentication.

### Order Management

All order endpoints are under `/api/flutter/orders/*` and require authentication.

### User Profile

User profile endpoints are under `/api/flutter/user/*` and require authentication.

## Troubleshooting

### Migration Errors

If you encounter migration errors:

1. Check if tables already exist
2. Run: `php artisan migrate:fresh` (WARNING: This will delete all data)
3. Or manually drop conflicting tables and re-run migrations

### CORS Errors

If you encounter CORS errors when calling the API from Flutter:

1. Check `config/cors.php`
2. Add your Flutter app's URL to `allowed_origins`
3. Ensure `supports_credentials` is set to `true`

### Authentication Errors

If you encounter authentication errors:

1. Ensure Sanctum is properly configured
2. Check `config/sanctum.php`
3. Verify the token is being sent in the Authorization header
4. Check that the token hasn't expired

### Cart Issues

If cart functionality doesn't work:

1. Ensure the user is authenticated
2. Check that the product exists
3. Verify the CartItem model relationships

## Production Considerations

1. **Security**:
   - Update CORS settings to allow only your Flutter app's domain
   - Implement rate limiting for API endpoints
   - Use HTTPS in production
   - Remove OTP from forgot password response (currently returned for demo)

2. **Performance**:
   - Implement caching for frequently accessed data
   - Use pagination for large datasets
   - Optimize database queries with eager loading

3. **Monitoring**:
   - Implement logging for API requests
   - Monitor API performance
   - Set up error tracking

## Support

For detailed API documentation, see `FLUTTER_API_DOCUMENTATION.md`.

For issues or questions, refer to the Promax project documentation.
