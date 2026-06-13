<?php

namespace App\Controller;

use App\Form\DeepInfraType;
use App\Service\DeepInfraService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class DeepInfraController extends AbstractController
{
    private $deepInfraService;
    private $logger;

    public function __construct(DeepInfraService $deepInfraService, LoggerInterface $logger)
    {
        $this->deepInfraService = $deepInfraService;
        $this->logger = $logger;
    }

    #[Route('/deepinfra', name: 'deepinfra')]
    public function index(Request $request, SessionInterface $session): Response
    {
        // Récupérer l'historique des messages depuis la session ou initialiser un tableau vide
        $messages = $session->get('chat_messages', []);

        $form = $this->createForm(DeepInfraType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $input = trim($data['input']);

            if (empty($input)) {
                $this->addFlash('error', 'Veuillez entrer un message valide.');
                return $this->render('chat/index.html.twig', [
                    'form' => $form->createView(),
                    'messages' => $messages,
                ]);
            }

            // Ajouter le message utilisateur à l'historique
            $messages[] = ['role' => 'user', 'content' => $input];

            try {
                $apiMessages = [
                    ['role' => 'user', 'content' => $input],
                ];
                $response = $this->deepInfraService->sendInferenceRequest('mistralai/Mistral-7B-Instruct-v0.3', $apiMessages);

                if (!isset($response['choices'][0]['message']['content'])) {
                    $this->logger->error('Réponse inattendue de l\'API DeepInfra', ['response' => $response]);
                    throw new \RuntimeException('La réponse de l\'API est invalide ou vide.');
                }

                $responseText = $response['choices'][0]['message']['content'];
                // Ajouter la réponse du bot à l'historique
                $messages[] = ['role' => 'bot', 'content' => $responseText];
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'appel à l\'API DeepInfra', [
                    'error' => $e->getMessage(),
                    'input' => $input,
                ]);
                $messages[] = ['role' => 'bot', 'content' => 'Erreur : Impossible de récupérer une réponse. Veuillez réessayer.'];
            }

            // Sauvegarder l'historique dans la session
            $session->set('chat_messages', $messages);

            return $this->redirectToRoute('deepinfra');
        }

        return $this->render('chat/index.html.twig', [
            'form' => $form->createView(),
            'messages' => $messages,
        ]);
    }

    #[Route('/deepinfra/reset', name: 'deepinfra_reset')]
    public function reset(SessionInterface $session): Response
    {
        // Effacer l'historique des messages
        $session->remove('chat_messages');
        return $this->redirectToRoute('deepinfra');
    }
}