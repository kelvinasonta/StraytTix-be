<?php

declare(strict_types=1);

namespace Modules\Credits\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Credits\app\Services\CreditGatewayManager;
use Modules\Credits\app\Services\CreditLedgerService;
use Modules\Credits\app\Services\CreditTopupService;
use Modules\Organizer\app\Models\Organizer;
use Throwable;

class CreditTopupController extends Controller
{
    public function __construct(
        protected CreditLedgerService $ledgerService,
        protected CreditTopupService $topupService,
        protected CreditGatewayManager $gatewayManager
    ) {}

    /**
     * Get available payment gateways for credit top-up.
     */
    public function gateways(): JsonResponse
    {
        return ApiResponse::success('Available payment gateways retrieved successfully.', [
            'default_gateway' => $this->gatewayManager->getDefaultDriverName(),
            'gateways' => $this->gatewayManager->getAvailableDrivers(),
            'rate_per_credit_kobo' => (int) (config('credits.rate_per_credit_kobo') ?: 1000),
        ]);
    }

    /**
     * Initialize a credit top-up checkout session.
     */
    public function initialize(Request $request, string $organizerUuid): JsonResponse
    {
        $validated = $request->validate([
            'credits' => 'required|integer|min:10|max:1000000',
            'callback_url' => 'nullable|url',
            'gateway' => 'nullable|string',
        ]);

        $organizer = $request->attributes->get('active_organizer')
            ?? Organizer::where('uuid', $organizerUuid)->firstOrFail();

        $wallet = $this->ledgerService->getOrCreateWallet($organizer);
        $customerEmail = $request->user()?->email ?: $organizer->email ?: 'billing@strayttix.com';
        $callbackUrl = $validated['callback_url'] ?? url("/organizer/{$organizer->uuid}/credits");

        try {
            $checkout = $this->topupService->initializeTopup(
                wallet: $wallet,
                credits: (int) $validated['credits'],
                callbackUrl: $callbackUrl,
                customerEmail: $customerEmail,
                gatewayName: $validated['gateway'] ?? null,
                metadata: [
                    'organizer_id' => $organizer->id,
                    'organizer_uuid' => $organizer->uuid,
                    'user_id' => $request->user()?->id,
                ]
            );

            return ApiResponse::success('Payment checkout initialized successfully.', $checkout->toArray());
        } catch (Throwable $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Unified multi-gateway webhook ingestion handler.
     */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        $gatewayName = strtolower($gateway);

        // Header detection for signature
        $signature = match ($gatewayName) {
            'flutterwave' => (string) $request->header('verif-hash', ''),
            default => (string) $request->header('x-paystack-signature', $request->header('signature', '')),
        };

        $rawPayload = $request->getContent();
        $payload = $request->all();

        try {
            $result = $this->topupService->processWebhook(
                gatewayName: $gatewayName,
                payload: $payload,
                signature: $signature,
                rawPayload: $rawPayload
            );

            if (($result['status'] ?? '') === 'invalid_signature') {
                return response()->json(['message' => 'Invalid webhook signature.'], 400);
            }

            return response()->json($result, 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
