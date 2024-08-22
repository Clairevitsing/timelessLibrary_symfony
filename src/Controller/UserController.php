<?php

namespace App\Controller;

use App\Entity\Loan;
use App\Entity\User;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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

        // Check if the 'email' key is present in the data.
        if (!isset($data['email'])) {
            return $this->json(['error' => 'Email missing'], Response::HTTP_BAD_REQUEST);
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
    public function create(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
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
        $user->setPlainPassword($data['password']);
        $user->setSubStartDate(new \DateTime($data['subStartDate']));
        $user->setSubEndDate(new \DateTime($data['subEndDate']));

        // Hash the password
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

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

    #[Route('/{id}/edit', name: 'user_edit', methods: ['PUT'])]
    public function edit(
        int $id,
        Request $request,
        UserRepository $userRepository,
        LoanRepository $loanRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        $content = $request->getContent();
        if (empty($content)) {
            return $this->json(['error' => 'No data provided'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Fetch the User entity by ID
        $user = $userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Update the User details
        $user->setEmail($data['email'] ?? $user->getEmail());
        $user->setRoles($data['roles'] ?? $user->getRoles());
        $user->setFirstName($data['firstName'] ?? $user->getFirstName());
        $user->setLastName($data['lastName'] ?? $user->getLastName());
        $user->setUserName($data['userName'] ?? $user->getUserName());
        $user->setPhoneNumber($data['phoneNumber'] ?? $user->getPhoneNumber());

        // For password
        if (isset($data['password'])) {
            $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        }

        if (isset($data['subStartDate'])) {
            $user->setSubStartDate(new \DateTime($data['subStartDate']));
        }
        if (isset($data['subEndDate'])) {
            $user->setSubEndDate(new \DateTime($data['subEndDate']));
        }

        // Handle Loans
        if (isset($data['loans']) && is_array($data['loans'])) {
            foreach ($data['loans'] as $loanData) {
                if (isset($loanData['id'])) {
                    // Loan exists, update it
                    $loan = $loanRepository->find($loanData['id']);
                    if (!$loan) {
                        return $this->json(['error' => 'Loan not found'], JsonResponse::HTTP_NOT_FOUND);
                    }
                } else {
                    // New loan, create it
                    $loan = new Loan();
                    $user->addLoan($loan);
                }

                // Update loan details
                $loan->setLoanDate(new \DateTime($loanData['loanDate']));
                $loan->setDueDate(new \DateTime($loanData['dueDate']));
                $loan->setReturnDate(isset($loanData['returnDate']) ? new \DateTime($loanData['returnDate']) : null);

                $entityManager->persist($loan);
            }
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json($user, JsonResponse::HTTP_OK, [], ['groups' => 'user:read']);
    }

    #[Route('/{id}', name: 'user_delete', methods: ['DELETE'])]
    public function delete(int $id, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        // Find the user by id
        $user = $userRepository->find($id);

        //dd($user);

        // If user not found, return a 404 error
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
         }
        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(['message' => 'User deleted successfully'] , Response::HTTP_OK);
        //return $this->redirectToRoute('user_index', [], Response::HTTP_SEE_OTHER);
    }
}
