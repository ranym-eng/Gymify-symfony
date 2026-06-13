<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Sportif;
use App\Entity\Entraineur;
use App\Enum\Role;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/responsable/user')]
class ResponsableUserController extends AbstractController
{
    private $userRepository;
    private $entityManager;
    private $passwordHasher;
    private $slugger;
    private $logger;

    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger,
        LoggerInterface $logger
    ) {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->slugger = $slugger;
        $this->logger = $logger;
    }

    #[Route('/', name: 'responsable_user_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESPONSABLE_SALLE');

        $allUsers = $this->userRepository->findAll();
        $this->logger->info('All users in database', [
            'count' => count($allUsers),
            'users' => array_map(fn(User $u) => ['id' => $u->getId(), 'email' => $u->getEmail(), 'role' => $u->getRole()->value], $allUsers),
        ]);

        $users = $this->userRepository->findAll();

        $this->logger->info('Users fetched for responsable_user_index', [
            'count' => count($users),
            'roles' => array_map(fn(User $u) => $u->getRole()->value, $users),
        ]);

        if (empty($users)) {
            $this->addFlash('info', 'Aucun utilisateur trouvé.');
        }

        return $this->render('responsable/user/index.html.twig', [
            'page_title' => 'Liste des utilisateurs',
            'users' => $users,
        ]);
    }

    #[Route('/create', name: 'responsable_user_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESPONSABLE_SALLE');

        $user = new Sportif();
        $form = $this->createForm(UserType::class, $user, [
            'role_choices' => [
                'Sportif' => Role::SPORTIF,
                'Entraîneur' => Role::ENTRAINEUR,
            ],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Role $selectedRole */
            $selectedRole = $form->get('role')->getData();

            $userClass = match ($selectedRole) {
                Role::SPORTIF => Sportif::class,
                Role::ENTRAINEUR => Entraineur::class,
                default => throw $this->createAccessDeniedException('Rôle non autorisé.'),
            };

            $newUser = new $userClass();
            $newUser->setNom($user->getNom());
            $newUser->setPrenom($user->getPrenom());
            $newUser->setEmail($user->getEmail());
            $newUser->setDateNaissance($user->getDateNaissance());

            // Only set specialite for Entraineur
            if ($selectedRole === Role::ENTRAINEUR) {
                $newUser->setSpecialite($user->getSpecialite());
            } else {
                $newUser->setSpecialite(null);
            }

            $existingUser = $this->userRepository->findOneBy(['email' => $newUser->getEmail()]);
            if ($existingUser) {
                $this->addFlash('danger', 'Cet email est déjà utilisé.');
                return $this->render('responsable/user/create.html.twig', [
                    'form' => $form->createView(),
                    'page_title' => 'Ajouter un utilisateur',
                ]);
            }

            $plainPassword = $form->get('password')->getData();
            if (!$plainPassword) {
                $this->addFlash('danger', 'Le mot de passe est requis.');
                return $this->render('responsable/user/create.html.twig', [
                    'form' => $form->createView(),
                    'page_title' => 'Ajouter un utilisateur',
                ]);
            }
            $hashedPassword = $this->passwordHasher->hashPassword($newUser, $plainPassword);
            $newUser->setPassword($hashedPassword);

            $imageFile = $form->get('imageUrl')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/users',
                        $newFilename
                    );
                    $newUser->setImageUrl($newFilename);
                } catch (FileException $e) {
                    $this->logger->error('Erreur lors de l\'upload de l\'image', [
                        'message' => $e->getMessage(),
                        'email' => $newUser->getEmail() ?? 'N/A',
                    ]);
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image.');
                    return $this->render('responsable/user/create.html.twig', [
                        'form' => $form->createView(),
                        'page_title' => 'Ajouter un utilisateur',
                    ]);
                }
            }

            $newUser->setRole($selectedRole);

            try {
                $this->entityManager->persist($newUser);
                $this->entityManager->flush();
                $this->logger->info('Utilisateur créé avec succès', [
                    'email' => $newUser->getEmail(),
                    'role' => $selectedRole->value,
                    'class' => get_class($newUser),
                ]);
                $this->addFlash('success', 'Utilisateur ajouté avec succès !');
                return $this->redirectToRoute('responsable_user_index');
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la création de l\'utilisateur', [
                    'message' => $e->getMessage(),
                    'email' => $newUser->getEmail() ?? 'N/A',
                ]);
                $this->addFlash('danger', 'Une erreur est survenue lors de la création.');
            }
        }

        return $this->render('responsable/user/create.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Ajouter un utilisateur',
        ]);
    }

    #[Route('/{id}/edit', name: 'responsable_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESPONSABLE_SALLE');

        if (!in_array($user->getRole(), [Role::SPORTIF, Role::ENTRAINEUR])) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cet utilisateur.');
        }

        $form = $this->createForm(UserType::class, $user, [
            'role_choices' => [
                'Sportif' => Role::SPORTIF,
                'Entraîneur' => Role::ENTRAINEUR,
            ],
            'validation_groups' => ['Default'],
        ]);
        $form->remove('email')->remove('password');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $imageFile = $form->get('imageUrl')->getData();
                if ($imageFile) {
                    if ($user->getImageUrl()) {
                        $oldImagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/users/' . $user->getImageUrl();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $this->slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/users',
                        $newFilename
                    );
                    $user->setImageUrl($newFilename);
                }

                // Only set specialite for Entraineur during edit
                if ($user->getRole() === Role::ENTRAINEUR) {
                    $user->setSpecialite($form->get('specialite')->getData());
                } else {
                    $user->setSpecialite(null);
                }

                $this->entityManager->flush();
                $this->addFlash('success', 'Utilisateur mis à jour avec succès !');
                return $this->redirectToRoute('responsable_user_index');
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la mise à jour de l\'utilisateur', [
                    'message' => $e->getMessage(),
                    'email' => $user->getEmail() ?? 'N/A',
                ]);
                $this->addFlash('danger', 'Une erreur est survenue lors de la mise à jour.');
            }
        }

        return $this->render('responsable/user/edit.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Modifier un utilisateur',
            'user' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'responsable_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESPONSABLE_SALLE');

        if (!in_array($user->getRole(), [Role::SPORTIF, Role::ENTRAINEUR])) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cet utilisateur.');
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            try {
                if ($user->getImageUrl()) {
                    $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/users/' . $user->getImageUrl();
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }

                $this->entityManager->remove($user);
                $this->entityManager->flush();
                $this->addFlash('success', 'Utilisateur supprimé avec succès !');
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la suppression', [
                    'message' => $e->getMessage(),
                    'email' => $user->getEmail() ?? 'N/A',
                ]);
                $this->addFlash('danger', 'Erreur lors de la suppression : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('responsable_user_index');
    }

    #[Route('/{id}', name: 'responsable_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESPONSABLE_SALLE');

        if (!in_array($user->getRole(), [Role::SPORTIF, Role::ENTRAINEUR])) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir cet utilisateur.');
        }

        return $this->render('responsable/user/show.html.twig', [
            'user' => $user,
            'page_title' => 'Détails de l\'utilisateur',
        ]);
    }
}