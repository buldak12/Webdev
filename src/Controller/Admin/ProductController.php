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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProductController extends AbstractController
{
    private function resolveProductsIndexRoute(Request $request): string
    {
        $currentRoute = (string) $request->attributes->get('_route', '');

        return str_starts_with($currentRoute, 'staff_products') ? 'staff_products' : 'admin_products';
    }

    #[Route('/admin/products', name: 'admin_products')]
    #[Route('/staff/products', name: 'staff_products')]
    public function index(ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        $currentRoute = (string) $this->container->get('request_stack')->getCurrentRequest()?->attributes->get('_route', '');
        $productsRoutePrefix = str_starts_with($currentRoute, 'staff_products') ? 'staff_products' : 'admin_products';

        $products = $productRepository->findAll();
        $categories = $categoryRepository->findActive();

        return $this->render('admin/products/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'products_route_prefix' => $productsRoutePrefix,
        ]);
    }

    #[Route('/admin/products/new', name: 'admin_products_new')]
    #[Route('/staff/products/new', name: 'staff_products_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepository,
        SluggerInterface $slugger
    ): Response {
        $categories = $categoryRepository->findActive();

        if ($request->isMethod('POST')) {
            $product = new Product();
            $product->setName($request->request->get('name'));
            $product->setSlug($slugger->slug($request->request->get('name'))->lower());
            $product->setDescription($request->request->get('description'));
            $product->setShortDescription($request->request->get('short_description'));
            $product->setBasePrice($request->request->get('base_price'));
            $product->setSku($request->request->get('sku'));
            $product->setBrand($request->request->get('brand'));
            $product->setMainImage($this->handleMainImageUpload($request->files->get('main_image_file')) ?? $request->request->get('main_image') ?: null);
            $product->setIsActive($request->request->getBoolean('is_active', true));
            $product->setRequiresAgeVerification($request->request->getBoolean('requires_age_verification', true));

            $category = $categoryRepository->find($request->request->get('category_id'));
            if ($category) {
                $product->setCategory($category);
            }

            // Handle variants
            $flavors = $request->request->all('variant_flavor') ?? [];
            $nicotines = $request->request->all('variant_nicotine') ?? [];
            $stocks = $request->request->all('variant_stock') ?? [];
            $prices = $request->request->all('variant_price') ?? [];

            foreach ($flavors as $i => $flavor) {
                if (!empty($flavor)) {
                    $variant = new ProductVariant();
                    $variant->setFlavor($flavor);
                    $variant->setNicotineStrength($nicotines[$i] ?? null);
                    $variant->setStock((int)($stocks[$i] ?? 0));
                    $variant->setPriceModifier($prices[$i] ?? '0.00');
                    $variant->setSku($product->getSku() . '-' . strtoupper(substr(md5($flavor . ($nicotines[$i] ?? '')), 0, 6)));
                    $product->addVariant($variant);
                }
            }

            $em->persist($product);
            $em->flush();

            $this->addFlash('success', 'Product created successfully');
            return $this->redirectToRoute($this->resolveProductsIndexRoute($request));
        }

        return $this->render('admin/products/form.html.twig', [
            'categories' => $categories,
            'product' => null,
            'products_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_products') ? 'staff_products' : 'admin_products',
        ]);
    }

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

        if ($request->isMethod('POST')) {
            $product->setName($request->request->get('name'));
            $product->setSlug($slugger->slug($request->request->get('name'))->lower());
            $product->setDescription($request->request->get('description'));
            $product->setShortDescription($request->request->get('short_description'));
            $product->setBasePrice($request->request->get('base_price'));
            $product->setBrand($request->request->get('brand'));
            $uploadedMainImage = $this->handleMainImageUpload($request->files->get('main_image_file'));
            $product->setMainImage($uploadedMainImage ?? $request->request->get('main_image') ?: $product->getMainImage());
            $product->setIsActive($request->request->getBoolean('is_active', true));
            $product->setRequiresAgeVerification($request->request->getBoolean('requires_age_verification', true));

            $category = $categoryRepository->find($request->request->get('category_id'));
            if ($category) {
                $product->setCategory($category);
            }

            $em->flush();

            $this->addFlash('success', 'Product updated successfully');
            return $this->redirectToRoute($this->resolveProductsIndexRoute($request));
        }

        return $this->render('admin/products/form.html.twig', [
            'product' => $product,
            'categories' => $categories,
            'products_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_products') ? 'staff_products' : 'admin_products',
        ]);
    }

    private function handleMainImageUpload(?UploadedFile $uploadedFile): ?string
    {
        if (!$uploadedFile) {
            return null;
        }

        if (!str_starts_with((string) $uploadedFile->getMimeType(), 'image/')) {
            throw new \InvalidArgumentException('Please upload a valid image file.');
        }

        $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $safeName = strtolower(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME));
        $safeName = preg_replace('/[^a-z0-9]+/i', '-', $safeName) ?: 'product-image';
        $fileName = $safeName . '-' . uniqid('', true) . '.' . $uploadedFile->guessExtension();

        $uploadedFile->move($targetDirectory, $fileName);

        return 'uploads/products/' . $fileName;
    }

    #[Route('/admin/products/{id}/delete', name: 'admin_products_delete', methods: ['POST'])]
    #[Route('/staff/products/{id}/delete', name: 'staff_products_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, ProductRepository $productRepository, EntityManagerInterface $em): Response
    {
        $product = $productRepository->find($id);
        if ($product) {
            $em->remove($product);
            $em->flush();
            $this->addFlash('success', 'Product deleted successfully');
        }

        return $this->redirectToRoute($this->resolveProductsIndexRoute($request));
    }

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
}
