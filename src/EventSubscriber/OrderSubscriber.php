<?php

namespace App\EventSubscriber;

use App\Entity\Commande;
use App\Service\SmsService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class OrderSubscriber implements EventSubscriberInterface
{
    private $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Commande) {
            return;
        }

        // Set the phone number directly
        $phoneNumber = '28609851';
        $entity->setPhoneNumber($phoneNumber);

        // Send SMS confirmation
        $this->smsService->sendOrderConfirmation(
            $phoneNumber,
            (string) $entity->getIdC(),
            $entity->getTotalC(),
            $entity->getDateC()
        );
    }
} 