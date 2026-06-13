<?php
namespace App\Controller;
use App\Entity\Admin;
use App\Entity\Sportif;
use App\Entity\ResponsableSalle;
use App\Entity\Entraineur;
use App\Enum\Role;
use App\Entity\User;
use App\Form\UserType;
use App\Service\EmailService;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
class UserController extends AbstractController
{
    private $userRepository;
    private $entityManager;
    private $passwordHasher;
    private $slugger;
    private $emailService;
    private $logger;

    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger,
        EmailService $emailService,
        LoggerInterface $logger
    ) {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->slugger = $slugger;
        $this->emailService = $emailService;
        $this->logger = $logger;
    }

    #[Route('/admin/user', name: 'user_index')]
    public function index(): Response
    {
        $users = $this->userRepository->findAll();

        $this->logger->info('Utilisateurs récupérés', ['count' => count($users)]);

        if (empty($users)) {
            $this->addFlash('info', 'Aucun utilisateur trouvé dans la base de données.');
        }

        return $this->render('user/index.html.twig', [
            'page_title' => 'Liste des utilisateurs',
            'users' => $users,
        ]);
    }
    #[Route('/admin/user/create', name: 'user_create', methods: ['GET', 'POST'])]
    public function addUser(Request $request): Response
    {
        $user = new Sportif(); // Valeur par défaut pour le formulaire
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Role $selectedRole */
            $selectedRole = $form->get('role')->getData();
    
            // Crée dynamiquement la bonne instance selon le rôle
            $userClass = match ($selectedRole) {
                Role::SPORTIF => Sportif::class,
                Role::ENTRAINEUR => Entraineur::class,
                Role::ADMIN => Admin::class,
                Role::RESPONSABLE_SALLE => ResponsableSalle::class,
                default => User::class,
            };
    
            /** @var User $user */
            $user = new $userClass();
    
            // Remplit les données depuis le formulaire
            $form = $this->createForm(UserType::class, $user);
            $form->handleRequest($request);
    
            // Vérifie si l'email existe déjà
            $existingUser = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
            if ($existingUser) {
                $this->addFlash('danger', 'Cet email est déjà utilisé.');
                return $this->render('user/create.html.twig', [
                    'form' => $form->createView(),
                    'page_title' => 'Ajouter un utilisateur',
                ]);
            }
    
            // Gestion du mot de passe
            $plainPassword = $form->get('password')->getData();
            if (!$plainPassword) {
                // Générer un mot de passe temporaire si aucun n'est fourni
                $plainPassword = bin2hex(random_bytes(8)); // Exemple : génère un mot de passe de 16 caractères
            }
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
    
            // Gestion de l'image
            $imageFile = $form->get('imageUrl')->getData();
            if ($imageFile) {
                $newFilename = uniqid().'.'.$imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('upload_directory'),
                    $newFilename
                );
                $user->setImageUrl($newFilename);
            }
    
            // Persister l'utilisateur
            $this->entityManager->persist($user);
            $this->entityManager->flush();
    
            // Envoyer un e-mail avec les identifiants
            try {
                $this->emailService->sendEmail(
                    $user->getEmail(),
                    'Vos identifiants de connexion',
                    'emails/user_credentials.html.twig',
                    [
                        'email' => $user->getEmail(),
                        'plainPassword' => $plainPassword,
                    ]
                );
                $this->addFlash('success', 'Utilisateur ajouté et e-mail envoyé avec succès !');
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi de l\'e-mail', [
                    'message' => $e->getMessage(),
                    'email' => $user->getEmail(),
                ]);
                $this->addFlash('warning', 'Utilisateur ajouté, mais l\'e-mail n\'a pas pu être envoyé.');
            }
    
            return $this->redirectToRoute('user_index');
        }
    
        return $this->render('user/create.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Ajouter un utilisateur',
        ]);
    }
    #[Route('/admin/user/{id}/edit', name: 'app_user_update', methods: ['GET', 'POST'])]
    public function update(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user, [
            'validation_groups' => ['Default'] // Skip email/password validation
        ]);
        $form->remove('email')->remove('password'); // Remove from form
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
                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/users';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $imageFile->move($uploadDir, $newFilename);
                    $user->setImageUrl($newFilename);
                }

                $this->entityManager->flush();
                $this->addFlash('success', 'Utilisateur mis à jour avec succès !');
                return $this->redirectToRoute('user_index');
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la mise à jour de l\'utilisateur', [
                    'message' => $e->getMessage(),
                    'email' => $user->getEmail() ?? 'N/A',
                ]);
                $this->addFlash('danger', 'Une erreur est survenue lors de la mise à jour de l\'utilisateur.');
            }
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Modifier un utilisateur',
            'user' => $user,
        ]);
    }
    #[Route('/admin/user/{id}/delete', name: 'user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
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

        return $this->redirectToRoute('user_index');
    }

    #[Route('/admin/user/{id}', name: 'user_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showById(int $id): Response
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            $this->logger->warning('showById: User not found', ['id' => $id]);
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $this->logger->info('showById: Rendering user details', ['user_id' => $id]);

        return $this->render('user/show.html.twig', [
            'user' => $user,
            'page_title' => 'Détails de l\'utilisateur',
        ]);
    }

    #[Route('/admin/user/email/{email}', name: 'admin_user_show', methods: ['GET'])]
    public function showByEmail(string $email): Response
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $this->logger->warning('showByEmail: User not found', ['email' => $email]);
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $this->logger->info('showByEmail: Rendering user details', ['email' => $email]);

        return $this->render('user/show.html.twig', [
            'user' => $user,
            'page_title' => 'Détails de l\'utilisateur',
        ]);
    }
    #[Route('/api/check-email', name: 'api_check_email', methods: ['POST'])]
    public function checkEmail(Request $request): JsonResponse
    {
        $email = $request->request->get('email');
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);

        return new JsonResponse(['exists' => $existingUser !== null]);
    }

    #[Route('/admin/user/{id}/toggle-block', name: 'user_toggle_block', methods: ['POST'])]
    public function toggleBlock(Request $request, User $user, EntityManagerInterface $em): Response
    {
        // Vérifier le jeton CSRF
        if (!$this->isCsrfTokenValid('toggle_block' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('user_index');
        }

        try {
            // Basculer l'état de blocage
            $user->setIsBlocked(!$user->isBlocked());
            $em->flush();

            // Logger l'action
            $status = $user->isBlocked() ? 'bloqué' : 'débloqué';
            $this->logger->info("Utilisateur $status", [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            // Ajouter un message flash
            $this->addFlash('success', "L'utilisateur a été $status avec succès.");
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du changement d\'état de blocage', [
                'message' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);
            $this->addFlash('danger', 'Une erreur est survenue lors du changement d\'état.');
        }

        return $this->redirectToRoute('user_index');
    }

    #[Route('/api/users/search', name: 'api_users_search', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        try {
            $query = $request->query->get('q', '');
            
            if (strlen($query) < 1) {
                return new JsonResponse([]);
            }
            
            // Search users whose name or first name contains the query
            $users = $this->userRepository->createQueryBuilder('u')
                ->where('u.nom LIKE :query OR u.prenom LIKE :query')
                ->setParameter('query', '%' . $query . '%')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
            
            // Format the results
            $formattedUsers = [];
            foreach ($users as $user) {
                $imageUrl = $user->getImageUrl() 
                    ? '/uploads/users/' . $user->getImageUrl() 
                    : '/img/screen/user.png';
                    
                $formattedUsers[] = [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'imageUrl' => $imageUrl
                ];
            }
            
            return new JsonResponse($formattedUsers);
        } catch (\Exception $e) {
            $this->logger->error('Error in user search API', [
                'message' => $e->getMessage(),
                'query' => $request->query->get('q', '')
            ]);
            
            return new JsonResponse(['error' => 'An error occurred during search'], 500);
        }
    }
}