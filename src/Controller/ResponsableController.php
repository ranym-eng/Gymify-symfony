<?php

namespace App\Controller;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\PaiementRepository; // Ajout du repository Paiement
use App\Repository\SalleRepository;
use App\Enum\Role;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use App\Entity\ResponsableSalle;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
final class ResponsableController extends AbstractController
{
    #[Route('/responsable', name: 'dashboard_responsable_salle')]
    public function index(): Response
    {
        $stats = [
            'events' => 150,
            'equipes' => 25,
            'salle_nom' => $this->getUser()?->getSalle()?->getNom() ?? 'Ma Salle' // Ajoutez cette ligne
        ];

        return $this->render('responsable/index.html.twig', [
            'stats' => $stats,
            'page_title' => 'Tableau de bord Responsable'
        ]);
    }

    #[Route('/responsable/home', name: 'app_responsable_home')]
    public function home(): Response
    {
        return $this->render('responsable/home.html.twig', [
            'page_title' => 'Accueil Responsable'
        ]);
    }

    #[Route('/responsable/dashboard', name: 'app_responsable_dashboard')]
    public function dashboard(): Response
    {
        $stats = [
            'events' => 150,
            'equipes' => 25,
            'salle_nom' => $this->getUser()?->getSalle()?->getNom() ?? 'Ma Salle' // Ajoutez cette ligne
        ];

        return $this->render('responsable/index.html.twig', [
            'stats' => $stats,
            'page_title' => 'Tableau de bord Responsable'
        ]);
    }

    #[Route('/responsable/profile', name: 'app_responsable_profile')]
    public function profile(): Response
  
       {
           $user = $this->getUser();
           if (!$user) {
               throw $this->createAccessDeniedException('Vous devez être connecté pour voir votre profil.');
           }
   
           return $this->render('/responsable/user/show.html.twig', [
               'user' => $user,
               'page_title' => 'Mon Profil'
           ]);
       }

    #[Route('/responsable/login', name: 'app_responsable_login')]
    public function login(): Response
    {
        return $this->render('responsable/login.html.twig', [
            'page_title' => 'Connexion Responsable'
        ]);
    }

    #[Route('/responsable/about', name: 'app_responsable_about')]
    public function about(): Response
    {
        return $this->render('responsable/about.html.twig', [
            'page_title' => 'À propos Responsable'
        ]);
    }

    #[Route('/responsable/help', name: 'app_responsable_help')]
    public function help(): Response
    {
        return $this->render('responsable/help.html.twig', [
            'page_title' => 'Aide Responsable'
        ]);
    }

    #[Route('/responsable/terms', name: 'app_responsable_terms')]
    public function terms(): Response
    {
        return $this->render('responsable/terms.html.twig', [
            'page_title' => 'Conditions d\'utilisation Responsable'
        ]);
    }
    #[Route('/responsable/mygym', name: 'app_responsable_mygym')]
    public function myGym(SalleRepository $salleRepo, PaiementRepository $paiementRepo, Request $request): Response
    {
        // 1. Vérification du rôle
        $this->denyAccessUnlessGranted('ROLE_' . strtoupper(Role::RESPONSABLE_SALLE->value));

        // 2. Vérification de l'utilisateur
        $user = $this->getUser();
        if (!$user instanceof ResponsableSalle) {
            throw $this->createAccessDeniedException();
        }

        // 3. Récupération de la salle
        $salle = $user->getSalle();
        if (!$salle) {
            $this->addFlash('warning', 'Aucune salle associée à votre compte');
            return $this->redirectToRoute('app_responsable_mygym');
        }

        // 4. Chargement des relations
        $freshSalle = $salleRepo->find($salle->getId());

        // 5. Calcul des statistiques existantes
        $stats = [
            'events' => $freshSalle->getEvents() instanceof \Countable 
                ? $freshSalle->getEvents()->count() 
                : 0,
            'equipes' => method_exists($freshSalle, 'getEquipes') 
                ? $freshSalle->getEquipes()->count() 
                : 0
        ];

        // 6. Création du formulaire de filtrage par période
        $form = $this->createFormBuilder()
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'required' => false,
                'data' => new \DateTime('first day of this year'),
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'required' => false,
                'data' => new \DateTime('last day of this month'),
            ])
            ->getForm();

        $form->handleRequest($request);

        // 7. Récupération des dates du formulaire ou valeurs par défaut
        $startDate = $form->get('startDate')->getData() ?? new \DateTime('first day of this year');
        $endDate = $form->get('endDate')->getData() ?? new \DateTime('last day of this month');

        // 8. Récupération des données pour le graphique
        $paiements = $paiementRepo->createQueryBuilder('p')
            ->join('p.abonnement', 'a')
            ->where('a.salle = :salle')
            ->andWhere('p.status = :status')
            ->andWhere('p.created_at BETWEEN :start AND :end')
            ->setParameter('salle', $salle)
            ->setParameter('status', 'succeeded')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('p.created_at', 'ASC')
            ->getQuery()
            ->getResult();

        // 9. Préparer les données pour le graphique par type d'abonnement
        $chartData = [
            'labels' => [], // Ex: ['Janvier 2025', 'Février 2025', ...]
            'datasets' => [
                ['label' => 'Mensuel', 'data' => [], 'backgroundColor' => 'rgba(102, 126, 234, 0.6)'],
                ['label' => 'Trimestriel', 'data' => [], 'backgroundColor' => 'rgba(255, 99, 132, 0.6)'],
                ['label' => 'Annuel', 'data' => [], 'backgroundColor' => 'rgba(75, 192, 192, 0.6)'],
            ]
        ];

        // Générer les labels pour tous les mois dans la période
        $interval = new \DateInterval('P1M');
        $period = new \DatePeriod($startDate, $interval, $endDate->modify('+1 month'));
        foreach ($period as $date) {
            $chartData['labels'][] = $date->format('F Y');
            foreach ($chartData['datasets'] as &$dataset) {
                $dataset['data'][] = 0; // Initialiser à 0 pour chaque mois
            }
        }

        // Agréger les ventes par mois et par type d'abonnement
        foreach ($paiements as $paiement) {
            $month = $paiement->getCreatedAt()->format('F Y');
            $type = $paiement->getAbonnement()->getType()->value; // 'mois', 'trimestre', 'année'
            $index = array_search($month, $chartData['labels']);
            if ($index !== false) {
                if ($type === 'mois') {
                    $chartData['datasets'][0]['data'][$index]++;
                } elseif ($type === 'trimestre') {
                    $chartData['datasets'][1]['data'][$index]++;
                } elseif ($type === 'année') {
                    $chartData['datasets'][2]['data'][$index]++;
                }
            }
        }

        // 10. Rendu du template
        return $this->render('responsable/mygym.html.twig', [
            'salle' => $freshSalle,
            'page_title' => 'Ma Salle - ' . $freshSalle->getNom(),
            'stats' => $stats,
            'chartData' => $chartData,
            'form' => $form->createView(),
        ]);
    }
}