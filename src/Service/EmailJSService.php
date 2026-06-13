<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

class EmailJSService
{
    private const SERVICE_ID = 'service_6d51s0a';
    private const TEMPLATE_ID = 'template_sutsb1u';
    private const USER_ID = 'eh95ayxVsB8mPI6L8';
    private const API_URL = 'https://api.emailjs.com/api/v1.0/email/send';

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendEmail(string $recipientEmail, string $subject, string $message): bool
    {
        $client = HttpClient::create();
        
        try {
            $this->logger->info('Début de l\'envoi d\'email à: ' . $recipientEmail);
            $this->logger->info('Sujet: ' . $subject);
            $this->logger->info('Message: ' . $message);
            
            $requestData = [
                'service_id' => self::SERVICE_ID,
                'template_id' => self::TEMPLATE_ID,
                'user_id' => self::USER_ID,
                'template_params' => [
                    'to_email' => $recipientEmail,
                    'subject' => $subject,
                    'message' => $message
                ]
            ];
            
            $this->logger->info('Données de la requête: ' . json_encode($requestData));
            
            $response = $client->request('POST', self::API_URL, [
                'json' => $requestData
            ]);

            $statusCode = $response->getStatusCode();
            $this->logger->info('Code de réponse HTTP: ' . $statusCode);
            
            if ($statusCode === 200) {
                $this->logger->info('Email envoyé avec succès');
                return true;
            } else {
                $errorContent = $response->getContent(false);
                $this->logger->error('Échec de l\'envoi de l\'email. Code: ' . $statusCode);
                $this->logger->error('Réponse d\'erreur: ' . $errorContent);
                return false;
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur EmailJS: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }
} 