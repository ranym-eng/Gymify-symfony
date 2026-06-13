<?php

namespace App\Controller;

use App\Entity\EquipeEvent;
use App\Entity\Events;
use App\Entity\Equipe;
use App\Form\EventsType;
use App\Form\EquipeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

class EquipeEventController extends AbstractController
{
    private $logger;
    private $entityManager;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager)
    {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
    }

    #[Route('/equipe/event', name: 'app_equipe_event', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Fetch events that have associated EquipeEvent entries
        $queryBuilder = $this->entityManager->getRepository(Events::class)
            ->createQueryBuilder('e')
            ->innerJoin('App\Entity\EquipeEvent', 'ee', 'WITH', 'ee.event = e.id')
            ->groupBy('e.id'); // Group by event to avoid duplicates

        $query = $queryBuilder->getQuery();

        // Create Pagerfanta instance for events
        $adapter = new QueryAdapter($query);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(6); // Items per page
        $pagerfanta->setCurrentPage($request->query->getInt('page', 1)); // Current page, default to 1

        // Fetch EquipeEvent entries for the current page's events to get associated teams
        $events = $pagerfanta->getCurrentPageResults();
        $eventIds = array_map(fn($event) => $event->getId(), iterator_to_array($events));
        
        $equipeEvents = [];
        if (!empty($eventIds)) {
            $equipeEvents = $this->entityManager->getRepository(EquipeEvent::class)
                ->createQueryBuilder('ee')
                ->where('ee.event IN (:eventIds)')
                ->setParameter('eventIds', $eventIds)
                ->getQuery()
                ->getResult();
        }

        // Group EquipeEvent by Event
        $groupedEquipeEvents = [];
        foreach ($equipeEvents as $ee) {
            $eventId = $ee->getEvent()->getId();
            if (!isset($groupedEquipeEvents[$eventId])) {
                $groupedEquipeEvents[$eventId] = [
                    'event' => $ee->getEvent(),
                    'equipeEvents' => [],
                ];
            }
            $groupedEquipeEvents[$eventId]['equipeEvents'][] = $ee;
        }

        return $this->render('equipe_event/index.html.twig', [
            'grouped_equipe_events' => $groupedEquipeEvents,
            'pager' => $pagerfanta,
            'page_title' => 'List of Teams Events',
        ]);
    }

    #[Route('/equipe/event/{id}', name: 'app_equipe_event_show1', methods: ['GET'])]
    public function show(EquipeEvent $equipeEvent): Response
    {
        return $this->render('equipe_event/show.html.twig', [
            'equipe_event' => $equipeEvent,
            'page_title' => 'Team Event Details - ' . $equipeEvent->getEvent()->getNom(),
        ]);
    }

    #[Route('/equipe/event/{id}/edit', name: 'app_equipe_event_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EquipeEvent $equipeEvent): Response
    {
        $event = $equipeEvent->getEvent();
        $form = $this->createForm(EventsType::class, $event, [
            'method' => 'POST',
            'action' => $this->generateUrl('app_equipe_event_edit', ['id' => $equipeEvent->getId()]),
            'is_edit' => true
        ]);

        $teamForm = $this->createForm(EquipeType::class, new Equipe(), [
            'method' => 'POST',
            'action' => $this->generateUrl('app_equipe_new')
        ]);

        $form->handleRequest($request);

        if ($request->isXmlHttpRequest() && $request->isMethod('GET')) {
            $equipeEvents = $this->entityManager->getRepository(EquipeEvent::class)->findBy(['event' => $event]);
            $teams = array_map(function($eqEvent) {
                $team = $eqEvent->getEquipe();
                return [
                    'id' => $team->getId(),
                    'nom' => $team->getNom(),
                    'niveau' => $team->getNiveau() ? $team->getNiveau()->value : null,
                    'nombre_membres' => $team->getNombreMembres(),
                    'imageFile' => $team->getImageUrl(),
                ];
            }, $equipeEvents);

            return new JsonResponse([
                'id' => $event->getId(),
                'nom' => $event->getNom(),
                'date' => $event->getDate()->format('Y-m-d'),
                'heure_debut' => $event->getHeureDebut()->format('H:i'),
                'heure_fin' => $event->getHeureFin()->format('H:i'),
                'type' => $event->getType() ? $event->getType()->value : null,
                'reward' => $event->getReward() ? $event->getReward()->value : null,
                'description' => $event->getDescription(),
                'lieu' => $event->getLieu(),
                'latitude' => $event->getLatitude(),
                'longitude' => $event->getLongitude(),
                'imageFile' => $event->getImageUrl(),
                'teams' => $teams
            ]);
        }

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $imageFile = $form->get('imageFile')->getData();
                $removeImage = $request->request->get('remove_image');

                if ($removeImage && $event->getImageUrl()) {
                    $oldImagePath = $this->getParameter('events_images_directory').'/'.basename($event->getImageUrl());
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                    $event->setImageUrl(null);
                }

                if ($imageFile) {
                    if ($event->getImageUrl()) {
                        $oldImagePath = $this->getParameter('events_images_directory').'/'.basename($event->getImageUrl());
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    $newFilename = uniqid().'.'.$imageFile->guessExtension();
                    try {
                        $imageFile->move(
                            $this->getParameter('events_images_directory'),
                            $newFilename
                        );
                        $event->setImageUrl('/uploads/events/'.$newFilename);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to upload image: '.$e->getMessage());
                        if ($request->isXmlHttpRequest()) {
                            return new JsonResponse([
                                'success' => false,
                                'message' => 'Failed to upload image: ' . $e->getMessage(),
                            ], Response::HTTP_BAD_REQUEST);
                        }
                        $this->addFlash('error', 'Failed to upload image.');
                        return $this->render('equipe_event/edit.html.twig', [
                            'equipe_event' => $equipeEvent,
                            'form' => $form->createView(),
                            'team_form' => $teamForm->createView(),
                            'page_title' => 'Edit Team Event',
                        ]);
                    }
                }

                $latitude = $event->getLatitude();
                $longitude = $event->getLongitude();
                if ($latitude === null || $longitude === null) {
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Please select a location.',
                        ], Response::HTTP_BAD_REQUEST);
                    }
                    $this->addFlash('error', 'Please select a location.');
                    return $this->render('equipe_event/edit.html.twig', [
                        'equipe_event' => $equipeEvent,
                        'form' => $form->createView(),
                        'team_form' => $teamForm->createView(),
                        'page_title' => 'Edit Team Event',
                    ]);
                }

                $teamsData = $request->request->get('teams');
                if (!$teamsData || empty(json_decode($teamsData, true))) {
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'At least one team is required.',
                        ], Response::HTTP_BAD_REQUEST);
                    }
                    $this->addFlash('error', 'At least one team is required.');
                    return $this->render('equipe_event/edit.html.twig', [
                        'equipe_event' => $equipeEvent,
                        'form' => $form->createView(),
                        'team_form' => $teamForm->createView(),
                        'page_title' => 'Edit Team Event',
                    ]);
                }

                try {
                    $teamIds = json_decode($teamsData, true);
                    $existingEquipeEvents = $this->entityManager->getRepository(EquipeEvent::class)->findBy(['event' => $event]);
                    $existingTeamIds = array_map(fn($ee) => $ee->getEquipe()->getId(), $existingEquipeEvents);
                    $newTeamIds = is_array($teamIds) ? $teamIds : [];

                    // Detect added and removed teams
                    $addedTeamIds = array_diff($newTeamIds, $existingTeamIds);
                    $removedTeamIds = array_diff($existingTeamIds, $newTeamIds);

                    // Log added teams
                    foreach ($addedTeamIds as $teamId) {
                        $this->logger->info("New EquipeEvent added for Event ID {$event->getId()} with Team ID {$teamId}");
                    }

                    // Log removed teams
                    foreach ($removedTeamIds as $teamId) {
                        $this->logger->info("EquipeEvent removed for Event ID {$event->getId()} with Team ID {$teamId}");
                    }

                    // Remove existing EquipeEvents
                    foreach ($existingEquipeEvents as $eqEvent) {
                        $this->entityManager->remove($eqEvent);
                    }
                    $this->entityManager->flush();

                    // Add new EquipeEvents
                    if (is_array($teamIds) && !empty($teamIds)) {
                        foreach ($teamIds as $teamId) {
                            $team = $this->entityManager->getRepository(Equipe::class)->find((int)$teamId);
                            if ($team) {
                                $newEquipeEvent = new EquipeEvent();
                                $newEquipeEvent->setEvent($event);
                                $newEquipeEvent->setEquipe($team);
                                $this->entityManager->persist($newEquipeEvent);
                            } else {
                                $this->logger->warning("Team with ID $teamId not found.");
                            }
                        }
                    }

                    $this->entityManager->persist($event);
                    $this->entityManager->flush();

                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => true,
                            'message' => 'Event and team associations updated successfully!',
                            'redirect' => $this->generateUrl('app_equipe_event')
                        ]);
                    }

                    $this->addFlash('success', 'Event and team associations updated successfully!');
                    return $this->redirectToRoute('app_equipe_event');
                } catch (\Exception $e) {
                    $this->logger->error('Error updating team event: ' . $e->getMessage());
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Error updating team event: ' . $e->getMessage(),
                        ], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                    $this->addFlash('error', 'Error updating team event: ' . $e->getMessage());
                    return $this->render('equipe_event/edit.html.twig', [
                        'equipe_event' => $equipeEvent,
                        'form' => $form->createView(),
                        'team_form' => $teamForm->createView(),
                        'page_title' => 'Edit Team Event',
                    ]);
                }
            } else {
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Invalid form data.',
                        'errors' => $this->getFormErrors($form),
                    ], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('error', 'Invalid form data.');
            }
        }

        return $this->render('equipe_event/edit.html.twig', [
            'equipe_event' => $equipeEvent,
            'form' => $form->createView(),
            'team_form' => $teamForm->createView(),
            'page_title' => 'Edit Team Event',
        ]);
    }

    #[Route('/equipe/event/{id}/delete', name: 'app_equipe_event_delete', methods: ['POST'])]
    public function delete(Request $request, EquipeEvent $equipeEvent): Response
    {
        if ($this->isCsrfTokenValid('delete'.$equipeEvent->getId(), $request->request->get('_token'))) {
            try {
                $eventId = $equipeEvent->getEvent()->getId();
                $teamId = $equipeEvent->getEquipe()->getId();
                $this->entityManager->remove($equipeEvent);
                $this->entityManager->flush();
                $this->logger->info("EquipeEvent deleted for Event ID {$eventId} with Team ID {$teamId}");
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Team event association deleted successfully!',
                    ]);
                }
                $this->addFlash('success', 'Team event association deleted successfully!');
            } catch (\Exception $e) {
                $this->logger->error('Error deleting team event: ' . $e->getMessage());
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Error deleting team event: ' . $e->getMessage(),
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                $this->addFlash('error', 'Error deleting team event: ' . $e->getMessage());
            }
        } else {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid CSRF token.',
                ], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_equipe_event');
    }

    private function getFormErrors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true, true) as $error) {
            $errors[] = $error->getMessage();
        }
        foreach ($form->all() as $child) {
            if (!$child->isValid()) {
                foreach ($child->getErrors() as $error) {
                    $errors[$child->getName()] = $error->getMessage();
                }
            }
        }
        return $errors;
    }
}