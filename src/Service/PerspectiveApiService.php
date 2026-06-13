<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

class PerspectiveApiService
{
    private const API_URL = 'https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $apiKey
    ) {
    }

    public function analyzeCommentToxicity(string $commentText): float
    {
        $client = HttpClient::create();
        
        try {
            $this->logger->info('Analyse de toxicité pour le commentaire: ' . $commentText);
            
            $requestData = [
                'comment' => ['text' => $commentText],
                'languages' => ['fr'],
                'requestedAttributes' => [
                    'TOXICITY' => new \stdClass()
                ]
            ];
            
            $this->logger->info('Données envoyées à l\'API: ' . json_encode($requestData));
            
            $response = $client->request('POST', self::API_URL . '?key=' . $this->apiKey, [
                'json' => $requestData,
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $this->logger->info('Code de réponse HTTP: ' . $statusCode);
            
            if ($statusCode !== 200) {
                $errorContent = $response->getContent(false);
                $this->logger->error('Réponse d\'erreur de l\'API: ' . $errorContent);
                throw new \RuntimeException('L\'API a retourné un code d\'erreur: ' . $statusCode . '. Message: ' . $errorContent);
            }
            
            $content = $response->getContent();
            $this->logger->info('Réponse brute de l\'API: ' . $content);
            
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Erreur de décodage JSON: ' . json_last_error_msg());
            }
            
            $this->logger->info('Données décodées: ' . json_encode($data));
            
            if (!isset($data['attributeScores']['TOXICITY']['summaryScore']['value'])) {
                $this->logger->error('Structure de réponse inattendue: ' . json_encode($data));
                throw new \RuntimeException('Structure de réponse inattendue');
            }
            
            $score = $data['attributeScores']['TOXICITY']['summaryScore']['value'];
            $this->logger->info('Score de toxicité: ' . $score);
            
            return $score;
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur API Perspective: ' . $e->getMessage());
            if ($e instanceof ClientExceptionInterface) {
                $this->logger->error('Réponse d\'erreur: ' . $e->getResponse()->getContent(false));
            }
            throw $e;
        }
    }
} 
