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

    public function createPropertyFeeLink(float $amountEur, string $productName = 'Property Fee (1 month rent)', string $description = null, array $metadata = []): ?string
    {
        // If description is null, use the product name as the description
        if ($description === null) {
            $description = $productName;
        }
        try {
            $amountEur = ceil($amountEur);
            $unitAmount = (int)($amountEur * 100);
            
            // Check for existing links with the same parameters
            $existingLink = $this->findExistingPaymentLink($unitAmount, $productName, $description);
            if ($existingLink) {
                Log::info("Using existing payment link for {$productName} with amount {$amountEur}EUR");
                return $existingLink;
            }

            $product = $this->stripe->products->create([
                'name' => $productName,
                'description' => $description,
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
    
    /**
     * Find an existing payment link with the same amount, product name, and description
     * 
     * @param int $unitAmount Amount in cents
     * @param string $productName Product name to match
     * @param string $description Product description to match
     * @return string|null URL of existing payment link or null if not found
     */
    private function findExistingPaymentLink(int $unitAmount, string $productName, string $description): ?string
    {
        try {
            // Get all active payment links
            $paymentLinks = $this->stripe->paymentLinks->all(['limit' => 100, 'active' => true]);
            
            foreach ($paymentLinks->data as $link) {
                // Skip links without line items
                if (empty($link->line_items->data)) {
                    continue;
                }
                
                $lineItem = $link->line_items->data[0];
                
                // Skip if no price or product
                if (!isset($lineItem->price) || !isset($lineItem->price->product)) {
                    continue;
                }
                
                // Get the product details to check the name
                $productId = $lineItem->price->product;
                $product = $this->stripe->products->retrieve($productId);
                
                // Check if the amount, product name, and description match
                if ($product->name === $productName && 
                    $lineItem->price->unit_amount === $unitAmount && 
                    $product->description === $description) {
                    return $link->url;
                }
            }
            
            return null;
        } catch (Exception $e) {
            Log::warning('Error checking for existing payment links: ' . $e->getMessage());
            return null;
        }
    }
}


