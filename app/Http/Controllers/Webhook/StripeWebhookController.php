<?php

namespace App\Http\Controllers\Webhook;

use App\Constants\Payments;
use App\Constants\Sheets;
use App\Http\Controllers\Controller;
use App\Services\GoogleServices\GoogleSheetsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Stripe\StripeClient;
use Stripe\Webhook as StripeWebhook;

/**
 * @OA\Tag(name="Webhooks")
 */
class StripeWebhookController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/webhooks/stripe/checkout",
     *     summary="Stripe webhook endpoint",
     *     tags={"Webhooks"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook processed successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid webhook signature"
     *     )
     * )
     */
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

        $type = $event->type ?? null;

        try {
            if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
                $session = $event->data->object;
                $paymentStatus = $session->payment_status ?? null;

                if ($type === 'checkout.session.completed' && $paymentStatus !== 'paid') {
                    return response('OK', 200);
                }

                $paymentLinkId = $session->payment_link ?? null;
                $referenceViewingId = $session->client_reference_id ?? null;

                if ($referenceViewingId && config('sheets.export_enabled', true)) {
                    $sheets->markPaidByViewingId(
                        Sheets::VIEWINGS_SHEET_ID,
                        Sheets::VIEWINGS_TAB,
                        (string) $referenceViewingId,
                        Sheets::VIEWINGS_PAID_COLUMN
                    );
                } elseif ($paymentLinkId) {
                    $client = new StripeClient(env('STRIPE_SECRET_KEY') ?: env('STRIPE_SECRET'));
                    $plink = $client->paymentLinks->retrieve($paymentLinkId, []);
                    $paymentLinkUrl = $plink->url ?? null;
                    $checkoutType = $plink->metadata['checkout_type'] ?? null;
                    $isViewingLink = $checkoutType === 'viewing'
                        || $paymentLinkUrl === Payments::STRIPE_VIEWING_EXPRESS_LINK
                        || $paymentLinkUrl === Payments::STRIPE_VIEWING_STANDARD_LINK;

                    if ($paymentLinkUrl && $isViewingLink && config('sheets.export_enabled', true)) {
                        $sheets->markPaidByPaymentLink(
                            Sheets::VIEWINGS_SHEET_ID,
                            Sheets::VIEWINGS_TAB,
                            $paymentLinkUrl,
                            Sheets::VIEWINGS_PAID_COLUMN
                        );
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


