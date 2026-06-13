<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Planning;
use App\Repository\PlanningRepository;
use App\Form\PlanningType;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\Attribute\Route;

final class PlanningController extends AbstractController
{
    #[Route('/planning', name: 'app_planning')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        $plannings = $entityManager->getRepository(Planning::class)->findBy(['entaineur' => $user]);
        
        return $this->render('planning/index.html.twig', [
            'plannings' => $plannings,
            'page_title' => 'Liste des plannings'
        ]);
    }

    #[Route('/planning/new', name: 'app_planning_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $planning = new Planning();
        $form = $this->createForm(PlanningType::class, $planning);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\User $user */
            $user = $security->getUser();
            $planning->setEntaineur($user);
            
            $entityManager->persist($planning);
            $entityManager->flush();

            $this->addFlash('success', 'Planning créé avec succès!');
            return $this->redirectToRoute('app_planning');
        }

        return $this->render('planning/new.html.twig', [
            'page_title' => 'Add New Planning',
            'form' => $form->createView(),
        ]);
    }

    #[Route('/planning/create', name: 'app_planning_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $planning = new Planning();
        $form = $this->createForm(PlanningType::class, $planning);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\User $user */
            $user = $security->getUser();
            $planning->setEntaineur($user);
            
            $entityManager->persist($planning);
            $entityManager->flush();

            $this->addFlash('success', 'Planning créé avec succès!');
            return $this->redirectToRoute('app_planning');
        }

        // Si le formulaire n'est pas valide, afficher les erreurs
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->render('planning/new.html.twig', [
            'page_title' => 'Add New Planning',
            'form' => $form->createView(),
        ]);
    }
    #[Route('/planning/edit/{id}', name: 'app_planning_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Planning $planning, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PlanningType::class, $planning);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Planning mis à jour avec succès!');
            return $this->redirectToRoute('app_planning');
        }

        return $this->render('planning/edit.html.twig', [
            'page_title' => 'Edit Planning',
            'form' => $form->createView(),
            'planning' => $planning,
        ]);
    }

    #[Route('/planning/{id}', name: 'app_planning_delete', methods: ['POST'])]
    public function delete(Request $request, Planning $planning, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$planning->getId(), $request->request->get('_token'))) {
            $entityManager->remove($planning);
            $entityManager->flush();
            $this->addFlash('success', 'Planning supprimé avec succès');
        }

        return $this->redirectToRoute('app_planning');
    }

    #[Route('/planning/{id}/update', name: 'app_planning_update', methods: ['POST'])]
    public function update(Request $request, Planning $planning, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PlanningType::class, $planning);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Planning mis à jour avec succès');
            return $this->redirectToRoute('app_planning');
        }

        // Si le formulaire n'est pas valide, afficher les erreurs
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->render('planning/edit.html.twig', [
            'page_title' => 'Edit Planning',
            'form' => $form->createView(),
            'planning' => $planning,
        ]);
    }
}
