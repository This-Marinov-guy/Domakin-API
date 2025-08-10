<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\GoogleServices\GoogleSheetsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Stripe\StripeClient;
use Stripe\Webhook as StripeWebhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, GoogleSheetsService $sheets): Response
    {
        $signatureHeader = $request->header('Stripe-Signature');
        $webhookSecret = env('STRIPE_WEBHOOK_CH_KEY');

        $payload = $request->getContent();

        try {
            if ($webhookSecret) {
                $event = StripeWebhook::constructEvent($payload, $signatureHeader, $webhookSecret);
            } else {
                $event = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
            }
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature verification failed: '.$e->getMessage());
            return response('Invalid signature', 400);
        }

        $type = $event->type ?? ($event->type ?? null);

        try {
            if ($type === 'checkout.session.completed') {
                $session = $event->data->object;
                $paymentLinkId = $session->payment_link ?? null;

                if ($paymentLinkId) {
                    $client = new StripeClient(env('STRIPE_SECRET_KEY') ?: env('STRIPE_SECRET'));
                    $plink = $client->paymentLinks->retrieve($paymentLinkId, []);
                    $paymentLinkUrl = $plink->url ?? null;
                    $checkoutType = $plink->metadata['checkout_type'] ?? null;

                    if ($paymentLinkUrl && $checkoutType === 'viewing') {
                        $sheetId = '1asA0dtjw7jk7BADin97SaMNDiB_Eb0m6yClPKp-6iAQ';
                        $sheets->markPaidByPaymentLink($sheetId, 'viewingsAndLinks', $paymentLinkUrl);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing error: '.$e->getMessage());
            // Do not fail webhook delivery; acknowledge receipt
        }

        return response('OK', 200);
    }
}


