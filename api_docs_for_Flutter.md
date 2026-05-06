# API Documentation for Flutter Integration

## Overview

This document provides comprehensive API documentation for the Awan e-commerce application, specifically designed for Flutter mobile app integration. The API follows RESTful conventions and returns JSON responses with consistent formatting.

## Base URL

```
https://your-domain.com/api/v1
```

## Authentication

The API uses Laravel Sanctum for authentication. Include the token in the Authorization header:

```
Authorization: Bearer {token}
```

## Response Format

All API responses follow this consistent format:

```json
{
  "success": true,
  "message": "Success message",
  "data": {
    // Response data here
  }
}
```

Error responses:

```json
{
  "success": false,
  "message": "Error message",
  "data": null,
  "errors": {
    // Validation errors (if applicable)
  }
}
```

## API Endpoints

### 1. Authentication

#### 1.1 Register User
**POST** `/auth/register`

Register a new user account.

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+1234567890"
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم تسجيل الحساب بنجاح",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "is_email_verified": false,
      "created_at": "2024-01-01T10:00:00.000000Z"
    },
    "token": "1|abcdef123456...",
    "token_type": "Bearer"
  }
}
```

#### 1.2 Login
**POST** `/auth/login`

Authenticate user and return access token.

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "is_email_verified": false
    },
    "token": "1|abcdef123456...",
    "token_type": "Bearer"
  }
}
```

#### 1.3 Logout
**POST** `/auth/logout` *(Requires Authentication)*

Revoke the current access token.

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "تم تسجيل الخروج بنجاح",
  "data": null
}
```

#### 1.4 Get Current User
**GET** `/auth/user` *(Requires Authentication)*

Get the currently authenticated user's details.

**Response:**
```json
{
  "success": true,
  "message": "User data retrieved successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "is_email_verified": true,
      "created_at": "2024-01-01T10:00:00.000000Z",
      "last_login": "2024-01-01T15:30:00.000000Z"
    }
  }
}
```

#### 1.5 Update Profile
**PUT** `/auth/profile` *(Requires Authentication)*

Update the authenticated user's profile.

**Request Body:**
```json
{
  "name": "John Smith",
  "email": "johnsmith@example.com",
  "phone": "+1234567890"
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم تحديث الملف الشخصي بنجاح",
  "data": {
    "user": {
      "id": 1,
      "name": "John Smith",
      "email": "johnsmith@example.com",
      "phone": "+1234567890",
      "is_email_verified": true
    }
  }
}
```

#### 1.6 Change Password
**POST** `/auth/change-password` *(Requires Authentication)*

Change the user's password.

**Request Body:**
```json
{
  "current_password": "oldpassword",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم تغيير كلمة المرور بنجاح، يرجى تسجيل الدخول مرة أخرى",
  "data": null
}
```

### 2. Home & Featured Content

#### 2.1 Home Data
**GET** `/home`

Get home page data including categories and featured products.

**Response:**
```json
{
  "success": true,
  "message": "Home data retrieved successfully",
  "data": {
    "categories": [
      {
        "id": 1,
        "name_ar": "إلكترونيات",
        "name_en": "Electronics",
        "slug": "electronics",
        "icon": "electronics-icon.svg",
        "image": "https://your-domain.com/storage/categories/electronics.jpg",
        "product_count": 25
      }
    ],
    "featured_products": [
      {
        "id": 1,
        "name_ar": "لابتوب ديل",
        "name_en": "Dell Laptop",
        "slug": "dell-laptop",
        "price": 2500.00,
        "sale_price": 2200.00,
        "image_main": "https://your-domain.com/storage/products/dell-laptop.jpg",
        "has_sale": true,
        "discount_percentage": 12.0,
        "category": {
          "id": 1,
          "name_ar": "إلكترونيات",
          "slug": "electronics"
        }
      }
    ]
  }
}
```

#### 2.2 Featured Products
**GET** `/featured-products`

Get paginated list of featured products.

**Query Parameters:**
- `page` (optional): Page number (default: 1)

**Response:**
```json
{
  "success": true,
  "message": "Featured products retrieved successfully",
  "data": {
    "products": [...],
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 12,
      "total": 35,
      "has_more_pages": true
    }
  }
}
```

### 3. Categories

#### 3.1 List Categories
**GET** `/categories`

Get all active categories with product counts.

**Response:**
```json
{
  "success": true,
  "message": "Categories retrieved successfully",
  "data": [
    {
      "id": 1,
      "name_ar": "إلكترونيات",
      "name_en": "Electronics",
      "slug": "electronics",
      "description": "الأجهزة الإلكترونية والكمبيوترات",
      "icon": "electronics-icon.svg",
      "image": "https://your-domain.com/storage/categories/electronics.jpg",
      "is_active": true,
      "sort_order": 1,
      "product_count": 25
    }
  ]
}
```

#### 3.2 Get Category Details
**GET** `/categories/{category}`

Get category details by slug.

**Response:**
```json
{
  "success": true,
  "message": "Category retrieved successfully",
  "data": {
    "id": 1,
    "name_ar": "إلكترونيات",
    "name_en": "Electronics",
    "slug": "electronics",
    "description": "الأجهزة الإلكترونية والكمبيوترات",
    "icon": "electronics-icon.svg",
    "image": "https://your-domain.com/storage/categories/electronics.jpg",
    "is_active": true,
    "sort_order": 1,
    "product_count": 25
  }
}
```

#### 3.3 Category Products
**GET** `/categories/{category}/products`

Get products for a specific category.

**Query Parameters:**
- `page` (optional): Page number (default: 1)

**Response:**
```json
{
  "success": true,
  "message": "Category products retrieved successfully",
  "data": {
    "category": {
      "id": 1,
      "name_ar": "إلكترونيات",
      "slug": "electronics"
    },
    "products": [...],
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 12,
      "total": 25,
      "has_more_pages": true
    }
  }
}
```

### 4. Products

#### 4.1 List Products
**GET** `/products`

Get all products with optional filters and sorting.

**Query Parameters:**
- `category_id` (optional): Filter by category ID
- `featured` (optional): Filter featured products (true/false)
- `in_stock` (optional): Filter in-stock products (true/false)
- `search` (optional): Search term for name, brand, or model
- `sort_by` (optional): Sort field (name_ar, name_en, price, created_at)
- `sort_order` (optional): Sort order (asc, desc)
- `page` (optional): Page number (default: 1)

**Example:** `/products?category_id=1&featured=true&sort_by=price&sort_order=asc`

**Response:**
```json
{
  "success": true,
  "message": "Products retrieved successfully",
  "data": {
    "products": [
      {
        "id": 1,
        "name_ar": "لابتوب ديل",
        "name_en": "Dell Laptop",
        "slug": "dell-laptop",
        "description_ar": "لابتوب ديل عالي الأداء",
        "description_en": "High-performance Dell laptop",
        "short_description_ar": "لابتوب ديل 15 بوصة",
        "short_description_en": "Dell 15-inch laptop",
        "brand": "Dell",
        "model": "XPS 15",
        "sku": "DELL-XPS15-001",
        "price": 2500.00,
        "sale_price": 2200.00,
        "show_price": true,
        "in_stock": true,
        "stock_quantity": 10,
        "is_featured": true,
        "is_active": true,
        "image_main": "https://your-domain.com/storage/products/dell-laptop.jpg",
        "image_gallery": [
          "https://your-domain.com/storage/products/dell-laptop-1.jpg",
          "https://your-domain.com/storage/products/dell-laptop-2.jpg"
        ],
        "has_sale": true,
        "discount_percentage": 12.0,
        "category": {
          "id": 1,
          "name_ar": "إلكترونيات",
          "slug": "electronics"
        },
        "created_at": "2024-01-01T10:00:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 12,
      "total": 58,
      "has_more_pages": true
    },
    "filters": {
      "category_id": "1",
      "featured": "true",
      "in_stock": null,
      "search": null,
      "sort_by": "price",
      "sort_order": "asc"
    }
  }
}
```

#### 4.2 Get Product Details
**GET** `/products/{product}`

Get product details by slug.

**Response:**
```json
{
  "success": true,
  "message": "Product retrieved successfully",
  "data": {
    "id": 1,
    "name_ar": "لابتوب ديل",
    "name_en": "Dell Laptop",
    "slug": "dell-laptop",
    "description_ar": "لابتوب ديل عالي الأداء مع مواصفات رائعة",
    "description_en": "High-performance Dell laptop with great specifications",
    "short_description_ar": "لابتوب ديل 15 بوصة",
    "short_description_en": "Dell 15-inch laptop",
    "brand": "Dell",
    "model": "XPS 15",
    "sku": "DELL-XPS15-001",
    "price": 2500.00,
    "sale_price": 2200.00,
    "show_price": true,
    "in_stock": true,
    "stock_quantity": 10,
    "is_featured": true,
    "is_active": true,
    "image_main": "https://your-domain.com/storage/products/dell-laptop.jpg",
    "image_gallery": [
      "https://your-domain.com/storage/products/dell-laptop-1.jpg",
      "https://your-domain.com/storage/products/dell-laptop-2.jpg",
      "https://your-domain.com/storage/products/dell-laptop-3.jpg"
    ],
    "seo": {
      "meta_title": "لابتوب ديل XPS 15",
      "meta_description": "أفضل لابتوب ديل XPS 15 بأفضل سعر"
    },
    "has_sale": true,
    "discount_percentage": 12.0,
    "category": {
      "id": 1,
      "name_ar": "إلكترونيات",
      "name_en": "Electronics",
      "slug": "electronics"
    },
    "created_at": "2024-01-01T10:00:00.000000Z"
  }
}
```

#### 4.3 Related Products
**GET** `/products/{product}/related`

Get related products for a specific product.

**Response:**
```json
{
  "success": true,
  "message": "Related products retrieved successfully",
  "data": [
    {
      "id": 2,
      "name_ar": "لابتوب HP",
      "name_en": "HP Laptop",
      "slug": "hp-laptop",
      "price": 2300.00,
      "image_main": "https://your-domain.com/storage/products/hp-laptop.jpg",
      "has_sale": false,
      "discount_percentage": 0,
      "category": {
        "id": 1,
        "name_ar": "إلكترونيات",
        "slug": "electronics"
      }
    }
  ]
}
```

### 5. Search

#### 5.1 Search
**GET** `/search`

Comprehensive search across products and categories.

**Query Parameters:**
- `q` (required): Search query (minimum 2 characters)

**Example:** `/search?q=لابتوب`

**Response:**
```json
{
  "success": true,
  "message": "Search results retrieved successfully",
  "data": {
    "products": [
      {
        "id": 1,
        "name_ar": "لابتوب ديل",
        "name_en": "Dell Laptop",
        "slug": "dell-laptop",
        "price": 2500.00,
        "image_main": "https://your-domain.com/storage/products/dell-laptop.jpg",
        "category": {
          "name_ar": "إلكترونيات",
          "slug": "electronics"
        }
      }
    ],
    "categories": [
      {
        "id": 1,
        "name_ar": "إلكترونيات",
        "slug": "electronics",
        "product_count": 25
      }
    ],
    "suggestions": ["لابتوب ديل", "لابتوب HP", "إكسسوارات لابتوب"],
    "query": "لابتوب",
    "total_results": 15
  }
}
```

#### 5.2 Search Suggestions
**GET** `/search/suggestions`

Get search suggestions as user types.

**Query Parameters:**
- `q` (required): Partial search query (minimum 1 character)

**Example:** `/search/suggestions?q=لابت`

**Response:**
```json
{
  "success": true,
  "message": "Suggestions retrieved successfully",
  "data": {
    "suggestions": ["لابتوب ديل", "لابتوب HP", "لابتوب Lenovo"]
  }
}
```

### 6. Inquiries

#### 6.1 Create Inquiry
**POST** `/inquiries`

Create a new inquiry about a product or general question.

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "subject": "استفسار عن المنتج",
  "message": "أريد معرفة المزيد عن هذا المنتج",
  "product_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم إرسال استفسارك بنجاح، سنتواصل معك في أقرب وقت ممكن.",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "subject": "استفسار عن المنتج",
    "message": "أريد معرفة المزيد عن هذا المنتج",
    "status": "new",
    "status_text": "جديد",
    "product": {
      "id": 1,
      "name_ar": "لابتوب ديل",
      "slug": "dell-laptop",
      "image_main": "https://your-domain.com/storage/products/dell-laptop.jpg"
    },
    "created_at": "2024-01-01T10:00:00.000000Z",
    "created_at_formatted": "2024-01-01 10:00:00",
    "created_at_human": "منذ دقيقة واحدة"
  }
}
```

#### 6.2 User Inquiries
**GET** `/user/inquiries` *(Requires Authentication)*

Get the authenticated user's inquiries.

**Query Parameters:**
- `page` (optional): Page number (default: 1)

**Response:**
```json
{
  "success": true,
  "message": "User inquiries retrieved successfully",
  "data": {
    "inquiries": [
      {
        "id": 1,
        "name": "John Doe",
        "subject": "استفسار عن المنتج",
        "status": "new",
        "status_text": "جديد",
        "product": {
          "id": 1,
          "name_ar": "لابتوب ديل",
          "slug": "dell-laptop"
        },
        "created_at": "2024-01-01T10:00:00.000000Z",
        "created_at_human": "منذ دقيقة واحدة"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 2,
      "per_page": 10,
      "total": 15,
      "has_more_pages": true
    }
  }
}
```

#### 6.3 Get Inquiry Details
**GET** `/user/inquiries/{inquiry}` *(Requires Authentication)*

Get specific inquiry details.

**Response:**
```json
{
  "success": true,
  "message": "Inquiry retrieved successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "subject": "استفسار عن المنتج",
    "message": "أريد معرفة المزيد عن هذا المنتج",
    "status": "new",
    "status_text": "جديد",
    "product": {
      "id": 1,
      "name_ar": "لابتوب ديل",
      "slug": "dell-laptop",
      "image_main": "https://your-domain.com/storage/products/dell-laptop.jpg"
    },
    "created_at": "2024-01-01T10:00:00.000000Z",
    "created_at_formatted": "2024-01-01 10:00:00",
    "created_at_human": "منذ دقيقة واحدة"
  }
}
```

## Error Codes

| Status Code | Description |
|-------------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Internal Server Error |

## Flutter Integration Tips

### 1. HTTP Client Setup

```dart
import 'package:dio/dio.dart';

class ApiService {
  final Dio _dio = Dio();
  
  ApiService() {
    _dio.options.baseUrl = 'https://your-domain.com/api/v1';
    _dio.options.connectTimeout = const Duration(seconds: 10);
    _dio.options.receiveTimeout = const Duration(seconds: 10);
    
    // Add authentication interceptor
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        final token = storage.getToken(); // Get token from secure storage
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
    ));
  }
}
```

### 2. Response Models

```dart
class Product {
  final int id;
  final String nameAr;
  final String nameEn;
  final String slug;
  final double price;
  final double? salePrice;
  final String imageMain;
  final bool hasSale;
  final double discountPercentage;
  final Category? category;
  
  Product.fromJson(Map<String, dynamic> json)
      : id = json['id'],
        nameAr = json['name_ar'],
        nameEn = json['name_en'],
        slug = json['slug'],
        price = json['price'].toDouble(),
        salePrice = json['sale_price']?.toDouble(),
        imageMain = json['image_main'],
        hasSale = json['has_sale'],
        discountPercentage = json['discount_percentage'].toDouble(),
        category = json['category'] != null 
            ? Category.fromJson(json['category']) 
            : null;
}

class ApiResponse<T> {
  final bool success;
  final String message;
  final T? data;
  
  ApiResponse.fromJson(Map<String, dynamic> json, T Function(dynamic) fromJsonT)
      : success = json['success'],
        message = json['message'],
        data = json['data'] != null ? fromJsonT(json['data']) : null;
}
```

### 3. Error Handling

```dart
try {
  final response = await _dio.get('/products');
  final apiResponse = ApiResponse<List<dynamic>>.fromJson(
    response.data,
    (data) => (data['products'] as List)
        .map((item) => Product.fromJson(item))
        .toList(),
  );
  
  if (apiResponse.success) {
    return apiResponse.data!;
  } else {
    throw Exception(apiResponse.message);
  }
} on DioException catch (e) {
  if (e.response?.statusCode == 422) {
    // Handle validation errors
    final errors = e.response?.data['errors'];
    throw ValidationException(errors);
  } else if (e.response?.statusCode == 401) {
    // Handle unauthorized - redirect to login
    throw UnauthorizedException();
  } else {
    throw Exception('Network error: ${e.message}');
  }
}
```

### 4. Pagination

```dart
class PaginatedResponse<T> {
  final List<T> items;
  final Pagination pagination;
  
  PaginatedResponse.fromJson(
    Map<String, dynamic> json,
    T Function(dynamic) fromJsonT,
  ) : items = (json['items'] as List)
          .map((item) => fromJsonT(item))
          .toList(),
        pagination = Pagination.fromJson(json['pagination']);
}

class Pagination {
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;
  final bool hasMorePages;
  
  Pagination.fromJson(Map<String, dynamic> json)
      : currentPage = json['current_page'],
        lastPage = json['last_page'],
        perPage = json['per_page'],
        total = json['total'],
        hasMorePages = json['has_more_pages'];
}
```

## Rate Limiting

The API implements rate limiting to prevent abuse:
- Unauthenticated requests: 60 requests per hour
- Authenticated requests: 1000 requests per hour

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests per hour
- `X-RateLimit-Remaining`: Remaining requests in current window
- `X-RateLimit-Reset`: Unix timestamp when rate limit resets

## Testing

Use Postman or similar tools to test the API endpoints before implementing in Flutter. Import the following collection for quick testing:

```json
{
  "info": {
    "name": "Awan API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "variable": [
    {
      "key": "baseUrl",
      "value": "https://your-domain.com/api/v1"
    },
    {
      "key": "token",
      "value": ""
    }
  ]
}
```

## Support

For API support and questions:
- Email: api-support@your-domain.com
- Documentation: https://docs.your-domain.com/api
- Status Page: https://status.your-domain.com

---

**Last Updated:** January 2024
**Version:** 1.0.0
