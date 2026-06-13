<?php

namespace App\Controller;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use App\Entity\Salle;
use App\Enum\ActivityType;
use App\Entity\Abonnement;
use App\Entity\EquipeEvent;
use App\Entity\User;
use Symfony\Contracts\HttpClient\HttpClientInterface; 
use App\Entity\Cours;
use App\Entity\Paiement;
use App\Enum\ObjectifCours;
use App\Form\SportifParticipationType;
use App\Form\QuestionnaireType;
use App\Repository\AbonnementRepository;
use App\Repository\EquipeEventRepository;
use App\Repository\SalleRepository;
use App\Service\WeatherService;
use App\Repository\CoursRepository;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

final class SportifController extends AbstractController
{
    private $logger;
    private $params;

    public function __construct(LoggerInterface $logger,ParameterBagInterface $params)
    {
        $this->logger = $logger;
        $this->params = $params;

    }

    #[Route('/sportif', name: 'home')]
    public function sportif(SalleRepository $salleRepository): Response
    {
        $salles = $salleRepository->findAll();
        return $this->render('sportif/index.html.twig', [
            'salles' => $salles,
            
        ]);
    }

    #[Route('/sportif/planning', name: 'plan')]
    public function index(CoursRepository $coursRepository): Response
    {
        $cours = $coursRepository->findAll();
        $events = [];

        foreach ($cours as $cour) {
            $events[] = [
                'title' => $cour->getTitle(),
                'start' => $this->mergeDateTime($cour->getDateDebut(), $cour->getHeurDebut()),
                'end' => $this->mergeDateTime($cour->getDateDebut(), $cour->getHeurFin()),
                'color' => $this->getColorForObjectif($cour->getObjectif()),
                'description' => $cour->getDescription(),
                'extendedProps' => [
                    'activite' => $cour->getActivité() ? $cour->getActivité()->getNom() : null,
                    'salle' => $cour->getSalle() ? $cour->getSalle()->getNom() : null,
                ],
            ];
        }

        return $this->render('sportif/planning.html.twig', [
            'events' => $events,
        ]);
    }

    #[Route('/salle/{id}', name: 'sportif_salle_details')]
    public function salleDetails(
        Salle $salle,
        AbonnementRepository $abonnementRepository,
        EquipeEventRepository $equipeEventRepository,
        ActivityRepository $activityRepository,
        WeatherService $weatherService,
        Request $request
    ): Response {
        // Check if user is authenticated
        $sportif = $this->getUser();
        if (!$sportif) {
            throw $this->createAccessDeniedException('Vous devez être connecté en tant que sportif.');
        }

        // Fetch abonnements
        $abonnements = $abonnementRepository->findBy(['salle' => $salle]);

        // Fetch activities
        $activities = $activityRepository->findAll();
        $this->logger->debug('Activities found for salle', [
            'salle_id' => $salle->getId(),
            'activity_count' => count($activities),
        ]);

        // Extract query parameters
        $queryParams = [
            'fitness_goal' => $request->query->get('fitness_goal'),
            'budget' => $request->query->get('budget'),
            'activity' => $request->query->get('activity'),
            'weekly_availability' => $request->query->get('weekly_availability'),
        ];

        // Handle equipe events and type filter
        $typeFilter = $request->query->get('type', 'all');
        $equipeEvents = [];

        try {
            if ($typeFilter === 'all') {
                $equipeEvents = $equipeEventRepository->findBySalle($salle);
            } else {
                $equipeEvents = $equipeEventRepository->findBySalleAndType($salle, EventType::from($typeFilter));
            }
        } catch (\ValueError $e) {
            $this->addFlash('error', 'Type d\'événement invalide sélectionné.');
            $equipeEvents = $equipeEventRepository->findBySalle($salle);
            $typeFilter = 'all';
        }

        // Group equipe events
        $groupedEquipeEvents = [];
        foreach ($equipeEvents as $ee) {
            $eventId = $ee->getEvent()->getId();
            if (!isset($groupedEquipeEvents[$eventId])) {
                $groupedEquipeEvents[$eventId] = [
                    'event' => [
                        'id' => $eventId,
                        'nom' => $ee->getEvent()->getNom(),
                        'imageUrl' => $ee->getEvent()->getImageUrl(),
                        'type' => $ee->getEvent()->getType()?->value ?? 'N/A',
                        'dateDay' => $ee->getEvent()->getDate()->format('d'),
                        'dateMonth' => $ee->getEvent()->getDate()->format('M'),
                        'heureDebut' => $ee->getEvent()->getHeureDebut()->format('H:i'),
                        'heureFin' => $ee->getEvent()->getHeureFin()->format('H:i'),
                        'reward' => $ee->getEvent()->getReward()?->value ?? 'N/A',
                        'description' => $ee->getEvent()->getDescription(),
                        'lieu' => $ee->getEvent()->getLieu() ?? 'Non spécifié',
                    ],
                    'equipeEvents' => [],
                ];
            }
            $groupedEquipeEvents[$eventId]['equipeEvents'][] = [
                'id' => $ee->getId(),
                'equipeNom' => $ee->getEquipe()->getNom(),
                'detailsUrl' => $this->generateUrl('app_equipe_event_show', ['id' => $ee->getId()]),
                'joinUrl' => $this->generateUrl('app_equipe_event_join', ['id' => $ee->getId()]),
            ];
        }
        $groupedEquipeEvents = array_values($groupedEquipeEvents); // Reindex array

        // Fetch weather data
        $weatherData = null;
        $forecastData = null;
        $location = $request->query->get('location', $salle->getAdresse() ?? 'Tunis');
        $days = 7;

        try {
            $weatherData = $weatherService->getCurrentWeather($location);
            $forecastData = $weatherService->getForecast($location, $days);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de récupérer les données météo : ' . $e->getMessage());
        }

        // Render the template with all parameters
        return $this->render('sportif/salle_details.html.twig', [
            'salle' => $salle,
            'abonnements' => $abonnements,
            'equipe_events' => $equipeEvents, // For backward compatibility
            'sportif' => $sportif,
            'stripe_public_key' => $this->getParameter('stripe_public_key'),
            'activities' => $activities,
            'query_params' => $queryParams,
            'grouped_equipe_events' => $groupedEquipeEvents,
            'weatherData' => $weatherData,
            'forecastData' => $forecastData,
            'location' => $location,
            'current_type_filter' => $typeFilter,
        ]);
    }

    #[Route('/sportif/salle/{id}/events', name: 'sportif_salle_events', methods: ['GET'])]
    public function salleEvents(Salle $salle, EquipeEventRepository $equipeEventRepository, Request $request): JsonResponse
    {
        $type = $request->query->get('type', 'all');
        $equipeEvents = [];

        try {
            if ($type === 'all') {
                $equipeEvents = $equipeEventRepository->findBySalle($salle);
            } else {
                $equipeEvents = $equipeEventRepository->findBySalleAndType($salle, EventType::from($type));
            }
        } catch (\ValueError $e) {
            return new JsonResponse(['error' => 'Invalid event type'], Response::HTTP_BAD_REQUEST);
        }

        $groupedEquipeEvents = [];
        foreach ($equipeEvents as $ee) {
            $eventId = $ee->getEvent()->getId();
            if (!isset($groupedEquipeEvents[$eventId])) {
                $groupedEquipeEvents[$eventId] = [
                    'event' => [
                        'id' => $eventId,
                        'nom' => $ee->getEvent()->getNom(),
                        'imageUrl' => $ee->getEvent()->getImageUrl(),
                        'type' => $ee->getEvent()->getType()?->value ?? 'N/A',
                        'dateDay' => $ee->getEvent()->getDate()->format('d'),
                        'dateMonth' => $ee->getEvent()->getDate()->format('M'),
                        'heureDebut' => $ee->getEvent()->getHeureDebut()->format('H:i'),
                        'heureFin' => $ee->getEvent()->getHeureFin()->format('H:i'),
                        'reward' => $ee->getEvent()->getReward()?->value ?? 'N/A',
                        'description' => $ee->getEvent()->getDescription(),
                        'lieu' => $ee->getEvent()->getLieu() ?? 'Non spécifié',
                    ],
                    'equipeEvents' => [],
                ];
            }
            $groupedEquipeEvents[$eventId]['equipeEvents'][] = [
                'id' => $ee->getId(),
                'equipeNom' => $ee->getEquipe()->getNom(),
                'detailsUrl' => $this->generateUrl('app_equipe_event_show', ['id' => $ee->getId()]),
                'joinUrl' => $this->generateUrl('app_equipe_event_join', ['id' => $ee->getId()]),
            ];
        }

        // Add a timestamp to help detect changes
        return new JsonResponse([
            'grouped_equipe_events' => array_values($groupedEquipeEvents),
            'timestamp' => time(),
        ]);
    }

    #[Route('/sportif/equipe-event/join/{id}', name: 'app_equipe_event_join', methods: ['GET', 'POST'])]
    public function joinEquipeEvent(
        Request $request,
        EquipeEvent $equipeEvent,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $sportif */
        $sportif = $this->getUser();

        if (!$sportif || !in_array('ROLE_SPORTIF', $sportif->getRoles(), true)) {
            $this->addFlash('error', 'Vous devez être connecté en tant que sportif pour participer.');
            return $this->redirectToRoute('sportif_salle_details', [
                'id' => $equipeEvent->getEvent()->getSalle()->getId()
            ]);
        }

        $existingParticipation = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.id = :sportifId')
            ->andWhere('u.equipe = :equipeId')
            ->setParameter('sportifId', $sportif->getId())
            ->setParameter('equipeId', $equipeEvent->getEquipe()->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingParticipation) {
            $this->addFlash('error', 'Vous participez déjà à cette équipe pour cet événement.');
            return $this->redirectToRoute('sportif_salle_details', [
                'id' => $equipeEvent->getEvent()->getSalle()->getId()
            ]);
        }

        $equipe = $equipeEvent->getEquipe();
        if ($equipe->getNombreMembres() >= 8) {
            $this->addFlash('error', 'Cette équipe est complète (8/8).');
            return $this->redirectToRoute('sportif_salle_details', [
                'id' => $equipeEvent->getEvent()->getSalle()->getId()
            ]);
        }

        $form = $this->createForm(SportifParticipationType::class, $sportif);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sportif->setEquipe($equipe);
            $equipe->setNombreMembres($equipe->getNombreMembres() + 1);

            $entityManager->persist($sportif);
            $entityManager->persist($equipe);
            $entityManager->flush();

            // Generate a secure token using HMAC
            $timestamp = time();
            $data = $sportif->getId() . ':' . $equipeEvent->getId() . ':' . $timestamp;
            $tokenSecret = $this->params->get('app.token_secret');
            $token = hash_hmac('sha256', $data, $tokenSecret);

            // Generate absolute URL
            $routeParams = [
                'id' => $equipeEvent->getId(),
                'token' => $token,
                'timestamp' => $timestamp,
            ];
            $confirmationUrl = $this->generateUrl(
                'app_confirm_participation',
                $routeParams,
                \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Ensure the URL has the correct scheme and host
            if (strpos($confirmationUrl, 'http') !== 0) {
                $scheme = $request->getScheme();
                $host = $request->getHost();
                $baseUrl = sprintf('%s://%s', $scheme, $host);
                $confirmationUrl = $baseUrl . $confirmationUrl;
            }

            // Generate QR code
            $qrCode = new QrCode($confirmationUrl);
            
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            $qrCodeDataUri = $result->getDataUri();

            return $this->render('sportif/join_equipe_event.html.twig', [
                'form' => $form->createView(),
                'equipe_event' => $equipeEvent,
                'qr_code' => $qrCodeDataUri,
                'confirmation_url' => $confirmationUrl,
                'success_message' => sprintf(
                    'Le sportif %s %s a été ajouté à l\'équipe %s pour cet événement ! Scannez le QR Code pour confirmer votre participation.',
                    htmlspecialchars($sportif->getPrenom(), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($sportif->getNom(), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($equipe->getNom(), ENT_QUOTES, 'UTF-8')
                ),
            ]);
        }

        return $this->render('sportif/join_equipe_event.html.twig', [
            'form' => $form->createView(),
            'equipe_event' => $equipeEvent,
        ]);
    }
    #[Route('/sportif/equipe-event/confirm-participation/{id}/{token}/{timestamp}', name: 'app_confirm_participation', methods: ['GET'])]
    public function confirmParticipation(
        EquipeEvent $equipeEvent,
        string $token,
        int $timestamp,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $sportif */
        $sportif = $this->getUser();

        if (!$sportif || !in_array('ROLE_SPORTIF', $sportif->getRoles(), true)) {
            return $this->render('sportif/confirmation.html.twig', [
                'error' => 'Vous devez être connecté en tant que sportif pour confirmer votre participation. Veuillez vous connecter.',
                'equipe_event' => $equipeEvent,
                'sportif' => null,
            ]);
        }

        // Validate token using HMAC
        $data = $sportif->getId() . ':' . $equipeEvent->getId() . ':' . $timestamp;
        $tokenSecret = $this->params->get('app.token_secret');
        $expectedToken = hash_hmac('sha256', $data, $tokenSecret);

        if (!hash_equals($expectedToken, $token)) {
            return $this->render('sportif/confirmation.html.twig', [
                'error' => 'Token de confirmation invalide.',
                'equipe_event' => $equipeEvent,
                'sportif' => $sportif,
            ]);
        }

        // Check token expiration (24 hours)
        $currentTime = time();
        if (($currentTime - $timestamp) > 24 * 60 * 60) {
            return $this->render('sportif/confirmation.html.twig', [
                'error' => 'Le lien de confirmation a expiré. Veuillez rejoindre l\'événement à nouveau.',
                'equipe_event' => $equipeEvent,
                'sportif' => $sportif,
            ]);
        }

        // Check if user is part of the team
        $existingParticipation = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.id = :sportifId')
            ->andWhere('u.equipe = :equipeId')
            ->setParameter('sportifId', $sportif->getId())
            ->setParameter('equipeId', $equipeEvent->getEquipe()->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if (!$existingParticipation) {
            return $this->render('sportif/confirmation.html.twig', [
                'error' => 'Vous ne participez pas à cette équipe pour cet événement.',
                'equipe_event' => $equipeEvent,
                'sportif' => $sportif,
            ]);
        }

        // Log the successful confirmation
        $this->logger->info('QR code scanned and participation confirmed for EquipeEvent ID: ' . $equipeEvent->getId());

        // Prepare messages for the confirmation page
        $welcomeMessage = sprintf(
            'Bienvenue, %s %s ! Vous avez rejoint l\'équipe %s pour l\'événement %s.',
            htmlspecialchars($sportif->getPrenom(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($sportif->getNom(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($equipeEvent->getEquipe()->getNom(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($equipeEvent->getEvent()->getNom(), ENT_QUOTES, 'UTF-8')
        );

        $confirmationMessage = 'Votre participation a été confirmée avec succès. Préparez-vous pour une expérience sportive incroyable !';

        return $this->render('sportif/confirmation.html.twig', [
            'sportif' => $sportif,
            'equipe_event' => $equipeEvent,
            'welcome_message' => $welcomeMessage,
            'confirmation_message' => $confirmationMessage,
        ]);
    }

    #[Route('/sportif/equipe-event/show/{id}', name: 'app_equipe_event_show', methods: ['GET'])]
    public function showEquipeEvent(EquipeEvent $equipeEvent): Response
    {
        return $this->render('sportif/event_details.html.twig', [
            'equipe_event' => $equipeEvent,
        ]);
    }

    private function mergeDateTime(\DateTime $date, \DateTime $time): string
    {
        return $date->format('Y-m-d') . 'T' . $time->format('H:i:s');
    }

    private function getColorForObjectif(?ObjectifCours $objectif): string
    {
        return match ($objectif) {
            ObjectifCours::ENDURANCE => '#1890ff',
            ObjectifCours::PERTE_POIDS => '#52c41a',
            ObjectifCours::PRISE_DE_MASSE => '#33FF57',
            ObjectifCours::RELAXATION => '#F033FF',
            default => '#CCCCCC',
        };
    }

    #[Route('/sportif/mes-abonnements', name: 'sportif_abonnements')]
    public function mesAbonnements(EntityManagerInterface $em): Response
    {
        $sportif = $this->getUser();
        $paiements = $em->getRepository(Paiement::class)->findBy(['user' => $sportif, 'status' => 'succeeded']);
        
        return $this->render('sportif/mes_abonnements.html.twig', [
            'paiements' => $paiements,
        ]);
    }
    #[Route('/profil-sportif/{id}', name: 'profil_sportif')]
    public function showSportif(int $id, EntityManagerInterface $entityManager): Response
    {
        $sportif = $entityManager->getRepository(User::class)->find($id);

        if (!$sportif) {
            throw $this->createNotFoundException('Sportif non trouvé.');
        }

        return $this->render('sportif/profil.html.twig', [
            'sportif' => $sportif,
        ]);
    }
    #[Route('/notifications/unread', name: 'notifications_unread', methods: ['GET'])]
    public function getUnreadNotifications(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([]);
        }
        
        $notifications = $em->getRepository(Notification::class)->findBy([
            'user' => $user,
            'isRead' => false
        ], ['createdAt' => 'DESC']);
        
        return new JsonResponse(array_map(function($notification) {
            return [
                'id' => $notification->getId(),
                'message' => $notification->getMessage(),
                'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }, $notifications));
    }

    #[Route('/sportif/salle/{id}/questionnaire', name: 'sportif_questionnaire', methods: ['GET', 'POST'])]
    public function questionnaire(
        Request $request,
        Salle $salle,
        AbonnementRepository $abonnementRepository,
        ActivityRepository $activityRepository,
        HttpClientInterface $httpClient
    ): Response {
        $form = $this->createForm(QuestionnaireType::class);
        $form->handleRequest($request);
        $recommendedAbonnement = null;
        $recommendationReason = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $abonnements = $abonnementRepository->findBy(['salle' => $salle]);

            // Prepare data for DeepSeek API
            $abonnementsData = array_map(function($abonnement) {
                return [
                    'id' => $abonnement->getId(),
                    'type' => $abonnement->getType()->value,
                    'tarif' => $abonnement->getTarif(),
                    'activite' => $abonnement->getActivite()->getNom(),
                    'jours_acces' => $abonnement->getJoursAcces(),
                ];
            }, $abonnements);

            // Create a prompt for DeepSeek
            $prompt = "You are a fitness subscription recommendation assistant. Based on the user's questionnaire and the available subscriptions, recommend the best subscription plan and provide a reason for your choice. Return the response in JSON format with `recommended_abonnement_id` and `reason` fields.\n\n" .
                      "User Questionnaire:\n" .
                      "- Fitness Goal: {$data['fitness_goal']}\n" .
                      "- Preferred Activities: {$data['activity']}\n" .
                      "- Weekly Availability: {$data['weekly_availability']} days\n" .
                      "- Budget: {$data['budget']} DT\n\n" .
                      "Available Subscriptions:\n" . json_encode($abonnementsData, JSON_PRETTY_PRINT) . "\n\n" .
                      "Please analyze the user's preferences and select the subscription that best matches their needs. Ensure the recommendation is within the user's budget and aligns with their fitness goal and preferred activities. Return only the JSON response.";

            try {
                // Call DeepSeek API
                $response = $httpClient->request('POST', 'https://api.deepseek.com/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->getParameter('deepseek_api_key'),
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'deepseek-r1',
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are a helpful assistant that provides JSON responses.'],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'response_format' => ['type' => 'json_object'],
                    ],
                ]);

                $result = $response->toArray();
                $content = json_decode($result['choices'][0]['message']['content'], true);

                $recommendedAbonnementId = $content['recommended_abonnement_id'] ?? null;
                $recommendationReason = $content['reason'] ?? 'No reason provided by AI.';

                if ($recommendedAbonnementId) {
                    $recommendedAbonnement = $abonnementRepository->find($recommendedAbonnementId);
                    if (!$recommendedAbonnement) {
                        $this->addFlash('error', 'Recommended subscription not found.');
                    }
                } else {
                    $this->addFlash('error', 'No recommendation received from AI.');
                }
            } catch (\Exception $e) {
                $this->logger->error('DeepSeek API call failed: ' . $e->getMessage());
                $this->addFlash('error', 'Failed to get recommendation from AI.');
            }
        }

        // Fetch all activities for the activity dropdown
        $activities = $activityRepository->findAll();

        return $this->render('sportif/questionnaire.html.twig', [
            'salle' => $salle,
            'form' => $form->createView(),
            'recommended_abonnement' => $recommendedAbonnement,
            'recommendation_reason' => $recommendationReason,
            'activities' => $activities,
        ]);
    }


    #[Route('/sportif/cours', name: 'cours_sportif')]
    public function coursSportif(CoursRepository $coursRepository): Response
    {
        $cours = $coursRepository->findAll();

        return $this->render('sportif/cours.html.twig', [
            'cours' => $cours,
        ]);
    }

    private function getDuration(string $type): string
    {
        return match ($type) {
            'mois' => '30 days',
            'trimestre' => '90 days',
            'année' => '365 days',
            default => 'Custom',
        };
    }

    #[Route('/api/sportif/salle/{id}/recommendation', name: 'api_sportif_recommendation', methods: ['POST'])]
    public function apiRecommendation(
        Request $request,
        Salle $salle,
        AbonnementRepository $abonnementRepository,
        HttpClientInterface $httpClient
    ): JsonResponse {
        try {
            // 1. Get and validate input
            $data = json_decode($request->getContent(), true);
            if (!$data) {
                throw new \InvalidArgumentException('Invalid JSON data');
            }
    
            $requiredFields = ['fitness_goal', 'budget', 'activity', 'weekly_availability'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new \InvalidArgumentException("Missing required field: $field");
                }
            }
    
            // 2. Get available subscriptions
            $abonnements = $abonnementRepository->findBy(['salle' => $salle]);
            if (empty($abonnements)) {
                throw new \RuntimeException('No subscriptions available for this gym');
            }
    
            // 3. Cache response
            $cache = new FilesystemAdapter();
            $cacheKey = 'recommendation_' . md5(json_encode($data) . $salle->getId());
            $cachedResult = $cache->get($cacheKey, function (ItemInterface $item) use ($data, $salle, $abonnementRepository, $httpClient) {
                $item->expiresAfter(3600); // Cache for 1 hour

                // 4. Prepare DeepSeek prompt
                $abonnementsData = array_map(function($abonnement) {
                    return [
                        'id' => $abonnement->getId(),
                        'type' => $abonnement->getType()->value,
                        'tarif' => $abonnement->getTarif(),
                        'activite' => $abonnement->getActivite()->getNom(),
                        'jours_acces' => $abonnement->getJoursAcces(),
                    ];
                }, $abonnements);
    
                $prompt = [
                    "role" => "system",
                    "content" => "You're a fitness subscription recommendation assistant. Analyze the user's needs and recommend the most suitable subscription from the options provided. Return JSON with: recommended_abonnement_id, reason, match_score (0-100)."
                ];
    
                $userPrompt = [
                    "role" => "user",
                    "content" => json_encode([
                        'user_data' => $data,
                        'available_subscriptions' => $abonnementsData
                    ], JSON_PRETTY_PRINT)
                ];
    
                // 5. Call DeepSeek API
                $response = $httpClient->request('POST', 'https://api.deepseek.com/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->getParameter('deepseek_api_key'),
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'deepseek-r1',
                        'messages' => [$prompt, $userPrompt],
                        'response_format' => ['type' => 'json_object'],
                        'temperature' => 0.3,
                    ],
                    'timeout' => 15,
                ]);
    
                // 6. Process response
                $result = $response->toArray();
                $content = json_decode($result['choices'][0]['message']['content'], true);
    
                if (!isset($content['recommended_abonnement_id'])) {
                    throw new \RuntimeException('Invalid response format from DeepSeek');
                }
    
                $abonnement = $abonnementRepository->find($content['recommended_abonnement_id']);
                if (!$abonnement) {
                    throw new \RuntimeException('Recommended subscription not found');
                }
    
                return [
                    'success' => true,
                    'abonnement_id' => $abonnement->getId(),
                    'type' => $abonnement->getType()->value,
                    'activity' => $abonnement->getActivite()->getNom(),
                    'price' => $abonnement->getTarif(),
                    'duration' => $this->getDuration($abonnement->getType()->value),
                    'message' => $content['reason'] ?? 'Recommended based on your preferences',
                    'match_score' => $content['match_score'] ?? null,
                ];
            });

            return $this->json($cachedResult);
    
        } catch (\Exception $e) {
            $this->logger->error('Recommendation failed: ' . $e->getMessage());
            
            // Fallback: return cheapest subscription
            $cheapest = $abonnementRepository->findOneBy(['salle' => $salle], ['tarif' => 'ASC']);
            
            if ($cheapest) {
                return $this->json([
                    'success' => false,
                    'fallback' => true,
                    'abonnement_id' => $cheapest->getId(),
                    'type' => $cheapest->getType()->value,
                    'activity' => $cheapest->getActivite()->getNom(),
                    'price' => $cheapest->getTarif(),
                    'duration' => $this->getDuration($cheapest->getType()->value),
                    'message' => 'Fallback recommendation: ' . $e->getMessage()
                ]);
            }
    
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/test-deepseek', name: 'test_deepseek')]
    public function testDeepSeek(HttpClientInterface $httpClient): Response
    {
        try {
            $apiKey = $this->getParameter('deepseek_api_key');
            
            // Simple test request
            $response = $httpClient->request('POST', 'https://api.deepseek.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'deepseek-r1',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Just say "TEST PASSED"']
                    ],
                    'max_tokens' => 10,
                ],
                'timeout' => 10,
            ]);
    
            $statusCode = $response->getStatusCode();
            $content = $response->toArray();
    
            return new Response(
                "DeepSeek Connection Test:<br><br>" .
                "Status: $statusCode<br>" .
                "Response: " . json_encode($content, JSON_PRETTY_PRINT)
            );
    
        } catch (\Exception $e) {
            $apiKey = $this->getParameter('deepseek_api_key'); // Ensure $apiKey is defined
            return new Response(
                "DeepSeek Connection Failed:<br><br>" .
                "Error: " . $e->getMessage() . "<br><br>" .
                "API Key: " . substr($apiKey, 0, 3) . '...' . substr($apiKey, -3) . "<br>" .
                "Key Length: " . strlen($apiKey) . " characters"
            );
        }
    }
}