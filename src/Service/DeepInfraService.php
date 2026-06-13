<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\Exception\ClientException;

class DeepInfraService
{
    private $httpClient;
    private $apiKey;
    private $baseUrl = 'https://api.deepinfra.com/v1/openai/chat/completions';

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $params)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $params->get('deepinfra_api_key');

        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('La clé API DeepInfra est manquante ou vide.');
        }
    }

    public function sendInferenceRequest(string $model, array $messages, int $maxTokens = 100): array
    {
        if (empty($model)) {
            throw new \InvalidArgumentException('Le modèle ne peut pas être vide.');
        }

        if (empty($messages)) {
            throw new \InvalidArgumentException('Les messages ne peuvent pas être vides.');
        }

        try {
            $payload = [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
            ];

            $response = $this->httpClient->request('POST', $this->baseUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new \RuntimeException(sprintf('Erreur API DeepInfra : code HTTP %d', $statusCode));
            }

            return $response->toArray();
        } catch (ClientException $e) {
            throw new \RuntimeException(sprintf('Erreur lors de l\'appel à l\'API DeepInfra : %s', $e->getMessage()), 0, $e);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Erreur inattendue lors de l\'appel à l\'API DeepInfra : %s', $e->getMessage()), 0, $e);
        }
    }
}