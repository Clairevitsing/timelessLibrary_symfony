<?php

namespace App\Controller;

use App\Entity\BookLoan;
use App\Entity\Loan;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
    public function createLoan(Request $request,
                               EntityManagerInterface $entityManager,
                               UserRepository $userRepository,
                               BookRepository $bookRepository,
                               UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Check if user exists, if not create a new one
        $user = $userRepository->findOneBy(['email' => $data['user']['email']]);
        if (!$user) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Create the loan
        $loan = new Loan();
        $loan->setLoanDate(new \DateTime($data['loanDate']))
            ->setDueDate(new \DateTime($data['dueDate']))
            //->setReturnDate(new \DateTime($data['returnDate']))
            ->setUser($user);

        if (array_key_exists('returnDate', $data) && $data['returnDate'] !== null) {
            $loan->setReturnDate(new \DateTime($data['returnDate']));
        } else {
            $loan->setReturnDate(null);
        }

        $entityManager->persist($loan);

        // Add books to the loan
        foreach ($data['bookLoans'] as $bookLoanData) {
            $book = $bookRepository->findOneBy(['ISBN' => $bookLoanData['book']['ISBN']]);
            if (!$book) {
                return $this->json(['error' => 'Book not found: ' . $bookLoanData['book']['ISBN']], Response::HTTP_NOT_FOUND);
            }
            if (!$book->isAvailable()) {
                return $this->json(['error' => 'Book not available: ' . $book->getTitle()], Response::HTTP_BAD_REQUEST);
            }
            $bookLoan = new BookLoan();
            $bookLoan->setBook($book)
                ->setLoan($loan);
            $book->setAvailable(false);
            //$loan->addBookLoan($bookLoan);
            $entityManager->persist($bookLoan);
        }

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

        // Update loan dates
        if (array_key_exists('loanDate', $data)) {
            $loan->setLoanDate(new \DateTime($data['loanDate']));
        }
        if (array_key_exists('dueDate', $data)) {
            $loan->setDueDate(new \DateTime($data['dueDate']));
        }
        if (array_key_exists('returnDate', $data)) {
            $loan->setReturnDate($data['returnDate'] ? new \DateTime($data['returnDate']) : null);
        }

        // Handle book loans
        if (array_key_exists('bookLoans', $data) && is_array($data['bookLoans'])) {
            // First, make all current books available
            foreach ($loan->getBookLoans() as $existingBookLoan) {
                $existingBookLoan->getBook()->setAvailable(true);
                $entityManager->remove($existingBookLoan);
            }

            // Then add new book loans
            foreach ($data['bookLoans'] as $bookLoanData) {
                if (array_key_exists('book', $bookLoanData) && array_key_exists('ISBN', $bookLoanData['book'])) {
                    $book = $bookRepository->findOneBy(['ISBN' => $bookLoanData['book']['ISBN']]);
                    if (!$book) {
                        return $this->json(['error' => 'Book not found: ' . $bookLoanData['book']['ISBN']], Response::HTTP_NOT_FOUND);
                    }
                    if (!$book->isAvailable()) {
                        return $this->json(['error' => 'Book not available: ' . $book->getTitle()], Response::HTTP_BAD_REQUEST);
                    }

                    $bookLoan = new BookLoan();
                    $bookLoan->setBook($book)
                        ->setLoan($loan);
                    $book->setAvailable(false);
                    $entityManager->persist($bookLoan);
                } else {
                    return $this->json(['error' => 'Invalid book data'], Response::HTTP_BAD_REQUEST);
                }
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
