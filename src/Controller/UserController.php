<?php

namespace App\Controller;

use App\Entity\Loan;
use App\Entity\User;
use App\Repository\AuthorRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users')]
class UserController extends AbstractController
{
    #[Route('/', name: 'user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
        return $this->json(
            $users,
            context: ['groups' => 'user:read']
        );
    }

    #[Route('/{id}', name: 'user_read', methods: ['GET'])]
    public function read(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);
        //dd($user);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }
        //dd($user);
        return $this->json($user, context: ['groups' => 'user:read']);
    }

    #[Route('/findBy', name: 'user_findBy', methods: ['POST'])]
    public function findBy(UserRepository $userRepository, Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        //dd($data);

        // Vérifier si la clé 'email' est présente dans les données
        if (!isset($data['email'])) {
            return $this->json(['error' => 'Email manquant'], Response::HTTP_BAD_REQUEST);
        }

        // find user by email
        $user = $userRepository->findOneBy(['email' => $data['email']]);

        // verify if the user is found
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // return the user found
        return $this->json($user, Response::HTTP_OK, [], ['groups' => 'user:read']);

    }

    #[Route('/new', name: 'user_create', methods: ['POST'])]
    public function create(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        // Check if JSON decoding failed
        if ($data === null) {
            return $this->json(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }
        // Check if all required fields are present
        if (!isset($data['email'], $data['roles'], $data['firstName'], $data['lastName'], $data['userName'], $data['phoneNumber'], $data['subStartDate'], $data['subEndDate'])) {
            return $this->json(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }
        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], Response::HTTP_BAD_REQUEST);
        }
        // Check if the user already exists
        $existingUser = $userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json(['error' => 'This email is already in use'], Response::HTTP_CONFLICT);
        }
        // Create the new user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setRoles($data['roles']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setUserName($data['userName']);
        $user->setPhoneNumber($data['phoneNumber']);
        $user->setSubStartDate(new \DateTime($data['subStartDate']));
        $user->setSubEndDate(new \DateTime($data['subEndDate']));

        // Create the loans
        foreach ($data['loans'] as $loanData) {
            $loan = new Loan();
            $loan->setLoanDate(new \DateTime($loanData['loanDate']));
            $loan->setDueDate(new \DateTime($loanData['dueDate']));
            $loan->setReturnDate($loanData['returnDate'] ? new \DateTime($loanData['returnDate']) : null);
            $user->addLoan($loan);
        }
        // Persist and flush the entity
        $entityManager->persist($user);
        $entityManager->flush();
        return $this->json($user, Response::HTTP_CREATED, [], ['groups' => 'user:read']);
    }

    #[Route('/user', name: 'app_user')]
    public function edit(): Response
    {
        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }

    #[Route('/user', name: 'app_user')]
    public function delete(): Response
    {
        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }
}
