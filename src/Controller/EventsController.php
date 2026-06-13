<?php

namespace App\Controller;

use App\Entity\Events;
use App\Entity\Salle;
use App\Entity\Equipe;
use App\Entity\EquipeEvent;
use App\Form\EventsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;

class EventsController extends AbstractController
{
    private $logger;
    private $entityManager;
    private $snappy;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager, \Knp\Snappy\Pdf $snappy)
    {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->snappy = $snappy;
    }

    #[Route('/events', name: 'app_events_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $qb = $this->entityManager->getRepository(Events::class)->createQueryBuilder('e');
        $adapter = new QueryAdapter($qb);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(5);
        $pagerfanta->setCurrentPage($request->query->getInt('page', 1));

        if ($request->isXmlHttpRequest()) {
            $events = $pagerfanta->getCurrentPageResults();
            return new JsonResponse(array_map(function ($event) {
                return [
                    'id' => $event->getId(),
                    'nom' => $event->getNom(),
                    'date' => $event->getDate() ? $event->getDate()->format('Ymd') : null,
                    'heureDebut' => $event->getHeureDebut() ? $event->getHeureDebut()->format('H:i') : null,
                    'heureFin' => $event->getHeureFin() ? $event->getHeureFin()->format('H:i') : null,
                    'type' => $event->getType() ? $event->getType()->value : null,
                    'reward' => $event->getReward() ? $event->getReward()->value : null,
                    'description' => $event->getDescription(),
                    'lieu' => $event->getLieu(),
                    'latitude' => $event->getLatitude(),
                    'longitude' => $event->getLongitude(),
                    'imageUrl' => $event->getImageUrl(),
                ];
            }, iterator_to_array($events)));
        }

        return $this->render('events/index.html.twig', [
            'events' => $pagerfanta,
            'page_title' => 'Liste des événements',
        ]);
    }

    #[Route('/events/export-pdf', name: 'app_events_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request): Response
    {
        $events = $this->entityManager->getRepository(Events::class)->findAll();
        $html = $this->renderView('events/pdf.html.twig', [
            'events' => $events,
        ]);
        $filename = 'events_list_' . date('Ymd_His') . '.pdf';
        return new PdfResponse(
            $this->snappy->getOutputFromHtml($html),
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    #[Route('/events/search', name: 'app_events_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('query', '');
        $qb = $this->entityManager->getRepository(Events::class)->createQueryBuilder('e');

        if ($query) {
            $qb->where('LOWER(e.nom) LIKE :query')
               ->orWhere('LOWER(e.type) LIKE :query')
               ->orWhere('LOWER(e.lieu) LIKE :query')
               ->setParameter('query', '%' . strtolower($query) . '%');
        }

        $events = $qb->getQuery()->getResult();

        return new JsonResponse(array_map(function ($event) {
            return [
                'id' => $event->getId(),
                'nom' => $event->getNom(),
                'date' => $event->getDate() ? $event->getDate()->format('Ymd') : null,
                'heureDebut' => $event->getHeureDebut() ? $event->getHeureDebut()->format('H:i') : null,
                'heureFin' => $event->getHeureFin() ? $event->getHeureFin()->format('H:i') : null,
                'type' => $event->getType() ? $event->getType()->value : null,
                'reward' => $event->getReward() ? $event->getReward()->value : null,
                'description' => $event->getDescription(),
                'lieu' => $event->getLieu(),
                'latitude' => $event->getLatitude(),
                'longitude' => $event->getLongitude(),
                'imageUrl' => $event->getImageUrl(),
            ];
        }, $events));
    }

    #[Route('/events/new', name: 'app_events_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'L\'utilisateur doit être connecté pour créer un événement.');

        $event = new Events();
        $form = $this->createForm(EventsType::class, $event, ['is_edit' => false]);
        $teamForm = $this->createForm(\App\Form\EquipeType::class, new Equipe());
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $teamsData = $request->request->get('teams');
            $teamIds = $teamsData ? json_decode($teamsData, true) : [];
            $errors = [];

            if (empty($teamIds)) {
                $errors['teams'] = ['Au moins une équipe est requise.'];
            }

            if ($form->isValid() && empty($errors)) {
                $imageFile = $form->get('imageFile')->getData();
                if ($imageFile) {
                    $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                    try {
                        $imageFile->move(
                            $this->getParameter('events_images_directory'),
                            $newFilename
                        );
                        $event->setImageUrl('/Uploads/events/' . $newFilename);
                    } catch (\Exception $e) {
                        $this->logger->error('Échec du téléchargement de l\'image : ' . $e->getMessage());
                        if ($request->isXmlHttpRequest()) {
                            return new JsonResponse([
                                'success' => false,
                                'message' => 'Échec du téléchargement de l\'image.',
                                'errors' => ['imageFile' => ['Échec du téléchargement de l\'image.']],
                            ], Response::HTTP_BAD_REQUEST);
                        }
                        $this->addFlash('error', 'Échec du téléchargement de l\'image.');
                        return $this->render('events/new.html.twig', [
                            'form' => $form->createView(),
                            'team_form' => $teamForm->createView(),
                            'page_title' => 'Créer un nouvel événement',
                        ]);
                    }
                }

                $user = $this->getUser();
                if ($user) {
                    $salle = $this->entityManager->getRepository(Salle::class)->findOneBy(['responsable' => $user]);
                    if ($salle) {
                        $event->setSalle($salle);
                    }
                }

                try {
                    $this->entityManager->persist($event);
                    $this->entityManager->flush();

                    foreach ($teamIds as $teamId) {
                        $team = $this->entityManager->getRepository(Equipe::class)->find((int)$teamId);
                        if ($team) {
                            $equipeEvent = new EquipeEvent();
                            $equipeEvent->setEvent($event);
                            $equipeEvent->setEquipe($team);
                            $this->entityManager->persist($equipeEvent);
                        } else {
                            $this->logger->warning("Équipe avec l'ID $teamId introuvable.");
                        }
                    }
                    $this->entityManager->flush();

                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => true,
                            'message' => 'Event and team association added successfully!',
                            'eventId' => $event->getId(),
                        ]);
                    }
                    $this->addFlash('success', 'Event and team association added successfully!');
                    return $this->redirectToRoute('app_events_index');
                } catch (\Exception $e) {
                    $this->logger->error('Erreur lors de l\'enregistrement de l\'événement : ' . $e->getMessage());
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Erreur lors de l\'enregistrement de l\'événement.',
                            'errors' => ['general' => ['Une erreur s\'est produite lors de l\'enregistrement de l\'événement.']],
                        ], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                    $this->addFlash('error', 'Erreur lors de l\'enregistrement de l\'événement.');
                    return $this->render('events/new.html.twig', [
                        'form' => $form->createView(),
                        'team_form' => $teamForm->createView(),
                        'page_title' => 'Créer un nouvel événement',
                    ]);
                }
            } else {
                $formErrors = $this->getFormErrors($form);
                $allErrors = array_merge($formErrors, $errors);
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Données de formulaire non valides.',
                        'errors' => $allErrors,
                    ], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('error', 'Échec de la création de l\'événement.');
                return $this->render('events/new.html.twig', [
                    'form' => $form->createView(),
                    'team_form' => $teamForm->createView(),
                    'page_title' => 'Créer un nouvel événement',
                ]);
            }
        }

        return $this->render('events/new.html.twig', [
            'form' => $form->createView(),
            'team_form' => $teamForm->createView(),
            'page_title' => 'Créer un nouvel événement',
        ]);
    }

    #[Route('/events/{id}/edit', name: 'app_events_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ?Events $event): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'L\'utilisateur doit être connecté pour modifier un événement.');

        if (!$event) {
            $this->addFlash('error', 'Événement non trouvé.');
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Événement non trouvé.',
                    'errors' => ['general' => ['Événement non trouvé.']],
                ], Response::HTTP_NOT_FOUND);
            }
            return $this->redirectToRoute('app_events_index');
        }

        $form = $this->createForm(EventsType::class, $event, [
            'method' => 'POST',
            'action' => $this->generateUrl('app_events_edit', ['id' => $event->getId()]),
            'is_edit' => true,
        ]);

        if ($request->isXmlHttpRequest() && $request->isMethod('GET')) {
            $equipeEvents = $this->entityManager->getRepository(EquipeEvent::class)->findBy(['event' => $event]);
            $teams = array_map(function ($equipeEvent) {
                return [
                    'id' => $equipeEvent->getEquipe()->getId(),
                    'nom' => $equipeEvent->getEquipe()->getNom(),
                ];
            }, $equipeEvents);

            return new JsonResponse([
                'id' => $event->getId(),
                'nom' => $event->getNom(),
                'date' => $event->getDate() ? $event->getDate()->format('Ymd') : null,
                'heure_debut' => $event->getHeureDebut() ? $event->getHeureDebut()->format('H:i') : null,
                'heure_fin' => $event->getHeureFin() ? $event->getHeureFin()->format('H:i') : null,
                'type' => $event->getType() ? $event->getType()->value : null,
                'reward' => $event->getReward() ? $event->getReward()->value : null,
                'description' => $event->getDescription(),
                'lieu' => $event->getLieu(),
                'latitude' => $event->getLatitude(),
                'longitude' => $event->getLongitude(),
                'imageUrl' => $event->getImageUrl(),
                'teams' => $teams,
            ]);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            $removeImage = $request->request->get('remove_image');

            if ($removeImage && $event->getImageUrl()) {
                $oldImagePath = $this->getParameter('events_images_directory') . '/' . basename($event->getImageUrl());
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
                $event->setImageUrl(null);
            }

            if ($imageFile) {
                if ($event->getImageUrl()) {
                    $oldImagePath = $this->getParameter('events_images_directory') . '/' . basename($event->getImageUrl());
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move(
                        $this->getParameter('events_images_directory'),
                        $newFilename
                    );
                    $event->setImageUrl('/Uploads/events/' . $newFilename);
                } catch (\Exception $e) {
                    $this->logger->error('Échec du téléchargement de l\'image : ' . $e->getMessage());
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Échec du téléchargement de l\'image.',
                            'errors' => ['imageFile' => ['Échec du téléchargement de l\'image.']],
                        ], Response::HTTP_BAD_REQUEST);
                    }
                    $this->addFlash('error', 'Échec du téléchargement de l\'image.');
                    return $this->render('events/edit.html.twig', [
                        'event' => $event,
                        'form' => $form->createView(),
                        'page_title' => 'Modifier l\'événement',
                    ]);
                }
            }

            try {
                $this->entityManager->persist($event);
                $this->entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Event updated successfully!',
                        'redirect' => $this->generateUrl('app_events_index'),
                    ]);
                }
                $this->addFlash('success', 'Event updated successfully!');
                return $this->redirectToRoute('app_events_index');
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la mise à jour de l\'événement : ' . $e->getMessage());
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Erreur lors de la mise à jour de l\'événement.',
                        'errors' => ['general' => ['Une erreur s\'est produite lors de la mise à jour de l\'événement.']],
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                $this->addFlash('error', 'Erreur lors de la mise à jour de l\'événement.');
                return $this->render('events/edit.html.twig', [
                    'event' => $event,
                    'form' => $form->createView(),
                    'page_title' => 'Modifier l\'événement',
                ]);
            }
        } elseif ($form->isSubmitted()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Données de formulaire non valides.',
                    'errors' => $this->getFormErrors($form),
                ], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('error', 'Échec de la mise à jour de l\'événement.');
            return $this->render('events/edit.html.twig', [
                'event' => $event,
                'form' => $form->createView(),
                'page_title' => 'Modifier l\'événement',
            ]);
        }

        return $this->render('events/edit.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            'page_title' => 'Modifier l\'événement',
        ]);
    }

    #[Route('/events/{id}', name: 'app_events_show', methods: ['GET'])]
    public function show(?Events $event): Response
    {
        if (!$event) {
            $this->addFlash('error', 'Événement non trouvé.');
            return $this->redirectToRoute('app_events_index');
        }

        $equipeEvents = $this->entityManager->getRepository(EquipeEvent::class)->findBy(['event' => $event]);
        $teams = array_map(fn($equipeEvent) => $equipeEvent->getEquipe(), $equipeEvents);

        return $this->render('events/show.html.twig', [
            'event' => $event,
            'teams' => $teams,
            'page_title' => 'Détails de l\'événement - ' . $event->getNom(),
        ]);
    }

    #[Route('/events/{id}/delete', name: 'app_events_delete', methods: ['POST'])]
    public function delete(Request $request, ?Events $event): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'L\'utilisateur doit être connecté pour supprimer un événement.');

        if (!$event) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Événement non trouvé.',
                    'errors' => ['general' => ['Événement non trouvé.']],
                ], Response::HTTP_NOT_FOUND);
            }
            $this->addFlash('error', 'Événement non trouvé.');
            return $this->redirectToRoute('app_events_index');
        }

        try {
            if ($event->getImageUrl()) {
                $imagePath = $this->getParameter('events_images_directory') . '/' . basename($event->getImageUrl());
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $equipeEvents = $this->entityManager->getRepository(EquipeEvent::class)->findBy(['event' => $event]);
            foreach ($equipeEvents as $equipeEvent) {
                $this->entityManager->remove($equipeEvent);
            }

            $this->entityManager->remove($event);
            $this->entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Event and team association deleted successfully!',
                ]);
            }
            $this->addFlash('success', 'Event and team association deleted successfully!');
            return $this->redirectToRoute('app_events_index');
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la suppression de l\'événement : ' . $e->getMessage());
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de la suppression de l\'événement.',
                    'errors' => ['general' => ['Une erreur s\'est produite lors de la suppression de l\'événement.']],
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $this->addFlash('error', 'Erreur lors de la suppression de l\'événement.');
            return $this->redirectToRoute('app_events_index');
        }
    }

    private function getFormErrors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $field = $error->getOrigin()->getName();
            if (!isset($errors[$field])) {
                $errors[$field] = [];
            }
            $errors[$field][] = $error->getMessage();
        }
        return $errors;
    }
}