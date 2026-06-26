# Flutter E-commerce API Documentation (Integrated with Promax)

## Base URL
```
http://localhost:8000/api/flutter
```

## Authentication

Most Flutter endpoints require authentication using Laravel Sanctum. Include the token in the Authorization header:

```
Authorization: Bearer {token}
```

## Response Format

All responses are in JSON format:

```json
{
  "success": true,
  "message": "Success message",
  "data": { ... }
}
```

Error responses:

```json
{
  "success": false,
  "message": "Error message",
  "data": null,
  "errors": { ... }
}
```

---

## Authentication Endpoints

### Register
**POST** `/api/v1/auth/register` (Use existing promax endpoint)

Register a new user account.

### Login
**POST** `/api/v1/auth/login` (Use existing promax endpoint)

Authenticate a user and return a token.

### Logout
**POST** `/api/v1/auth/logout` (Use existing promax endpoint) 🔒

Logout the authenticated user.

### Get Current User
**GET** `/api/v1/auth/user` (Use existing promax endpoint) 🔒

Get the currently authenticated user.

### Forgot Password
**POST** `/api/flutter/auth/forgot-password`

Request a password reset OTP.

**Request Body:**
```json
{
  "email": "john@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم إرسال رمز التحقق بنجاح",
  "data": {
    "otp": "123456"
  }
}
```

### Verify OTP
**POST** `/api/flutter/auth/verify-otp`

Verify the OTP for password reset.

**Request Body:**
```json
{
  "email": "john@example.com",
  "otp": "123456"
}
```

### Reset Password
**POST** `/api/flutter/auth/reset-password`

Reset the password with OTP.

**Request Body:**
```json
{
  "email": "john@example.com",
  "otp": "123456",
  "password": "newpassword123!"
}
```

---

## Product Endpoints

### Get All Products
**GET** `/api/v1/products` (Use existing promax endpoint)

Get a paginated list of products with optional filters.

### Get Product Details
**GET** `/api/v1/products/{product}` (Use existing promax endpoint)

Get detailed information about a specific product.

### Search Products (Flutter)
**GET** `/api/flutter/products/search`

Search products with advanced filters.

**Query Parameters:**
- `query` (string) - Search term
- `size` (string) - Filter by size
- `color` (string) - Filter by color
- `brand` (string) - Filter by brand
- `price_min` (decimal) - Minimum price
- `price_max` (decimal) - Maximum price
- `per_page` (integer) - Items per page

**Response:**
```json
{
  "success": true,
  "data": [ ... ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 12,
  "total": 50,
  "has_more_pages": true
}
```

### Get Popular Products
**GET** `/api/flutter/products/popular`

Get popular products.

**Response:**
```json
{
  "success": true,
  "data": [ ... ]
}
```

### Get Flash Sale Products
**GET** `/api/flutter/products/flash-sale`

Get products on flash sale.

**Response:**
```json
{
  "success": true,
  "data": [ ... ]
}
```

### Get Best Sellers
**GET** `/api/flutter/products/best-sellers`

Get best-selling products.

**Response:**
```json
{
  "success": true,
  "data": [ ... ]
}
```

---

## Category Endpoints

### Get All Categories
**GET** `/api/v1/categories` (Use existing promax endpoint)

Get all categories with subcategories.

### Get Category Details
**GET** `/api/v1/categories/{category}` (Use existing promax endpoint)

Get category details with products.

### Get Subcategories
**GET** `/api/v1/categories/{category}/products` (Use existing promax endpoint)

Get products for a specific category.

---

## Cart Endpoints 🔒

### Get Cart
**GET** `/api/flutter/cart`

Get the authenticated user's cart.

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [ ... ],
    "total": 159.98,
    "count": 1
  }
}
```

### Add to Cart
**POST** `/api/flutter/cart/add`

Add an item to the cart.

**Request Body:**
```json
{
  "product_id": 1,
  "quantity": 2,
  "size": "M",
  "color": "Red"
}
```

### Update Cart Item
**PUT** `/api/flutter/cart/update`

Update cart item quantity.

**Request Body:**
```json
{
  "cart_item_id": 1,
  "quantity": 3
}
```

### Remove from Cart
**DELETE** `/api/flutter/cart/remove`

Remove an item from the cart.

**Request Body:**
```json
{
  "cart_item_id": 1
}
```

### Clear Cart
**DELETE** `/api/flutter/cart/clear`

Clear all items from the cart.

---

## Order Endpoints 🔒

### Get Orders
**GET** `/api/flutter/orders`

Get the authenticated user's orders.

**Query Parameters:**
- `status` (string) - Filter by status: `pending`, `processing`, `shipped`, `delivered`, `canceled`, `returned`

**Response:**
```json
{
  "success": true,
  "data": [ ... ]
}
```

### Get Order Details
**GET** `/api/flutter/orders/{id}`

Get details of a specific order.

### Create Order
**POST** `/api/flutter/orders`

Create a new order from cart items.

**Request Body:**
```json
{
  "shipping_address_id": 1,
  "payment_method_type": "card",
  "payment_method_id": 1,
  "notes": "Please deliver in the morning"
}
```

### Cancel Order
**PUT** `/api/flutter/orders/{id}/cancel`

Cancel an order.

### Return Order
**POST** `/api/flutter/orders/{id}/return`

Request a return for a delivered order.

**Request Body:**
```json
{
  "reason": "Product doesn't fit"
}
```

---

## User Profile Endpoints 🔒

### Get Profile
**GET** `/api/flutter/user/profile`

Get the authenticated user's profile.

**Response:**
```json
{
  "success": true,
  "data": {
    "user": { ... }
  }
}
```

### Update Profile
**PUT** `/api/flutter/user/profile`

Update the authenticated user's profile.

**Request Body:**
```json
{
  "name": "John Smith",
  "email": "johnsmith@example.com",
  "phone": "+9876543210",
  "avatar": "https://..."
}
```

### Get Addresses
**GET** `/api/flutter/user/addresses`

Get the authenticated user's addresses.

**Response:**
```json
{
  "success": true,
  "data": {
    "addresses": [ ... ]
  }
}
```

### Add Address
**POST** `/api/flutter/user/addresses`

Add a new address.

**Request Body:**
```json
{
  "street": "456 Oak Ave",
  "city": "Los Angeles",
  "country": "USA",
  "zip": "90001",
  "phone": "+9876543210",
  "is_default": false
}
```

### Update Address
**PUT** `/api/flutter/user/addresses/{id}`

Update an existing address.

### Delete Address
**DELETE** `/api/flutter/user/addresses/{id}`

Delete an address.

---

## Payment Endpoints 🔒

### Get Payment Methods
**GET** `/api/flutter/user/payment-methods`

Get the authenticated user's payment methods.

**Response:**
```json
{
  "success": true,
  "data": {
    "payment_methods": [ ... ]
  }
}
```

### Add Payment Method
**POST** `/api/flutter/user/payment-methods`

Add a new payment method.

**Request Body:**
```json
{
  "card_number": "4111111111111111",
  "cardholder_name": "John Doe",
  "expiry_date": "12/25",
  "card_type": "visa",
  "is_default": false
}
```

### Delete Payment Method
**DELETE** `/api/flutter/user/payment-methods/{id}`

Delete a payment method.

---

## Wallet Endpoints 🔒

### Get Wallet Balance
**GET** `/api/flutter/user/wallet`

Get the authenticated user's wallet balance.

**Response:**
```json
{
  "success": true,
  "data": {
    "balance": 384.90
  }
}
```

### Get Wallet History
**GET** `/api/flutter/user/wallet/history`

Get the authenticated user's wallet transaction history.

**Response:**
```json
{
  "success": true,
  "data": [ ... ]
}
```

### Charge Wallet
**POST** `/api/flutter/user/wallet/charge`

Charge the wallet with funds.

**Request Body:**
```json
{
  "amount": 100.00,
  "payment_method": "card"
}
```

---

## Review Endpoints 🔒

### Get Product Reviews
**GET** `/api/flutter/products/{id}/reviews`

Get reviews for a specific product.

**Response:**
```json
{
  "success": true,
  "data": [ ... ]
}
```

### Add Review
**POST** `/api/flutter/products/{id}/reviews`

Add a review for a product.

**Request Body:**
```json
{
  "rating": 5,
  "comment": "Great product!",
  "images": ["https://example.com/image.jpg"]
}
```

### Update Review
**PUT** `/api/flutter/reviews/{id}`

Update an existing review.

### Delete Review
**DELETE** `/api/flutter/reviews/{id}`

Delete a review.

---

## Wishlist Endpoints 🔒

### Get Wishlist
**GET** `/api/flutter/user/wishlist`

Get the authenticated user's wishlist.

**Response:**
```json
{
  "success": true,
  "data": {
    "wishlist_items": [ ... ]
  }
}
```

### Add to Wishlist
**POST** `/api/flutter/user/wishlist`

Add a product to the wishlist.

**Request Body:**
```json
{
  "product_id": 1
}
```

### Remove from Wishlist
**DELETE** `/api/flutter/user/wishlist/{id}`

Remove an item from the wishlist.

---

## Notification Endpoints 🔒

### Get Notifications
**GET** `/api/flutter/user/notifications`

Get the authenticated user's notifications.

**Response:**
```json
{
  "success": true,
  "data": [ ... ]
}
```

### Mark as Read
**PUT** `/api/flutter/user/notifications/{id}/read`

Mark a notification as read.

### Update Settings
**POST** `/api/flutter/user/notifications/settings`

Update notification preferences.

**Request Body:**
```json
{
  "push_enabled": true,
  "email_enabled": true
}
```

---

## Error Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

---

## Notes

🔒 = Requires authentication

All dates are in ISO 8601 format.

All monetary values are in decimal format with 2 decimal places.

## Integration with Promax

This Flutter API is integrated with the existing Promax Laravel project. The Flutter-specific endpoints are prefixed with `/api/flutter` to avoid conflicts with existing Promax API routes.

### Existing Promax Endpoints Used

- Authentication: `/api/v1/auth/*`
- Products: `/api/v1/products`
- Categories: `/api/v1/categories`

### New Flutter-Specific Endpoints

- Cart: `/api/flutter/cart/*`
- Orders: `/api/flutter/orders/*`
- User Profile: `/api/flutter/user/*`
- Payment Methods: `/api/flutter/user/payment-methods/*`
- Wallet: `/api/flutter/user/wallet/*`
- Reviews: `/api/flutter/products/{id}/reviews`
- Wishlist: `/api/flutter/user/wishlist/*`
- Notifications: `/api/flutter/user/notifications/*`

### New Tables Added

- `addresses` - User shipping addresses
- `payment_methods` - User payment methods
- `orders` - Flutter orders
- `order_items` - Flutter order items
- `wallet_transactions` - Wallet transactions
- `reviews` - Product reviews
- `wishlist_items` - Wishlist items
- `notifications` - User notifications

### New Models Added

- `Address`
- `PaymentMethod`
- `Order`
- `OrderItem`
- `WalletTransaction`
- `Review`
- `WishlistItem`
- `Notification`

### New Controllers Added

- `FlutterCartController`
- `FlutterOrderController`
- `FlutterUserController`
- `FlutterPaymentController`
- `FlutterWalletController`
- `FlutterReviewController`
- `FlutterWishlistController`
- `FlutterNotificationController`

### Updated Models

- `User` - Added relationships for Flutter features
- `Product` - Added relationships for reviews, wishlist, etc.
- `CartItem` - Added total attribute

### Updated Controllers

- `AuthController` - Added forgotPassword, verifyOtp, resetPassword methods
- `ProductController` - Added flutterSearch, popular, flashSale, bestSellers methods
