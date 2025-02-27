<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;


class SecurityController extends AbstractController
{
    #[Route("/api/login_check", name: "app_login", methods: ["POST"])]
    public function login(
        #[CurrentUser] ?User $user,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        if (!$user) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // Générer le token JWT pour l'utilisateur authentifié
        return $this->json([
            'token' => $jwtManager->create($user),
            'user' => [
                'email' => $user->getEmail(),
                'userName' => $user->getUserName(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ]
        ]);
    }

    #[Route('/api/register', name: 'user_create', methods: ['POST'])]
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
        if (!isset($data['email'], $data['roles'], $data['firstName'], $data['lastName'], $data['userName'], $data['phoneNumber'], $data['subStartDate'], $data['subEndDate'], $data['password'])) {
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

        // Hash the password
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Persist and save the user without loans
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json($user, Response::HTTP_CREATED, [], ['groups' => 'user:read']);
    }
    #[Route(path: '/api/logout', name: 'app_logout')]
    public function logout()
    {
        return new JsonResponse(['message' => 'Logout successful']);
        //throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
