# VapeShop Mobile API Documentation

**Base URL:** `https://your-railway-domain.com/api`

**Content-Type:** `application/json`

---

## Authentication

All protected endpoints require a token. Include in the header:
```
Authorization: Bearer <token>
```

### Register
**POST** `/auth/register`

```json
{
  "email": "user@example.com",
  "password": "securepass123",
  "first_name": "Juan",
  "last_name": "Dela Cruz",
  "phone": "+63 9123456789"
}
```

Response (201):
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "Juan",
    "last_name": "Dela Cruz",
    "roles": ["ROLE_CUSTOMER"],
    "loyalty_points": 0,
    "age_verification_status": "pending",
    "is_active": true,
    "created_at": "2024-07-07T10:30:00+00:00"
  }
}
```

### Login
**POST** `/auth/login`

```json
{
  "email": "user@example.com",
  "password": "securepass123"
}
```

Response (200):
```json
{
  "message": "Login successful",
  "token": "abc123def456...",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "Juan",
    "last_name": "Dela Cruz",
    "roles": ["ROLE_CUSTOMER"],
    "loyalty_points": 0,
    "age_verification_status": "pending",
    "is_active": true
  }
}
```

### Get Current User
**GET** `/auth/me` (requires auth)

Response (200):
```json
{
  "id": 1,
  "email": "user@example.com",
  "first_name": "Juan",
  "last_name": "Dela Cruz",
  "phone": "+63 9123456789",
  "roles": ["ROLE_CUSTOMER"],
  "loyalty_points": 150,
  "age_verification_status": "verified",
  "is_active": true,
  "created_at": "2024-07-07T10:30:00+00:00"
}
```

### Update Profile
**PUT** `/auth/me` (requires auth)

```json
{
  "first_name": "Juan",
  "last_name": "Dela Cruz",
  "phone": "+63 9123456789"
}
```

Response (200):
```json
{
  "message": "Profile updated",
  "user": { ... }
}
```

---

## Products

### Get Categories
**GET** `/categories`

Response (200):
```json
[
  {
    "id": 1,
    "name": "E-Liquids",
    "slug": "e-liquids",
    "description": "Premium vape juices",
    "image": "images/category-eliquids.png",
    "sort_order": 1
  }
]
```

### Get Products
**GET** `/products?category_id=1&search=mint&limit=20&offset=0`

Query Parameters:
- `category_id` (optional): Filter by category
- `search` (optional): Search by name/description
- `limit` (default: 20): Items per page
- `offset` (default: 0): Pagination offset

Response (200):
```json
{
  "total": 45,
  "limit": 20,
  "offset": 0,
  "products": [
    {
      "id": 1,
      "name": "Mint Ice",
      "slug": "mint-ice",
      "description": "Cool minty flavor",
      "short_description": "30ml bottle",
      "base_price": "299.00",
      "lowest_price": "299.00",
      "highest_price": "349.00",
      "main_image": "images/mint-ice.png",
      "brand": "VapeShop Premium",
      "requires_age_verification": true,
      "is_active": true,
      "category_id": 1,
      "category_name": "E-Liquids",
      "variant_count": 5,
      "total_stock": 150,
      "available_stock": 145
    }
  ]
}
```

### Get Product Details
**GET** `/products/{id}`

Response (200):
```json
{
  "id": 1,
  "name": "Mint Ice",
  "slug": "mint-ice",
  "description": "Cool minty flavor with ICE effect",
  "short_description": "30ml bottle",
  "base_price": "299.00",
  "lowest_price": "299.00",
  "highest_price": "349.00",
  "main_image": "images/mint-ice.png",
  "brand": "VapeShop Premium",
  "requires_age_verification": true,
  "is_active": true,
  "category_id": 1,
  "category_name": "E-Liquids",
  "variant_count": 5,
  "total_stock": 150,
  "available_stock": 145,
  "variants": [
    {
      "id": 10,
      "product_id": 1,
      "product_name": "Mint Ice",
      "sku": "MINT-ICE-3MG",
      "flavor": "Mint",
      "nicotine_strength": "3mg",
      "nicotine_label": "3mg",
      "size": null,
      "price": "299.00",
      "price_modifier": "0.00",
      "stock": 50,
      "reserved_stock": 2,
      "available_stock": 48,
      "in_stock": true,
      "is_low_stock": false,
      "display_name": "Mint Ice - Mint - 3mg"
    }
  ]
}
```

### Get Product Variants
**GET** `/products/{id}/variants`

Response (200):
```json
{
  "product_id": 1,
  "product_name": "Mint Ice",
  "base_price": "299.00",
  "variants": [
    {
      "id": 10,
      "flavor": "Mint",
      "nicotine_strength": "3mg",
      "price": "299.00",
      "available_stock": 48,
      "in_stock": true
    }
  ]
}
```

### Get All Variants
**GET** `/variants?search=mint&in_stock=true`

Query Parameters:
- `search` (optional): Search by flavor or product name
- `in_stock` (default: false): Only show in-stock variants

Response (200):
```json
{
  "variants": [
    {
      "id": 10,
      "product_id": 1,
      "product_name": "Mint Ice",
      "flavor": "Mint",
      "nicotine_strength": "3mg",
      "price": "299.00",
      "available_stock": 48,
      "in_stock": true,
      "display_name": "Mint Ice - Mint - 3mg"
    }
  ]
}
```

---

## Checkout & Orders

### Get User Addresses
**GET** `/addresses` (requires auth)

Response (200):
```json
{
  "addresses": [
    {
      "id": 1,
      "full_name": "Juan Dela Cruz",
      "street_address": "123 Main Street",
      "barangay": "San Antonio",
      "city": "Makati",
      "province": "Metro Manila",
      "postal_code": "1200",
      "country": "Philippines",
      "phone": "+63 9123456789",
      "region": "NCR"
    }
  ]
}
```

### Add Address
**POST** `/addresses` (requires auth)

```json
{
  "full_name": "Juan Dela Cruz",
  "street_address": "456 Oak Avenue",
  "barangay": "Barangay Name",
  "city": "Manila",
  "province": "Metro Manila",
  "postal_code": "1000",
  "country": "Philippines",
  "phone": "+63 9123456789"
}
```

Response (201):
```json
{
  "message": "Address added",
  "address": {
    "id": 2,
    "full_name": "Juan Dela Cruz",
    "street_address": "456 Oak Avenue",
    "city": "Manila",
    "province": "Metro Manila"
  }
}
```

### Create Order (Checkout)
**POST** `/orders` (requires auth)

```json
{
  "shipping_address_id": 1,
  "billing_address_id": 1,
  "items": [
    {
      "variant_id": 10,
      "quantity": 2
    },
    {
      "variant_id": 11,
      "quantity": 1
    }
  ],
  "discount": "0.00",
  "shipping_cost": "100.00",
  "notes": "Please deliver in the morning"
}
```

Response (201):
```json
{
  "message": "Order created",
  "order": {
    "id": 42,
    "order_number": "VS20240707ABC123",
    "status": "awaiting_payment",
    "subtotal": "598.00",
    "discount": "0.00",
    "tax": "71.76",
    "shipping": "100.00",
    "total": "769.76",
    "created_at": "2024-07-07T14:30:00+00:00"
  }
}
```

### Get User Orders
**GET** `/orders?limit=10&offset=0` (requires auth)

Query Parameters:
- `limit` (default: 10): Items per page
- `offset` (default: 0): Pagination offset

Response (200):
```json
{
  "total": 5,
  "limit": 10,
  "offset": 0,
  "orders": [
    {
      "id": 42,
      "order_number": "VS20240707ABC123",
      "status": "awaiting_payment",
      "status_label": "Awaiting Payment",
      "total": "769.76",
      "items_count": 3,
      "created_at": "2024-07-07T14:30:00+00:00",
      "paid_at": null,
      "shipped_at": null
    }
  ]
}
```

### Get Order Details
**GET** `/orders/{id}` (requires auth)

Response (200):
```json
{
  "id": 42,
  "order_number": "VS20240707ABC123",
  "status": "paid",
  "status_label": "Paid",
  "subtotal": "598.00",
  "discount": "0.00",
  "tax": "71.76",
  "shipping": "100.00",
  "total": "769.76",
  "items": [
    {
      "id": 1,
      "product_name": "Mint Ice",
      "variant_attributes": "Mint - 3mg",
      "quantity": 2,
      "unit_price": "299.00",
      "total": "598.00"
    }
  ],
  "shipping_address": {
    "full_name": "Juan Dela Cruz",
    "street": "123 Main Street",
    "city": "Makati",
    "province": "Metro Manila",
    "postal_code": "1200",
    "phone": "+63 9123456789"
  },
  "created_at": "2024-07-07T14:30:00+00:00",
  "paid_at": "2024-07-07T14:35:00+00:00",
  "shipped_at": null
}
```

---

## Error Responses

### 400 Bad Request
```json
{
  "error": "Missing required fields: email, password, first_name, last_name"
}
```

### 401 Unauthorized
```json
{
  "error": "Unauthorized"
}
```

### 404 Not Found
```json
{
  "error": "Product not found"
}
```

### 409 Conflict
```json
{
  "error": "Email already registered"
}
```

---

## React Native Usage Example

```javascript
import axios from 'axios';

const API_BASE = 'https://your-railway-domain.com/api';

// Register
const register = async (email, password, firstName, lastName, phone) => {
  try {
    const res = await axios.post(`${API_BASE}/auth/register`, {
      email,
      password,
      first_name: firstName,
      last_name: lastName,
      phone,
    });
    return res.data;
  } catch (error) {
    throw error.response.data;
  }
};

// Login
const login = async (email, password) => {
  try {
    const res = await axios.post(`${API_BASE}/auth/login`, {
      email,
      password,
    });
    // Store token
    AsyncStorage.setItem('authToken', res.data.token);
    return res.data;
  } catch (error) {
    throw error.response.data;
  }
};

// Get Products
const getProducts = async (categoryId = null, search = '') => {
  try {
    const params = {
      limit: 20,
      offset: 0,
    };
    if (categoryId) params.category_id = categoryId;
    if (search) params.search = search;
    
    const res = await axios.get(`${API_BASE}/products`, { params });
    return res.data;
  } catch (error) {
    throw error.response.data;
  }
};

// Create Order
const createOrder = async (shippingAddressId, items, shippingCost = 100) => {
  try {
    const token = await AsyncStorage.getItem('authToken');
    const res = await axios.post(`${API_BASE}/orders`, {
      shipping_address_id: shippingAddressId,
      items,
      shipping_cost: shippingCost,
    }, {
      headers: {
        Authorization: `Bearer ${token}`,
      }
    });
    return res.data;
  } catch (error) {
    throw error.response.data;
  }
};

export { register, login, getProducts, createOrder };
```

---

## Order Statuses

- `pending` - Order created but not paid
- `awaiting_payment` - Waiting for payment
- `paid` - Payment received
- `processing` - Being packed
- `ready_to_ship` - Ready for courier
- `shipped` - In transit
- `delivered` - Delivered to customer
- `cancelled` - Order cancelled
- `refunded` - Refund processed

---

## Age Verification Statuses

- `pending` - Awaiting verification
- `verified` - Verified (18+)
- `rejected` - Verification rejected

---

## Support

For API issues, contact: support@vapeshop.ph
