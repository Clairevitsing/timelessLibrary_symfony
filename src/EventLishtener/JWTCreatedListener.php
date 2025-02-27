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

        // Ajouter userName au JWT depuis l'entité User
        if (method_exists($user, 'getUserName')) {
            $payload['userName'] = $user->getUserName();
        }

        // Mettre à jour le payload
        $event->setData($payload);
    }
}


