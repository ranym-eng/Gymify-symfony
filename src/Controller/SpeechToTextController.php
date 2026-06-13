<?php

namespace App\Controller;

use App\Service\SpeechToTextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SpeechToTextController extends AbstractController
{
    #[Route('/speech-to-text', name: 'api_speech_to_text', methods: ['POST'])]
    public function transcribe(
        Request $request, 
        SpeechToTextService $speechToTextService,
        LoggerInterface $logger
    ): JsonResponse
    {
        try {
            /** @var UploadedFile $audioFile */
            $audioFile = $request->files->get('audio_file');

            if (!$audioFile) {
                $logger->error('No audio file was provided in the request');
                return new JsonResponse(['error' => 'No audio file provided'], Response::HTTP_BAD_REQUEST);
            }

            $logger->info(sprintf(
                'Processing audio file: %s, size: %d bytes, mime type: %s', 
                $audioFile->getClientOriginalName(),
                $audioFile->getSize(),
                $audioFile->getMimeType()
            ));

            // Validate file type
            $validMimeTypes = [
                'audio/wav', 'audio/mpeg', 'audio/mp3', 'audio/ogg', 
                'audio/flac', 'audio/webm', 'audio/x-m4a', 'audio/mp4'
            ];

            if (!in_array($audioFile->getMimeType(), $validMimeTypes)) {
                $logger->warning(sprintf(
                    'Invalid file type: %s. Supported types: WAV, MP3, OGG, FLAC, WEBM, M4A',
                    $audioFile->getMimeType()
                ));
                
                return new JsonResponse([
                    'error' => 'Invalid file type. Supported types: WAV, MP3, OGG, FLAC, WEBM, M4A'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Move the file to a temporary location
            $tempDir = sys_get_temp_dir();
            $filename = uniqid() . '.' . $audioFile->getClientOriginalExtension();
            $filePath = $tempDir . DIRECTORY_SEPARATOR . $filename;
            
            $logger->info("Moving uploaded file to: " . $filePath);
            $audioFile->move($tempDir, $filename);
            
            if (!file_exists($filePath)) {
                throw new FileException("Failed to move uploaded file to temporary location: " . $filePath);
            }

            // Transcribe the audio file
            $logger->info("Starting transcription process");
            $transcription = $speechToTextService->transcribe($filePath);
            $logger->info("Transcription completed: " . substr($transcription, 0, 50) . "...");

            // Delete the temporary file
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return new JsonResponse(['transcription' => $transcription], Response::HTTP_OK, [
                'Content-Type' => 'application/json'
            ]);
        } catch (\Exception $e) {
            $logger->error('Error during transcription: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'An error occurred during transcription: ' . $e->getMessage();
            if ($this->getParameter('kernel.environment') === 'dev') {
                $errorMessage .= "\n" . $e->getTraceAsString();
            }
            
            return new JsonResponse([
                'error' => $errorMessage
            ], Response::HTTP_INTERNAL_SERVER_ERROR, [
                'Content-Type' => 'application/json'
            ]);
        }
    }
    
    #[Route('/speech-to-text/test-connection', name: 'api_speech_to_text_test', methods: ['GET'])]
    public function testConnection(LoggerInterface $logger): JsonResponse
    {
        try {
            $apiKey = $this->getParameter('ibm_watson_api_key');
            $serviceUrl = $this->getParameter('ibm_watson_service_url');
            
            $client = new Client(['verify' => false]);
            
            // Test IBM Watson API connection
            try {
                $response = $client->request('GET', $serviceUrl . '/v1/models', [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode('apikey:' . $apiKey),
                    ],
                ]);
                
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                $models = json_decode($body, true);
                
                return new JsonResponse([
                    'status' => 'success',
                    'message' => 'Successfully connected to IBM Watson Speech to Text API',
                    'statusCode' => $statusCode,
                    'availableModels' => isset($models['models']) ? count($models['models']) : 0,
                    'credentials' => [
                        'apiKey' => substr($apiKey, 0, 5) . '...' . substr($apiKey, -5),
                        'serviceUrl' => $serviceUrl
                    ]
                ], Response::HTTP_OK, ['Content-Type' => 'application/json']);
            } catch (RequestException $e) {
                $response = $e->hasResponse() ? $e->getResponse() : null;
                $statusCode = $response ? $response->getStatusCode() : 'Unknown';
                $responseBody = $response ? $response->getBody()->getContents() : 'No response';
                
                $logger->error("API request failed", [
                    'status_code' => $statusCode,
                    'response' => $responseBody,
                    'exception' => $e->getMessage()
                ]);
                
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Failed to connect to IBM Watson Speech to Text API',
                    'error' => $e->getMessage(),
                    'statusCode' => $statusCode,
                    'response' => $responseBody,
                    'credentials' => [
                        'apiKey' => substr($apiKey ?? '', 0, 5) . '...' . substr($apiKey ?? '', -5),
                        'serviceUrl' => $serviceUrl ?? 'Not configured'
                    ]
                ], Response::HTTP_OK, ['Content-Type' => 'application/json']);
            }
        } catch (\Exception $e) {
            $logger->error('API connection test failed: ' . $e->getMessage());
            
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Failed to connect to IBM Watson Speech to Text API',
                'error' => $e->getMessage(),
                'credentials' => [
                    'apiKey' => substr($apiKey ?? '', 0, 5) . '...' . substr($apiKey ?? '', -5),
                    'serviceUrl' => $serviceUrl ?? 'Not configured'
                ]
            ], Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }
    }

    #[Route('/speech-to-text/debug', name: 'api_speech_to_text_debug', methods: ['GET'])]
    public function debugConnection(LoggerInterface $logger): Response
    {
        $apiKey = $this->getParameter('ibm_watson_api_key');
        $serviceUrl = $this->getParameter('ibm_watson_service_url');
        
        // Check if cURL is installed and enabled
        $curlEnabled = function_exists('curl_version');
        $curlVersion = $curlEnabled ? curl_version() : null;
        
        // Test basic PHP capabilities
        $phpVersion = phpversion();
        $gdEnabled = function_exists('imagecreate');
        $jsonEnabled = function_exists('json_encode');
        
        // Create HTML response for debugging
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Speech-to-Text Debug</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
                pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
                .success { color: green; }
                .error { color: red; }
                .section { margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
            </style>
        </head>
        <body>
            <h1>Speech-to-Text Debug Information</h1>
            
            <div class="section">
                <h2>Environment</h2>
                <p><strong>PHP Version:</strong> ' . $phpVersion . '</p>
                <p><strong>cURL Enabled:</strong> ' . ($curlEnabled ? 'Yes' : 'No') . '</p>
                ' . ($curlEnabled ? '<p><strong>cURL Version:</strong> ' . $curlVersion['version'] . '</p>' : '') . '
                <p><strong>GD Library:</strong> ' . ($gdEnabled ? 'Enabled' : 'Disabled') . '</p>
                <p><strong>JSON Support:</strong> ' . ($jsonEnabled ? 'Enabled' : 'Disabled') . '</p>
            </div>
            
            <div class="section">
                <h2>Watson API Configuration</h2>
                <p><strong>API Key:</strong> ' . substr($apiKey, 0, 5) . '...' . substr($apiKey, -5) . '</p>
                <p><strong>Service URL:</strong> ' . $serviceUrl . '</p>
            </div>';
        
        // Test connection to Watson API
        $html .= '
            <div class="section">
                <h2>Watson API Connection Test</h2>';
        
        try {
            // Use raw cURL for more detailed error information
            $ch = curl_init($serviceUrl . '/v1/models');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode('apikey:' . $apiKey)
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            
            if ($response === false) {
                $html .= '<p class="error">Connection failed: ' . $error . '</p>';
                $html .= '<pre>' . print_r($info, true) . '</pre>';
                $logger->error('Connection to Watson API failed', [
                    'error' => $error,
                    'info' => $info
                ]);
            } else {
                $html .= '<p class="success">Connection successful (HTTP ' . $httpCode . ')</p>';
                
                $result = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $html .= '<p>Available models: ' . (isset($result['models']) ? count($result['models']) : 0) . '</p>';
                    $html .= '<p>First 500 characters of response:</p>';
                    $html .= '<pre>' . htmlspecialchars(substr($response, 0, 500)) . '...</pre>';
                } else {
                    $html .= '<p class="error">Invalid JSON response: ' . json_last_error_msg() . '</p>';
                    $html .= '<pre>' . htmlspecialchars(substr($response, 0, 500)) . '...</pre>';
                }
            }
            
            curl_close($ch);
        } catch (\Exception $e) {
            $html .= '<p class="error">Exception: ' . $e->getMessage() . '</p>';
            $html .= '<pre>' . $e->getTraceAsString() . '</pre>';
            $logger->error('Exception during Watson API connection test', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        $html .= '
            </div>
            
            <div class="section">
                <h2>Test Audio Transcription</h2>
                <form method="post" action="/ibm_test.php" enctype="multipart/form-data">
                    <p>Upload an audio file to test transcription directly:</p>
                    <input type="file" name="audio_file" accept="audio/*">
                    <button type="submit">Test Transcription</button>
                </form>
                <p>Or use our standalone test script: <a href="/ibm_test.php">/ibm_test.php</a></p>
            </div>
        </body>
        </html>';
        
        return new Response($html);
    }
} 