<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ActivityRepository; 
use App\Entity\Activité; 
use App\Entity\Reponse;
use App\Form\ReponseType;
use App\Repository\UserRepository;
use App\Repository\ReclamationRepository;
use App\Repository\ReponseRepository;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(UserRepository $userRepository): Response
    
    {
       $this->denyAccessUnlessGranted('ROLE_ADMIN');
      $stats = [
        'visitors' => 1294,
        'subscribers' => 1303,
        'sales' => 1345,
        'usersByRole' => $userRepository->getUserCountByRole(),
        'orders' => 576
    ];

    return $this->render('admin/index.html.twig', [
        'stats' => $stats,
        'page_title' => 'Tableau de bord'
    ]);
    }
    #[Route('/home', name: 'app_home')]
    public function home(): Response
    {
        return new Response('Welcome to the home page');
    }
       #[Route('/dashboard', name:'app_dashboard')]
      public function dashboard(UserRepository $userRepository): Response
      
      {
        $totalUsers = $userRepository->createQueryBuilder('u')
    ->select('COUNT(u.id)')
    ->getQuery()
    ->getSingleScalarResult();

        $stats = [
          'visitors' => (int) $totalUsers,
          'subscribers' => 1303,
          'sales' => 1345,
          'orders' => 576,
          'usersByRole' => $userRepository->getUserCountByRole(),
      ];
  
      return $this->render('admin/index.html.twig', [
          'stats' => $stats,
          'page_title' => 'Tableau de bord'
      ]);
        
    
       }
       #[Route('/admin/reclamation', name: 'app_admin_reclamation_index', methods: ['GET', 'POST'])]
       #[IsGranted('ROLE_ADMIN')]
       public function reclamation(Request $request, ReclamationRepository $reclamationRepository, ReponseRepository $reponseRepository, EntityManagerInterface $entityManager): Response
       {
           $reclamations = $reclamationRepository->findAll();
           $reponses = $reponseRepository->findAll();
           $reponse = new Reponse();
           $form = $this->createForm(ReponseType::class, $reponse);
           $form->handleRequest($request);
   
           if ($form->isSubmitted() && $form->isValid()) {
               $selectedReclamations = $request->request->get('selected_reclamations', []);
               if (empty($selectedReclamations)) {
                   $this->addFlash('warning', 'Veuillez sélectionner au moins une réclamation.');
                   return $this->redirectToRoute('app_admin_reclamation_index');
               }
   
               foreach ($selectedReclamations as $reclamationId) {
                   $reclamation = $reclamationRepository->find($reclamationId);
                   if ($reclamation) {
                       $newReponse = new Reponse();
                       $newReponse->setMessage($reponse->getMessage())
                                  ->setDateReponse(new \DateTime())
                                  ->setAdmin($this->getUser())
                                  ->setReclamation($reclamation);
                       $reclamation->setStatut('Traitée');
                       $entityManager->persist($newReponse);
                       $entityManager->persist($reclamation); // Ensure status update is persisted
                   }
               }
               $entityManager->flush();
   
               $this->addFlash('success', 'Réponses envoyées avec succès !');
               return $this->redirectToRoute('app_admin_reclamation_index');
           }
   
           return $this->render('admin_reclamation/index.html.twig', [
               'reclamations' => $reclamations,
               'reponses' => $reponses,
               'form' => $form->createView(),
               'page_title' => 'Gestion des Réclamations'
           ]);
       }
   
       #[Route('/admin/reclamation/delete-reponses', name: 'app_admin_reponse_delete', methods: ['POST'])]
       #[IsGranted('ROLE_ADMIN')]
       public function deleteReponses(Request $request, ReponseRepository $reponseRepository, EntityManagerInterface $entityManager): Response
       {
           $selectedReponses = $request->request->get('selected_reponses', []);
           if (empty($selectedReponses)) {
               $this->addFlash('warning', 'Veuillez sélectionner au moins une réponse.');
               return $this->redirectToRoute('app_admin_reclamation_index');
           }
   
           foreach ($selectedReponses as $reponseId) {
               $reponse = $reponseRepository->find($reponseId);
               if ($reponse) {
                   $reponse->getReclamation()->setStatut('En attente');
                   $reponseRepository->remove($reponse);
               }
           }
           $entityManager->flush();
   
           $this->addFlash('success', 'Réponses supprimées avec succès !');
           return $this->redirectToRoute('app_admin_reclamation_index');
       }
   
    

       #[Route('/profile', name: 'app_profile')]
       public function profile(): Response
       {
           $user = $this->getUser();
           if (!$user) {
               throw $this->createAccessDeniedException('Vous devez être connecté pour voir votre profil.');
           }
   
           return $this->render('user/show.html.twig', [
               'user' => $user,
               'page_title' => 'Mon Profil'
           ]);
       }
       #[Route('/login', name:'app_login')]
      public function login()
      {
    
       }
       #[Route('/about', name:'app_about')]
      public function about()
      {
    
       }
       #[Route('/help', name:'app_help')]
      public function help()
      {
    
       }
       #[Route('/term', name:'app_terms')]
       public function term()
       {
     
        }
        #[Route('/admin/user', name:'user_index')]
       public function user()
       {
        return $this->render('user/index.html.twig', [ ]);
        }
        #[Route('/admin/activity', name:'app_activity')]
       public function activity(ActivityRepository $activityRepository): Response
       { return $this->render('activity/index.html.twig', [
          'page_title' => 'Activity Dashboard',
          'activities' => $activityRepository->findAll(),
          'stats' => [
                'visitors' => 1294,
                'subscribers' => 1303,
                'sales' => 1345,
                'orders' => 576
            ]
      ]);
       }
     
  }


       

    
    

