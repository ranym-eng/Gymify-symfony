<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\Reponse;
use App\Form\ReponseType;
use App\Repository\ReclamationRepository;
use App\Repository\ReponseRepository;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/admin/reclamation')]
class AdminReclamationController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_admin_reclamation_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ReclamationRepository $reclamationRepository,
        ReponseRepository $reponseRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Fetch all reclamations and responses
        $reclamations = $reclamationRepository->findAll();
        $reponses = $reponseRepository->findAll();

        // Create the response form
        $reponse = new Reponse();
        $form = $this->createForm(ReponseType::class, $reponse);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->logger->info('Formulaire de réponse soumis');
            if (!$form->isValid()) {
                $errors = $form->getErrors(true);
                $this->logger->error('Formulaire invalide', ['errors' => (string) $errors]);
                $this->addFlash('error', 'Formulaire invalide. Vérifiez le champ de réponse.');
            } else {
                $selectedReclamations = $request->get('selected_reclamations', []);
                if (!is_array($selectedReclamations)) {
                    $selectedReclamations = [];
                }
                $this->logger->info('Réclamations sélectionnées', ['selected' => $selectedReclamations]);

                if (empty($selectedReclamations)) {
                    $this->addFlash('warning', 'Veuillez sélectionner au moins une réclamation.');
                } else {
                    $admin = $this->getUser();
                    if (!$admin) {
                        $this->logger->error('Utilisateur admin non connecté');
                        $this->addFlash('error', 'Vous devez être connecté en tant qu\'admin.');
                        return $this->redirectToRoute('app_admin_reclamation_index');
                    }

                    foreach ($selectedReclamations as $reclamationId) {
                        $reclamation = $reclamationRepository->find($reclamationId);
                        if (!$reclamation) {
                            $this->logger->warning('Réclamation introuvable', ['id' => $reclamationId]);
                            $this->addFlash('error', 'Réclamation #' . $reclamationId . ' introuvable.');
                            continue;
                        }

                        $newReponse = new Reponse();
                        $newReponse->setMessage($reponse->getMessage());
                        $newReponse->setDateReponse(new \DateTime());
                        $newReponse->setAdmin($admin);
                        $newReponse->setReclamation($reclamation);

                        $reclamation->setStatut('Traitée');

                        $entityManager->persist($newReponse);
                        $entityManager->persist($reclamation);
                    }

                    try {
                        $entityManager->flush();
                        $this->addFlash('success', 'Réponses envoyées avec succès.');
                        $this->logger->info('Réponses enregistrées avec succès');
                    } catch (\Exception $e) {
                        $this->logger->error('Erreur lors de l\'enregistrement des réponses', ['exception' => $e->getMessage()]);
                        $this->addFlash('error', 'Erreur lors de l\'envoi des réponses : ' . $e->getMessage());
                    }
                }
            }
            return $this->redirectToRoute('app_admin_reclamation_index');
        }

        return $this->render('admin_reclamation/index.html.twig', [
            'reclamations' => $reclamations,
            'reponses' => $reponses,
            'form' => $form->createView(),
            'page_title' => 'Complaint Management',
        ]);
    }

    #[Route('/reponse/delete', name: 'app_admin_reponse_delete', methods: ['POST'])]
    public function deleteReponse(
        Request $request,
        ReponseRepository $reponseRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $selectedReponses = $request->get('selected_reponses', []);
        if (!is_array($selectedReponses)) {
            $selectedReponses = [];
        }
        $this->logger->info('Suppression des réponses', ['selected' => $selectedReponses]);

        if (empty($selectedReponses)) {
            $this->addFlash('warning', 'Veuillez sélectionner au moins une réponse à supprimer.');
        } else {
            foreach ($selectedReponses as $reponseId) {
                $reponse = $reponseRepository->find($reponseId);
                if ($reponse) {
                    $entityManager->remove($reponse);
                }
            }
            try {
                $entityManager->flush();
                $this->addFlash('success', 'Réponses supprimées avec succès.');
                $this->logger->info('Réponses supprimées avec succès');
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la suppression des réponses', ['exception' => $e->getMessage()]);
                $this->addFlash('error', 'Erreur lors de la suppression des réponses : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_admin_reclamation_index');
    }

    #[Route('/translate-page', name: 'admin_reclamation_translate_page', methods: ['POST'])]
    public function translatePage(
        Request $request,
        TranslationService $translationService,
        ReclamationRepository $reclamationRepository,
        ReponseRepository $reponseRepository
    ): JsonResponse {
        $targetLang = $request->request->get('lang', 'en');
        if (!in_array($targetLang, ['en', 'fr', 'es', 'de', 'it'])) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid language'], 400);
        }

        // Static text to translate
        $staticTexts = [
            'page_title' => 'Complaint Management',
            'page_subtitle' => 'Monitor and manage user complaints efficiently',
            'total_complaints' => 'Total Complaints',
            'pending_complaints' => 'Pending Complaints',
            'processed_complaints' => 'Processed Complaints',
            'complaints_list' => 'Complaints List',
            'respond_to_complaints' => 'Respond to Complaints',
            'response_message' => 'Response Message',
            'send_response' => 'Send Response',
            'type_response' => 'Type your response...',
            'sent_responses' => 'Sent Responses',
            'select_all' => 'Select All',
            'delete_selected' => 'Delete Selected',
            'no_complaints' => 'No complaints found',
            'no_responses' => 'No responses found',
            'confirm_deletion' => 'Confirm Deletion',
            'delete_complaint_prompt' => 'Are you sure you want to delete this complaint? This action cannot be undone.',
            'delete_responses_prompt' => 'Are you sure you want to delete the selected responses? This action cannot be undone.',
            'cancel' => 'Cancel',
            'delete' => 'Delete',
            'translate_to_english' => 'Translate to English',
            'translate_to_french' => 'Translate to French',
            'translate_to_spanish' => 'Translate to Spanish',
            'respond' => 'Respond',
            'delete' => 'Delete',
            'status_pending' => 'En attente',
            'status_processed' => 'Traitée',
            'flash_select_complaint' => 'Veuillez sélectionner au moins une réclamation.',
            'flash_form_invalid' => 'Formulaire invalide. Vérifiez le champ de réponse.',
            'flash_admin_not_logged' => 'Vous devez être connecté en tant qu\'admin.',
            'flash_reclamation_not_found' => 'Réclamation introuvable.',
            'flash_responses_sent' => 'Réponses envoyées avec succès.',
            'flash_responses_error' => 'Erreur lors de l\'envoi des réponses.',
            'flash_select_response' => 'Veuillez sélectionner au moins une réponse à supprimer.',
            'flash_responses_deleted' => 'Réponses supprimées avec succès.',
            'flash_responses_delete_error' => 'Erreur lors de la suppression des réponses.',
        ];

        // Dynamic text to translate
        $reclamations = $reclamationRepository->findAll();
        $reponses = $reponseRepository->findAll();
        $dynamicTexts = [];

        foreach ($reclamations as $index => $reclamation) {
            $dynamicTexts["reclamation_{$reclamation->getId()}_sujet"] = $reclamation->getSujet();
            $dynamicTexts["reclamation_{$reclamation->getId()}_description"] = $reclamation->getDescription();
            $dynamicTexts["reclamation_{$reclamation->getId()}_user_nom"] = $reclamation->getUser()->getNom();
            $dynamicTexts["reclamation_{$reclamation->getId()}_user_prenom"] = $reclamation->getUser()->getPrenom();
            $dynamicTexts["reclamation_{$reclamation->getId()}_statut"] = $reclamation->getStatut();
        }

        foreach ($reponses as $index => $reponse) {
            $dynamicTexts["reponse_{$reponse->getId()}_message"] = $reponse->getMessage();
            $dynamicTexts["reponse_{$reponse->getId()}_admin_nom"] = $reponse->getAdmin()->getNom();
            $dynamicTexts["reponse_{$reponse->getId()}_admin_prenom"] = $reponse->getAdmin()->getPrenom();
            $dynamicTexts["reponse_{$reponse->getId()}_reclamation_sujet"] = $reponse->getReclamation()->getSujet();
        }

        // Combine static and dynamic texts
        $textsToTranslate = array_merge($staticTexts, $dynamicTexts);
        $textKeys = array_keys($textsToTranslate);
        $textValues = array_values($textsToTranslate);

        // Translate all texts
        $translations = $translationService->translateBulk($textValues, $targetLang);
        if ($translations === null) {
            $this->logger->error('Échec de la traduction de la page', ['lang' => $targetLang]);
            return new JsonResponse(['success' => false, 'error' => 'Translation failed. Please try again.'], 500);
        }

        // Map translations back to keys
        $translatedTexts = [];
        foreach ($textKeys as $index => $key) {
            $translatedTexts[$key] = $translations[$index];
        }

        return new JsonResponse([
            'success' => true,
            'translations' => $translatedTexts,
            'target_lang' => $targetLang,
        ]);
    }
}