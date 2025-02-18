<?php

namespace App\Controller;

use App\Entity\BookLoan;
use App\Entity\Book;
use App\Entity\Loan;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\BookLoanRepository;

#[Route('/api/book/loan')]
class BookLoanController extends AbstractController
{
    #[Route('/', name: 'app_book_loan')]
    public function index(BookLoanRepository $bookLoanRepository): Response
    {
        $bookLoans = $bookLoanRepository->findAll();
        return $this->json(
            $bookLoans,
            context: ['groups' => 'bookLoan:read']
        );
    }
    #[Route('/create', name: 'app_book_loan_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        BookRepository $bookRepository,
        LoanRepository $loanRepository
    ): Response {
        // Decode JSON data from the request body
        $data = json_decode($request->getContent(), true);

        // Validate the required fields
        if (!isset($data['bookIds']) || !is_array($data['bookIds']) || !isset($data['loanId'])) {
            return $this->json(['error' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }
        // Array of book IDs
        $bookIds = $data['bookIds']; 
          // Loan ID
        $loanId = $data['loanId']; 

        // Fetch the Loan entity
        $loan = $loanRepository->find($loanId);
        if (!$loan) {
            return $this->json(['error' => 'Loan not found'], Response::HTTP_NOT_FOUND);
        }

        // Iterate through the list of book IDs
        foreach ($bookIds as $bookId) {
            // Fetch the Book entity
            $book = $bookRepository->find($bookId);
            if (!$book) {
                return $this->json(['error' => "Book with ID $bookId not found"], Response::HTTP_NOT_FOUND);
            }

            // Create a new BookLoan entity
            $bookLoan = new BookLoan();     
            // Set the Loan entity
            $bookLoan->setBook($book);  
             // Set the Loan entity
            $bookLoan->setLoan($loan); 

            // Persist the BookLoan entity
            $entityManager->persist($bookLoan);
        }

        // Save all changes to the database
        $entityManager->flush();

        // Return a success response
        return $this->json(['message' => 'Books successfully borrowed'], Response::HTTP_CREATED);
    }
}

