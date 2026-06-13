<?php

namespace App\Service;

use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class GoogleCalendarService
{
    private Google_Client $client;
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
        $this->client = new Google_Client();
        $this->client->setApplicationName('GYMIFY');
        $this->client->setScopes([\Google_Service_Calendar::CALENDAR]);
        $this->client->setAuthConfig('config/secrets/client_secret.json');
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        $this->client->setRedirectUri('http://localhost:8000/callback'); // Adaptez à votre URL
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function authenticate(string $code): void
    {
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);
        $this->session->set('access_token', $accessToken);
    }

    public function getClient(): Google_Client
    {
        if ($this->session->get('access_token')) {
            $this->client->setAccessToken($this->session->get('access_token'));
            // Rafraîchir le jeton si expiré
            if ($this->client->isAccessTokenExpired()) {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                $this->session->set('access_token', $newToken);
            }
        }
        return $this->client;
    }

    public function getCalendarEvents(): array
    {
        $client = $this->getClient();
        $service = new Google_Service_Calendar($client);
        $calendarId = 'primary';
        $optParams = [
            'timeMin' => date('c'),
            'timeMax' => (new \DateTime('+1 month'))->format('c'),
            'maxResults' => 10,
            'orderBy' => 'startTime',
            'singleEvents' => true,
        ];

        $events = [];
        try {
            $results = $service->events->listEvents($calendarId, $optParams);
            foreach ($results->getItems() as $event) {
                $events[] = [
                    'id' => $event->getId(),
                    'title' => $event->getSummary(),
                    'start' => $event->getStart()->getDateTime() ?: $event->getStart()->getDate(),
                    'end' => $event->getEnd()->getDateTime() ?: $event->getEnd()->getDate(),
                    'backgroundColor' => '#ff0000', // Couleur pour les événements Google
                    'description' => $event->getDescription() ?: 'Pas de description',
                ];
            }
        } catch (\Exception $e) {
            // Gérer l'erreur (par exemple, jeton invalide)
        }

        return $events;
    }

    public function addEventToGoogleCalendar(array $eventData): void
    {
        $client = $this->getClient();
        $service = new Google_Service_Calendar($client);
        $calendarId = 'primary';

        $event = new Google_Service_Calendar_Event([
            'summary' => $eventData['title'],
            'description' => $eventData['description'] ?? '',
            'start' => [
                'dateTime' => $eventData['start'],
                'timeZone' => 'Europe/Paris',
            ],
            'end' => [
                'dateTime' => $eventData['end'],
                'timeZone' => 'Europe/Paris',
            ],
        ]);

        $service->events->insert($calendarId, $event);
    }
}