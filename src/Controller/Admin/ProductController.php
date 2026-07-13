<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProductController extends AbstractController
{
    private function resolveProductsIndexRoute(Request $request): string
    {
        $currentRoute = (string) $request->attributes->get('_route', '');
        return str_starts_with($currentRoute, 'staff_products') ? 'staff_products' : 'admin_products';
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    #[Route('/admin/products', name: 'admin_products')]
    #[Route('/staff/products', name: 'staff_products')]
    public function index(ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        $currentRoute = (string) $this->container->get('request_stack')->getCurrentRequest()?->attributes->get('_route', '');
        $prefix = str_starts_with($currentRoute, 'staff_products') ? 'staff_products' : 'admin_products';

        return $this->render('admin/products/index.html.twig', [
            'products'               => $productRepository->findAll(),
            'categories'             => $categoryRepository->findActive(),
            'products_route_prefix'  => $prefix,
        ]);
    }

    // ─── New ──────────────────────────────────────────────────────────────────

    #[Route('/admin/products/new', name: 'admin_products_new')]
    #[Route('/staff/products/new', name: 'staff_products_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepository,
        SluggerInterface $slugger
    ): Response {
        $categories = $categoryRepository->findActive();
        $prefix = str_starts_with((string) $request->attributes->get('_route', ''), 'staff_products')
            ? 'staff_products' : 'admin_products';

        if ($request->isMethod('POST')) {
            try {
                $product = new Product();
                $this->hydrateProduct($product, $request, $categoryRepository, $slugger, $em, true);

                $em->persist($product);
                $em->flush();

                $this->addFlash('success', 'Product created successfully');
                return $this->redirectToRoute($this->resolveProductsIndexRoute($request));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error saving product: ' . $e->getMessage());
            }
        }

        return $this->render('admin/products/form.html.twig', [
            'categories'            => $categories,
            'product'               => null,
            'products_route_prefix' => $prefix,
        ]);
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    #[Route('/admin/products/{id}/edit', name: 'admin_products_edit')]
    #[Route('/staff/products/{id}/edit', name: 'staff_products_edit')]
    public function edit(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $product = $productRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        $categories = $categoryRepository->findActive();
        $prefix = str_starts_with((string) $request->attributes->get('_route', ''), 'staff_products')
            ? 'staff_products' : 'admin_products';

        if ($request->isMethod('POST')) {
            try {
                $this->hydrateProduct($product, $request, $categoryRepository, $slugger, $em, false);
                $em->flush();

                $this->addFlash('success', 'Product updated successfully');
                return $this->redirectToRoute($this->resolveProductsIndexRoute($request));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating product: ' . $e->getMessage());
            }
        }

        return $this->render('admin/products/form.html.twig', [
            'product'               => $product,
            'categories'            => $categories,
            'products_route_prefix' => $prefix,
        ]);
    }

    // ─── Shared hydration ────────────────────────────────────────────────────

    /**
     * Apply all POST fields to a Product (new or existing).
     * For edits, also replaces variants using raw SQL to sidestep FK issues.
     */
    private function hydrateProduct(
        Product $product,
        Request $request,
        CategoryRepository $categoryRepository,
        SluggerInterface $slugger,
        EntityManagerInterface $em,
        bool $isNew
    ): void {
        $name      = trim((string) $request->request->get('name', ''));
        $basePrice = trim((string) $request->request->get('base_price', '0'));
        $brand     = trim((string) $request->request->get('brand', ''));
        $sku       = trim((string) $request->request->get('sku', ''));
        $desc      = $request->request->get('description');
        $shortDesc = $request->request->get('short_description');

        if ($name === '') {
            throw new \RuntimeException('Product name is required.');
        }
        if (!is_numeric($basePrice) || (float) $basePrice < 0) {
            throw new \RuntimeException('Base price must be a valid number.');
        }

        // Handle SKU
        if ($isNew) {
            if ($sku === '') {
                // Auto-generate SKU if not provided
                $sku = 'PROD-' . strtoupper(substr(md5($name . time()), 0, 8));
            }
            $product->setSku($sku);
        }
        // For edits, SKU is readonly and not updated

        // Generate slug
        $baseSlug = (string) $slugger->slug($name)->lower();
        $slug     = $baseSlug;
        
        // Check if slug needs uniqueness suffix
        if ($isNew) {
            // For new products, add random suffix if needed
            $slug = $baseSlug . '-' . substr(uniqid(), -6);
        } else {
            // For edits, keep existing slug if name unchanged, or regenerate with ID
            if ($product->getName() !== $name) {
                $slug = $baseSlug . '-' . $product->getId();
            } else {
                $slug = $product->getSlug(); // Keep existing slug
            }
        }

        $product->setName($name);
        $product->setSlug($slug);
        $product->setDescription($desc);
        $product->setShortDescription($shortDesc ?: null);
        $product->setBasePrice(number_format((float) $basePrice, 2, '.', ''));
        $product->setBrand($brand ?: null);
        $product->setIsActive($request->request->getBoolean('is_active', true)); // Default to true
        $product->setRequiresAgeVerification($request->request->getBoolean('requires_age_verification', true)); // Default to true for safety

        // Image
        $uploaded = $this->handleMainImageUpload($request->files->get('main_image_file'));
        if ($uploaded) {
            $product->setMainImage($uploaded);
        } elseif ($isNew) {
            $product->setMainImage($request->request->get('main_image') ?: null);
        }
        // On edit: keep existing image if nothing new uploaded

        // Category
        $categoryId = $request->request->get('category_id');
        if ($categoryId) {
            $category = $categoryRepository->find($categoryId);
            if ($category) {
                $product->setCategory($category);
            }
        }

        // --- Variants ---
        $flavors   = (array) $request->request->all('variant_flavor');
        $nicotines = (array) $request->request->all('variant_nicotine');
        $stocks    = (array) $request->request->all('variant_stock');
        $prices    = (array) $request->request->all('variant_price');

        if (!$isNew && $product->getId()) {
            // Delete existing variants via raw SQL to avoid FK constraint on order_item.
            // The FK was altered to ON DELETE SET NULL by migration; this also works
            // if the migration hasn't run yet.
            $conn = $em->getConnection();
            $conn->executeStatement(
                'DELETE FROM product_variant WHERE product_id = ?',
                [$product->getId()]
            );
            // Clear Doctrine's identity map so it doesn't try to re-flush removed entities
            $em->clear(ProductVariant::class);
            // Re-fetch the product so variants collection is fresh
            $em->refresh($product);
        }

        $productSku = $product->getSku();
        if (!$productSku) {
            $productSku = 'PROD-' . uniqid();
        }
        
        foreach ($flavors as $i => $flavor) {
            $flavor = trim((string) $flavor);
            if ($flavor === '') {
                continue;
            }

            $variant = new ProductVariant();
            $variant->setFlavor($flavor);
            $variant->setNicotineStrength(trim((string) ($nicotines[$i] ?? '')) ?: null);
            $variant->setStock(max(0, (int) ($stocks[$i] ?? 0)));
            $variant->setPriceModifier(number_format((float) ($prices[$i] ?? 0), 2, '.', ''));
            
            // Generate unique SKU for variant
            $variantSku = strtoupper(substr($productSku, 0, 10))
                . '-' . strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $flavor), 0, 4))
                . '-' . strtoupper(substr(uniqid('', true), -4));
            
            $variant->setSku($variantSku);
            $product->addVariant($variant);
            $em->persist($variant);
        }
    }

    // ─── Image upload ─────────────────────────────────────────────────────────

    private function handleMainImageUpload(?UploadedFile $uploadedFile): ?string
    {
        if (!$uploadedFile) {
            return null;
        }

        $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $safeName = strtolower(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME));
        $safeName = preg_replace('/[^a-z0-9]+/i', '-', $safeName) ?: 'product';
        $fileName = $safeName . '-' . uniqid('', true) . '.' . ($uploadedFile->guessExtension() ?? 'jpg');
        $uploadedFile->move($targetDirectory, $fileName);

        return 'uploads/products/' . $fileName;
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    #[Route('/admin/products/{id}/delete', name: 'admin_products_delete', methods: ['POST'])]
    #[Route('/staff/products/{id}/delete', name: 'staff_products_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, ProductRepository $productRepository, EntityManagerInterface $em): Response
    {
        $product = $productRepository->find($id);
        if ($product) {
            // Null out order_item.variant_id references before deleting variants
            $conn = $em->getConnection();
            $conn->executeStatement(
                'UPDATE order_item oi
                 JOIN product_variant pv ON oi.variant_id = pv.id
                 SET oi.variant_id = NULL
                 WHERE pv.product_id = ?',
                [$id]
            );

            $em->remove($product);
            $em->flush();
            $this->addFlash('success', 'Product deleted successfully');
        }

        return $this->redirectToRoute($this->resolveProductsIndexRoute($request));
    }

    // ─── Toggle active ────────────────────────────────────────────────────────

    #[Route('/admin/products/{id}/toggle', name: 'admin_products_toggle', methods: ['POST'])]
    #[Route('/staff/products/{id}/toggle', name: 'staff_products_toggle', methods: ['POST'])]
    public function toggle(int $id, Request $request, ProductRepository $productRepository, EntityManagerInterface $em): Response
    {
        $product = $productRepository->find($id);
        if ($product) {
            $product->setIsActive(!$product->isActive());
            $em->flush();
        }

        return $this->redirectToRoute($this->resolveProductsIndexRoute($request));
    }

    // ─── Stock adjust ─────────────────────────────────────────────────────────

    #[Route('/admin/products/{id}/variants/{variantId}/stock', name: 'admin_products_variant_stock', methods: ['POST'])]
    #[Route('/staff/products/{id}/variants/{variantId}/stock', name: 'staff_products_variant_stock', methods: ['POST'])]
    public function adjustStock(
        int $id,
        int $variantId,
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $em
    ): Response {
        $product = $productRepository->find($id);
        if (!$product) {
            $this->addFlash('error', 'Product not found');
            return $this->redirectToRoute($this->resolveProductsIndexRoute($request));
        }

        $variant = null;
        foreach ($product->getVariants() as $v) {
            if ($v->getId() === $variantId) {
                $variant = $v;
                break;
            }
        }

        if (!$variant) {
            $this->addFlash('error', 'Variant not found');
            $prefix = str_starts_with((string) $request->attributes->get('_route', ''), 'staff_products')
                ? 'staff_products_edit' : 'admin_products_edit';
            return $this->redirectToRoute($prefix, ['id' => $id]);
        }

        $newStock = $request->request->get('new_stock');
        $action   = $request->request->get('action', 'add');
        $quantity = abs((int) $request->request->get('quantity', 0));

        if ($newStock !== null && $newStock !== '') {
            $variant->setStock(max(0, (int) $newStock));
        } elseif ($action === 'remove') {
            $variant->setStock(max(0, $variant->getStock() - $quantity));
        } else {
            $variant->addStock($quantity);
        }

        $em->flush();
        $this->addFlash('success', sprintf('Stock updated — now %d units.', $variant->getStock()));

        $prefix = str_starts_with((string) $request->attributes->get('_route', ''), 'staff_products')
            ? 'staff_products_edit' : 'admin_products_edit';
        return $this->redirectToRoute($prefix, ['id' => $id]);
    }

    // ─── Delete single variant ───────────────────────────────────────────────

    #[Route('/admin/products/{id}/variants/{variantId}/delete', name: 'admin_products_variant_delete', methods: ['POST'])]
    #[Route('/staff/products/{id}/variants/{variantId}/delete', name: 'staff_products_variant_delete', methods: ['POST'])]
    public function deleteVariant(
        int $id,
        int $variantId,
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $em
    ): Response {
        $product = $productRepository->find($id);
        if (!$product) {
            $this->addFlash('error', 'Product not found');
            return $this->redirectToRoute($this->resolveProductsIndexRoute($request));
        }

        $conn = $em->getConnection();
        // Null out any order_item references first
        $conn->executeStatement('UPDATE order_item SET variant_id = NULL WHERE variant_id = ?', [$variantId]);
        // Then delete
        $conn->executeStatement('DELETE FROM product_variant WHERE id = ? AND product_id = ?', [$variantId, $id]);

        $this->addFlash('success', 'Variant deleted.');
        
        $prefix = str_starts_with((string) $request->attributes->get('_route', ''), 'staff_products')
            ? 'staff_products_edit' : 'admin_products_edit';
        return $this->redirectToRoute($prefix, ['id' => $id]);
    }
}
