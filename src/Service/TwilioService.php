<?php

namespace App\Service;

use Twilio\Rest\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TwilioService
{
    private $twilioClient;
    private $twilioPhoneNumber;

    public function __construct(ParameterBagInterface $params)
    {
        $accountSid = getenv('TWILIO_ACCOUNT_SID');
        $authToken = getenv('TWILIO_AUTH_TOKEN');
        $this->twilioPhoneNumber = getenv('TWILIO_PHONE_NUMBER');

        if (!$accountSid || !$authToken || !$this->twilioPhoneNumber) {
            throw new \RuntimeException('Twilio credentials are not properly configured.');
        }

        $this->twilioClient = new Client($accountSid, $authToken);
    }

    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any spaces or special characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If the number doesn't start with +216 (Tunisia), add it
        if (!str_starts_with($phoneNumber, '216')) {
            $phoneNumber = '216' . $phoneNumber;
        }
        
        return '+' . $phoneNumber;
    }

    public function sendOrderConfirmation(string $phoneNumber, string $orderNumber, float $amount, \DateTime $date)
    {
        try {
            $formattedPhoneNumber = $this->formatPhoneNumber($phoneNumber);
            
            $message = sprintf(
                "Sent from your Twilio trial account -\nMerci pour votre commande!\nCommande N°%s\nDate: %s\nMontant: %.2f DT\nStatut: CONFIRMÉE",
                $orderNumber,
                $date->format('d/m/Y H:i'),
                $amount
            );

            $result = $this->twilioClient->messages->create(
                $formattedPhoneNumber,
                [
                    'from' => $this->twilioPhoneNumber,
                    'body' => $message
                ]
            );

            // Log successful SMS
            error_log(sprintf('SMS sent successfully to %s for order %s', $formattedPhoneNumber, $orderNumber));
            return true;
        } catch (\Exception $e) {
            // Log the error with details
            error_log(sprintf(
                'Twilio SMS Error: %s. Phone: %s, Order: %s', 
                $e->getMessage(), 
                $formattedPhoneNumber ?? $phoneNumber, 
                $orderNumber
            ));
            return false;
        }
    }
} 