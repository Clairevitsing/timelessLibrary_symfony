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
                               BookRepository $bookRepository
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Check if user exists, if not create a new one
        $user = $userRepository->findOneBy(['email' => $data['user']['email']]);
        if (!$user) {
            $user = new User();
            $user->setEmail($data['user']['email'])
                ->setRoles($data['user']['roles'])
                ->setFirstName($data['user']['firstName'])
                ->setLastName($data['user']['lastName'])
                ->setUserName($data['user']['userName'])
                ->setPhoneNumber($data['user']['phoneNumber'])
                ->setSubStartDate(new \DateTime($data['user']['subStartDate']))
                ->setSubEndDate(new \DateTime($data['user']['subEndDate']));
            $entityManager->persist($user);
        }

        // Create the loan
        $loan = new Loan();
        $loan->setLoanDate(new \DateTime($data['loanDate']))
            ->setDueDate(new \DateTime($data['dueDate']))
            ->setReturnDate(new \DateTime($data['returnDate']))
            ->setUser($user);

        // Add books to the loan
        foreach ($data['bookLoans'] as $bookLoanData) {
            $book = $bookRepository->findOneBy(['ISBN' => $bookLoanData['book']['ISBN']]);
            if (!$book) {
                return $this->json(['error' => 'Book not found: ' . $bookLoanData['book']['id']], Response::HTTP_NOT_FOUND);
            }
            if (!$book->isAvailable()) {
                return $this->json(['error' => 'Book not available: ' . $book->getTitle()], Response::HTTP_BAD_REQUEST);
            }
            $bookLoan = new BookLoan();
            $bookLoan->setBook($book)
                ->setLoan($loan);
            $book->setAvailable(false);
            $loan->addBookLoan($bookLoan);
            $entityManager->persist($bookLoan);
        }

        $entityManager->persist($loan);
        $entityManager->flush();

        return $this->json(['message' => 'Loan created successfully', 'id' => $loan->getId()], Response::HTTP_CREATED);
    }

    #[Route('/{id}/edit', name: 'loan_edit',methods:['PUT'])]
    public function edit(): Response
    {
        return $this->render('loan/index.html.twig', [
            'controller_name' => 'LoanController',
        ]);
    }

    #[Route('/{id}', name: 'loan_delete',methods:['DELETE'])]
    public function delete(): Response
    {
        return $this->render('loan/index.html.twig', [
            'controller_name' => 'LoanController',
        ]);
    }
}
