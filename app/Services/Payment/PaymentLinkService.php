<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Exception;

class PaymentLinkService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $secretKey = env('STRIPE_SECRET_KEY');
        $this->stripe = new StripeClient($secretKey);
    }

    public function createPropertyFeeLink(float $amountEur, string $productName = 'Property Fee (1 month rent)', array $metadata = []): ?string
    {
        try {
            $amountEur = ceil($amountEur);
            $unitAmount = (int)($amountEur * 100);

            $product = $this->stripe->products->create([
                'name' => $productName,
            ]);

            $price = $this->stripe->prices->create([
                'unit_amount' => $unitAmount,
                'currency' => 'eur',
                'product' => $product->id,
            ]);

            $paymentLink = $this->stripe->paymentLinks->create([
                'line_items' => [
                    [
                        'price' => $price->id,
                        'quantity' => 1,
                    ]
                ],
                'metadata' => $metadata,
            ]);

            return $paymentLink->url;
        } catch (Exception $e) {
            Log::error('Failed to create Stripe payment link: ' . $e->getMessage());
            return null;
        }
    }
}


