<?php

namespace App\Controller;

use App\Entity\Equipe;
use App\Form\EquipeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

class EquipeController extends AbstractController
{
    private $entityManager;
    private $logger;
    private $validator;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger, ValidatorInterface $validator)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->validator = $validator;
    }

    #[Route('/equipe', name: 'app_equipe_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $qb = $this->entityManager->getRepository(Equipe::class)->createQueryBuilder('e');

        // Create Pagerfanta instance
        $adapter = new QueryAdapter($qb);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(5); // Limit to 5 teams per page
        $pagerfanta->setCurrentPage($request->query->getInt('page', 1));

        if ($request->isXmlHttpRequest()) {
            $equipes = $pagerfanta->getCurrentPageResults();
            return new JsonResponse(array_map(function($equipe) {
                return [
                    'id' => $equipe->getId(),
                    'nom' => $equipe->getNom(),
                    'niveau' => $equipe->getNiveau() ? $equipe->getNiveau()->value : null,
                    'nombre_membres' => $equipe->getNombreMembres(),
                    'imageUrl' => $equipe->getImageUrl(),
                ];
            }, iterator_to_array($equipes)));
        }

        return $this->render('equipe/index.html.twig', [
            'equipes' => $pagerfanta,
            'page_title' => 'List of Teams',
        ]);
    }

    #[Route('/equipe/new', name: 'app_equipe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        $equipe = new Equipe();
        $form = $this->createForm(EquipeType::class, $equipe, [
            'csrf_protection' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $uploadDir = $this->getParameter('teams_images_directory');
                $filesystem = new Filesystem();

                try {
                    if (!$filesystem->exists($uploadDir)) {
                        $filesystem->mkdir($uploadDir, 0775);
                    }
                    if (!is_writable($uploadDir)) {
                        throw new \Exception('Upload directory is not writable: ' . $uploadDir);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Failed to create or verify upload directory: ' . $e->getMessage());
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Server configuration error: Upload directory is not accessible.',
                        ], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                    $this->addFlash('error', 'Server configuration error: Upload directory is not accessible.');
                    return $this->render('equipe/new.html.twig', [
                        'form' => $form->createView(),
                        'page_title' => 'Add New Team',
                    ]);
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $equipe->setImageUrl($this->getParameter('teams_images_base_url') . $newFilename);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to upload image for new team: ' . $e->getMessage());
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Failed to upload image: ' . $e->getMessage(),
                        ], Response::HTTP_BAD_REQUEST);
                    }
                    $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
                    return $this->render('equipe/new.html.twig', [
                        'form' => $form->createView(),
                        'page_title' => 'Add New Team',
                    ]);
                }
            }

            try {
                $this->entityManager->persist($equipe);
                $this->entityManager->flush();
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => true,
                        'teamId' => $equipe->getId(),
                        'nom' => $equipe->getNom(),
                        'niveau' => $equipe->getNiveau() ? $equipe->getNiveau()->value : null,
                        'nombre_membres' => $equipe->getNombreMembres(),
                        'imageUrl' => $equipe->getImageUrl(),
                        'message' => 'Team added successfully!',
                    ]);
                }
                $this->addFlash('success', 'Team added successfully!');
                return $this->redirectToRoute('app_equipe_index');
            } catch (\Exception $e) {
                $this->logger->error('Error saving new team: ' . $e->getMessage());
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Error saving team: ' . $e->getMessage(),
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                $this->addFlash('error', 'Error saving team: ' . $e->getMessage());
            }
        }

        if ($form->isSubmitted() && !$form->isValid() && $request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid form data.',
                'errors' => $this->getFormErrors($form),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->render('equipe/new.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Add New Team',
        ]);
    }

    #[Route('/equipe/{id}', name: 'app_equipe_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(?Equipe $equipe, int $id): Response
    {
        if (!$equipe) {
            throw new NotFoundHttpException("Team with ID $id not found.");
        }

        return $this->render('equipe/show.html.twig', [
            'equipe' => $equipe,
            'page_title' => 'Team Details - ' . $equipe->getNom(),
        ]);
    }

    #[Route('/equipe/{id}/edit', name: 'app_equipe_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Equipe $equipe, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(EquipeType::class, $equipe, [
            'method' => 'POST',
            'action' => $this->generateUrl('app_equipe_edit', ['id' => $equipe->getId()]),
            'csrf_protection' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $imageFile = $form->get('imageFile')->getData();
                $removeImage = $request->request->get('remove_team_image');

                if ($removeImage && $equipe->getImageUrl()) {
                    $oldImagePath = $this->getParameter('teams_images_directory') . '/' . basename($equipe->getImageUrl());
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                    $equipe->setImageUrl(null);
                }

                if ($imageFile) {
                    $uploadDir = $this->getParameter('teams_images_directory');
                    $filesystem = new Filesystem();

                    try {
                        if (!$filesystem->exists($uploadDir)) {
                            $filesystem->mkdir($uploadDir, 0775);
                        }
                        if (!is_writable($uploadDir)) {
                            throw new \Exception('Upload directory is not writable: ' . $uploadDir);
                        }
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to create or verify upload directory: ' . $e->getMessage());
                        if ($request->isXmlHttpRequest()) {
                            return new JsonResponse([
                                'success' => false,
                                'message' => 'Server configuration error: Upload directory is not accessible.',
                            ], Response::HTTP_INTERNAL_SERVER_ERROR);
                        }
                        $this->addFlash('error', 'Server configuration error: Upload directory is not accessible.');
                        return $this->render('equipe/edit.html.twig', [
                            'equipe' => $equipe,
                            'form' => $form->createView(),
                            'page_title' => 'Edit Team',
                        ]);
                    }

                    if ($equipe->getImageUrl()) {
                        $oldImagePath = $this->getParameter('teams_images_directory') . '/' . basename($equipe->getImageUrl());
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                    try {
                        $imageFile->move($uploadDir, $newFilename);
                        $equipe->setImageUrl($this->getParameter('teams_images_base_url') . $newFilename);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to upload image for team edit: ' . $e->getMessage());
                        if ($request->isXmlHttpRequest()) {
                            return new JsonResponse([
                                'success' => false,
                                'message' => 'Failed to upload image: ' . $e->getMessage(),
                            ], Response::HTTP_BAD_REQUEST);
                        }
                        $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
                        return $this->render('equipe/edit.html.twig', [
                            'equipe' => $equipe,
                            'form' => $form->createView(),
                            'page_title' => 'Edit Team',
                        ]);
                    }
                }

                try {
                    $this->entityManager->flush();
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => true,
                            'teamId' => $equipe->getId(),
                            'nom' => $equipe->getNom(),
                            'niveau' => $equipe->getNiveau() ? $equipe->getNiveau()->value : null,
                            'nombre_membres' => $equipe->getNombreMembres(),
                            'imageUrl' => $equipe->getImageUrl(),
                            'message' => 'Team updated successfully!',
                        ]);
                    }
                    $this->addFlash('success', 'Team updated successfully!');
                    return $this->redirectToRoute('app_equipe_index');
                } catch (\Exception $e) {
                    $this->logger->error('Error updating team: ' . $e->getMessage());
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Error updating team: ' . $e->getMessage(),
                        ], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                    $this->addFlash('error', 'Error updating team: ' . $e->getMessage());
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

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid request.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->render('equipe/edit.html.twig', [
            'equipe' => $equipe,
            'form' => $form->createView(),
            'page_title' => 'Edit Team',
        ]);
    }

    #[Route('/equipe/{id}/delete', name: 'app_equipe_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Equipe $equipe): Response
    {
        if ($this->isCsrfTokenValid('delete' . $equipe->getId(), $request->request->get('_token'))) {
            if ($equipe->getImageUrl()) {
                $imagePath = $this->getParameter('teams_images_directory') . '/' . basename($equipe->getImageUrl());
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            try {
                $this->entityManager->remove($equipe);
                $this->entityManager->flush();
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Team deleted successfully!',
                    ]);
                }
                $this->addFlash('success', 'Team deleted successfully!');
            } catch (\Exception $e) {
                $this->logger->error('Error deleting team: ' . $e->getMessage());
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Error deleting team: ' . $e->getMessage(),
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                $this->addFlash('error', 'Error deleting team: ' . $e->getMessage());
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

        return $this->redirectToRoute('app_equipe_index');
    }

    private function getFormErrors($form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
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