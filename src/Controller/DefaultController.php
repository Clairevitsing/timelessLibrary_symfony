<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    #[Route('/api', name: 'app_home')]
    public function index(): JsonResponse
    {
    return $this->json([
    'application' => 'TimelessLibrary API',
    'version' => '1.0.0',
    'endpoints' => [
    '/api/books',
    '/api/authors',
    '/api/users',
    '/api/login_check',

    
    ]
    ]);
    }
}