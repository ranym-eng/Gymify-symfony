<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AblyService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;

    public function __construct(string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? 'WWlNRQ.NtFTkw:nUpGoNHsYuuGPEGaiG7MFXg-VnDJCA-huFa182u2_0c';
        $this->httpClient = HttpClient::create();
    }

    public function publishEvent(string $channel, string $eventName, array $data): bool
    {
        try {
            $response = $this->httpClient->request('POST', 'https://rest.ably.io/channels/' . $channel . '/messages', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->apiKey),
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'name' => $eventName,
                    'data' => $data
                ]
            ]);

            return $response->getStatusCode() === 201;
        } catch (\Exception $e) {
            // Log error
            return false;
        }
    }

    public function publishNewPost(array $postData): bool
    {
        return $this->publishEvent('posts', 'new-post', $postData);
    }

    public function publishNewComment(array $commentData): bool
    {
        return $this->publishEvent('comments', 'new-comment', $commentData);
    }
    
    public function publishNewReaction(array $reactionData): bool
    {
        return $this->publishEvent('reactions', 'new-reaction', $reactionData);
    }
} 