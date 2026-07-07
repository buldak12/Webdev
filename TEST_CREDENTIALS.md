# Test Credentials for VapeShop Mobile App

Use these credentials to test your backend on your mobile app (React Native).

---

## 📱 Mobile App User (Customer)

**Email:** `customer@vapeshop.ph`  
**Password:** `Customer123456`

**User Details:**
- Name: Juan Dela Cruz
- Role: ROLE_CUSTOMER
- Phone: +63 9123456789
- Age Verification: ✅ Verified
- Email Verified: ✅ Yes
- Account Status: ✅ Active

**Use this account to test:**
- ✅ User registration & login
- ✅ Browse products & categories
- ✅ View product details & variants
- ✅ Manage shipping addresses
- ✅ Create orders (checkout)
- ✅ View order history

---

## 🔧 Admin User (Dashboard)

**Email:** `admin@vapeshop.ph`  
**Password:** `Admin123456`

**User Details:**
- Name: Admin User
- Role: ROLE_ADMIN
- Account Status: ✅ Active
- Email Verified: ✅ Yes

**Use this account to:**
- ✅ Create products & categories
- ✅ Manage inventory & variants
- ✅ Create orders manually
- ✅ Create customers
- ✅ View all orders & customers
- ✅ Manage activity logs

---

## 🧪 API Endpoints to Test

### 1. Login (Get Auth Token)
```bash
POST https://your-railway-domain.com/api/auth/login
Content-Type: application/json

{
  "email": "customer@vapeshop.ph",
  "password": "Customer123456"
}

Response:
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "email": "customer@vapeshop.ph",
    "first_name": "Juan",
    "loyalty_points": 0
  }
}
```

### 2. Get Current User Profile
```bash
GET https://your-railway-domain.com/api/auth/me
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...

Response:
{
  "id": 1,
  "email": "customer@vapeshop.ph",
  "first_name": "Juan",
  "last_name": "Dela Cruz",
  "phone": "+63 9123456789",
  "loyalty_points": 0,
  "age_verification_status": "verified"
}
```

### 3. Browse Products
```bash
GET https://your-railway-domain.com/api/products

Response:
{
  "total": 10,
  "products": [
    {
      "id": 1,
      "name": "Mint Ice",
      "base_price": "299.00",
      "main_image": "...",
      "available_stock": 50
    }
  ]
}
```

### 4. Get Shipping Addresses
```bash
GET https://your-railway-domain.com/api/addresses
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...

Response:
{
  "addresses": [
    {
      "id": 1,
      "full_name": "Juan Dela Cruz",
      "street_address": "123 Main Street",
      "city": "Makati"
    }
  ]
}
```

### 5. Create Order
```bash
POST https://your-railway-domain.com/api/orders
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json

{
  "shipping_address_id": 1,
  "items": [
    {
      "variant_id": 10,
      "quantity": 2
    }
  ],
  "shipping_cost": "100.00",
  "discount": "0.00"
}

Response:
{
  "order": {
    "id": 1,
    "order_number": "VS20240707ABC123",
    "status": "awaiting_payment",
    "total": "769.76"
  }
}
```

---

## 📋 Quick Testing Checklist

- [ ] **Register** a new user with `/api/auth/register`
- [ ] **Login** with the customer credentials
- [ ] **Get token** from login response
- [ ] **Browse products** with GET `/api/products`
- [ ] **View product** details with GET `/api/products/{id}`
- [ ] **Get addresses** with GET `/api/addresses` (use token)
- [ ] **Create address** with POST `/api/addresses` (use token)
- [ ] **Create order** with POST `/api/orders` (use token)
- [ ] **Get orders** with GET `/api/orders` (use token)
- [ ] **View order details** with GET `/api/orders/{id}` (use token)

---

## 🌐 Replace Domain

Replace `https://your-railway-domain.com` with your actual Railway domain:

Example:
```
https://vapeshop-production.up.railway.app
https://webdev-prod.up.railway.app
```

Find your Railway domain in the Railway dashboard → Project → Deployments → Domain

---

## ⚠️ Important Notes

1. **Token expiration:** Token may expire. Re-login to get a new token.
2. **Protected endpoints:** All endpoints starting with `/api/` (except register/login) require `Authorization: Bearer <token>` header
3. **CORS enabled:** All `/api/*` endpoints support CORS for mobile app access
4. **Test products:** You must have products created via admin dashboard first
5. **Test addresses:** Create an address before creating an order

---

## 🔗 API Documentation

For full API details, see:
- `API_DOCUMENTATION.md` - Complete endpoint reference
- `REACT_NATIVE_SETUP.md` - Mobile app integration guide
- `MOBILE_APP_CHECKLIST.md` - Feature checklist

---

## 🆘 Need More Test Users?

Create additional test users with the register endpoint:
```bash
POST https://your-domain.com/api/auth/register
{
  "email": "test@example.com",
  "password": "Test123456",
  "first_name": "Test",
  "last_name": "User"
}
```

Or use the admin dashboard to create customers manually.

**Happy testing!** 🚀
