# 🔄 Real-Time Implementation Guide
## Symfony Backend + React Native Mobile App

This guide implements real-time synchronization between your Symfony backend and React Native mobile app.

---

## 📋 Architecture Overview

```
┌─────────────────┐
│  Mobile App     │
│  (React Native) │
└────────┬────────┘
         │
         │ SSE/WebSocket
         │ (Real-time updates)
         ↓
┌─────────────────┐
│  Symfony API    │
│  + Mercure Hub  │
└────────┬────────┘
         │
         │ Doctrine Events
         │ (Auto-publish)
         ↓
┌─────────────────┐
│  MySQL Database │
└─────────────────┘
```

## 🎯 What Will Be Real-Time

1. **Products** - Inventory updates, new products, price changes
2. **Orders** - Status updates, new orders
3. **Cart** - Stock availability changes
4. **Notifications** - New messages, alerts
5. **User Status** - Account changes, loyalty points

---

## 🚀 Backend Implementation (Symfony)

### Step 1: Install Mercure Bundle

```bash
cd C:\Users\Preciado\landing-page
composer require symfony/mercure-bundle
```

### Step 2: Configure Mercure

**File: `.env`**
```env
# Add these lines
MERCURE_URL=https://webdev-production-7ab3.up.railway.app/.well-known/mercure
MERCURE_PUBLIC_URL=https://webdev-production-7ab3.up.railway.app/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
```

**For Railway (Production):**
Add these environment variables in Railway Dashboard:
```
MERCURE_URL=/.well-known/mercure
MERCURE_PUBLIC_URL=https://webdev-production-7ab3.up.railway.app/.well-known/mercure
MERCURE_JWT_SECRET=your-super-secret-jwt-key-here
```

### Step 3: Create Real-Time Service

**File: `src/Service/RealTimeService.php`**
```php
<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class RealTimeService
{
    public function __construct(
        private HubInterface $hub
    ) {}

    /**
     * Publish product update to all subscribers
     */
    public function publishProductUpdate(array $product): void
    {
        $update = new Update(
            topics: ['products', "products/{$product['id']}"],
            data: json_encode([
                'type' => 'product.updated',
                'product' => $product,
                'timestamp' => time()
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish order update to specific user
     */
    public function publishOrderUpdate(int $userId, array $order): void
    {
        $update = new Update(
            topics: ["user/{$userId}/orders", "orders/{$order['id']}"],
            data: json_encode([
                'type' => 'order.updated',
                'order' => $order,
                'timestamp' => time()
            ]),
            private: true // Only visible to authenticated users
        );

        $this->hub->publish($update);
    }

    /**
     * Publish stock change
     */
    public function publishStockChange(int $productId, int $variantId, int $newStock): void
    {
        $update = new Update(
            topics: ["products/{$productId}/stock", 'stock.changes'],
            data: json_encode([
                'type' => 'stock.changed',
                'product_id' => $productId,
                'variant_id' => $variantId,
                'new_stock' => $newStock,
                'timestamp' => time()
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish notification to user
     */
    public function publishNotification(int $userId, string $message, string $type = 'info'): void
    {
        $update = new Update(
            topics: ["user/{$userId}/notifications"],
            data: json_encode([
                'type' => 'notification',
                'message' => $message,
                'notification_type' => $type,
                'timestamp' => time()
            ]),
            private: true
        );

        $this->hub->publish($update);
    }

    /**
     * Broadcast system-wide message
     */
    public function broadcastMessage(string $message, string $type = 'announcement'): void
    {
        $update = new Update(
            topics: ['broadcast'],
            data: json_encode([
                'type' => 'broadcast',
                'message' => $message,
                'broadcast_type' => $type,
                'timestamp' => time()
            ])
        );

        $this->hub->publish($update);
    }
}
```

### Step 4: Create Doctrine Event Listeners

**File: `src/EventListener/ProductUpdateListener.php`**
```php
<?php

namespace App\EventListener;

use App\Entity\Product;
use App\Service\RealTimeService;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postUpdate, entity: Product::class)]
#[AsEntityListener(event: Events::postPersist, entity: Product::class)]
class ProductUpdateListener
{
    public function __construct(
        private RealTimeService $realTimeService
    ) {}

    public function postUpdate(Product $product, PostUpdateEventArgs $args): void
    {
        if ($product->isActive()) {
            $this->realTimeService->publishProductUpdate($this->serializeProduct($product));
        }
    }

    public function postPersist(Product $product, PostPersistEventArgs $args): void
    {
        if ($product->isActive()) {
            $this->realTimeService->publishProductUpdate($this->serializeProduct($product));
        }
    }

    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'base_price' => $product->getBasePrice(),
            'available_stock' => $product->getAvailableStock(),
            'is_active' => $product->isActive(),
        ];
    }
}
```

**File: `src/EventListener/OrderUpdateListener.php`**
```php
<?php

namespace App\EventListener;

use App\Entity\Order;
use App\Service\RealTimeService;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postUpdate, entity: Order::class)]
class OrderUpdateListener
{
    public function __construct(
        private RealTimeService $realTimeService
    ) {}

    public function postUpdate(Order $order, PostUpdateEventArgs $args): void
    {
        $userId = $order->getUser()?->getId();
        
        if ($userId) {
            $this->realTimeService->publishOrderUpdate($userId, [
                'id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'total' => $order->getTotal(),
            ]);
        }
    }
}
```

### Step 5: Add Real-Time API Endpoint

**File: `src/Controller/Api/RealTimeController.php`**
```php
<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

#[Route('/api')]
class RealTimeController extends AbstractController
{
    /**
     * Get Mercure authorization token for authenticated users
     * GET /api/realtime/token
     */
    #[Route('/realtime/token', name: 'api_realtime_token', methods: ['GET'])]
    public function getMercureToken(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $jwtKey = $_ENV['MERCURE_JWT_SECRET'] ?? 'default-secret';
        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($jwtKey)
        );

        $token = $config->builder()
            ->withClaim('mercure', [
                'subscribe' => [
                    "user/{$user->getId()}/*",
                    'products',
                    'broadcast'
                ]
            ])
            ->getToken($config->signer(), $config->signingKey());

        return $this->json([
            'token' => $token->toString(),
            'hub_url' => $_ENV['MERCURE_PUBLIC_URL'] ?? '/.well-known/mercure'
        ]);
    }
}
```

---

## 📱 Mobile App Implementation (React Native)

### Step 1: Install EventSource

```bash
cd C:\Users\Preciado\Appdev
npm install react-native-sse
npm install @react-native-community/netinfo
```

### Step 2: Create Real-Time Service

**File: `src/services/realtime.ts`**
```typescript
import EventSource from 'react-native-sse';
import { API_BASE_URL } from '../app/api/config';

export type RealTimeEventType =
    | 'product.updated'
    | 'order.updated'
    | 'stock.changed'
    | 'notification'
    | 'broadcast';

export interface RealTimeEvent {
    type: RealTimeEventType;
    data: any;
    timestamp: number;
}

export type RealTimeCallback = (event: RealTimeEvent) => void;

class RealTimeService {
    private eventSource: EventSource | null = null;
    private callbacks: Map<string, Set<RealTimeCallback>> = new Map();
    private token: string | null = null;
    private reconnectAttempts = 0;
    private maxReconnectAttempts = 5;
    private reconnectDelay = 1000;

    /**
     * Initialize real-time connection
     */
    async connect(authToken: string): Promise<void> {
        if (this.eventSource) {
            console.log('🔄 [RealTime] Already connected');
            return;
        }

        try {
            // Get Mercure token from backend
            const response = await fetch(`${API_BASE_URL}/api/realtime/token`, {
                headers: {
                    'Authorization': `Bearer ${authToken}`,
                },
            });

            if (!response.ok) {
                throw new Error('Failed to get Mercure token');
            }

            const { token, hub_url } = await response.json();
            this.token = authToken;

            // Subscribe to topics
            const topics = [
                'products',
                'stock.changes',
                'broadcast',
            ];

            const url = new URL(hub_url, API_BASE_URL);
            topics.forEach(topic => url.searchParams.append('topic', topic));

            console.log('🔄 [RealTime] Connecting to:', url.toString());

            this.eventSource = new EventSource(url.toString(), {
                headers: {
                    'Authorization': `Bearer ${token}`,
                },
            });

            this.eventSource.addEventListener('open', () => {
                console.log('✅ [RealTime] Connected');
                this.reconnectAttempts = 0;
            });

            this.eventSource.addEventListener('message', (event: any) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleEvent(data);
                } catch (error) {
                    console.error('❌ [RealTime] Parse error:', error);
                }
            });

            this.eventSource.addEventListener('error', (error: any) => {
                console.error('❌ [RealTime] Connection error:', error);
                this.handleDisconnect();
            });

        } catch (error) {
            console.error('❌ [RealTime] Connection failed:', error);
            this.handleDisconnect();
        }
    }

    /**
     * Disconnect from real-time service
     */
    disconnect(): void {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this.callbacks.clear();
        console.log('🔌 [RealTime] Disconnected');
    }

    /**
     * Subscribe to event type
     */
    subscribe(eventType: RealTimeEventType, callback: RealTimeCallback): () => void {
        if (!this.callbacks.has(eventType)) {
            this.callbacks.set(eventType, new Set());
        }
        this.callbacks.get(eventType)!.add(callback);

        console.log(`📡 [RealTime] Subscribed to ${eventType}`);

        // Return unsubscribe function
        return () => {
            this.callbacks.get(eventType)?.delete(callback);
            console.log(`📡 [RealTime] Unsubscribed from ${eventType}`);
        };
    }

    /**
     * Handle incoming event
     */
    private handleEvent(event: RealTimeEvent): void {
        console.log('📨 [RealTime] Event received:', event.type);

        const callbacks = this.callbacks.get(event.type);
        if (callbacks) {
            callbacks.forEach(callback => {
                try {
                    callback(event);
                } catch (error) {
                    console.error('❌ [RealTime] Callback error:', error);
                }
            });
        }
    }

    /**
     * Handle disconnection and reconnect
     */
    private handleDisconnect(): void {
        this.eventSource = null;

        if (this.reconnectAttempts < this.maxReconnectAttempts && this.token) {
            this.reconnectAttempts++;
            const delay = this.reconnectDelay * this.reconnectAttempts;

            console.log(`🔄 [RealTime] Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);

            setTimeout(() => {
                if (this.token) {
                    this.connect(this.token);
                }
            }, delay);
        } else {
            console.error('❌ [RealTime] Max reconnect attempts reached');
        }
    }

    /**
     * Check connection status
     */
    isConnected(): boolean {
        return this.eventSource !== null;
    }
}

export const realTimeService = new RealTimeService();
```

### Step 3: Create Real-Time Context

**File: `src/contexts/RealTimeContext.tsx`**
```typescript
import React, { createContext, useContext, useEffect, useState } from 'react';
import { useSelector } from 'react-redux';
import { RootState } from '../app/reducers';
import { realTimeService, RealTimeEvent, RealTimeEventType } from '../services/realtime';

interface RealTimeContextType {
    isConnected: boolean;
    subscribe: (eventType: RealTimeEventType, callback: (event: RealTimeEvent) => void) => () => void;
}

const RealTimeContext = createContext<RealTimeContextType | undefined>(undefined);

export const RealTimeProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const [isConnected, setIsConnected] = useState(false);
    const { data: user } = useSelector((state: RootState) => state.auth);

    useEffect(() => {
        if (user?.token) {
            console.log('🔄 [RealTime] Initializing connection...');
            
            realTimeService.connect(user.token).then(() => {
                setIsConnected(true);
            }).catch((error) => {
                console.error('❌ [RealTime] Failed to connect:', error);
                setIsConnected(false);
            });

            return () => {
                realTimeService.disconnect();
                setIsConnected(false);
            };
        }
    }, [user?.token]);

    const subscribe = (eventType: RealTimeEventType, callback: (event: RealTimeEvent) => void) => {
        return realTimeService.subscribe(eventType, callback);
    };

    return (
        <RealTimeContext.Provider value={{ isConnected, subscribe }}>
            {children}
        </RealTimeContext.Provider>
    );
};

export const useRealTime = () => {
    const context = useContext(RealTimeContext);
    if (!context) {
        throw new Error('useRealTime must be used within RealTimeProvider');
    }
    return context;
};
```

### Step 4: Update Products Screen with Real-Time

**Update: `src/screens/ProductsScreen.tsx`**

Add at the top:
```typescript
import { useRealTime } from '../contexts/RealTimeContext';
```

Add inside component:
```typescript
const { subscribe } = useRealTime();

// Subscribe to product updates
useEffect(() => {
    const unsubscribe = subscribe('product.updated', (event) => {
        console.log('📦 [Products] Real-time update:', event.data);
        
        // Update local products state
        setProducts((current) => {
            const updatedProduct = event.data.product;
            const index = current.findIndex(p => p.id === updatedProduct.id);
            
            if (index !== -1) {
                // Update existing product
                const newProducts = [...current];
                newProducts[index] = { ...newProducts[index], ...updatedProduct };
                return newProducts;
            } else {
                // Add new product
                return [...current, updatedProduct];
            }
        });
    });

    return unsubscribe;
}, [subscribe]);

// Subscribe to stock changes
useEffect(() => {
    const unsubscribe = subscribe('stock.changed', (event) => {
        console.log('📊 [Stock] Real-time update:', event.data);
        
        // Update stock for specific product
        setProducts((current) => 
            current.map(product => 
                product.id === event.data.product_id
                    ? { ...product, quantity: event.data.new_stock }
                    : product
            )
        );
    });

    return unsubscribe;
}, [subscribe]);
```

---

## 🧪 Testing Real-Time

### Backend Test (Trigger Update)

```bash
# Create a simple test endpoint or use Symfony console
php bin/console app:test-realtime
```

Or create test controller:
```php
#[Route('/api/test/realtime', name: 'api_test_realtime')]
public function testRealTime(RealTimeService $realTimeService): JsonResponse
{
    $realTimeService->publishProductUpdate([
        'id' => 1,
        'name' => 'Test Product',
        'base_price' => '999.00',
        'available_stock' => 50,
    ]);

    return $this->json(['message' => 'Real-time event published']);
}
```

### Mobile App Test

1. Open your app
2. Navigate to Products screen
3. Trigger a product update from backend
4. Watch the product list update automatically!

---

## 🚀 Deployment to Railway

### Add Mercure to Railway

**Option 1: Use Railway's Mercure Template**
1. Go to Railway Dashboard
2. Click "New Project"
3. Select "Deploy a Template"
4. Search for "Mercure"
5. Deploy it

**Option 2: Use External Mercure Hub**
- Use [Mercure.rocks](https://mercure.rocks/) managed service
- Or deploy your own Mercure hub on separate Railway service

### Environment Variables

Add to Railway:
```
MERCURE_URL=https://your-mercure-hub.railway.app/.well-known/mercure
MERCURE_PUBLIC_URL=https://your-mercure-hub.railway.app/.well-known/mercure
MERCURE_JWT_SECRET=your-long-random-secret-key
```

---

## ✅ What's Now Real-Time

- ✅ **Products** - Instant inventory updates
- ✅ **Stock** - Real-time availability
- ✅ **Orders** - Status changes push to user
- ✅ **Notifications** - Instant alerts
- ✅ **Broadcast** - System-wide messages

---

## 📊 Next Enhancements

1. **Add Connection Indicator** - Show real-time status in UI
2. **Offline Queue** - Store updates when offline
3. **Optimistic Updates** - Update UI immediately, sync later
4. **Push Notifications** - Native alerts when app is closed
5. **Analytics** - Track real-time engagement

---

## 🔧 Troubleshooting

**Connection fails:**
- Check MERCURE_URL is correct
- Verify JWT secret matches
- Check CORS configuration

**Events not received:**
- Verify subscription topics match
- Check authentication token
- Look for errors in browser/mobile console

**Performance issues:**
- Limit number of subscriptions
- Debounce rapid updates
- Consider pagination for large datasets

---

Generated: 2026-07-08
Status: Ready for Implementation
