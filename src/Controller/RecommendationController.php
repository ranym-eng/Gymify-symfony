<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class RecommendationController extends AbstractController
{
    #[Route('/recommendations', name: 'recommendations')]
    public function index(Security $security): Response
    {
        /** @var \App\Entity\Sportif $sportif */
        $sportif = $security->getUser();

        // Vérifier si l'utilisateur est connecté
        if (!$sportif) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour voir les recommandations.');
        }

        // Extraire l'ID de l'utilisateur
        $userId = $sportif->getId();

        return $this->render('recommendation/index.html.twig', [
            'userId' => $userId,
        ]);
    }
}