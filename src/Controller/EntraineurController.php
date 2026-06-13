<?php

namespace App\Controller;

use App\Entity\Cours;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Planning;
use App\Enum\ObjectifCours;
use Google_Client;
use Google_Service_Calendar;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Security;


final class EntraineurController extends AbstractController
{
    #[Route('/calendar', name: 'app_calendar')]
    public function calendar(
        Request $request,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        $coursRepository = $entityManager->getRepository(Cours::class);
        $coursList = $coursRepository->findAll(['entaineur' => $user]);

        $events = [];

        foreach ($coursList as $cours) {
            if (!$cours->getDateDebut() || !$cours->getHeurDebut() || !$cours->getHeurFin()) {
                continue;
            }

            $start = $cours->getDateDebut()->format('Y-m-d') . 'T' . $cours->getHeurDebut()->format('H:i:s');
            $end = $cours->getDateDebut()->format('Y-m-d') . 'T' . $cours->getHeurFin()->format('H:i:s');

            $events[] = [
                'title' => $cours->getTitle(),
                'start' => $start,
                'end' => $end,
                'description' => $cours->getDescription(),
                'backgroundColor' => $this->getColorForObjectif($cours->getObjectif()),
            ];
        }

        // Ajout des événements Google Calendar
        $googleEvents = [];
        if ($session->get('google_access_token')) {
            $googleEvents = $this->getGoogleCalendarEvents($session);
        }

        $allEvents = array_merge($events, $googleEvents);

        return $this->render('cours/calendar.html.twig', [
            'page_title' => 'Mon Calendrier',
            'events' => json_encode($allEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
        ]);
    }

    #[Route('/google-auth', name: 'google_auth')]
    public function googleAuth(SessionInterface $session): RedirectResponse
    {
        $client = $this->getGoogleClient();
        $authUrl = $client->createAuthUrl();
        return new RedirectResponse($authUrl);
    }

    #[Route('/callback', name: 'google_callback')]
    public function googleCallback(Request $request, SessionInterface $session): RedirectResponse
    {
        $client = $this->getGoogleClient();
        $code = $request->query->get('code');

        if ($code) {
            $token = $client->fetchAccessTokenWithAuthCode($code);
            if (!isset($token['error'])) {
                $session->set('google_access_token', $token);
            } else {
                $this->addFlash('error', 'Erreur lors de la récupération du token Google');
            }
        }

        return $this->redirectToRoute('app_calendar');
    }

    #[Route('/entraineur', name: 'app_entraineur')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $plannings = $entityManager->getRepository(Planning::class)->findAll();

        return $this->render('planning/index.html.twig', [
            'plannings' => $plannings,
            'page_title' => 'Liste des plannings'
        ]);
    }

    private function getColorForObjectif(?ObjectifCours $objectif): string
    {
        return match ($objectif) {
            ObjectifCours::PERTE_POIDS => '#FF5733',
            ObjectifCours::PRISE_DE_MASSE => '#33FF57',
            ObjectifCours::ENDURANCE => '#3357FF',
            ObjectifCours::RELAXATION => '#F033FF',
            default => '#CCCCCC',
        };
    }

    private function getGoogleClient(): Google_Client
    {
        $client = new Google_Client();
        $client->setClientId($this->getParameter('google_client_id'));
        $client->setClientSecret($this->getParameter('google_client_secret'));
        $client->setRedirectUri($this->getParameter('google_redirect_uri'));
        $client->addScope(Google_Service_Calendar::CALENDAR);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        return $client;
    }

    private function getGoogleCalendarEvents(SessionInterface $session): array
    {
        try {
            $client = $this->getGoogleClient();
            $accessToken = $session->get('google_access_token');
            $client->setAccessToken($accessToken);

            if ($client->isAccessTokenExpired()) {
                $refreshToken = $accessToken['refresh_token'] ?? null;
                if ($refreshToken) {
                    $client->fetchAccessTokenWithRefreshToken($refreshToken);
                    $session->set('google_access_token', $client->getAccessToken());
                } else {
                    return [];
                }
            }

            $service = new Google_Service_Calendar($client);
            $events = $service->events->listEvents('primary', [
                'timeMin' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('c'),
                'timeMax' => (new \DateTime('+1 month', new \DateTimeZone('UTC')))->format('c'),
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ]);

            $googleEvents = [];
            foreach ($events->getItems() as $event) {
                $start = $event->getStart()->getDateTime() ?: $event->getStart()->getDate();
                $end = $event->getEnd()->getDateTime() ?: $event->getEnd()->getDate();

                if ($start && $end) {
                    $googleEvents[] = [
                        'title' => $event->getSummary(),
                        'start' => $start,
                        'end' => $end,
                        'description' => $event->getDescription() ?: 'Aucune description',
                        'backgroundColor' => '#34C759',
                    ];
                }
            }

            return $googleEvents;

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la récupération des événements Google: ' . $e->getMessage());
            return [];
        }
    }
    
#[Route('/reset-google-auth', name: 'reset_google_auth')]
public function resetGoogleAuth(SessionInterface $session): RedirectResponse
{
    $session->remove('google_access_token');
    return $this->redirectToRoute('google_auth');
}
#[Route('/import-cours-to-calendar', name: 'import_cours_to_calendar')]
public function importCoursToGoogleCalendar(
    SessionInterface $session,
    EntityManagerInterface $entityManager
): RedirectResponse {
    if (!$session->get('google_access_token')) {
        return $this->redirectToRoute('google_auth');
    }

    $client = $this->getGoogleClient();
    $client->setAccessToken($session->get('google_access_token'));

    if ($client->isAccessTokenExpired()) {
        $refreshToken = $client->getRefreshToken();
        if ($refreshToken) {
            $client->fetchAccessTokenWithRefreshToken($refreshToken);
            $session->set('google_access_token', $client->getAccessToken());
        } else {
            $this->addFlash('error', 'Le token a expiré et aucun refresh token n\'est disponible.');
            return $this->redirectToRoute('app_calendar');
        }
    }

    $service = new Google_Service_Calendar($client);
    $coursList = $entityManager->getRepository(Cours::class)->findAll();

    // Récupérer la liste des IDs déjà importés depuis la session (ou tableau vide si non défini)
    $importedIds = $session->get('imported_cours_ids', []);

    foreach ($coursList as $cours) {
        $id = $cours->getId();

        // Vérifie si le cours a déjà été importé
        if (in_array($id, $importedIds)) {
            continue;
        }

        if (!$cours->getDateDebut() || !$cours->getHeurDebut() || !$cours->getHeurFin()) {
            continue;
        }

        $startDateTime = $cours->getDateDebut()->format('Y-m-d') . 'T' . $cours->getHeurDebut()->format('H:i:s');
        $endDateTime = $cours->getDateDebut()->format('Y-m-d') . 'T' . $cours->getHeurFin()->format('H:i:s');

        $event = new \Google_Service_Calendar_Event([
            'summary' => $cours->getTitle(),
            'description' => $cours->getDescription(),
            'start' => [
                'dateTime' => $startDateTime,
                'timeZone' => 'Europe/Paris',
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => 'Europe/Paris',
            ],
            'colorId' => '10',
        ]);

        $service->events->insert('primary', $event);

        // Marque le cours comme importé
        $importedIds[] = $id;
    }

    // Sauvegarde la liste mise à jour dans la session
    $session->set('imported_cours_ids', $importedIds);

    $this->addFlash('success', 'Les nouveaux cours ont été ajoutés à votre Google Calendar !');
    return $this->redirectToRoute('app_calendar');
}
#[Route('/reset-imported-cours', name: 'reset_imported_cours')]
public function resetImportedCours(SessionInterface $session): RedirectResponse
{
    $session->remove('imported_cours_ids');
    $this->addFlash('success', 'La liste des cours importés a été réinitialisée.');
    return $this->redirectToRoute('app_calendar');
}



}
