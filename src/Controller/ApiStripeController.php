<?php

namespace App\Controller;

use App\Entity\Abonnement;
use App\Entity\Paiement;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/stripe')]
class ApiStripeController extends AbstractController
{
    private $stripeSecretKey;

    public function __construct(LoggerInterface $logger)
    {
        $this->stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!$this->stripeSecretKey) {
            $logger->error('Stripe secret key not configured');
            throw new \Exception('Stripe secret key not configured');
        }
        $logger->info('Stripe secret key loaded', [
            'partial_key' => substr($this->stripeSecretKey, 0, 5) . '...',
        ]);
    }

    #[Route('/payment-intent', name: 'api_stripe_create_payment_intent', methods: ['POST'])]
    public function createPaymentIntent(Request $request, LoggerInterface $logger): JsonResponse
    {
        try {
            $logger->info('Received request to create Payment Intent', [
                'content' => $request->getContent(),
            ]);

            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $logger->error('Invalid JSON in request', ['error' => json_last_error_msg()]);
                return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
            }

            $amount = $data['amount'] ?? null;
            $currency = $data['currency'] ?? 'usd'; // Use 'usd' for Tunisian Dinar

            if (!$amount || !is_numeric($amount) || $amount <= 0) {
                $logger->error('Invalid amount provided', ['amount' => $amount]);
                return new JsonResponse(['error' => 'Amount must be a positive number'], Response::HTTP_BAD_REQUEST);
            }

            $logger->info('Creating Payment Intent with parameters', [
                'amount' => $amount,
                'converted_amount' => (int)($amount * 100),
                'currency' => $currency,
                'stripe_secret_key' => substr($this->stripeSecretKey, 0, 5) . '...',
            ]);

            $client = HttpClient::create();
            $response = $client->request('POST', 'https://api.stripe.com/v1/payment_intents', [
                'auth_basic' => [$this->stripeSecretKey, ''],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => http_build_query([
                    'amount' => (int)($amount * 100),
                    'currency' => $currency,
                    'payment_method_types' => ['card'],
                ]),
            ]);

            $responseData = $response->toArray(false);

            if ($response->getStatusCode() !== 200) {
                $logger->error('Stripe API error', [
                    'status_code' => $response->getStatusCode(),
                    'response' => $responseData,
                    'request_body' => [
                        'amount' => (int)($amount * 100),
                        'currency' => $currency,
                        'payment_method_types' => ['card'],
                    ],
                ]);
                return new JsonResponse(['error' => $responseData['error']['message'] ?? 'Failed to create Payment Intent'], Response::HTTP_BAD_REQUEST);
            }

            $logger->info('Payment Intent created successfully', ['paymentIntentId' => $responseData['id']]);
            return new JsonResponse([
                'paymentIntentId' => $responseData['id'],
                'clientSecret' => $responseData['client_secret'],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $logger->error('Exception in createPaymentIntent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new JsonResponse(['error' => 'Server error: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/payment-intent/confirm', name: 'api_stripe_confirm_payment_intent', methods: ['POST'])]
    public function confirmPaymentIntent(Request $request, LoggerInterface $logger, EntityManagerInterface $em): JsonResponse
    {
        try {
            $logger->info('Received request to confirm Payment Intent', [
                'content' => $request->getContent(),
            ]);

            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $logger->error('Invalid JSON in request', ['error' => json_last_error_msg()]);
                return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
            }

            $paymentIntentId = $data['paymentIntentId'] ?? null;
            $paymentMethodId = $data['paymentMethodId'] ?? null;
            $abonnementId = $data['abonnementId'] ?? null;
            $amount = $data['amount'] ?? null; // Added to match Paiement entity

            if (!$paymentIntentId || !$paymentMethodId || !$abonnementId || !$amount) {
                $logger->error('Missing required fields', [
                    'paymentIntentId' => $paymentIntentId,
                    'paymentMethodId' => $paymentMethodId,
                    'abonnementId' => $abonnementId,
                    'amount' => $amount,
                ]);
                return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
            }

            $client = HttpClient::create();
            $response = $client->request('POST', "https://api.stripe.com/v1/payment_intents/$paymentIntentId/confirm", [
                'auth_basic' => [$this->stripeSecretKey, ''],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => http_build_query([
                    'payment_method' => $paymentMethodId,
                ]),
            ]);

            $responseData = $response->toArray(false);

            if ($response->getStatusCode() !== 200) {
                $logger->error('Stripe confirm error', [
                    'status_code' => $response->getStatusCode(),
                    'response' => $responseData,
                ]);
                return new JsonResponse(['error' => $responseData['error']['message'] ?? 'Failed to confirm Payment Intent'], Response::HTTP_BAD_REQUEST);
            }

            // Save payment record
            $abonnement = $em->getRepository(Abonnement::class)->find($abonnementId);
            if (!$abonnement) {
                $logger->error('Abonnement not found', ['abonnementId' => $abonnementId]);
                return new JsonResponse(['error' => 'Subscription not found'], Response::HTTP_BAD_REQUEST);
            }

         // In confirmPaymentIntent, update the Paiement creation block:
$paiement = new Paiement();
$paiement->setUser($this->getUser());
$paiement->setAbonnement($abonnement);
$paiement->setStatus($responseData['status']);
$paiement->setAmount($amount);
$paiement->setCurrency($data['currency'] ?? 'usd');
$paiement->setPaymentIntentId($paymentIntentId); // Add this line
$paiement->setCreatedAt(new \DateTimeImmutable());
$paiement->setUpdatedAt(new \DateTimeImmutable());
$dateDebut = new \DateTimeImmutable();
$dateFin = match ($abonnement->getType()->value) {
    'mois' => $dateDebut->modify('+1 month'),
    'trimestre' => $dateDebut->modify('+3 months'),
    'année' => $dateDebut->modify('+1 year'),
    default => $dateDebut->modify('+1 month'),
};
$paiement->setDateDebut($dateDebut);
$paiement->setDateFin($dateFin);

            $em->persist($paiement);
            $em->flush();

            $logger->info('Payment Intent confirmed and saved', [
                'paymentIntentId' => $paymentIntentId,
                'status' => $responseData['status'],
            ]);

            return new JsonResponse([
                'status' => $responseData['status'],
                'paymentIntentId' => $paymentIntentId,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $logger->error('Exception in confirmPaymentIntent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new JsonResponse(['error' => 'Server error: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}