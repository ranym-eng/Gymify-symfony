<?php
namespace App\Controller;

use App\Entity\Salle;
use App\Entity\ResponsableSalle;
use App\Form\SalleType;
use App\Repository\SalleRepository;
use App\Repository\ResponsableSalleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/admin/salle')]
final class SalleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private ResponsableSalleRepository $responsableSalleRepository,
        private LoggerInterface $logger
    ) {}

    #[Route('/', name: 'admin_salle_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, SalleRepository $salleRepository): Response
    {
        $search = $request->query->get('search', '');

        // Fetch salles based on search query
        $salles = $search
            ? $salleRepository->findBySearchQuery($search)
            : $salleRepository->findAll();

        return $this->render('salle/index.html.twig', [
            'salles' => $salles,
        ]);
    }

    #[Route('/new', name: 'admin_salle_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        $salle = new Salle();
        $form = $this->createForm(SalleType::class, $salle);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                $this->addFlash('error', 'Please correct the errors in the form.');
                foreach ($form->get('image')->getErrors(true) as $error) {
                    $this->addFlash('error', 'Image error: ' . $error->getMessage());
                }
            } else {
                // Handle file upload
                $imageFile = $form->get('image')->getData();
                if ($imageFile) {
                    $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                    try {
                        $imageFile->move(
                            $this->getParameter('images_directory'),
                            $newFilename
                        );
                        $salle->setUrlPhoto($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
                        return $this->render('salle/new.html.twig', [
                            'form' => $form->createView(),
                            'page_title' => 'Add New Gym Room',
                        ]);
                    }
                }

                $this->entityManager->persist($salle);
                $this->entityManager->flush();
                
                $this->addFlash('success', 'Gym room created successfully!');
                return $this->redirectToRoute('admin_salle_index');
            }
        }

        return $this->render('salle/new.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Add New Gym Room',
        ]);
    }

    #[Route('/{id}', name: 'admin_salle_show', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function show(Salle $salle): Response
    {
        return $this->render('salle/show.html.twig', [
            'salle' => $salle,
            'page_title' => 'Gym Room: ' . $salle->getNom(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_salle_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Salle $salle): Response
    {
        $form = $this->createForm(SalleType::class, $salle, [
            'current_salle_id' => $salle->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                $this->addFlash('error', 'Please correct the errors in the form.');
                foreach ($form->get('image')->getErrors(true) as $error) {
                    $this->addFlash('error', 'Image error: ' . $error->getMessage());
                }
            } else {
                // Handle file upload
                $imageFile = $form->get('image')->getData();
                if ($imageFile) {
                    $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                    try {
                        $imageFile->move(
                            $this->getParameter('images_directory'),
                            $newFilename
                        );
                        // Delete old image if it exists
                        if ($salle->getUrlPhoto()) {
                            $oldImagePath = $this->getParameter('images_directory') . '/' . $salle->getUrlPhoto();
                            if (file_exists($oldImagePath)) {
                                unlink($oldImagePath);
                            }
                        }
                        $salle->setUrlPhoto($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
                        return $this->render('salle/edit.html.twig', [
                            'salle' => $salle,
                            'form' => $form->createView(),
                            'page_title' => 'Edit Gym Room: ' . $salle->getNom(),
                        ]);
                    }
                }

                $this->entityManager->flush();
                $this->addFlash('success', 'Gym room updated successfully!');
                return $this->redirectToRoute('admin_salle_index');
            }
        }

        return $this->render('salle/edit.html.twig', [
            'salle' => $salle,
            'form' => $form->createView(),
            'page_title' => 'Edit Gym Room: ' . $salle->getNom(),
        ]);
    }

    #[Route('/validate/new', name: 'admin_salle_validate_new', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function validateNewSalle(Request $request): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            // Validate CSRF token
            $csrfToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('salle', $csrfToken)) {
                return $this->json(['errors' => ['_token' => ['Invalid CSRF token.']]], 400);
            }

            // Extract field name and value
            $salleData = $request->request->get('salle', []);
            $fieldName = array_key_first($salleData) ?? null;
            if (!$fieldName) {
                return $this->json(['errors' => ['general' => ['No field data provided.']]], 400);
            }

            $fieldValue = $salleData[$fieldName] ?? null;

            // Create a new Salle entity
            $salle = new Salle();

            // Handle the field value
            if ($fieldName === 'image' && $request->files->has('salle')) {
                $fieldValue = $request->files->get('salle')['image'] ?? null;
                // The image field is handled by SalleType constraints
            } elseif ($fieldName === 'responsable') {
                if (empty($fieldValue)) {
                    return $this->json(['errors' => ['responsable' => ['Le responsable est requis.']]], 400);
                }
                $responsable = $this->responsableSalleRepository->find((int)$fieldValue);
                if (!$responsable) {
                    return $this->json(['errors' => ['responsable' => ['Responsable not found.']]], 400);
                }
                $salle->setResponsable($responsable);
            } else {
                $setter = 'set' . ucfirst($fieldName);
                if (method_exists($salle, $setter)) {
                    $salle->$setter($fieldValue);
                } else {
                    return $this->json(['errors' => [$fieldName => ['Invalid field name.']]], 400);
                }
            }

            // Validate the Salle entity
            $violations = $this->validator->validate($salle, null, ['Default', $fieldName]);

            $errors = [];
            foreach ($violations as $violation) {
                $propertyPath = $violation->getPropertyPath();
                if (!isset($errors[$propertyPath])) {
                    $errors[$propertyPath] = [];
                }
                $errors[$propertyPath][] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors]);
        } catch (\Exception $e) {
            $this->logger->error('Validation error in validateNewSalle: ' . $e->getMessage(), [
                'exception' => $e,
                'fieldName' => $fieldName,
                'fieldValue' => $fieldValue,
            ]);
            return $this->json(['errors' => ['general' => ['Server error during validation.']]], 500);
        }
    }

    #[Route('/validate/{id}', name: 'admin_salle_validate_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function validateSalle(Request $request, Salle $salle): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            // Validate CSRF token
            $csrfToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('salle', $csrfToken)) {
                return $this->json(['errors' => ['_token' => ['Invalid CSRF token.']]], 400);
            }

            // Extract field name and value
            $salleData = $request->request->get('salle', []);
            $fieldName = array_key_first($salleData) ?? null;
            if (!$fieldName) {
                return $this->json(['errors' => ['general' => ['No field data provided.']]], 400);
            }

            $fieldValue = $salleData[$fieldName] ?? null;

            // Handle the field value
            if ($fieldName === 'image' && $request->files->has('salle')) {
                $file = $request->files->get('salle')['image'] ?? null;
                if ($file) {
                    $salle->setUrlPhoto($file->getClientOriginalName());
                } else {
                    $salle->setUrlPhoto(null); // Allow null for optional image
                }
            } elseif ($fieldName === 'responsable') {
                if (empty($fieldValue)) {
                    return $this->json(['errors' => ['responsable' => ['Le responsable est requis.']]], 400);
                }
                $responsable = $this->responsableSalleRepository->find((int)$fieldValue);
                if (!$responsable) {
                    return $this->json(['errors' => ['responsable' => ['Responsable not found.']]], 400);
                }
                $salle->setResponsable($responsable);
            } else {
                $setter = 'set' . ucfirst($fieldName);
                if (method_exists($salle, $setter)) {
                    $salle->$setter($fieldValue);
                } else {
                    return $this->json(['errors' => [$fieldName => ['Invalid field name.']]], 400);
                }
            }

            // Validate the Salle entity
            $violations = $this->validator->validate($salle, null, ['Default', $fieldName]);

            $errors = [];
            foreach ($violations as $violation) {
                $propertyPath = $violation->getPropertyPath();
                if (!isset($errors[$propertyPath])) {
                    $errors[$propertyPath] = [];
                }
                $errors[$propertyPath][] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors]);
        } catch (\Exception $e) {
            $this->logger->error('Validation error in validateSalle: ' . $e->getMessage(), [
                'exception' => $e,
                'fieldName' => $fieldName,
                'fieldValue' => $fieldValue,
            ]);
            return $this->json(['errors' => ['general' => ['Server error during validation.']]], 500);
        }
    }

    #[Route('/{id}', name: 'admin_salle_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Salle $salle): Response
    {
        if ($this->isCsrfTokenValid('delete' . $salle->getId(), $request->request->get('_token'))) {
            // Delete image if it exists
            if ($salle->getUrlPhoto()) {
                $imagePath = $this->getParameter('images_directory') . '/' . $salle->getUrlPhoto();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $this->entityManager->remove($salle);
            $this->entityManager->flush();
            $this->addFlash('success', 'Gym room deleted successfully!');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_salle_index');
    }
    #[Route('/salles/load-more/{page}', name: 'salle_load_more', methods: ['GET'])]
    public function loadMore(SalleRepository $salleRepository, int $page = 1): JsonResponse
    {
        $limit = 3; // Number of salles per page
        $offset = ($page - 1) * $limit;
        $salles = $salleRepository->findBy([], [], $limit, $offset);

        return new JsonResponse([
            'html' => $this->renderView('sportif/_salle_cards.html.twig', ['salles' => $salles]),
            'hasMore' => count($salles) === $limit,
        ]);
    }
}