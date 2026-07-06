<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class CategoryController extends AbstractController
{
    private function resolveRoute(Request $request, string $adminRoute, string $staffRoute): string
    {
        $currentRoute = (string) $request->attributes->get('_route', '');

        return str_starts_with($currentRoute, 'staff_categories') ? $staffRoute : $adminRoute;
    }

    private function redirectAfterWrite(Request $request, string $adminRoute, string $staffRoute, array $params = []): Response
    {
        return $this->redirectToRoute($this->resolveRoute($request, $adminRoute, $staffRoute), $params);
    }

    #[Route('/admin/categories', name: 'admin_categories')]
    #[Route('/staff/categories', name: 'staff_categories')]
    public function index(Request $request, CategoryRepository $categoryRepository): Response
    {
        return $this->render('admin/categories/index.html.twig', [
            'categories' => $categoryRepository->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']),
            'categories_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_categories') ? 'staff_categories' : 'admin_categories',
        ]);
    }

    #[Route('/admin/categories/new', name: 'admin_categories_new')]
    #[Route('/staff/categories/new', name: 'staff_categories_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $category = new Category();

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));

            if ($name === '') {
                $this->addFlash('error', 'Category name is required.');
                return $this->redirectAfterWrite($request, 'admin_categories_new', 'staff_categories_new');
            }

            $category->setName($name);
            $category->setSlug((string) $slugger->slug($name)->lower());
            $category->setDescription($request->request->get('description'));
            $category->setSortOrder((int) $request->request->get('sort_order', 0));
            $category->setIsActive($request->request->getBoolean('is_active', true));

            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'Category created successfully.');
            return $this->redirectAfterWrite($request, 'admin_categories', 'staff_categories');
        }

        return $this->render('admin/categories/form.html.twig', [
            'category' => null,
            'categories_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_categories') ? 'staff_categories' : 'admin_categories',
        ]);
    }

    #[Route('/admin/categories/{id}/edit', name: 'admin_categories_edit', requirements: ['id' => '\\d+'])]
    #[Route('/staff/categories/{id}/edit', name: 'staff_categories_edit', requirements: ['id' => '\\d+'])]
    public function edit(
        int $id,
        Request $request,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $category = $categoryRepository->find($id);
        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));

            if ($name === '') {
                $this->addFlash('error', 'Category name is required.');
                return $this->redirectAfterWrite($request, 'admin_categories_edit', 'staff_categories_edit', ['id' => $id]);
            }

            $category->setName($name);
            $category->setSlug((string) $slugger->slug($name)->lower());
            $category->setDescription($request->request->get('description'));
            $category->setSortOrder((int) $request->request->get('sort_order', 0));
            $category->setIsActive($request->request->getBoolean('is_active', true));

            $em->flush();

            $this->addFlash('success', 'Category updated successfully.');
            return $this->redirectAfterWrite($request, 'admin_categories', 'staff_categories');
        }

        return $this->render('admin/categories/form.html.twig', [
            'category' => $category,
            'categories_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_categories') ? 'staff_categories' : 'admin_categories',
        ]);
    }

    #[Route('/admin/categories/{id}/toggle', name: 'admin_categories_toggle', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[Route('/staff/categories/{id}/toggle', name: 'staff_categories_toggle', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function toggle(int $id, Request $request, CategoryRepository $categoryRepository, EntityManagerInterface $em): Response
    {
        $category = $categoryRepository->find($id);
        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }

        $category->setIsActive(!$category->isActive());
        $em->flush();

        $this->addFlash('success', $category->isActive() ? 'Category activated.' : 'Category deactivated.');

        return $this->redirectAfterWrite($request, 'admin_categories', 'staff_categories');
    }

    #[Route('/admin/categories/{id}/delete', name: 'admin_categories_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[Route('/staff/categories/{id}/delete', name: 'staff_categories_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(int $id, Request $request, CategoryRepository $categoryRepository, EntityManagerInterface $em): Response
    {
        $category = $categoryRepository->find($id);
        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }

        if ($category->getProducts()->count() > 0) {
            $this->addFlash('error', 'Cannot delete category with existing products. Reassign products first.');
            return $this->redirectAfterWrite($request, 'admin_categories', 'staff_categories');
        }

        $em->remove($category);
        $em->flush();

        $this->addFlash('success', 'Category deleted successfully.');
        return $this->redirectAfterWrite($request, 'admin_categories', 'staff_categories');
    }
}
