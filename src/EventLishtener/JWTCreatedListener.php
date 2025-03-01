<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        // Récupérer le payload du token
        $payload = $event->getData();

        // // Ajouter userName au JWT depuis l'entité User
        // if (method_exists($user, 'getUserName')) {
        //     $payload['userName'] = $user->getUserName();
        // }

        // Ajouter les informations supplémentaires de l'utilisateur
        $payload['userName'] = method_exists($user, 'getUserName') ? $user->getUserName() : '';
        $payload['firstName'] = method_exists($user, 'getFirstName') ? $user->getFirstName() : '';
        $payload['lastName'] = method_exists($user, 'getLastName') ? $user->getLastName() : '';

        // Mettre à jour le payload
        $event->setData($payload);
    }
}


