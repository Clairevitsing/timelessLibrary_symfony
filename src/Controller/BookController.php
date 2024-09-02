<?php

namespace App\Controller;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Category;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Repository\CategoryRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/api/books')]
class BookController extends AbstractController
{
    public function __construct(
        private BookRepository         $bookRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'book_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $books = $this->bookRepository->findAll();
        return $this->json($books, context: ['groups' => 'book:read']);
    }

    #[Route('/{id}', name: 'book_read', methods: ['GET'])]
    public function read(int $id): JsonResponse
    {
        $book = $this->bookRepository->find($id);
        if (!$book) {
            throw $this->createNotFoundException('Book not found');
        }
        return $this->json($book, context: ['groups' => 'book:read']);
    }

    #[Route('/new', name: 'book_create', methods: ['POST'])]
    public function create(
        Request $request,
        AuthorRepository $authorRepository,
        CategoryRepository $categoryRepository
    ): JsonResponse
    {
        $content = $request->getContent();
        if (empty($content)) {
            return new JsonResponse(['error' => 'No data provided'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Check that all required fields are present
        if (!isset($data['title'], $data['ISBN'], $data['publishedYear'], $data['description'], $data['image'], $data['available'], $data['categoryId'], $data['authorIds'])) {
            return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $book = new Book();
        $book->setTitle($data['title']);
        $book->setISBN($data['ISBN']);
        $publishedDate = DateTime::createFromFormat('Y-m-d', $data['publishedYear']);
        if ($publishedDate === false) {
            return $this->json(['error' => 'Invalid published year format'], Response::HTTP_BAD_REQUEST);
        }
        $book->setPublishedYear($publishedDate);
        $book->setDescription($data['description']);
        $book->setImage($data['image']);
        $book->setAvailable($data['available']);

        // Handle category
        $category = $categoryRepository->find($data['categoryId']);
        if (!$category) {
            return new JsonResponse(['error' => 'Category not found'], Response::HTTP_BAD_REQUEST);
        }
        $book->setCategory($category);

        // Handle authors
        foreach ($data['authorIds'] as $authorId) {
            $author = $authorRepository->find($authorId);
            if (!$author) {
                return new JsonResponse(['error' => 'Author not found: ' . $authorId], Response::HTTP_BAD_REQUEST);
            }
            $book->addAuthor($author);
            $author->addBook($book);
        }

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Book created successfully',
            'id' => $book->getId()
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'book_edit', methods: ['PUT'])]
    public function edit(
        int $id,
        Request $request,
        BookRepository $bookRepository,
        CategoryRepository $categoryRepository,
        AuthorRepository $authorRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        $content = $request->getContent();
        if (empty($content)) {
            return $this->json(['error' => 'No data provided'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Fetch the Book entity by ID
        $book = $bookRepository->find($id);
        if (!$book) {
            return new JsonResponse(['error' => 'Book not found'], Response::HTTP_NOT_FOUND);
        }

        // Update the Book details
        $book->setTitle($data['title'] ?? $book->getTitle());
        $book->setISBN($data['ISBN'] ?? $book->getISBN());
        if (isset($data['publishedYear'])) {
            $publishedDate = DateTime::createFromFormat('Y-m-d', $data['publishedYear']);
            if ($publishedDate === false) {
                return $this->json(['error' => 'Invalid published year format'], Response::HTTP_BAD_REQUEST);
            }
            $book->setPublishedYear($publishedDate);
        }
        $book->setDescription($data['description'] ?? $book->getDescription());
        $book->setImage($data['image'] ?? $book->getImage());
        $book->setAvailable($data['available'] ?? $book->getAvailable());

        // Handle Category
        if (isset($data['categoryId'])) {
            $category = $categoryRepository->find($data['categoryId']);
            if (!$category) {
                return new JsonResponse(['error' => 'Category not found'], Response::HTTP_BAD_REQUEST);
            }
            $book->setCategory($category);
        }

        // Handle Authors
        if (isset($data['authorIds']) && is_array($data['authorIds'])) {
            // Remove existing authors
            foreach ($book->getAuthors() as $existingAuthor) {
                $book->removeAuthor($existingAuthor);
            }

            foreach ($data['authorIds'] as $authorId) {
                $author = $authorRepository->find($authorId);
                if (!$author) {
                    return new JsonResponse(['error' => 'Author not found: ' . $authorId], Response::HTTP_BAD_REQUEST);
                }
                $book->addAuthor($author);
            }
        }

        $entityManager->persist($book);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Book updated successfully',
            'id' => $book->getId()
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'book_delete', methods: ['DELETE'])]
    public function delete(int $id, Book $book, BookRepository $bookRepository,EntityManagerInterface $entityManager): JsonResponse
    {
        // Find the book by id
        //$book = $bookRepository->find($id);

        //dd($book);

        // If book not found, return a 404 error
        //if (!$book) {
         //   return new JsonResponse(['error' => 'Book not found'], Response::HTTP_NOT_FOUND);
      // }

        // Remove the book
        $entityManager->remove($book);

        $entityManager->flush();

        // Return a success message
        return new JsonResponse(['message' => 'Book deleted successfully'] , Response::HTTP_OK);
    }
}

