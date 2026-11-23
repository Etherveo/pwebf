<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Payments\PaymentAdapter;
use App\Payments\ProviderRegistry;
use App\Payments\SignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Versioned payment webhook endpoint. Provider validity and signature checks
// are enforced by ProviderRegistry and the active PaymentAdapter.
class WebhookController extends Controller
{
    public function handle(Request $request, string $provider): JsonResponse
    {
        if (! ProviderRegistry::isKnownProvider($provider)) {
            return response()->json(['result' => 'error', 'message' => 'Provider tidak dikenal.'], 404);
        }

        $rawBody = (string) $request->getContent();
        $signature = $request->header(SignatureVerifier::HEADER);
        $requestId = (string) Str::uuid();

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            Log::warning('Webhook pembayaran: JSON payload tidak valid.', [
                'provider' => $provider,
                'request_id' => $requestId,
            ]);

            return response()->json(['result' => 'invalid', 'message' => 'Body harus JSON yang valid.'], 422);
        }

        $adapter = app(ProviderRegistry::adapterClassForProvider($provider));
        $result = $adapter->handleCallback($payload, $signature, $rawBody);

        Log::info('Webhook pembayaran diproses.', [
            'provider' => $provider,
            'request_id' => $result->requestId ?? $requestId,
            'payment_id' => $result->payment?->id,
            'result' => $result->result,
            'http_status' => $result->httpStatus(),
        ]);

        return response()->json($result->toArray(), $result->httpStatus());
    }
}
