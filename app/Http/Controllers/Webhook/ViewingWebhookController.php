<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\SendViewingPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ViewingWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        SendViewingPush::dispatch($request->input('record'));

        return response()->json(['ok' => true]);
    }
}
