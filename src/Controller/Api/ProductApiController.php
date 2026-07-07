<?php

namespace App\Controller\Api;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ProductApiController extends AbstractController
{
    /**
     * Get all categories
     * GET /api/categories
     */
    #[Route('/categories', name: 'api_categories', methods: ['GET'])]
    public function getCategories(CategoryRepository $categoryRepository): JsonResponse
    {
        $categories = $categoryRepository->findActive();

        $data = array_map(fn($cat) => [
            'id' => $cat->getId(),
            'name' => $cat->getName(),
            'slug' => $cat->getSlug(),
            'description' => $cat->getDescription(),
            'image' => $cat->getImage(),
            'sort_order' => $cat->getSortOrder(),
        ], $categories);

        return $this->json($data);
    }

    /**
     * Get products (with optional filtering)
     * GET /api/products?category_id=1&search=mint&limit=20&offset=0
     */
    #[Route('/products', name: 'api_products', methods: ['GET'])]
    public function getProducts(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository
    ): JsonResponse {
        $categoryId = $request->query->getInt('category_id');
        $search = $request->query->getString('search');
        $limit = $request->query->getInt('limit', 20);
        $offset = $request->query->getInt('offset', 0);

        $qb = $productRepository->createQueryBuilder('p')
            ->where('p.isActive = true');

        if ($categoryId) {
            $category = $categoryRepository->find($categoryId);
            if ($category) {
                $qb->andWhere('p.category = :category')
                   ->setParameter('category', $category);
            }
        }

        if ($search) {
            $qb->andWhere('(p.name LIKE :search OR p.description LIKE :search)')
               ->setParameter('search', "%{$search}%");
        }

        $total = count($qb->getQuery()->getResult());

        $products = $qb
            ->orderBy('p.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(fn($product) => $this->serializeProduct($product), $products);

        return $this->json([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'products' => $data,
        ]);
    }

    /**
     * Get single product details with variants
     * GET /api/products/{id}
     */
    #[Route('/products/{id}', name: 'api_product_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getProduct(int $id, ProductRepository $productRepository): JsonResponse
    {
        $product = $productRepository->find($id);

        if (!$product || !$product->isActive()) {
            return $this->json(
                ['error' => 'Product not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json($this->serializeProductWithVariants($product));
    }

    /**
     * Get product variants
     * GET /api/products/{id}/variants
     */
    #[Route('/products/{id}/variants', name: 'api_product_variants', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getProductVariants(int $id, ProductRepository $productRepository): JsonResponse
    {
        $product = $productRepository->find($id);

        if (!$product) {
            return $this->json(
                ['error' => 'Product not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $variants = $product->getVariants()->filter(fn($v) => $v->isActive());

        $data = array_map(fn($variant) => $this->serializeVariant($variant), $variants->toArray());

        return $this->json([
            'product_id' => $product->getId(),
            'product_name' => $product->getName(),
            'base_price' => $product->getBasePrice(),
            'variants' => $data,
        ]);
    }

    /**
     * Get all variants (for search/filtering)
     * GET /api/variants?search=mint&in_stock=true
     */
    #[Route('/variants', name: 'api_variants', methods: ['GET'])]
    public function getVariants(
        Request $request,
        ProductVariantRepository $variantRepository
    ): JsonResponse {
        $search = $request->query->getString('search');
        $inStock = $request->query->getBoolean('in_stock', false);

        $qb = $variantRepository->createQueryBuilder('v')
            ->leftJoin('v.product', 'p')
            ->where('p.isActive = true')
            ->andWhere('v.isActive = true');

        if ($search) {
            $qb->andWhere('(v.flavor LIKE :search OR p.name LIKE :search)')
               ->setParameter('search', "%{$search}%");
        }

        if ($inStock) {
            $qb->andWhere('v.stock > v.reservedStock');
        }

        $variants = $qb
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('v.flavor', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(fn($variant) => $this->serializeVariant($variant), $variants);

        return $this->json(['variants' => $data]);
    }

    private function serializeProduct($product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'description' => $product->getDescription(),
            'short_description' => $product->getShortDescription(),
            'base_price' => $product->getBasePrice(),
            'lowest_price' => $product->getLowestPrice(),
            'highest_price' => $product->getHighestPrice(),
            'main_image' => $product->getMainImage(),
            'brand' => $product->getBrand(),
            'requires_age_verification' => $product->requiresAgeVerification(),
            'is_active' => $product->isActive(),
            'category_id' => $product->getCategory()?->getId(),
            'category_name' => $product->getCategory()?->getName(),
            'variant_count' => $product->getVariants()->count(),
            'total_stock' => $product->getTotalStock(),
            'available_stock' => $product->getAvailableStock(),
        ];
    }

    private function serializeProductWithVariants($product): array
    {
        $data = $this->serializeProduct($product);
        
        $data['variants'] = array_map(
            fn($variant) => $this->serializeVariant($variant),
            $product->getVariants()->filter(fn($v) => $v->isActive())->toArray()
        );

        return $data;
    }

    private function serializeVariant($variant): array
    {
        return [
            'id' => $variant->getId(),
            'product_id' => $variant->getProduct()?->getId(),
            'product_name' => $variant->getProduct()?->getName(),
            'sku' => $variant->getSku(),
            'flavor' => $variant->getFlavor(),
            'nicotine_strength' => $variant->getNicotineStrength(),
            'nicotine_label' => $variant->getNicotineStrengthLabel(),
            'size' => $variant->getSize(),
            'price' => $variant->getPrice(),
            'price_modifier' => $variant->getPriceModifier(),
            'stock' => $variant->getStock(),
            'reserved_stock' => $variant->getReservedStock(),
            'available_stock' => $variant->getAvailableStock(),
            'in_stock' => $variant->isInStock(),
            'is_low_stock' => $variant->isLowStock(),
            'display_name' => $variant->getDisplayName(),
        ];
    }
}
