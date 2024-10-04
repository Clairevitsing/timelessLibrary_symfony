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

    #[Route('/{id}', name: 'author_read', methods: ['GET'])]
    public function read(int $id, AuthorRepository $authorRepository): JsonResponse
    {
        $author = $authorRepository->find($id);
        if (!$author) {
            throw $this->createNotFoundException('Author not found');
        }
        //dd($author);
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
        if (!isset($data['firstName'], $data['lastName'], $data['biography'], $data['birthDate'], $data['bookIds'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $author = new Author();
        $author->setFirstName($data['firstName']);
        $author->setLastName($data['lastName']);
        $author->setBiography($data['biography']);
        $author->setBirthDate(new \DateTime($data['birthDate']));

        // Associate existing books
        foreach ($data['bookIds'] as $bookId) {
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

        return new JsonResponse([
            'message' => 'Author created successfully',
            'id' => $author->getId()
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
    public function delete(int $id, AuthorRepository $authorRepository,EntityManagerInterface $entityManager): Response
    {
        // Retrieve the author to delete using the AuthorRepository
        $author = $authorRepository->find($id);

        //dd($author);

        // Check if the animal exists
        if (!$author) {
            return new JsonResponse(['message' => 'Author not found'], Response::HTTP_NOT_FOUND);
        }

        // Use the repository's remove method to delete the animal
        //$authorRepository->remove($author);
        // Remove the specified author from the entity manager
        $entityManager->remove($author);
        // Commit the changes to the database
        $entityManager->flush();

        // Return a JSON response indicating success
        return new JsonResponse(['message' => 'Author is deleted successfully'], Response::HTTP_OK);
    }
}
