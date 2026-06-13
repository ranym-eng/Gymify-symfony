<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use App\Enum\ObjectifCours;
use App\Entity\Cours;
use App\Notification\CoursNotification;
use App\Repository\PlanningRepository;
use App\Repository\ActivityRepository;
use App\Repository\SalleRepository;
use App\Repository\CoursRepository;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use App\Enum\Role;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;




final class CoursController extends AbstractController
{
    private $notifier;
    private $userRepository;
    private $mailer;  // Add this line


    public function __construct(NotifierInterface $notifier, UserRepository $userRepository,  MailerInterface $mailer )
    {
        $this->notifier = $notifier;
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;  // Add this line

    }

    #[Route('/cours/{idPlanning}', name: 'app_cours')]
    public function index(
        Request $request,
        CoursRepository $coursRepository,
        PlanningRepository $planningRepository,
        $idPlanning = null
    ): Response {
        $planning = $planningRepository->find($idPlanning);
    
        if (!$planning) {
            throw $this->createNotFoundException('Planning non trouvé');
        }
    
        // Générer toutes les dates entre dateDebut et dateFin du planning
        $joursCalendrier = [];
        $dateDebut = $planning->getDateDebut();
        $dateFin = $planning->getDateFin();
    
        // Vérifier que les dates sont valides
        if ($dateDebut && $dateFin) {
            $currentDate = clone $dateDebut;
            while ($currentDate <= $dateFin) {
                $joursCalendrier[] = clone $currentDate;
                $currentDate->modify('+1 day');
            }
        }
    
        // Récupérer les cours associés à ce planning
        $cours = $coursRepository->findBy(['planning' => $planning]);
        $datesAvecCours = array_map(function ($cour) {
            return $cour->getDateDebut()->format('Y-m-d');
        }, $cours);
    
        // Calculer l'état de chaque cours
        $now = new \DateTime();
        foreach ($cours as $cour) {
            $dateDebut = $cour->getDateDebut();
            $heureDebut = $cour->getHeurDebut();
            $heureFin = $cour->getHeurFin();
    
            // Combiner la date et l'heure de début pour une comparaison précise
            $coursDateTimeDebut = clone $dateDebut;
            $coursDateTimeDebut->setTime($heureDebut->format('H'), $heureDebut->format('i'));
    
            // Combiner la date et l'heure de fin
            $coursDateTimeFin = clone $dateDebut;
            $coursDateTimeFin->setTime($heureFin->format('H'), $heureFin->format('i'));
    
            if ($now > $coursDateTimeFin) {
                // Cours terminé
                $cour->etat = 'Terminé';
            } elseif ($now >= $coursDateTimeDebut && $now <= $coursDateTimeFin) {
                // Cours en cours
                $interval = $now->diff($coursDateTimeDebut);
                $minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
                $cour->etat = sprintf('Début il y a %d minute%s', $minutes, $minutes > 1 ? 's' : '');
            } elseif ($coursDateTimeDebut->format('Y-m-d') === $now->format('Y-m-d')) {
                // Cours aujourd'hui
                $interval = $now->diff($coursDateTimeDebut);
                $hours = ($interval->days * 24) + $interval->h + ($interval->i / 60);
                $cour->etat = sprintf('Début dans %d heure%s', (int)$hours, $hours >= 2 ? 's' : '');
            } else {
                // Cours à venir
                $interval = $now->diff($coursDateTimeDebut);
                $days = $interval->days;
                $cour->etat = sprintf('Début dans %d jour%s', $days, $days > 1 ? 's' : '');
            }
        }
    
        return $this->render('cours/index.html.twig', [
            'page_title' => 'Liste des cours',
            'cours' => $cours,
            'planning' => $planning,
            'joursCalendrier' => $joursCalendrier,
            'idPlanning' => $idPlanning,
            'datesAvecCours' => $datesAvecCours,
        ]);
    }
    #[Route('/cours/new/{idPlanning}', name: 'app_cours_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        $idPlanning = null,
        SalleRepository $salleRepository,
        PlanningRepository $planningRepository,
        ActivityRepository $activityRepository
    ): Response {
        $planning = $planningRepository->find($idPlanning);

        return $this->render('cours/new.html.twig', [
            'page_title' => 'Add New Courses',
            'objectifs' => ObjectifCours::cases(),
            'activites' => $activityRepository->findAll(),
            'salles' => $salleRepository->findAll(),
            'idPlanning' => $idPlanning,
            'planning_start' => $planning->getDateDebut()->format('Y-m-d'),
            'planning_end' => $planning->getDateFin()->format('Y-m-d'),
            'planning' => $planning,
        ]);
    }

    #[Route('/cours/create/{idPlanning}', name: 'app_cours_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        PlanningRepository $planningRepo,
        ActivityRepository $activiteRepo,
        SalleRepository $salleRepo,
        Security $security,
        ValidatorInterface $validator,
        $idPlanning = null
    ): Response {
        // Vérifier l'utilisateur connecté
        $user = $security->getUser();
        if (!$user || !in_array('ROLE_ENTRAINEUR', $user->getRoles())) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à créer un cours.');
            return $this->redirectToRoute('app_cours', ['idPlanning' => $idPlanning]);
        }

        // Vérifier le planning
        $planning = $planningRepo->find($idPlanning);
        if (!$planning) {
            $this->addFlash('error', 'Planning non trouvé.');
            return $this->redirectToRoute('app_cours_new', ['idPlanning' => $idPlanning]);
        }

        // Créer le cours
        $cours = new Cours();

        // Valider et assigner les champs
        $title = $request->request->get('title');
        if (!$title) {
            $this->addFlash('error', 'Le titre est requis.');
            return $this->redirectToRoute('app_cours_new', ['idPlanning' => $idPlanning]);
        }
        $cours->setTitle($title);

        $description = $request->request->get('description');
        if (!$description) {
            $this->addFlash('error', 'La description est requise.');
            return $this->redirectToRoute('app_cours_new', ['idPlanning' => $idPlanning]);
        }
        $cours->setDescription($description);

        try {
            $cours->setObjectif(ObjectifCours::from($request->request->get('objectif')));
        } catch (\ValueError $e) {
            $this->addFlash('error', 'Objectif invalide.');
            return $this->redirectToRoute('app_cours_new', ['idPlanning' => $idPlanning]);
        }

        try {
            $dateDebut = new \DateTime($request->request->get('dateDebut'));
            $cours->setDateDebut($dateDebut);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Format de date invalide.');
            return $this->redirectToRoute('app_cours_new', ['idPlanning' => $idPlanning]);
        }

        $heurDebut = \DateTime::createFromFormat('H:i', $request->request->get('heurDebut'));
        if ($heurDebut === false) {
            $this->addFlash('error', 'Format de l\'heure de début invalide.');
            return $this->redirectToRoute('app_cours_new', ['idPlanning' => $idPlanning]);
        }
        $cours->setHeurDebut($heurDebut);

        $heurFin = \DateTime::createFromFormat('H:i', $request->request->get('heurFin'));
        if ($heurFin === false) {
            $this->addFlash('error', 'Format de l\'heure de fin invalide.');
            return $this->redirectToRoute('app_cours_new', ['idPlanning' => $idPlanning]);
        }
        $cours->setHeurFin($heurFin);

        // Associer les relations
        $activite = $activiteRepo->find($request->request->get('activite'));
        if (!$activite) {
            $this->addFlash('error', 'Activité invalide.');
            return $this->redirectToRoute('app_cours_new', ['idPlanning' => $idPlanning]);
        }
        $cours->setActivité($activite);

        $salle = $salleRepo->find($request->request->get('salle'));
        if (!$salle) {
            $this->addFlash('error', 'Salle invalide.');
            return $this->redirectToRoute('app_cours_new', ['idPlanning' => $idPlanning]);
        }
        $cours->setSalle($salle);

        $cours->setEntaineur($user);
        $cours->setPlanning($planning);

        // Valider l'entité
        $errors = $validator->validate($cours);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->redirectToRoute('app_cours_new', ['idPlanning' => $idPlanning]);
        }

        // Enregistrer
        $entityManager->persist($cours);
        $entityManager->flush();

        // Envoyer la notification
        $this->sendNotificationsToSportifs($cours, 'created');

        $this->addFlash('success', 'Cours créé avec succès !');
        return $this->redirectToRoute('app_cours', ['idPlanning' => $idPlanning]);
    }

    #[Route('/delete/{id}', name: 'app_cours_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Cours $cour,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$cour->getPlanning()) {
            throw new \RuntimeException('Aucun planning associé à ce cours');
        }
        $idPlanning = $cour->getPlanning()->getId();

        if ($this->isCsrfTokenValid('delete' . $cour->getId(), $request->request->get('_token'))) {
            // Envoyer la notification avant la suppression
            $this->sendNotificationsToSportifs($cour, 'deleted');

            $entityManager->remove($cour);
            $entityManager->flush();

            $this->addFlash('success', 'Le cours a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_cours', ['idPlanning' => $idPlanning]);
    }

    #[Route('/cours/edit/{id}', name: 'app_cours_edit')]
    public function edit(
        Request $request,
        Cours $cour,
        ActivityRepository $activiteRepository,
        SalleRepository $salleRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérification du planning
        $planning = $cour->getPlanning();

        if (!$planning) {
            $this->addFlash('error', 'Ce cours n\'est associé à aucun planning');
            return $this->redirectToRoute('app_cours_index');
        }

        return $this->render('cours/edit.html.twig', [
            'page_title' => 'Modifier le cours : ' . $cour->getTitle(),
            'cour' => $cour,
            'objectifs' => ObjectifCours::cases(),
            'activites' => $activiteRepository->findAll(),
            'salles' => $salleRepository->findAll(),
            'idPlanning' => $planning->getId(),
            'planning_start' => $planning->getDateDebut() ? $planning->getDateDebut()->format('Y-m-d') : null,
            'planning_end' => $planning->getDateFin() ? $planning->getDateFin()->format('Y-m-d') : null,
            'planning' => $planning,
        ]);
    }

    #[Route('/cours/update/{id}', name: 'app_cours_Update', methods: ['POST'])]
    public function update(
        Request $request,
        Cours $cours,
        EntityManagerInterface $entityManager,
        ActivityRepository $activiteRepo,
        SalleRepository $salleRepo,
        ValidatorInterface $validator
    ): Response {
        // Récupérer les données du formulaire
        $title = $request->request->get('title');
        if (!$title) {
            $this->addFlash('error', 'Le titre est requis.');
            return $this->redirectToRoute('app_cours_edit', ['id' => $cours->getId()]);
        }
        $cours->setTitle($title);

        $description = $request->request->get('description');
        if (!$description) {
            $this->addFlash('error', 'La description est requise.');
            return $this->redirectToRoute('app_cours_edit', ['id' => $cours->getId()]);
        }
        $cours->setDescription($description);

        try {
            $cours->setObjectif(ObjectifCours::from($request->request->get('objectif')));
        } catch (\ValueError $e) {
            $this->addFlash('error', 'Objectif invalide.');
            return $this->redirectToRoute('app_cours_edit', ['id' => $cours->getId()]);
        }

        try {
            $dateDebut = new \DateTime($request->request->get('dateDebut'));
            $cours->setDateDebut($dateDebut);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Format de date invalide.');
            return $this->redirectToRoute('app_cours_edit', ['id' => $cours->getId()]);
        }

        $heurDebut = \DateTime::createFromFormat('H:i', $request->request->get('heurDebut'));
        if ($heurDebut === false) {
            $this->addFlash('error', 'Format de l\'heure de début invalide.');
            return $this->redirectToRoute('app_cours_edit', ['id' => $cours->getId()]);
        }
        $cours->setHeurDebut($heurDebut);

        $heurFin = \DateTime::createFromFormat('H:i', $request->request->get('heurFin'));
        if ($heurFin === false) {
            $this->addFlash('error', 'Format de l\'heure de fin invalide.');
            return $this->redirectToRoute('app_cours_edit', ['id' => $cours->getId()]);
        }
        $cours->setHeurFin($heurFin);

        // Gestion des relations
        $activite = $activiteRepo->find($request->request->get('activite'));
        if (!$activite) {
            $this->addFlash('error', 'Activité invalide.');
            return $this->redirectToRoute('app_cours_edit', ['id' => $cours->getId()]);
        }
        $cours->setActivité($activite);

        $salle = $salleRepo->find($request->request->get('salle'));
        if (!$salle) {
            $this->addFlash('error', 'Salle invalide.');
            return $this->redirectToRoute('app_cours_edit', ['id' => $cours->getId()]);
        }
        $cours->setSalle($salle);

        // Valider l'entité
        $errors = $validator->validate($cours);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->redirectToRoute('app_cours_edit', ['id' => $cours->getId()]);
        }

        try {
            // Enregistrer en base
            $entityManager->flush();

            // Envoyer la notification
            $this->sendNotificationsToSportifs($cours, 'updated');

            // Récupérer l'ID du planning pour la redirection
            $planningId = $cours->getPlanning() ? $cours->getPlanning()->getId() : null;

            if ($planningId) {
                $this->addFlash('success', 'Le cours a été modifié avec succès');
                return $this->redirectToRoute('app_cours', ['idPlanning' => $planningId]);
            } else {
                throw new \Exception('Aucun planning associé à ce cours');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
            return $this->redirectToRoute('app_cours_edit', ['id' => $cours->getId()]);
        }
    }
    private function sendNotificationsToSportifs(Cours $cours, string $action): void
    {
        // Ici vous pouvez ajouter la logique pour envoyer des notifications aux sportifs
        // Exemple : Envoyer des notifications par mail ou par le système de notification de Symfony

 $sportifs = $this->userRepository->findAll();
        foreach ($sportifs as $sportif) {
            // Exemple d'envoi d'un mail
            $email = (new Email())
                ->from('benameur808@gmail.com')
                ->to($sportif->getEmail())
                ->subject('Notification de cours ' . ucfirst($action))
                ->html($this->renderView('emails/cours_notification.html.twig', [
                'cours' => $cours,
                'action' => $action,
                 ]));
              

            $this->mailer->send($email);
        }

        // Si vous avez un système de notification en temps réel, vous pouvez l'intégrer ici.
    }
  #[Route('/test-email', name: 'app_test_email')]
    public function testEmail(MailerInterface $mailer)
    {
        $email = (new Email())
            ->from('benameur808@gmail.com')
            ->to('benameur808@gmail.com')
            ->subject('Test email')
            ->text('This is a test email');
    
        $mailer->send($email);
        return $this->json(['message' => 'Email sent']);

    }
}