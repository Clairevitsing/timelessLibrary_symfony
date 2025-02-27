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

        // Ajouter les informations supplémentaires
        if (method_exists($user, 'getUserName')) {
            $payload['userName'] = $user->getUserName();
        }
        if (method_exists($user, 'getFirstName')) {
            $payload['firstName'] = $user->getFirstName();
        }
        if (method_exists($user, 'getLastName')) {
            $payload['lastName'] = $user->getLastName();
        }

        // Mettre à jour le payload
        $event->setData($payload);
    }
}


