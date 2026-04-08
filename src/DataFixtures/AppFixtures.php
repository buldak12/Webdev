<?php

namespace App\DataFixtures;

use App\Entity\Address;
use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\PromoCode;
use App\Entity\Shipment;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // Create Admin User
        $admin = new User();
        $admin->setEmail('admin@vapeshop.ph');
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setRoles([User::ROLE_ADMIN]);
        $admin->setAgeVerificationStatus(User::AGE_STATUS_VERIFIED);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // Create Staff User
        $staff = new User();
        $staff->setEmail('staff@vapeshop.ph');
        $staff->setFirstName('Staff');
        $staff->setLastName('User');
        $staff->setRoles([User::ROLE_STAFF]);
        $staff->setAgeVerificationStatus(User::AGE_STATUS_VERIFIED);
        $staff->setPassword($this->passwordHasher->hashPassword($staff, 'staff123'));
        $manager->persist($staff);

        // Create Test Customers
        $customers = [];
        $customerData = [
            ['Juan', 'Dela Cruz', 'juan@email.com', User::AGE_STATUS_VERIFIED, 450],
            ['Maria', 'Santos', 'maria@email.com', User::AGE_STATUS_PENDING, 0],
            ['Pedro', 'Reyes', 'pedro@email.com', User::AGE_STATUS_VERIFIED, 120],
            ['Lisa', 'Garcia', 'lisa@email.com', User::AGE_STATUS_VERIFIED, 890],
        ];

        foreach ($customerData as $data) {
            $customer = new User();
            $customer->setFirstName($data[0]);
            $customer->setLastName($data[1]);
            $customer->setEmail($data[2]);
            $customer->setPhone('+63 9' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(1000, 9999));
            $customer->setRoles([User::ROLE_CUSTOMER]);
            $customer->setAgeVerificationStatus($data[3]);
            $customer->setLoyaltyPoints($data[4]);
            $customer->setPassword($this->passwordHasher->hashPassword($customer, 'customer123'));
            $manager->persist($customer);
            $customers[] = $customer;

            // Create address for verified customers
            if ($data[3] === User::AGE_STATUS_VERIFIED) {
                $address = new Address();
                $address->setUser($customer);
                $address->setFullName($data[0] . ' ' . $data[1]);
                $address->setPhone($customer->getPhone());
                $address->setStreetAddress(rand(1, 999) . ' Sample Street');
                $address->setBarangay('Barangay ' . rand(1, 100));
                $address->setCity(['Makati', 'Quezon City', 'Cebu City', 'Davao City'][rand(0, 3)]);
                $address->setProvince(['Metro Manila', 'Metro Manila', 'Cebu', 'Davao del Sur'][rand(0, 3)]);
                $address->setRegion([Address::REGION_METRO_MANILA, Address::REGION_LUZON, Address::REGION_VISAYAS, Address::REGION_MINDANAO][rand(0, 3)]);
                $address->setPostalCode((string) rand(1000, 9999));
                $address->setIsDefaultShipping(true);
                $manager->persist($address);
            }
        }

        // Create Categories
        $categories = [];
        $categoryData = [
            ['E-Liquids', 'e-liquids', 'Premium vape juices in various flavors'],
            ['Salt Nic', 'salt-nic', 'High nicotine salt e-liquids'],
            ['Devices', 'devices', 'Vape mods, pods, and kits'],
            ['Accessories', 'accessories', 'Coils, tanks, batteries, and more'],
        ];

        foreach ($categoryData as $i => $data) {
            $category = new Category();
            $category->setName($data[0]);
            $category->setSlug($data[1]);
            $category->setDescription($data[2]);
            $category->setSortOrder($i);
            $manager->persist($category);
            $categories[$data[1]] = $category;
        }

        // Create Products with Variants
        $products = [];
        $productData = [
            [
                'name' => 'Lilac Ice',
                'slug' => 'lilac-ice',
                'category' => 'e-liquids',
                'price' => '350.00',
                'sku' => 'LIL-ICE',
                'brand' => 'CloudBrew',
                'description' => 'A refreshing blend of grape and menthol',
                'image' => 'images/lilac-prod-300x300.png',
                'variants' => [
                    ['Lilac Ice', '3mg', 50, '0.00'],
                    ['Lilac Ice', '6mg', 35, '0.00'],
                    ['Lilac Ice', '0mg', 20, '0.00'],
                ],
            ],
            [
                'name' => 'Mango Tango',
                'slug' => 'mango-tango',
                'category' => 'e-liquids',
                'price' => '380.00',
                'sku' => 'MNG-TNG',
                'brand' => 'TropicalVapes',
                'description' => 'Sweet Philippine mango with a tropical twist',
                'image' => 'images/mango-tago.webp',
                'variants' => [
                    ['Mango Tango', '3mg', 12, '0.00'],
                    ['Mango Tango', '6mg', 45, '0.00'],
                ],
            ],
            [
                'name' => 'Blue Razz',
                'slug' => 'blue-razz',
                'category' => 'e-liquids',
                'price' => '350.00',
                'sku' => 'BLU-RZZ',
                'brand' => 'CloudBrew',
                'description' => 'Blue raspberry candy flavor',
                'image' => 'images/BLUE-RAZZ-ICE.webp',
                'variants' => [
                    ['Blue Razz', '0mg', 8, '0.00'],
                    ['Blue Razz', '3mg', 30, '0.00'],
                ],
            ],
            [
                'name' => 'Strawberry Milk',
                'slug' => 'strawberry-milk',
                'category' => 'e-liquids',
                'price' => '400.00',
                'sku' => 'STR-MLK',
                'brand' => 'CreamyCloud',
                'description' => 'Creamy strawberry milkshake',
                'image' => 'images/strawberry milk.webp',
                'variants' => [
                    ['Strawberry Milk', '3mg', 25, '0.00'],
                    ['Strawberry Milk', '6mg', 18, '0.00'],
                ],
            ],
            [
                'name' => 'Iced Coffee',
                'slug' => 'iced-coffee',
                'category' => 'salt-nic',
                'price' => '450.00',
                'sku' => 'ICD-COF',
                'brand' => 'SaltMasters',
                'description' => 'Rich coffee with a cool finish',
                'image' => 'images/Salt Nic.jpg',
                'variants' => [
                    ['Iced Coffee', '25mg', 40, '0.00'],
                    ['Iced Coffee', '50mg', 35, '50.00'],
                ],
            ],
        ];

        foreach ($productData as $data) {
            $product = new Product();
            $product->setName($data['name']);
            $product->setSlug($data['slug']);
            $product->setCategory($categories[$data['category']]);
            $product->setBasePrice($data['price']);
            $product->setSku($data['sku']);
            $product->setBrand($data['brand']);
            $product->setDescription($data['description']);
            $product->setMainImage($data['image']);
            $manager->persist($product);
            $products[$data['slug']] = $product;

            foreach ($data['variants'] as $variantData) {
                $variant = new ProductVariant();
                $variant->setProduct($product);
                $variant->setFlavor($variantData[0]);
                $variant->setNicotineStrength($variantData[1]);
                $variant->setStock($variantData[2]);
                $variant->setPriceModifier($variantData[3]);
                $variant->setSku($data['sku'] . '-' . strtoupper(str_replace('mg', '', $variantData[1])));
                $variant->setLowStockThreshold(15);
                $manager->persist($variant);
            }
        }

        // Create Promo Codes
        $promoData = [
            ['WELCOME10', 'percentage', '10', 'Welcome discount for new customers', null, '500.00'],
            ['SUMMER25', 'percentage', '25', 'Summer sale 25% off', '200.00', '1000.00'],
            ['FLAT100', 'fixed', '100', '₱100 off your order', null, '800.00'],
            ['FREESHIP', 'free_shipping', '0', 'Free shipping on all orders', null, null],
        ];

        foreach ($promoData as $data) {
            $promo = new PromoCode();
            $promo->setCode($data[0]);
            $promo->setType($data[1]);
            $promo->setValue($data[2]);
            $promo->setDescription($data[3]);
            $promo->setMaximumDiscount($data[4]);
            $promo->setMinimumOrderAmount($data[5]);
            $promo->setUsageLimit(100);
            $promo->setExpiresAt((new \DateTime())->modify('+30 days'));
            $manager->persist($promo);
        }

        $manager->flush();

        // Create Sample Orders (need IDs from flush)
        $this->createSampleOrders($manager, $customers, $products);
    }

    private function createSampleOrders(ObjectManager $manager, array $customers, array $products): void
    {
        // Get variants for orders
        $variantRepo = $manager->getRepository(ProductVariant::class);
        $variants = $variantRepo->findAll();

        if (empty($variants) || empty($customers)) {
            return;
        }

        $addressRepo = $manager->getRepository(Address::class);
        $statuses = [
            Order::STATUS_PAID,
            Order::STATUS_PROCESSING,
            Order::STATUS_READY_TO_SHIP,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
        ];

        for ($i = 0; $i < 8; $i++) {
            $customer = $customers[array_rand($customers)];
            
            // Only create orders for verified customers
            if ($customer->getAgeVerificationStatus() !== User::AGE_STATUS_VERIFIED) {
                continue;
            }

            $address = $addressRepo->findOneBy(['user' => $customer]);
            if (!$address) {
                continue;
            }

            $order = new Order();
            $order->setUser($customer);
            $order->setShippingAddress($address);
            $order->setBillingAddress($address);
            $order->setStatus($statuses[array_rand($statuses)]);
            
            // Add 1-3 random items
            $numItems = rand(1, 3);
            $subtotal = '0.00';
            
            for ($j = 0; $j < $numItems; $j++) {
                $variant = $variants[array_rand($variants)];
                $qty = rand(1, 3);
                
                $item = new OrderItem();
                $item->setVariant($variant);
                $item->setQuantity($qty);
                $item->setUnitPrice($variant->getPrice());
                $item->setProductName($variant->getProduct()->getName());
                $item->setVariantSku($variant->getSku());
                $item->setVariantAttributes($variant->getVariantAttributes());
                $order->addItem($item);
                
                $subtotal = bcadd($subtotal, bcmul($variant->getPrice(), (string)$qty, 2), 2);
            }

            $order->setSubtotal($subtotal);
            $order->setTax(bcmul($subtotal, '0.12', 2));
            $order->setShippingCost('100.00');
            $order->calculateTotal();

            if ($order->getStatus() !== Order::STATUS_PAID) {
                $order->setPaidAt((new \DateTime())->modify('-' . rand(1, 7) . ' days'));
            }

            $manager->persist($order);

            // Create payment
            $payment = new Payment();
            $payment->setOrder($order);
            $payment->setGateway([Payment::GATEWAY_GCASH, Payment::GATEWAY_MAYA, Payment::GATEWAY_COD][rand(0, 2)]);
            $payment->setAmount($order->getTotal());
            $payment->setStatus(Payment::STATUS_COMPLETED);
            $manager->persist($payment);

            // Create shipment
            $shipment = new Shipment();
            $shipment->setOrder($order);
            $shipment->setShippingCost($order->getShippingCost());
            
            if (in_array($order->getStatus(), [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])) {
                $shipment->setCourier([Shipment::COURIER_LBC, Shipment::COURIER_JT, Shipment::COURIER_NINJA_VAN][rand(0, 2)]);
                $shipment->setTrackingNumber('TRK' . rand(100000000, 999999999));
                $shipment->setStatus(Shipment::STATUS_IN_TRANSIT);
            } else {
                $shipment->setStatus(Shipment::STATUS_PENDING);
            }
            
            $manager->persist($shipment);
        }

        $manager->flush();
    }
}
