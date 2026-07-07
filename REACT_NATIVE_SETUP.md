# React Native Mobile App Setup Guide

This guide shows how to integrate your React Native app with the VapeShop backend API.

## Installation

### 1. Install Required Packages

```bash
npm install axios react-native-async-storage @react-native-async-storage/async-storage
# or
yarn add axios react-native-async-storage @react-native-async-storage/async-storage
```

### 2. Create API Service File

Create `src/services/api.js`:

```javascript
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

const API_BASE_URL = 'https://your-railway-domain.com/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add token to requests
api.interceptors.request.use(async (config) => {
  const token = await AsyncStorage.getItem('authToken');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token expired, clear storage and redirect to login
      AsyncStorage.removeItem('authToken');
      AsyncStorage.removeItem('user');
    }
    return Promise.reject(error.response?.data || error);
  }
);

export default api;
```

## Authentication

### Register User

```javascript
import api from './services/api';

const registerUser = async (email, password, firstName, lastName, phone) => {
  try {
    const response = await api.post('/auth/register', {
      email,
      password,
      first_name: firstName,
      last_name: lastName,
      phone,
    });
    return response.data;
  } catch (error) {
    throw error;
  }
};
```

### Login User

```javascript
const loginUser = async (email, password) => {
  try {
    const response = await api.post('/auth/login', {
      email,
      password,
    });

    // Store token and user data
    await AsyncStorage.setItem('authToken', response.data.token);
    await AsyncStorage.setItem('user', JSON.stringify(response.data.user));

    return response.data;
  } catch (error) {
    throw error;
  }
};
```

### Get Current User

```javascript
const getCurrentUser = async () => {
  try {
    const response = await api.get('/auth/me');
    return response.data;
  } catch (error) {
    throw error;
  }
};
```

## Products

### Fetch Categories

```javascript
const getCategories = async () => {
  try {
    const response = await api.get('/categories');
    return response.data;
  } catch (error) {
    throw error;
  }
};
```

### Fetch Products

```javascript
const getProducts = async (categoryId = null, search = '', page = 0) => {
  try {
    const params = {
      limit: 20,
      offset: page * 20,
    };

    if (categoryId) params.category_id = categoryId;
    if (search) params.search = search;

    const response = await api.get('/products', { params });
    return response.data;
  } catch (error) {
    throw error;
  }
};
```

### Get Product Details

```javascript
const getProductDetails = async (productId) => {
  try {
    const response = await api.get(`/products/${productId}`);
    return response.data;
  } catch (error) {
    throw error;
  }
};
```

### Search Variants

```javascript
const searchVariants = async (search = '', inStock = false) => {
  try {
    const params = {};
    if (search) params.search = search;
    if (inStock) params.in_stock = true;

    const response = await api.get('/variants', { params });
    return response.data;
  } catch (error) {
    throw error;
  }
};
```

## Checkout & Orders

### Get User Addresses

```javascript
const getUserAddresses = async () => {
  try {
    const response = await api.get('/addresses');
    return response.data;
  } catch (error) {
    throw error;
  }
};
```

### Add New Address

```javascript
const addAddress = async (address) => {
  try {
    const response = await api.post('/addresses', {
      full_name: address.fullName,
      street_address: address.street,
      barangay: address.barangay,
      city: address.city,
      province: address.province,
      postal_code: address.postalCode,
      country: address.country,
      phone: address.phone,
    });
    return response.data;
  } catch (error) {
    throw error;
  }
};
```

### Create Order

```javascript
const createOrder = async (shippingAddressId, cartItems, shippingCost = 100) => {
  try {
    const items = cartItems.map(item => ({
      variant_id: item.variantId,
      quantity: item.quantity,
    }));

    const response = await api.post('/orders', {
      shipping_address_id: shippingAddressId,
      items,
      shipping_cost: shippingCost,
      discount: 0,
    });

    return response.data;
  } catch (error) {
    throw error;
  }
};
```

### Get User Orders

```javascript
const getUserOrders = async (page = 0) => {
  try {
    const response = await api.get('/orders', {
      params: {
        limit: 10,
        offset: page * 10,
      },
    });
    return response.data;
  } catch (error) {
    throw error;
  }
};
```

### Get Order Details

```javascript
const getOrderDetails = async (orderId) => {
  try {
    const response = await api.get(`/orders/${orderId}`);
    return response.data;
  } catch (error) {
    throw error;
  }
};
```

## React Context for State Management

Create `src/context/AuthContext.js`:

```javascript
import React, { createContext, useState, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from '../services/api';

export const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [state, dispatch] = React.useReducer(authReducer, initialLoginState);

  const authContext = {
    state,
    signIn: async (email, password) => {
      try {
        const response = await api.post('/auth/login', { email, password });
        await AsyncStorage.setItem('authToken', response.data.token);
        await AsyncStorage.setItem('user', JSON.stringify(response.data.user));
        
        dispatch({ type: 'RESTORE_TOKEN', token: response.data.token });
        return response.data;
      } catch (error) {
        throw error;
      }
    },

    signUp: async (email, password, firstName, lastName) => {
      try {
        const response = await api.post('/auth/register', {
          email,
          password,
          first_name: firstName,
          last_name: lastName,
        });
        return response.data;
      } catch (error) {
        throw error;
      }
    },

    signOut: async () => {
      await AsyncStorage.removeItem('authToken');
      await AsyncStorage.removeItem('user');
      dispatch({ type: 'SIGN_OUT' });
    },

    restoreToken: async () => {
      const token = await AsyncStorage.getItem('authToken');
      dispatch({ type: 'RESTORE_TOKEN', token });
    },
  };

  return (
    <AuthContext.Provider value={authContext}>
      {children}
    </AuthContext.Provider>
  );
};

const initialLoginState = {
  isLoading: true,
  isSignout: false,
  userToken: null,
};

const authReducer = (prevState, action) => {
  switch (action.type) {
    case 'RESTORE_TOKEN':
      return {
        ...prevState,
        userToken: action.token,
        isLoading: false,
      };
    case 'SIGN_IN':
      return {
        ...prevState,
        isSignout: false,
        userToken: action.token,
      };
    case 'SIGN_OUT':
      return {
        ...prevState,
        isSignout: true,
        userToken: null,
      };
    case 'SIGN_UP':
      return {
        ...prevState,
        isSignout: false,
        userToken: action.token,
      };
  }
};
```

## Usage in Components

### Login Screen

```javascript
import React, { useContext, useState } from 'react';
import { View, TextInput, TouchableOpacity, Text } from 'react-native';
import { AuthContext } from '../context/AuthContext';

export const LoginScreen = () => {
  const { signIn } = useContext(AuthContext);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    try {
      setLoading(true);
      await signIn(email, password);
    } catch (error) {
      alert(error.error || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={{ flex: 1, padding: 20 }}>
      <TextInput
        placeholder="Email"
        value={email}
        onChangeText={setEmail}
        keyboardType="email-address"
        editable={!loading}
        style={{ borderBottomWidth: 1, marginBottom: 10, padding: 10 }}
      />
      <TextInput
        placeholder="Password"
        value={password}
        onChangeText={setPassword}
        secureTextEntry
        editable={!loading}
        style={{ borderBottomWidth: 1, marginBottom: 20, padding: 10 }}
      />
      <TouchableOpacity
        onPress={handleLogin}
        disabled={loading}
        style={{
          backgroundColor: loading ? '#ccc' : '#007AFF',
          padding: 15,
          borderRadius: 8,
        }}
      >
        <Text style={{ color: '#fff', textAlign: 'center', fontWeight: 'bold' }}>
          {loading ? 'Logging in...' : 'Login'}
        </Text>
      </TouchableOpacity>
    </View>
  );
};
```

### Products List Screen

```javascript
import React, { useState, useEffect } from 'react';
import { View, FlatList, Text, Image, TouchableOpacity } from 'react-native';
import { getProducts } from '../services/api';

export const ProductsScreen = () => {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadProducts();
  }, []);

  const loadProducts = async () => {
    try {
      setLoading(true);
      const data = await getProducts();
      setProducts(data.products);
    } catch (error) {
      alert('Failed to load products');
    } finally {
      setLoading(false);
    }
  };

  return (
    <FlatList
      data={products}
      keyExtractor={(item) => item.id.toString()}
      onRefresh={loadProducts}
      refreshing={loading}
      renderItem={({ item }) => (
        <TouchableOpacity style={{ padding: 10, borderBottomWidth: 1 }}>
          <Image
            source={{ uri: `https://your-domain/${item.main_image}` }}
            style={{ width: 100, height: 100 }}
          />
          <Text style={{ fontSize: 16, fontWeight: 'bold' }}>{item.name}</Text>
          <Text>₱{item.base_price}</Text>
        </TouchableOpacity>
      )}
    />
  );
};
```

## Environment Configuration

Create `.env` file:

```
REACT_APP_API_URL=https://your-railway-domain.com/api
```

Then update `src/services/api.js`:

```javascript
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api';
```

## Testing

Use Postman or curl to test endpoints before integrating:

```bash
# Register
curl -X POST https://your-domain.com/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "test123456",
    "first_name": "Test",
    "last_name": "User"
  }'

# Login
curl -X POST https://your-domain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "test123456"
  }'

# Get Products
curl https://your-domain.com/api/products
```

## Troubleshooting

### CORS Errors
Make sure your domain is added to the CORS configuration in the backend.

### 401 Unauthorized
Check that the token is being sent correctly in the Authorization header.

### Connection Refused
Verify the API_BASE_URL is correct and the backend is running.

## Next Steps

1. Set up authentication with JWT tokens (optional but recommended for production)
2. Implement payment gateway integration (GCash, Maya)
3. Add push notifications for order updates
4. Implement image caching for products
5. Add offline support with local storage

---

**Need help?** Contact support@vapeshop.ph or check the API documentation.
