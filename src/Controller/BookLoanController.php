<?php

namespace App\Controller;

use App\Repository\BookLoanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
}
