<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookLoanController extends AbstractController
{
    #[Route('/book/loan', name: 'app_book_loan')]
    public function index(): Response
    {
        return $this->render('book_loan/index.html.twig', [
            'controller_name' => 'BookLoanController',
        ]);
    }
}
