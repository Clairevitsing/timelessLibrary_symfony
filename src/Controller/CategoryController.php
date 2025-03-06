<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/categories')]
class CategoryController extends AbstractController
{
    #[Route('/', name: 'category_list', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAll();
        return $this->json(
            $categories,
            context: ['groups' => 'category:read']
        );
    }

    #[Route('/{id}', name: 'category_read', methods: ['GET'])]
    public function read(int $id, CategoryRepository $categoryRepository): Response
    {
        $category = $categoryRepository->find($id);
        return $this->json(
            $category,
            context: ['groups' => 'category:read']
        );
    }

    #[Route('/new', name: 'category_create', methods: ['POST'])]
    public function create( Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $content = $request->getContent();
        if (empty($content)) {
            return $this->json(['error' => 'No data provided'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Check that all required fields are present
        if (!isset($data['name'], $data['description'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $category = new Category();
        $category->setName($data['name']);
        $category->setDescription($data['description']);


        $entityManager->persist($category);
        $entityManager->flush();

        return $this->json([
            'message' => 'Category created successfully',
            'id' => $category->getId()
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}/edit', name: 'category_edit', methods: ['PUT'])]
    public function edit( int $id,
                          Request $request,
                          CategoryRepository $categoryRepository,
                          EntityManagerInterface $entityManager
    ): JsonResponse
    {
        $content = $request->getContent();
        if (empty($content)) {
            return $this->json(['error' => 'No data provided'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Fetch the Category entity by ID
        $category = $categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => 'Category not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $category->setName($data['name'])?? $category->getName();
        $category->setDescription($data['description'])?? $category->getDescription();

        $entityManager->flush();

        return $this->json([
            'category' => $category,
            'message' => 'Category updated successfully'
        ], JsonResponse::HTTP_OK, [], ['groups' => 'category:read']);
    }

    #[Route('/{id}', name: 'category_delete', methods: ['DELETE'])]
    public function delete(int $id, CategoryRepository $categoryRepository, EntityManagerInterface $entityManager): Response
    {
        // Find the category by id
        $category = $categoryRepository->find($id);

        //dd($category);

        // If user not found, return a 404 error
        if (!$category) {
            return new JsonResponse(['error' => 'Category not found'], JsonResponse::HTTP_NOT_FOUND);
        }
        try {
            $entityManager->remove($category);
            $entityManager->flush();
        } catch (\Exception $e) {
            return $this->json(['error' => 'Unable to delete category', 'message' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['message' => 'Category deleted successfully'], JsonResponse::HTTP_OK);
    }

    public function getBooksByCategory(
        int $categoryId,
        BookRepository $bookRepository,
        CategoryRepository $categoryRepository
    ): JsonResponse {
        // Use the custom repository method
        $categoryWithBooks = $categoryRepository->findWithBooks($categoryId);

        if (!$categoryWithBooks) {
            return new JsonResponse(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        // Access the books from the result
        $books = $categoryWithBooks->getBooks(); 

        return $this->json([
            'books' => $books
        ], Response::HTTP_OK, [], ['groups' => 'book:read']);
    }
}
