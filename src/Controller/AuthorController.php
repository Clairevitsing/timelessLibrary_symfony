<?php

namespace App\Controller;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Category;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('api/authors')]
class AuthorController extends AbstractController
{
    public function __construct(
        private AuthorRepository $authorRepository,
        private EntityManagerInterface $entityManager
    ){
    }
    #[Route('/', name: 'author_index', methods: ['GET'])]
    public function index(AuthorRepository $authorRepository): JsonResponse
    {
        $authors = $authorRepository->findAll();
        //dd($authors);
        return $this->json($authors, context: ['groups' => 'author:read']);
    }

    #[Route('/{id}', name: 'author_read', methods: ['GET'])]
    public function read(int $id, AuthorRepository $authorRepository): JsonResponse
    {
        $author = $authorRepository->findOneById($id);
        if (!$author) {
            throw $this->createNotFoundException('Author not found');
        }
        //dd($author);
        return $this->json($author, context: ['groups' => 'author:read']);
    }

    #[Route('/new', name: 'author_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $content = $request->getContent();
        if (empty($content)) {
            return $this->json(['error' => 'No data provided'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Check that all required fields are present
        if (!isset($data['firstName'], $data['lastName'], $data['biography'], $data['birthDate'], $data['books'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $author = new Author();
        $author->setFirstName($data['firstName']);
        $author->setLastName($data['lastName']);
        $author->setBiography($data['biography']);
        $author->setBirthDate(new \DateTime($data['birthDate']));

        // Preload all categories
        $categoryRepository = $this->entityManager->getRepository(Category::class);
        $categories = $categoryRepository->findAll();
        $categoryMap = [];
        foreach ($categories as $category) {
            // Map category names to category objects
            $categoryMap[$category->getName()] = $category;
        }

        foreach ($data['books'] as $bookData) {
            if (!isset($bookData['title'], $bookData['category'])) {
                return $this->json(['error' => 'Book title and category are required'], Response::HTTP_BAD_REQUEST);
            }

            // Check if the book already exists
            $book = $this->entityManager->getRepository(Book::class)->findOneBy(['title' => $bookData['title']]);

            if (!$book) {
                $book = new Book();
                $book->setTitle($bookData['title']);
                $book->setISBN($bookData['ISBN'] ?? null);

                // Use DateTime::createFromFormat to handle year only
                if (isset($bookData['publishedYear'])) {
                    $publishedDate = \DateTime::createFromFormat('Y', $bookData['publishedYear']);
                    if ($publishedDate === false) {
                        return $this->json(['error' => 'Invalid published year format'], Response::HTTP_BAD_REQUEST);
                    }
                    $book->setPublishedYear($publishedDate);
                }

                $book->setDescription($bookData['description'] ?? null);
                $book->setImage($bookData['image'] ?? null);


                // Check and get or create Category
                if (isset($bookData['category']) && is_array($bookData['category'])) {
                    $categoryName = $bookData['category']['name'] ?? null;
                    if ($categoryName && !isset($categoryMap[$categoryName])) {
                        $category = new Category();
                        $category->setName($categoryName);
                        $category->setDescription($bookData['category']['description'] ?? null);
                        // Persist new category
                        $this->entityManager->persist($category);
                        // Add to category map
                        $categoryMap[$categoryName] = $category;
                    }
                    // Set the book's category
                    if ($categoryName) {
                        $book->setCategory($categoryMap[$categoryName] ?? null);
                    }
                }

                $book->setAvailable($bookData['available'] ?? false);

                $this->entityManager->persist($book);
            }

            // Add author to the book and book to the author
            $book->addAuthor($author);
            $author->addBook($book);
        }

        $this->entityManager->persist($author);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Author created successfully',
            'id' => $author->getId()
        ], Response::HTTP_CREATED);
    }
    #[Route('/{id}', name: 'author_edit', methods: ['PUT'])]
    public function edit(int $id, Request $request, AuthorRepository $authorRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        // Retrieve the author to edit using the AuthorRepository
        $author = $authorRepository->find($id);

        // Check if the author exists
        if (!$author) {
            return $this->json(['message' => 'Author not found'], Response::HTTP_NOT_FOUND);
        }

        // Retrieve the data sent from the request
        $data = json_decode($request->getContent(), true);

        // Validate the JSON data
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['message' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }

        // Update the author details
        $author->setFirstName($data['firstName'] ?? $author->getFirstName());
        $author->setLastName($data['lastName'] ?? $author->getLastName());
        $author->setBiography($data['biography'] ?? $author->getBiography());
        $author->setBirthDate(isset($data['birthDate']) ? new \DateTime($data['birthDate']) : $author->getBirthDate());

        // Handle books
        if (isset($data['books'])) {
            $bookRepository = $entityManager->getRepository(Book::class);
            $categoryRepository = $entityManager->getRepository(Category::class);
            $existingBooks = $author->getBooks()->toArray();

            foreach ($data['books'] as $bookData) {
                if (!isset($bookData['title'])) {
                    return $this->json(['message' => 'Book title is required'], Response::HTTP_BAD_REQUEST);
                }

                $book = $bookRepository->findOneBy(['title' => $bookData['title']]) ?? new Book();
                $book->setTitle($bookData['title']);
                $book->setISBN($bookData['ISBN'] ?? $book->getISBN());
                $book->setDescription($bookData['description'] ?? $book->getDescription());
                $book->setImage($bookData['image'] ?? $book->getImage());
                $book->setAvailable($bookData['available'] ?? $book->isAvailable());

                if (isset($bookData['publishedYear'])) {
                    $publishedDate = new \DateTime();
                    $publishedDate->setDate((int)$bookData['publishedYear'], 1, 1);
                    $book->setPublishedYear($publishedDate);
                }

                $book->setDescription($bookData['description'] ?? null);
                $book->setImage($bookData['image'] ?? null);

                // Handle category
                if (isset($bookData['category'])) {
                    $category = $categoryRepository->findOneBy(['name' => $bookData['category']['name']]) ?? new Category();
                    $category->setName($bookData['category']['name']);
                    $category->setDescription($bookData['category']['description'] ?? $category->getDescription());
                    $entityManager->persist($category);
                    $book->setCategory($category);
                }
                $entityManager->persist($book);
                // Ensure the book is linked to the author
                if (!$author->getBooks()->contains($book)) {
                    $author->addBook($book);
                }
            }
            // Remove books that are no longer in the list
            foreach ($existingBooks as $existingBook) {
                if (!in_array($existingBook->getTitle(), array_column($data['books'], 'title'))) {
                    $author->removeBook($existingBook);
                }
            }
        }

        // Persist changes to the author
        $entityManager->persist($author);
        $entityManager->flush();

        // Return a JSON response indicating success
        return $this->json([
            'message' => 'Author updated successfully',
            'author' => [
                'id' => $author->getId(),
                'firstName' => $author->getFirstName(),
                'lastName' => $author->getLastName(),
                'biography' => $author->getBiography(),
                'birthDate' => $author->getBirthDate()->format('Y-m-d'),
                'books' => $author->getBooks()->map(function(Book $book) {
                    return [
                        'id' => $book->getId(),
                        'title' => $book->getTitle(),
                        'category' => $book->getCategory() ? [
                            'id' => $book->getCategory()->getId(),
                            'name' => $book->getCategory()->getName()
                        ] : null
                    ];
                })->toArray()
            ]
        ], Response::HTTP_OK);
    }
    #[Route('/{id}', name: 'author_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request, AuthorRepository $authorRepository, EntityManagerInterface $entityManager): Response
    {
        // Retrieve the author to delete using the AuthorRepository
        $author = $authorRepository->find($id);

        // Check if the animal exists
        if (!$author) {
            return new JsonResponse(['message' => 'Author not found'], Response::HTTP_NOT_FOUND);
        }

        // Use the repository's remove method to delete the animal
        $authorRepository->remove($author);

        // Return a JSON response indicating success
        return new JsonResponse(['message' => 'Animal is deleted successfully'], Response::HTTP_OK);
    }
}
