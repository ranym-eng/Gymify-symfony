<?php

namespace App\Controller;

use App\Service\LocaleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class LanguageController extends AbstractController
{
    #[Route('/change-language/{locale}', name: 'app_change_language')]
    public function changeLanguage(string $locale, Request $request, LocaleService $localeService): RedirectResponse
    {
        // Vérifier si la locale est supportée
        if (in_array($locale, $localeService->getSupportedLocales())) {
            // Définir la nouvelle locale dans le service
            $localeService->setLocale($locale);
        }
        
        // Rediriger vers la page précédente ou l'accueil
        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_home');
    }
} 