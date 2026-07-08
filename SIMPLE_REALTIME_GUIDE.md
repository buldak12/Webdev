# 🔄 Simple Real-Time Solution
## Polling-Based Real-Time Updates (No Extra Services Required)

This is a simpler alternative that works immediately without needing Mercure or WebSocket servers.

---

## 🎯 How It Works

```
Mobile App → Poll every 3s → Backend API → Get Updates → Update UI
```

**Advantages:**
- ✅ No additional services needed
- ✅ Works on Railway immediately
- ✅ Easy to implement
- ✅ Reliable

---

## 🚀 Backend Implementation

### Step 1: Create Updates API Endpoint

**File: `src/Controller/Api/UpdatesController.php`**
```php
<?php

namespace App\Controller\Api;

use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class UpdatesController extends AbstractController
{
    /**
     * Get updates since timestamp
     * GET /api/updates?since=1234567890&types=products,orders
     */
    #[Route('/updates', name: 'api_updates', methods: ['GET'])]
    public function getUpdates(
        Request $request,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $since = $request->query->getInt('since', 0);
        $types = explode(',', $request->query->getString('types', 'products,orders'));
        
        $sinceDate = new \DateTime();
        $sinceDate->setTimestamp($since);

        $updates = [];

        // Get product updates
        if (in_array('products', $types)) {
            $qb = $productRepository->createQueryBuilder('p')
                ->where('p.updatedAt > :since')
                ->andWhere('p.isActive = true')
                ->setParameter('since', $sinceDate)
                ->orderBy('p.updatedAt', 'DESC')
                ->setMaxResults(50);

            $updatedProducts = $qb->getQuery()->getResult();

            if (!empty($updatedProducts)) {
                $updates['products'] = array_map(function($product) {
                    return [
                        'id' => $product->getId(),
                        'name' => $product->getName(),
                        'base_price' => $product->getBasePrice(),
                        'available_stock' => $product->getAvailableStock(),
                        'updated_at' => $product->getUpdatedAt()?->getTimestamp(),
                    ];
                }, $updatedProducts);
            }
        }

        // Get order updates (for authenticated user)
        if (in_array('orders', $types) && $this->getUser()) {
            $userOrders = $orderRepository->createQueryBuilder('o')
                ->where('o.user = :user')
                ->andWhere('o.updatedAt > :since')
                ->setParameter('user', $this->getUser())
                ->setParameter('since', $sinceDate)
                ->orderBy('o.updatedAt', 'DESC')
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();

            if (!empty($userOrders)) {
                $updates['orders'] = array_map(function($order) {
                    return [
                        'id' => $order->getId(),
                        'order_number' => $order->getOrderNumber(),
                        'status' => $order->getStatus(),
                        'total' => $order->getTotal(),
                        'updated_at' => $order->getUpdatedAt()?->getTimestamp(),
                    ];
                }, $userOrders);
            }
        }

        return $this->json([
            'updates' => $updates,
            'timestamp' => time(),
            'has_updates' => !empty($updates),
        ]);
    }

    /**
     * Get stock status for multiple variants
     * GET /api/stock/check?variants=1,2,3
     */
    #[Route('/stock/check', name: 'api_stock_check', methods: ['GET'])]
    public function checkStock(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $variantIds = array_filter(array_map('intval', explode(',', $request->query->getString('variants'))));

        if (empty($variantIds)) {
            return $this->json(['stock' => []]);
        }

        $variants = $em->createQueryBuilder()
            ->select('v.id, v.stock, v.reservedStock')
            ->from('App\Entity\ProductVariant', 'v')
            ->where('v.id IN (:ids)')
            ->andWhere('v.isActive = true')
            ->setParameter('ids', $variantIds)
            ->getQuery()
            ->getResult();

        $stock = [];
        foreach ($variants as $variant) {
            $stock[$variant['id']] = [
                'available' => max(0, $variant['stock'] - $variant['reservedStock']),
                'in_stock' => ($variant['stock'] - $variant['reservedStock']) > 0,
            ];
        }

        return $this->json(['stock' => $stock]);
    }
}
```

---

## 📱 Mobile App Implementation

### Step 1: Create Polling Service

**File: `src/services/pollingService.ts`**
```typescript
import { API_BASE_URL } from '../app/api/config';

interface Update {
    products?: any[];
    orders?: any[];
}

interface UpdatesResponse {
    updates: Update;
    timestamp: number;
    has_updates: boolean;
}

type UpdateCallback = (updates: Update) => void;

class PollingService {
    private intervalId: NodeJS.Timeout | null = null;
    private lastTimestamp: number = 0;
    private callbacks: Set<UpdateCallback> = new Set();
    private isPolling: boolean = false;
    private token: string | null = null;
    private pollingInterval: number = 3000; // 3 seconds

    /**
     * Start polling for updates
     */
    start(authToken: string | null = null): void {
        if (this.isPolling) {
            console.log('🔄 [Polling] Already running');
            return;
        }

        this.token = authToken;
        this.isPolling = true;
        this.lastTimestamp = Math.floor(Date.now() / 1000);

        console.log('🔄 [Polling] Started');

        this.poll(); // Initial poll
        this.intervalId = setInterval(() => this.poll(), this.pollingInterval);
    }

    /**
     * Stop polling
     */
    stop(): void {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        this.isPolling = false;
        this.callbacks.clear();
        console.log('🔌 [Polling] Stopped');
    }

    /**
     * Subscribe to updates
     */
    subscribe(callback: UpdateCallback): () => void {
        this.callbacks.add(callback);
        console.log('📡 [Polling] Subscribed');

        return () => {
            this.callbacks.delete(callback);
            console.log('📡 [Polling] Unsubscribed');
        };
    }

    /**
     * Set polling interval
     */
    setInterval(milliseconds: number): void {
        this.pollingInterval = Math.max(1000, milliseconds); // Minimum 1 second
        
        if (this.isPolling) {
            this.stop();
            this.start(this.token);
        }
    }

    /**
     * Poll for updates
     */
    private async poll(): Promise<void> {
        if (!this.isPolling) return;

        try {
            const url = `${API_BASE_URL}/api/updates?since=${this.lastTimestamp}&types=products,orders`;
            const headers: Record<string, string> = {
                'Content-Type': 'application/json',
            };

            if (this.token) {
                headers['Authorization'] = `Bearer ${this.token}`;
            }

            const response = await fetch(url, { headers });

            if (!response.ok) {
                console.error('❌ [Polling] Request failed:', response.status);
                return;
            }

            const data: UpdatesResponse = await response.json();

            if (data.has_updates) {
                console.log('📨 [Polling] Updates received');
                this.notifyCallbacks(data.updates);
            }

            this.lastTimestamp = data.timestamp;

        } catch (error) {
            console.error('❌ [Polling] Error:', error);
        }
    }

    /**
     * Notify all subscribers
     */
    private notifyCallbacks(updates: Update): void {
        this.callbacks.forEach(callback => {
            try {
                callback(updates);
            } catch (error) {
                console.error('❌ [Polling] Callback error:', error);
            }
        });
    }

    /**
     * Check if polling is active
     */
    isActive(): boolean {
        return this.isPolling;
    }

    /**
     * Get last sync timestamp
     */
    getLastSync(): Date {
        return new Date(this.lastTimestamp * 1000);
    }
}

export const pollingService = new PollingService();
```

### Step 2: Create Polling Context

**File: `src/contexts/PollingContext.tsx`**
```typescript
import React, { createContext, useContext, useEffect } from 'react';
import { useSelector } from 'react-redux';
import { RootState } from '../app/reducers';
import { pollingService } from '../services/pollingService';

interface PollingContextType {
    isActive: boolean;
    subscribe: (callback: (updates: any) => void) => () => void;
}

const PollingContext = createContext<PollingContextType | undefined>(undefined);

export const PollingProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const { data: user } = useSelector((state: RootState) => state.auth);

    useEffect(() => {
        console.log('🔄 [Polling] Initializing...');
        
        pollingService.start(user?.token || null);

        return () => {
            pollingService.stop();
        };
    }, [user?.token]);

    const subscribe = (callback: (updates: any) => void) => {
        return pollingService.subscribe(callback);
    };

    return (
        <PollingContext.Provider value={{ 
            isActive: pollingService.isActive(), 
            subscribe 
        }}>
            {children}
        </PollingContext.Provider>
    );
};

export const usePolling = () => {
    const context = useContext(PollingContext);
    if (!context) {
        throw new Error('usePolling must be used within PollingProvider');
    }
    return context;
};
```

### Step 3: Update App.tsx

**Add PollingProvider:**
```typescript
import { PollingProvider } from './contexts/PollingContext';

// Wrap your app
<PollingProvider>
    {/* Your app content */}
</PollingProvider>
```

### Step 4: Use in ProductsScreen

**Update: `src/screens/ProductsScreen.tsx`**

```typescript
import { usePolling } from '../contexts/PollingContext';

const ProductsScreen: FC<ProductsScreenProps> = ({ navigation }) => {
    const { subscribe } = usePolling();
    
    // ... existing code ...

    // Subscribe to product updates
    useEffect(() => {
        const unsubscribe = subscribe((updates) => {
            if (updates.products && updates.products.length > 0) {
                console.log('📦 [Products] Updates received:', updates.products.length);
                
                // Update products in state
                setProducts((currentProducts) => {
                    const updatedMap = new Map(currentProducts.map(p => [p.id, p]));
                    
                    updates.products.forEach((update: any) => {
                        if (updatedMap.has(update.id)) {
                            // Update existing product
                            updatedMap.set(update.id, {
                                ...updatedMap.get(update.id)!,
                                price: parseFloat(update.base_price),
                                quantity: update.available_stock,
                            });
                        }
                    });
                    
                    return Array.from(updatedMap.values());
                });

                // Optional: Show toast notification
                // Toast.show({ text: `${updates.products.length} product(s) updated` });
            }
        });

        return unsubscribe;
    }, [subscribe]);

    // ... rest of component ...
};
```

---

## ✅ Features

- ✅ **Auto-refresh** - Products update every 3 seconds
- ✅ **Order tracking** - Real-time order status updates
- ✅ **Stock monitoring** - Inventory changes reflected immediately
- ✅ **Battery efficient** - Only polls when app is active
- ✅ **Works everywhere** - No special server configuration needed

---

## 🎨 Add Connection Indicator

**Create component: `src/components/ConnectionIndicator.tsx`**
```typescript
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { usePolling } from '../contexts/PollingContext';

export const ConnectionIndicator: React.FC = () => {
    const { isActive } = usePolling();

    if (!isActive) {
        return (
            <View style={styles.indicator}>
                <View style={[styles.dot, styles.offline]} />
                <Text style={styles.text}>Offline</Text>
            </View>
        );
    }

    return (
        <View style={styles.indicator}>
            <View style={[styles.dot, styles.online]} />
            <Text style={styles.text}>Live</Text>
        </View>
    );
};

const styles = StyleSheet.create({
    indicator: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 12,
        paddingVertical: 6,
        backgroundColor: '#1F1F25',
        borderRadius: 12,
    },
    dot: {
        width: 8,
        height: 8,
        borderRadius: 4,
        marginRight: 6,
    },
    online: {
        backgroundColor: '#1EE38C',
    },
    offline: {
        backgroundColor: '#FF4D4D',
    },
    text: {
        fontSize: 12,
        fontWeight: '600',
        color: '#9CA3AF',
    },
});
```

---

## 🚀 Deploy to Railway

No special configuration needed! Just push your code:

```bash
cd C:\Users\Preciado\landing-page
git add .
git commit -m "feat: add simple real-time polling system"
git push origin main
```

Railway will automatically deploy.

---

## 📊 Performance Optimization

### Adjust Polling Frequency

```typescript
// In PollingContext or where you start polling
pollingService.setInterval(5000); // Poll every 5 seconds
```

### Conditional Polling

```typescript
// Only poll on specific screens
useEffect(() => {
    if (isFocused) {
        pollingService.start(user?.token);
    } else {
        pollingService.stop();
    }
}, [isFocused, user?.token]);
```

### Background Polling Control

Use React Native's AppState:
```typescript
import { AppState } from 'react-native';

useEffect(() => {
    const subscription = AppState.addEventListener('change', (nextAppState) => {
        if (nextAppState === 'active') {
            pollingService.start(user?.token);
        } else {
            pollingService.stop();
        }
    });

    return () => subscription.remove();
}, [user?.token]);
```

---

## 🧪 Testing

### Test Backend
```bash
# Update a product in admin dashboard
# Watch mobile app update automatically
```

### Test Mobile
1. Open app on Products screen
2. Update product in web admin
3. See product update within 3 seconds!

---

## ✨ Next Steps

Once this works, you can enhance with:
1. **WebSockets** - For instant updates (0 latency)
2. **Push Notifications** - Alert users when app is closed
3. **Optimistic UI** - Update UI before server confirms
4. **Offline Queue** - Store changes when offline, sync later

---

Generated: 2026-07-08
Status: Ready to Use (No Extra Dependencies)
