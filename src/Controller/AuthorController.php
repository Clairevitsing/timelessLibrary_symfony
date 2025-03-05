<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('api/authors', stateless: false)]
class AuthorController extends AbstractController
{
    #[Route('/', name: 'author_index', methods: ['GET'])]
    public function index(AuthorRepository $authorRepository): JsonResponse
    {
        $authors = $authorRepository->findAll();
        //dd($authors);
        return $this->json($authors, context: ['groups' => 'author:read']);
    }

    // #[Route('/{id}', name: 'author_read', methods: ['GET'])]
    // public function read(int $id, AuthorRepository $authorRepository): JsonResponse
    // {
    //     $author = $authorRepository->find($id);
    //     if (!$author) {
    //         throw $this->createNotFoundException('Author not found');
    //     }
    //     //dd($author);
    //     return $this->json($author, context: ['groups' => 'author:read']);
    // }

    #[Route('/{id}', name: 'author_read', methods: ['GET'])]
    public function read(string $id, AuthorRepository $authorRepository): JsonResponse
    {
        $authorId = filter_var($id, FILTER_VALIDATE_INT);

        if ($authorId === false) {
            return $this->json(['error' => 'Invalid author ID'], Response::HTTP_BAD_REQUEST);
        }

        $author = $authorRepository->find($authorId);

        if (!$author) {
            throw $this->createNotFoundException('Author not found');
        }

        return $this->json($author, context: ['groups' => 'author:read']);
    }

    #[Route('/new', name: 'author_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        BookRepository $bookRepository
    ): JsonResponse {

        $content = $request->getContent();
        if (empty($content)) {
            return new JsonResponse(['error' => 'No data provided'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Check that all required fields are present
        if (!isset($data['firstName'], $data['lastName'], $data['biography'], $data['birthDate'], $data['bookIds']) || !is_array($data['bookIds'])) {
            return $this->json(['error' => 'Missing or invalid required fields'], Response::HTTP_BAD_REQUEST);
        }

        // Validate birthDate
        $birthDate = \DateTime::createFromFormat('Y-m-d', $data['birthDate']);
        if (!$birthDate) {
            return $this->json(['error' => 'Invalid birthDate format'], Response::HTTP_BAD_REQUEST);
        }

        $author = new Author();
        $author->setFirstName($data['firstName']);
        $author->setLastName($data['lastName']);
        $author->setBiography($data['biography']);
        $author->setBirthDate($birthDate);

        // Find all books by their IDs
        $books = $bookRepository->findBy(['id' => $data['bookIds']]);
        if (count($books) !== count($data['bookIds'])) {
            return new JsonResponse(['error' => 'One or more books not found'], Response::HTTP_NOT_FOUND);
        }

        // Associate the author with the books and vice versa
        foreach ($books as $book) {
            $book->addAuthor($author);
            $author->addBook($book);
        }

        $entityManager->persist($author);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Author created successfully',
            'id' => $author->getId(),
            'books' => array_map(function ($book) {
                return [
                    'id' => $book->getId(),
                    'title' => $book->getTitle(),
                    'publicationDate' => $book->getPublicationDate()->format('Y-m-d')
                ];
            }, $books) 
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/edit', name: 'author_edit', methods: ['PUT'])]
    public function edit(
        int $id,
        Request $request,
        AuthorRepository $authorRepository,
        BookRepository $bookRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Retrieve the author to edit
        $author = $authorRepository->find($id);

        // Check if the author exists
        if (!$author instanceof Author) {
            return $this->json(['message' => 'Author not found'], Response::HTTP_NOT_FOUND);
        }

        // Retrieve the data sent from the request
        $data = json_decode($request->getContent(), true);

        //dd($data);

        // Update the author details
        $author->setFirstName($data['firstName'] ?? $author->getFirstName());
        $author->setLastName($data['lastName'] ?? $author->getLastName());
        $author->setBiography($data['biography'] ?? $author->getBiography());
        $author->setBirthDate(isset($data['birthDate']) ? new \DateTime($data['birthDate']) : $author->getBirthDate());


        $author->getBooks()->clear();

        // Retrieve 'bookIds' from the data
        $bookIds = $data['bookIds'];
        //dd($bookIds) ;

        // Iterate over each 'bookId' in the array
        foreach ($bookIds as $bookId) {
            $book = $bookRepository->find($bookId);
            if (!$book) {
                return new JsonResponse(['error' => 'Book not found: ' . $bookId], Response::HTTP_NOT_FOUND);
            }

            // Associate the author with the book and vice versa
            $book->addAuthor($author);
            $author->addBook($book);
        }

        $entityManager->persist($author);
        $entityManager->flush();

        // Return the updated author data with associated book IDs
        return $this->json($author, JsonResponse::HTTP_OK, [], ['groups' => 'author:read']);
    }


    #[Route('/{id}', name: 'author_delete', methods: ['DELETE'])]
    public function delete(int $id, AuthorRepository $authorRepository, EntityManagerInterface $entityManager): Response
    {
        // Retrieve the author to delete using the AuthorRepository
        $author = $authorRepository->find($id);

        // Check if the author exists
        if (!$author) {
            // If not, return a JSON response with a 404 Not Found status
            return new JsonResponse(['message' => 'Author not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            // Remove the specified author from the entity manager
            $entityManager->remove($author);
            // Commit the changes to the database
            $entityManager->flush();

            // Return a JSON response indicating success
            return new JsonResponse(['message' => 'Author deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            // In case of an error during the removal process
            return new JsonResponse(['message' => 'Failed to delete author', 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    #[Route('/search', name: 'api_search_authors', methods: ['GET'])]
    public function searchAuthors(
        Request $request,
        AuthorRepository $authorRepository
    ): JsonResponse {
        try {
            $firstName = $request->query->get('firstName');
            $lastName = $request->query->get('lastName');

            // Log incoming parameters
            $this->container->get('logger')->info('Search Parameters', [
                'firstName' => $firstName,
                'lastName' => $lastName
            ]);

            // Validate input parameters
            if (empty($firstName) && empty($lastName)) {
                return $this->json([
                    'error' => 'Please provide at least one search criterion'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Flexible search criteria
            $queryBuilder = $authorRepository->createQueryBuilder('a');

            if (!empty($firstName)) {
                $queryBuilder->andWhere('a.firstName LIKE :firstName')
                    ->setParameter('firstName', '%' . $firstName . '%');
            }

            if (!empty($lastName)) {
                $queryBuilder->andWhere('a.lastName LIKE :lastName')
                    ->setParameter('lastName', '%' . $lastName . '%');
            }

            $authors = $queryBuilder->getQuery()->getResult();

            // Log number of authors found
            $this->container->get('logger')->info('Authors found', [
                'count' => count($authors)
            ]);

            // If no authors found
            if (empty($authors)) {
                return $this->json([], Response::HTTP_OK);
            }

            return $this->json($authors, context: ['groups' => 'author:read']);
        } catch (\Exception $e) {
            // Log the full exception details
            $this->container->get('logger')->error('Exception in searchAuthors', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'error' => 'An unexpected error occurred',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
