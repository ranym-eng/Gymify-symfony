<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LocaleService
{
    private RequestStack $requestStack;
    private string $defaultLocale;
    
    // Langues supportées par l'application
    private array $supportedLocales = ['fr', 'en', 'ar'];
    
    // Noms des langues pour l'affichage dans l'interface
    private array $localeNames = [
        'fr' => 'Français',
        'en' => 'English',
        'ar' => 'العربية',
    ];
    
    public function __construct(RequestStack $requestStack, string $defaultLocale = 'fr')
    {
        $this->requestStack = $requestStack;
        $this->defaultLocale = $defaultLocale;
    }
    
    /**
     * Obtenir la locale actuelle depuis la session ou la locale par défaut
     */
    public function getCurrentLocale(): string
    {
        $session = $this->getSession();
        
        if ($session && $session->has('_locale')) {
            $locale = $session->get('_locale');
            if (in_array($locale, $this->supportedLocales)) {
                return $locale;
            }
        }
        
        return $this->defaultLocale;
    }
    
    /**
     * Définir la locale dans la session
     */
    public function setLocale(string $locale): void
    {
        if (!in_array($locale, $this->supportedLocales)) {
            throw new \InvalidArgumentException("Locale '$locale' non supportée.");
        }
        
        $session = $this->getSession();
        if ($session) {
            $session->set('_locale', $locale);
        }
    }
    
    /**
     * Récupérer la liste des locales supportées
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }
    
    /**
     * Récupérer la liste des noms de locales pour l'affichage
     */
    public function getLocaleNames(): array
    {
        return $this->localeNames;
    }
    
    /**
     * Récupérer le nom d'une locale spécifique
     */
    public function getLocaleName(string $locale): string
    {
        return $this->localeNames[$locale] ?? $locale;
    }
    
    /**
     * Récupérer la direction d'écriture (LTR ou RTL) pour une locale
     */
    public function getTextDirection(string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }
    
    /**
     * Récupérer la session active
     */
    private function getSession(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }
        
        return $request->hasSession() ? $request->getSession() : null;
    }
} 