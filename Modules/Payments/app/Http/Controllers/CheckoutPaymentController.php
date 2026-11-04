<?php

declare(strict_types=1);

namespace Modules\Payments\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Payments\app\Exceptions\OrderException;
use Modules\Payments\app\Models\Order;
use Modules\Payments\app\Services\PaymentService;
use Modules\Payments\app\Transformers\OrderResource;

class CheckoutPaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Initialize payment for an order.
     */
    public function initialize(Request $request, string $orderUuid): JsonResponse
    {
        $validated = $request->validate([
            'gateway' => 'nullable|string|in:paystack,stripe,free',
            'callback_url' => 'nullable|url',
        ]);

        $order = Order::where('uuid', $orderUuid)->firstOrFail();

        // Ensure user is authorized to pay for this order
        if ($order->user_id !== $request->user()->id) {
            return ApiResponse::error('Unauthorized to initiate payment for this order.', 403);
        }

        try {
            $result = $this->paymentService->initializePayment(
                order: $order,
                gateway: $validated['gateway'] ?? 'paystack',
                callbackUrl: $validated['callback_url'] ?? null
            );

            return ApiResponse::success('Payment initialized successfully.', $result);
        } catch (OrderException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Verify payment status using gateway reference.
     */
    public function verify(Request $request, string $reference): JsonResponse
    {
        try {
            $order = $this->paymentService->verifyPayment($reference);

            return ApiResponse::success(
                'Payment verified successfully.',
                new OrderResource($order)
            );
        } catch (OrderException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Payment gateway webhook receiver.
     */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        $payload = $request->all();

        if (strtolower($gateway) === 'paystack') {
            $event = $payload['event'] ?? '';
            $reference = $payload['data']['reference'] ?? null;

            if ($event === 'charge.success' && $reference) {
                try {
                    $this->paymentService->verifyPayment($reference, $payload['data']);
                } catch (\Exception $e) {
                    Log::error("Payment webhook verification failed for reference {$reference}: {$e->getMessage()}");
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}
