<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Reactions;
use App\Service\AblyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ReactionController extends AbstractController
{
    private AblyService $ablyService;
    
    public function __construct(AblyService $ablyService)
    {
        $this->ablyService = $ablyService;
    }
    
    #[Route('/reaction/{post}', name: 'app_reaction_toggle', methods: ['POST'])]
    public function toggle(Post $post, Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            // Récupération de l'utilisateur connecté
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'Unauthorized: User not authenticated'], 401);
            }

            // Log des données reçues
            $requestData = $request->request->all();
            error_log('Reaction request data: ' . json_encode($requestData));
            
            // Validation du type envoyé
            $type = $request->request->get('type');
            if (!isset(Reactions::TYPES[$type])) {
                return $this->json(['error' => 'Invalid reaction type: ' . $type], 400);
            }

            // Vérifier si l'utilisateur a déjà réagi à ce post
            $existing = $post->getReactionByUser($user);
            $isNewReaction = false;
            $isChangedReaction = false;
            $oldReactionType = null;
            
            // Désactiver le suivi des changements de doctrine pour résoudre les problèmes de concurrence
            $em->getConnection()->setAutoCommit(false);
            $em->beginTransaction();
            
            try {
                if ($existing) {
                    error_log('Existing reaction found: ' . $existing->getType() . ' vs new type ' . $type);
                    
                    if ($existing->getType() === $type) {
                        // Même réaction => suppression
                        error_log('Removing reaction (same type)');
                        $em->remove($existing);
                        $currentUserReaction = null;
                    } else {
                        // Modification du type de réaction
                        error_log('Changing reaction type from ' . $existing->getType() . ' to ' . $type);
                        $oldReactionType = $existing->getType();
                        $existing->setType($type);
                        $currentUserReaction = $type;
                        $isChangedReaction = true;
                    }
                } else {
                    // Nouvelle réaction
                    error_log('Creating new reaction of type ' . $type . ' for post ' . $post->getId() . ' by user ' . $user->getId());
                    $reaction = new Reactions();
                    $reaction->setUser($user)
                            ->setPost($post)
                            ->setType($type);
                    $em->persist($reaction);
                    $currentUserReaction = $type;
                    $isNewReaction = true;
                }
                
                // Valider les changements explicitement
                $em->flush();
                $em->commit();
            } catch (\Exception $e) {
                // Annuler la transaction en cas d'erreur
                $em->rollback();
                error_log('Error during reaction transaction: ' . $e->getMessage());
                return $this->json(['error' => 'Database error: ' . $e->getMessage()], 500);
            } finally {
                // Restaurer le comportement normal
                $em->getConnection()->setAutoCommit(true);
            }

            // Forcer le rafraîchissement du post pour obtenir les nouveaux décomptes à jour
            $em->refresh($post);
            
            // Calcul des totaux par type
            $counts = $post->getReactionsCountByType();
            error_log('Reaction counts: ' . json_encode($counts));
            
            // Only send notification for new or changed reactions (not for removals)
            if ($isNewReaction || $isChangedReaction) {
                $notificationData = [
                    'postId' => $post->getId(),
                    'postTitle' => $post->getTitle(),
                    'reactionType' => $type,
                    'reactionEmoji' => Reactions::TYPES[$type],
                    'isNew' => $isNewReaction,
                    'isChanged' => $isChangedReaction,
                    'oldType' => $oldReactionType,
                    'user' => [
                        'id' => $user->getId(),
                        'nom' => $user->getNom(),
                    ],
                    'currentUserId' => $user->getId(),
                    'totalReactions' => array_sum($counts),
                    'counts' => $counts
                ];
                
                error_log('Publishing Ably notification: ' . json_encode($notificationData));
                $this->ablyService->publishNewReaction($notificationData);
            }

            $response = [
                'counts' => $counts,
                'userReaction' => $currentUserReaction,
                'userId' => $user->getId(),
                'postId' => $post->getId(),
                'success' => true,
                'isNewReaction' => $isNewReaction,
                'isChangedReaction' => $isChangedReaction,
            ];
            
            error_log('Reaction response: ' . json_encode($response));
            return $this->json($response);
        } catch (\Exception $e) {
            error_log('Unexpected error in reaction controller: ' . $e->getMessage());
            return $this->json([
                'error' => 'Server error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
