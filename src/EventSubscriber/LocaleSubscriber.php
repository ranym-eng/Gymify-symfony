<?php

namespace App\EventSubscriber;

use App\Service\LocaleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private LocaleService $localeService;
    
    public function __construct(LocaleService $localeService)
    {
        $this->localeService = $localeService;
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            // Doit s'exécuter avant le LocaleListener de Symfony
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
    
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        
        $request = $event->getRequest();
        
        // Définir la locale à partir du service
        $locale = $this->localeService->getCurrentLocale();
        $request->setLocale($locale);
    }
} 