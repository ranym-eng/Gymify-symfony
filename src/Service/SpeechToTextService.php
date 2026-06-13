<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class SpeechToTextService
{
    private string $apiKey;
    private string $serviceUrl;
    private Client $client;
    private ?LoggerInterface $logger;

    public function __construct(string $apiKey, string $serviceUrl, LoggerInterface $logger = null)
    {
        $this->apiKey = $apiKey;
        $this->serviceUrl = $serviceUrl;
        $this->client = new Client([
            'verify' => false, // Disable SSL verification for development
            'timeout' => 30,   // Increase timeout for larger files
            'connect_timeout' => 10
        ]);
        $this->logger = $logger;
    }

    /**
     * Transcribe audio file using IBM Watson Speech to Text
     * 
     * @param string $audioFilePath Path to the audio file
     * @return string Transcribed text
     * @throws GuzzleException
     */
    public function transcribe(string $audioFilePath): string
    {
        if ($this->logger) {
            $this->logger->info("Starting transcription for file: {$audioFilePath}");
            $this->logger->info("Using Watson API: {$this->serviceUrl}");
        }
        
        $endpoint = $this->serviceUrl . '/v1/recognize';
        
        try {
            if (!file_exists($audioFilePath)) {
                throw new FileException("Audio file does not exist: $audioFilePath");
            }
            
            // Get the file content
            $audioContent = file_get_contents($audioFilePath);
            if ($audioContent === false) {
                throw new FileException("Failed to read audio file: $audioFilePath");
            }
            
            // Log file info
            $fileSize = filesize($audioFilePath);
            $fileExtension = pathinfo($audioFilePath, PATHINFO_EXTENSION);
            $contentType = $this->getContentType($fileExtension);
            
            if ($this->logger) {
                $this->logger->info("Transcribing file: {$audioFilePath}, size: {$fileSize} bytes, type: {$contentType}");
                $this->logger->info("Using API endpoint: {$endpoint}");
            }
            
            // Make API request
            $response = $this->client->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode('apikey:' . $this->apiKey),
                    'Content-Type' => $contentType,
                ],
                'body' => $audioContent,
                'query' => [
                    'model' => 'en-US_BroadbandModel',
                ]
            ]);
            
            // Parse response
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);
            
            if ($this->logger) {
                $this->logger->info("API Response status: " . $response->getStatusCode());
                $this->logger->info("API Response: " . substr(json_encode($result), 0, 255) . "...");
            }
            
            // Extract transcription
            $transcript = '';
            if (isset($result['results']) && !empty($result['results'])) {
                foreach ($result['results'] as $segment) {
                    if (isset($segment['alternatives'][0]['transcript'])) {
                        $transcript .= $segment['alternatives'][0]['transcript'] . ' ';
                    }
                }
                return trim($transcript);
            } else {
                if ($this->logger) {
                    $this->logger->warning("API returned no transcription results: " . json_encode($result));
                }
                return "No transcription found. The audio might be unclear or in an unsupported language.";
            }
        } catch (RequestException $e) {
            if ($this->logger) {
                $this->logger->error("Request error: " . $e->getMessage());
                if ($e->hasResponse()) {
                    $responseBody = $e->getResponse()->getBody()->getContents();
                    $this->logger->error("Response: " . $responseBody);
                    
                    // Try to decode the error message from Watson
                    $errorData = json_decode($responseBody, true);
                    if (isset($errorData['error'])) {
                        throw new \Exception("Watson API Error: " . $errorData['error'], 0, $e);
                    }
                }
            }
            throw new \Exception("Watson API Request Error: " . $e->getMessage(), 0, $e);
        } catch (GuzzleException $e) {
            if ($this->logger) {
                $this->logger->error("API request error: " . $e->getMessage());
            }
            throw new \Exception("Watson API Error: " . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("General error: " . $e->getMessage());
            }
            throw $e;
        }
    }
    
    /**
     * Determine content type from file extension
     * 
     * @param string $extension File extension
     * @return string Content type
     */
    private function getContentType(string $extension): string
    {
        return match (strtolower($extension)) {
            'wav' => 'audio/wav',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            'webm' => 'audio/webm',
            'm4a' => 'audio/mp4',
            default => 'audio/wav',
        };
    }
} 