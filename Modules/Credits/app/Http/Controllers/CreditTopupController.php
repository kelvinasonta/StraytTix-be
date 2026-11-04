<?php

namespace Modules\Credits\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Credits\app\Models\CreditTopupTransaction;
use Modules\Credits\app\Services\CreditLedgerService;
use Modules\Credits\app\Services\CreditTopupService;
use Modules\Organizer\app\Models\Organizer;

class CreditTopupController extends Controller
{
    public function __construct(
        protected CreditLedgerService $creditLedgerService,
        protected CreditTopupService $creditTopupService
    ) {}

    /**
     * Resolve the active organizer from route parameters or request attributes.
     */
    protected function resolveOrganizer(Request $request, string $organizerUuid): Organizer
    {
        /** @var Organizer|null $organizer */
        $organizer = $request->attributes->get('active_organizer');

        if ($organizer instanceof Organizer) {
            return $organizer;
        }

        return Organizer::where('uuid', $organizerUuid)
            ->orWhere('id', $organizerUuid)
            ->firstOrFail();
    }

    /**
     * Initialize a credit top-up checkout session.
     * POST /api/v1/organizers/{organizerUuid}/credits/top-up/initialize
     */
    public function initialize(Request $request, string $organizerUuid): JsonResponse
    {
        $organizer = $this->resolveOrganizer($request, $organizerUuid);
        $wallet = $this->creditLedgerService->getOrCreateWallet($organizer);

        $validated = $request->validate([
            'credits' => 'required_without:amount|nullable|integer|min:1',
            'amount' => 'required_without:credits|nullable|numeric|min:1',
            'callback_url' => 'nullable|url',
        ]);

        // Rate: 1 credit = 10 NGN = 1000 kobo
        if (! empty($validated['credits'])) {
            $credits = (int) $validated['credits'];
            $amountKobo = $credits * 1000;
        } else {
            $amountNgn = (float) $validated['amount'];
            $amountKobo = (int) round($amountNgn * 100);
            $credits = (int) floor($amountKobo / 1000);
        }

        if ($credits <= 0) {
            return ApiResponse::error('Minimum purchase is 1 credit (10 NGN).', 422);
        }

        $user = $request->user();
        $email = $user?->email ?? $organizer->email ?? 'billing@eventsos.com';
        $reference = 'CREDIT-'.strtoupper(Str::random(12));

        $resolvedCallback = $validated['callback_url']
            ?? config('app.frontend_url', config('app.url'))."/credits/callback?reference={$reference}";

        // Pre-create the pending top-up record
        $topup = CreditTopupTransaction::create([
            'wallet_id' => $wallet->id,
            'provider' => 'paystack',
            'provider_ref' => $reference,
            'amount_kobo' => $amountKobo,
            'credits_awarded' => $credits,
            'status' => 'PENDING',
            'metadata' => [
                'organizer_id' => $organizer->id,
                'organizer_uuid' => $organizer->uuid,
                'user_id' => $user?->id,
            ],
        ]);

        $checkoutUrl = $resolvedCallback;
        $secretKey = config('paystack.secret_key');

        if (! empty($secretKey) && ! app()->environment('testing')) {
            try {
                $paystackUrl = config('paystack.payment_url') ?: 'https://api.paystack.co';
                $response = Http::withToken($secretKey)->post(rtrim($paystackUrl, '/').'/transaction/initialize', [
                    'email' => $email,
                    'amount' => $amountKobo,
                    'reference' => $reference,
                    'currency' => $wallet->currency,
                    'callback_url' => $resolvedCallback,
                    'metadata' => [
                        'wallet_id' => $wallet->id,
                        'organizer_id' => $organizer->id,
                        'organizer_uuid' => $organizer->uuid,
                        'credits_purchased' => $credits,
                        'topup_id' => $topup->id,
                    ],
                ]);

                if ($response->successful() && $response->json('data.authorization_url')) {
                    $checkoutUrl = (string) $response->json('data.authorization_url');
                } else {
                    Log::warning('Paystack checkout initialization responded with non-success', [
                        'response' => $response->json(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Exception during Paystack checkout initialization: '.$e->getMessage());
            }
        }

        return ApiResponse::success('Credit top-up checkout initialized successfully.', [
            'reference' => $reference,
            'checkout_url' => $checkoutUrl,
            'credits' => $credits,
            'amount_kobo' => $amountKobo,
            'currency' => $wallet->currency,
        ]);
    }

    /**
     * Public webhook route to receive payment notifications from Paystack.
     * POST /api/v1/credits/webhooks/paystack
     */
    public function webhook(Request $request): JsonResponse
    {
        $signature = (string) ($request->header('x-paystack-signature') ?? '');
        $payload = $request->all();

        $result = $this->creditTopupService->processPaystackWebhook($payload, $signature);

        if (($result['status'] ?? '') === 'invalid_signature') {
            return ApiResponse::error($result['message'] ?? 'Invalid signature.', 400);
        }

        if (($result['status'] ?? '') === 'error') {
            return ApiResponse::error($result['message'] ?? 'Webhook processing error.', 422);
        }

        return response()->json($result, 200);
    }
}

