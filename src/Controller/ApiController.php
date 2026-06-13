<?php

namespace App\Controller;

use App\Entity\Infosportif;
use App\Repository\InfosportifRepository;
use App\Repository\CoursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/users/{id}', name: 'api_user', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getUserData(int $id, InfosportifRepository $infosportifRepository): JsonResponse
    {
        $infosportif = $infosportifRepository->findOneBy(['sportif' => $id]);

        if (!$infosportif) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }

        $data = [
            'poids' => $infosportif->getPoids(),
            'taille' => $infosportif->getTaille(),
            'age' => $infosportif->getAge(),
            'sexe' => $infosportif->getSexe(),
            'objectif' => $infosportif->getObjectif(),
        ];

        return new JsonResponse($data);
    }

    #[Route('/courses', name: 'api_courses', methods: ['GET'])]
    public function getCourses(CoursRepository $coursRepository): JsonResponse
    {
        $courses = $coursRepository->findAll();

        $data = array_map(function ($cours) {
            return [
                'title' => $cours->getTitle(),
                'description' => $cours->getDescription(),
                'heurDebut' => $cours->getHeurDebut()->format('H:i'),
                'heurFin' => $cours->getHeurFin()->format('H:i'),
                'objectif' => $cours->getObjectif()->value,
            ];
        }, $courses);

        return new JsonResponse($data);
    }
}