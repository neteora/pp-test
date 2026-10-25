<?php

namespace App\EventSubscriber;

use App\Doctrine\UserOwnedFilter;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/** Punto unico in cui si attiva l'isolamento per utente. */
final class UserFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $tokens, private EntityManagerInterface $em) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onController'];
    }

    public function onController(ControllerEvent $event): void
    {
        $user = $this->tokens->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }
        /** @var UserOwnedFilter $filter */
        $filter = $this->em->getFilters()->enable('user_owned');
        $filter->setParameter('user_id', $user->getId(), 'integer');
    }
}
