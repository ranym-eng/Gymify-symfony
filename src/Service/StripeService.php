<?php

namespace App\Service;

use Stripe\StripeClient;

class StripeService
{
    private $stripe;

    public function __construct(string $stripeSecretKey)
    {
        $this->stripe = new StripeClient($stripeSecretKey);
    }

    public function createPaymentIntent(float $amount, string $currency): string
    {
        $paymentIntent = $this->stripe->paymentIntents->create([
            'amount' => (int)$amount, // Amount already in cents
            'currency' => $currency,
            'payment_method_types' => ['card'],
        ]);
    
        return $paymentIntent->id;
    }

    public function confirmPaymentIntent(string $paymentIntentId, string $paymentMethodId): \Stripe\PaymentIntent
    {
        return $this->stripe->paymentIntents->confirm($paymentIntentId, [
            'payment_method' => $paymentMethodId,
        ]);
    }
}