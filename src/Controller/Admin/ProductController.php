<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/products')]
class ProductController extends AbstractController
{
    #[Route('', name: 'admin_products')]
    public function index(ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        $products = $productRepository->findAll();
        $categories = $categoryRepository->findActive();

        return $this->render('admin/products/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    #[Route('/new', name: 'admin_products_new')]
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
            $product->setMainImage($request->request->get('main_image') ?: null);
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
            return $this->redirectToRoute('admin_products');
        }

        return $this->render('admin/products/form.html.twig', [
            'categories' => $categories,
            'product' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_products_edit')]
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
            $product->setMainImage($request->request->get('main_image') ?: null);
            $product->setIsActive($request->request->getBoolean('is_active', true));
            $product->setRequiresAgeVerification($request->request->getBoolean('requires_age_verification', true));

            $category = $categoryRepository->find($request->request->get('category_id'));
            if ($category) {
                $product->setCategory($category);
            }

            $em->flush();

            $this->addFlash('success', 'Product updated successfully');
            return $this->redirectToRoute('admin_products');
        }

        return $this->render('admin/products/form.html.twig', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_products_delete', methods: ['POST'])]
    public function delete(int $id, ProductRepository $productRepository, EntityManagerInterface $em): Response
    {
        $product = $productRepository->find($id);
        if ($product) {
            $em->remove($product);
            $em->flush();
            $this->addFlash('success', 'Product deleted successfully');
        }

        return $this->redirectToRoute('admin_products');
    }

    #[Route('/{id}/toggle', name: 'admin_products_toggle', methods: ['POST'])]
    public function toggle(int $id, ProductRepository $productRepository, EntityManagerInterface $em): Response
    {
        $product = $productRepository->find($id);
        if ($product) {
            $product->setIsActive(!$product->isActive());
            $em->flush();
        }

        return $this->redirectToRoute('admin_products');
    }
}
