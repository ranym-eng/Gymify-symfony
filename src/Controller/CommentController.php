<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Form\CommentType;
use App\Repository\CommentRepository;
use App\Service\AblyService;
use App\Service\EmailJSService;
use App\Service\PerspectiveApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\PostRepository;                // <- Assurez‑vous d'importer LE BON namespace
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Exception as DBALException;


#[Route('/comment')]
final class CommentController extends AbstractController
{
    public function __construct(
        private readonly PerspectiveApiService $perspectiveApiService,
        private readonly EmailJSService $emailJSService,
        private readonly LoggerInterface $logger,
        private readonly AblyService $ablyService
    ) {
    }

    #[Route('/', name: 'app_comment_index', methods: ['GET'])]
    public function index(CommentRepository $commentRepository): Response
    {
        return $this->render('comment/index.html.twig', [
            'comments' => $commentRepository->findAll(),
        ]);
    }














    #[Route('/{postId}', name: 'app_comment_new', methods: ['POST'])]
    public function new(
        Request $request,
        PostRepository $postRepo,
        EntityManagerInterface $em,
        int $postId
    ): JsonResponse {
        $user = $this->getUser();
        $isAjaxRequest = $request->isXmlHttpRequest();
        
        if (!$user) {
            return new JsonResponse(['error' => 'Vous devez être connecté pour commenter.'], 401);
        }
        
        $post = $postRepo->find($postId);
        if (!$post) {
            return new JsonResponse(['error' => 'Post introuvable.'], 404);
        }
        
        // Récupérer le contenu du commentaire selon la méthode d'envoi
        if ($isAjaxRequest) {
            // Pour les requêtes AJAX, c'est dans "content"
            $content = $request->request->get('content');
        } else {
            // Pour les formulaires traditionnels, c'est dans "comment[content]" 
            $content = $request->request->get('comment')['content'] ?? null;
        }
        
        if (!$content || strlen(trim($content)) < 2) {
            return new JsonResponse(['error' => 'Le commentaire est trop court.'], 400);
        }
        
        // Vérification CSRF
        if (!$this->isCsrfTokenValid('comment' . $postId, $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], 403);
        }
        
        // Process @mentions in the content
        $processedContent = $this->processMentions($content);
        
        // Analyse de la toxicité du commentaire
        $this->logger->info('Début de l\'analyse de toxicité pour le commentaire: ' . $content);
        $toxicityScore = $this->perspectiveApiService->analyzeCommentToxicity($content);
        $this->logger->info('Score de toxicité reçu: ' . $toxicityScore);
        
        if ($toxicityScore > 0.6) {
            $this->logger->info('Commentaire trop toxique (score > 0.6), suppression');
            // Commentaire trop toxique - envoi d'email et suppression
            $this->emailJSService->sendEmail(
                $user->getEmail(),
                '⚠️ Commentaire supprimé pour non-respect des règles',
                'Votre commentaire a été jugé toxique et a été supprimé.'
            );
            
            return new JsonResponse([
                'error' => 'Commentaire supprimé pour toxicité excessive',
                'toxicityScore' => $toxicityScore
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Préparation du contenu à stocker en DB
        if ($toxicityScore > 0.4) {
            $this->logger->info('Commentaire sensible (0.4 < score <= 0.6), encodage du contenu original');
            
            // On encode le contenu original avec un préfixe spécial
            // Format: [SENSITIVE]{contenu original}
            $storedContent = '[SENSITIVE]' . $content;
            
            // Envoi d'email de notification
            $this->emailJSService->sendEmail(
                $user->getEmail(),
                '⚠️ Votre commentaire a été modéré',
                'Votre commentaire contient un contenu sensible. Veuillez le modifier si nécessaire.'
            );
        } else {
            $this->logger->info('Commentaire acceptable (score <= 0.4)');
            $storedContent = $content; // Store the original content, not the HTML processed one
        }
        
        // Création du commentaire
        $commentId = null;
        $comment = null;
        $date = new \DateTime();
        $insertSuccessful = false;
        
        // Première tentative: avec SQL direct
        try {
            $this->logger->info('Tentative d\'enregistrement via SQL direct');
            $userId = $user->getId();
            $postIdValue = $post->getId();
            $commentId = $this->directSqlInsertComment($em, $storedContent, $postIdValue, $userId, $date);
            
            if ($commentId) {
                $insertSuccessful = true;
                $this->logger->info('Commentaire enregistré avec succès via SQL direct, ID: ' . $commentId);
                
                // Créer un objet Comment pour l'affichage
                $comment = new Comment();
                $comment->setContent($storedContent)
                    ->setPost($post)
                    ->setUser($user)
                    ->setCreatedAt($date);
                
                // Assignation manuelle de l'ID
                $reflection = new \ReflectionClass($comment);
                $property = $reflection->getProperty('id');
                $property->setAccessible(true);
                $property->setValue($comment, $commentId);
            }
        } catch (\Exception $sqlException) {
            $this->logger->error('Échec de l\'enregistrement via SQL direct: ' . $sqlException->getMessage());
        }
        
        // Seconde tentative: avec Doctrine ORM si SQL direct a échoué
        if (!$insertSuccessful) {
            try {
                $this->logger->info('Tentative d\'enregistrement via ORM');
                $comment = new Comment();
                $comment->setContent($storedContent)
                    ->setPost($post)
                    ->setUser($user)
                    ->setCreatedAt($date);
                
                $em->persist($comment);
                $em->flush();
                $commentId = $comment->getId();
                $insertSuccessful = true;
                $this->logger->info('Commentaire enregistré avec succès via ORM, ID: ' . $commentId);
            } catch (\Exception $ormException) {
                $this->logger->error('Échec de l\'enregistrement via ORM: ' . $ormException->getMessage());
                throw new \Exception('Impossible d\'enregistrer le commentaire en base de données: ' . $ormException->getMessage());
            }
        }
        
        if (!$insertSuccessful || !$commentId) {
            throw new \Exception('Impossible d\'enregistrer le commentaire en base de données');
        }
        
        // Pour la réponse JSON, nous utilisons soit le contenu sensible masqué, soit le contenu traité avec mentions
        $displayContent = $toxicityScore > 0.4 
            ? '⚠️ Votre commentaire contient un contenu sensible. Veuillez le modifier si nécessaire.' 
            : $processedContent; // Use processed content with HTML for @mentions
        
        // Préparation de la réponse
        $response = [
            'id' => $commentId,
            'content' => $displayContent,
            'createdAt' => $date->format('d M Y à H:i'),
            'user' => [
                'nom' => $user->getNom(),
                'avatar' => $user->getImageUrl() 
                    ? '/uploads/users/' . $user->getImageUrl()
                    : '/img/screen/user.png'
            ],
            'tokens' => [
                'edit' => $this->generateToken('edit-comment' . $commentId),
                'delete' => $this->generateToken('delete' . $commentId)
            ],
            'toxicityScore' => $toxicityScore,
            'originalContent' => $toxicityScore > 0.4 ? $content : null // Pour le frontend
        ];
        
        // Send real-time notification via Ably for the new comment
        $this->ablyService->publishNewComment([
            'id' => $commentId,
            'content' => $displayContent,
            'postId' => $post->getId(),
            'postTitle' => $post->getTitle(),
            'createdAt' => $date->format('d M Y à H:i'),
            'user' => [
                'id' => $user->getId(),
                'nom' => $user->getNom(),
                'avatar' => $user->getImageUrl() 
                    ? '/uploads/users/' . $user->getImageUrl()
                    : '/img/screen/user.png'
            ]
        ]);
        
        return new JsonResponse($response, Response::HTTP_CREATED);
    }

    private function generateToken(string $intention): string
    {
        return $this->container->get('security.csrf.token_manager')->getToken($intention)->getValue();
    }

    /**
     * Effectue une insertion directe d'un commentaire via SQL
     */
    private function directSqlInsertComment(
        EntityManagerInterface $em, 
        string $content, 
        int $postId, 
        int $userId, 
        \DateTime $date
    ): ?int {
        try {
            $conn = $em->getConnection();
            $dateStr = $date->format('Y-m-d H:i:s');
            
            $stmt = $conn->prepare('
                INSERT INTO comment (content, postId, id_User, createdAt) 
                VALUES (:content, :postId, :userId, :createdAt)
            ');
            
            $stmt->executeQuery([
                'content' => $content,
                'postId' => $postId,
                'userId' => $userId,
                'createdAt' => $dateStr
            ]);
            
            $commentId = $conn->lastInsertId();
            $this->logger->info('SQL direct: Commentaire inséré avec ID ' . $commentId);
            return $commentId ? (int)$commentId : null;
        } catch (\Exception $e) {
            $this->logger->error('SQL direct: Erreur lors de l\'insertion: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Effectue une mise à jour directe d'un commentaire via SQL
     */
    private function directSqlUpdateComment(
        EntityManagerInterface $em, 
        int $commentId, 
        string $content, 
        \DateTime $date
    ): bool {
        try {
            $conn = $em->getConnection();
            $dateStr = $date->format('Y-m-d H:i:s');
            
            $stmt = $conn->prepare('
                UPDATE comment 
                SET content = :content, 
                    createdAt = :createdAt 
                WHERE id = :commentId
            ');
            
            $stmt->executeQuery([
                'content' => $content,
                'createdAt' => $dateStr,
                'commentId' => $commentId
            ]);
            
            $this->logger->info('SQL direct: Commentaire mis à jour avec ID ' . $commentId);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('SQL direct: Erreur lors de la mise à jour: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Effectue une suppression directe d'un commentaire via SQL
     */
    private function directSqlDeleteComment(
        EntityManagerInterface $em, 
        int $commentId
    ): bool {
        try {
            $conn = $em->getConnection();
            
            $stmt = $conn->prepare('
                DELETE FROM comment 
                WHERE id = :commentId
            ');
            
            $stmt->executeQuery([
                'commentId' => $commentId
            ]);
            
            $this->logger->info('SQL direct: Commentaire supprimé avec ID ' . $commentId);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('SQL direct: Erreur lors de la suppression: ' . $e->getMessage());
            return false;
        }
    }
























    #[Route('/{id}', name: 'app_comment_show', methods: ['GET'])]
    public function show(Comment $comment): Response
    {
        // Vérifie si c'est un commentaire sensible
        $content = $comment->getContent();
        $isSensitive = strpos($content, '[SENSITIVE]') === 0;
        
        // Si c'est un commentaire sensible, récupère le contenu original
        if ($isSensitive) {
            $originalContent = substr($content, 11); // Enlever le préfixe '[SENSITIVE]'
            // On conserve le contenu original pour l'affichage
            $comment->setContent($originalContent);
        }
        
        return $this->render('comment/show.html.twig', [
            'comment' => $comment,
            'isSensitive' => $isSensitive
        ]);
    }





    #[Route('/{id}/edit', name: 'app_comment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Comment $comment, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur est l'auteur du commentaire
        if ($comment->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas éditer ce commentaire.');
        }
        
        if ($request->isMethod('POST') && $request->isXmlHttpRequest()) {
            // Récupération et validation du contenu
            $content = $request->getContent();
            $data = json_decode($content, true);
            
            if (!$data) {
                return new JsonResponse(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
            }
            
            $content = $data['content'] ?? null;
            $token = $data['_token'] ?? null;
            
            if (!$content || strlen(trim($content)) < 3) {
                return new JsonResponse(['error' => 'Le commentaire doit contenir au moins 3 caractères'], Response::HTTP_BAD_REQUEST);
            }
            
            if (!$token || !$this->isCsrfTokenValid('edit-comment' . $comment->getId(), $token)) {
                return new JsonResponse(['error' => 'Token de sécurité invalide'], Response::HTTP_FORBIDDEN);
            }
            
            // Process @mentions in the content - keep original content and processed content separate
            $processedContent = $this->processMentions($content);
            
            // Analyse de toxicité
            $toxicityScore = $this->perspectiveApiService->analyzeCommentToxicity($content);
            $this->logger->info('Score de toxicité pour l\'édition du commentaire: ' . $toxicityScore);
            
            $newContentToStore = $content; // Default to original content
            $originalContent = $content;
            $dateUpdated = new \DateTime();
            
            if ($toxicityScore > 0.6) {
                // Commentaire trop toxique, on refuse la modification
                $this->emailJSService->sendEmail(
                    $comment->getUser()->getEmail(),
                    '⚠️ Modification de commentaire refusée',
                    'Votre commentaire modifié a été jugé toxique et n\'a pas été enregistré.'
                );
                
                return new JsonResponse([
                    'error' => 'Commentaire trop toxique, modification refusée',
                    'toxicityScore' => $toxicityScore
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Commentaire sensible
            if ($toxicityScore > 0.4) {
                $this->logger->info('Commentaire sensible (0.4 < score <= 0.6), encodage du contenu original');
                $newContentToStore = '[SENSITIVE]' . $content;
                
                // Notification par email
                $this->emailJSService->sendEmail(
                    $comment->getUser()->getEmail(),
                    '⚠️ Votre commentaire modifié contient du contenu sensible',
                    'Votre commentaire modifié contient du contenu sensible. Veuillez le réviser si nécessaire.'
                );
            }
            
            // Mise à jour en DB - d'abord direct SQL
            $updateSuccessful = false;
            try {
                $updateSuccessful = $this->directSqlUpdateComment(
                    $entityManager, 
                    $comment->getId(), 
                    $newContentToStore,
                    $dateUpdated
                );
                
                if ($updateSuccessful) {
                    $this->logger->info('Commentaire mis à jour avec succès via SQL direct');
                }
            } catch (\Exception $sqlException) {
                $this->logger->error('Échec de la mise à jour SQL directe: ' . $sqlException->getMessage());
                // On continue avec l'ORM
            }
            
            // Fallback sur l'ORM si nécessaire
            if (!$updateSuccessful) {
                try {
                    $comment->setContent($newContentToStore);
                    $comment->setCreatedAt($dateUpdated);
                    $entityManager->flush();
                    $updateSuccessful = true;
                    $this->logger->info('Commentaire mis à jour avec succès via ORM');
                } catch (\Exception $ormException) {
                    $this->logger->error('Échec de la mise à jour ORM: ' . $ormException->getMessage());
                    return new JsonResponse([
                        'error' => 'Erreur lors de la mise à jour du commentaire'
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
            
            // For display in the response
            $displayContent = $toxicityScore > 0.4 
                ? '⚠️ Votre commentaire contient un contenu sensible. Veuillez le modifier si nécessaire.' 
                : $processedContent; // Use HTML processed content for display
            
            // Si la mise à jour a réussi, on renvoie une réponse de succès
            return new JsonResponse([
                'success' => true,
                'content' => $displayContent,
                'originalContent' => $originalContent,
                'createdAt' => $dateUpdated->format('d M Y à H:i'),
                'toxicityScore' => $toxicityScore
            ]);
        }
        
        // Rendu du formulaire en mode GET (non utilisé actuellement car tout est en AJAX)
        return $this->render('comment/edit.html.twig', [
            'comment' => $comment,
            'form' => $this->createForm(CommentType::class, $comment)->createView(),
        ]);
    }





















    #[Route('/delete/{id}', name: 'app_comment_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        CommentRepository $commentRepo,
        int $id,
        EntityManagerInterface $entityManager
    ): Response {
        $comment = $commentRepo->find($id);

        if (!$comment) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Commentaire introuvable'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($comment->getUser() !== $this->getUser()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer ce commentaire'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($this->isCsrfTokenValid('delete' . $comment->getId(), $request->request->get('_token'))) {
            $deleteSuccess = false;
            
            // Capture les données avant suppression pour la notification
            $postId = $comment->getPost()->getId();
            $postTitle = $comment->getPost()->getTitle();
            $userId = $comment->getUser()->getId();
            $userName = $comment->getUser()->getNom();
            
            // Première tentative: avec Doctrine ORM
            try {
                $this->logger->info('Tentative de suppression via ORM');
                $entityManager->remove($comment);
                $entityManager->flush();
                $deleteSuccess = true;
                $this->logger->info('Commentaire supprimé avec succès via ORM, ID: ' . $id);
            } catch (\Exception $ormException) {
                $this->logger->warning('Échec de la suppression via ORM: ' . $ormException->getMessage());
                
                // Seconde tentative: avec SQL direct
                $deleteSuccess = $this->directSqlDeleteComment($entityManager, $id);
                
                if (!$deleteSuccess) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Impossible de supprimer le commentaire en base de données'
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
            
            // Envoie une notification Ably pour la suppression du commentaire
            $this->ablyService->publishEvent('comments', 'delete-comment', [
                'commentId' => $id,
                'postId' => $postId,
                'postTitle' => $postTitle,
                'user' => [
                    'id' => $userId,
                    'nom' => $userName
                ]
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Commentaire supprimé avec succès'
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Token CSRF invalide'
        ], Response::HTTP_BAD_REQUEST);
    }

    // Add a new helper method to process mentions
    private function processMentions(string $content): string 
    {
        // Find all @mentions in the content - updated to match full names with spaces
        preg_match_all('/@([a-zA-Z0-9_\s]+)(?=\s|$)/', $content, $matches);
        
        // If no mentions, return the original content
        if (empty($matches[1])) {
            return $content;
        }
        
        // For each mention, add a span with a class
        // Use preg_replace_callback to avoid issues with special characters
        return preg_replace_callback(
            '/@([a-zA-Z0-9_\s]+)(?=\s|$)/',
            function($match) {
                $username = htmlspecialchars(trim($match[1]), ENT_QUOTES, 'UTF-8');
                return '<span class="user-mention" data-username="' . $username . '">@' . $username . '</span>';
            },
            $content
        );
    }
}