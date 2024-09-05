<?php

namespace App\Controller;

use App\Entity\BookLoan;
use App\Entity\Loan;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/loans')]
class LoanController extends AbstractController
{
    #[Route('/', name: 'loan_index',methods:['GET'])]
    public function index(LoanRepository $loanRepository): Response
    {
        $loans = $loanRepository->findAll();
       // dd($loans);
        return $this->json(
            $loans,
            context: ['groups' => 'loan:read']
        );
    }

    #[Route('/{id}', name: 'loan_read',methods:['GET'])]
    public function read(int $id, EntityManagerInterface $entityManager, LoanRepository $loanRepository): Response
    {
        $loan = $loanRepository->find($id);
        // dd($loan);
        return $this->json(
            $loan,
            context: ['groups' => 'loan:read']
        );
    }

    #[Route('/new', name: 'loan_create', methods: ['POST'])]
    public function createLoan(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        BookRepository $bookRepository
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Check if JSON decoding failed
        if ($data === null) {
            return $this->json(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }

        // Check if all required fields are present
        if (!isset($data['loanDate'], $data['dueDate'], $data['bookIds'], $data['userId'])) {
            return $this->json(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }

        // Associate user with the loan
        $user = $userRepository->find($data['userId']);
        if (!$user) {
            return $this->json(['error' => 'User not found with ID: ' . $data['userId']], Response::HTTP_BAD_REQUEST);
        }

        // Create the loan
        $loan = new Loan();
        $loan->setLoanDate(new \DateTime($data['loanDate']))
            ->setDueDate(new \DateTime($data['dueDate']))
            ->setUser($user);

        // Set returnDate to null if not provided
        if (isset($data['returnDate']) && $data['returnDate'] !== null) {
            $loan->setReturnDate(new \DateTime($data['returnDate']));
        } else {
            $loan->setReturnDate(null);
        }

        $entityManager->persist($loan);

        // Associate books with the loan
        foreach ($data['bookIds'] as $bookId) {
            $book = $bookRepository->find($bookId);
            if (!$book) {
                return $this->json(['error' => 'Book not found with ID: ' . $bookId], Response::HTTP_BAD_REQUEST);
            }
            if (!$book->isAvailable()) {
                return $this->json(['error' => 'Book not available: ' . $book->getTitle()], Response::HTTP_BAD_REQUEST);
            }

            // Create a new BookLoan entity to link the book and the loan
            $bookLoan = new BookLoan();
            $bookLoan->setBook($book)
                ->setLoan($loan);

            //The book will be unavailable if returnDate is null
            if ($data['returnDate'] === null) {
                $book->setAvailable(false);
            }

            $entityManager->persist($bookLoan);
        }

        // Flush all changes to the database
        $entityManager->flush();

        return $this->json(['message' => 'Loan created successfully', 'id' => $loan->getId()], Response::HTTP_CREATED);
    }


    #[Route('/{id}/edit', name: 'loan_edit', methods: ['PUT'])]
    public function editLoan(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        LoanRepository $loanRepository,
        BookRepository $bookRepository
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $loan = $loanRepository->find($id);
        if (!$loan) {
            return $this->json(['error' => 'Loan not found'], Response::HTTP_NOT_FOUND);
        }

        // Update loan dates if provided in the request data
        $loan->setLoanDate(!empty($data['loanDate']) ? new \DateTime($data['loanDate']) : $loan->getLoanDate());
        $loan->setDueDate(!empty($data['dueDate']) ? new \DateTime($data['dueDate']) : $loan->getDueDate());
        $loan->setReturnDate(!empty($data['returnDate']) ? new \DateTime($data['returnDate']) : $loan->getReturnDate());

        // Handle book loans
        if (array_key_exists('bookIds', $data) && is_array($data['bookIds'])) {
            // First, make all books in the current loan available
            foreach ($loan->getBookLoans() as $existingBookLoan) {
                $book = $existingBookLoan->getBook();
                // Ensure book is available only if it was marked as unavailable before
                if (!$book->isAvailable()) {
                    $book->setAvailable(true);
                    $entityManager->persist($book);
                }
                $entityManager->remove($existingBookLoan);
            }

            // Then add new book loans
            foreach ($data['bookIds'] as $bookId) {
                $book = $bookRepository->find($bookId);
                if (!$book) {
                    return $this->json(['error' => 'Book not found with ID: ' . $bookId], Response::HTTP_NOT_FOUND);
                }
                if (!$book->isAvailable()) {
                    return $this->json(['error' => 'Book not available: ' . $book->getTitle()], Response::HTTP_BAD_REQUEST);
                }

                // Create a new BookLoan entity to link the book and the loan
                $bookLoan = new BookLoan();
                $bookLoan->setBook($book)
                    ->setLoan($loan);
                //The book will be unavailable if returnDate is null
                if ($data['returnDate'] === null) {
                    $book->setAvailable(false);
                }
                $entityManager->persist($bookLoan);
            }
        }

        $entityManager->flush();

        return $this->json(['message' => 'Loan updated successfully', 'id' => $loan->getId()], Response::HTTP_OK);
    }


    #[Route('/{id}', name: 'loan_delete',methods:['DELETE'])]
    public function delete(): Response
    {
        return $this->render('loan/index.html.twig', [
            'controller_name' => 'LoanController',
        ]);
    }
}
