<?php

declare(strict_types=1);

namespace Modules\Payments\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Models\Organizer;
use Modules\Payments\app\Exceptions\OrderException;
use Modules\Payments\app\Services\OrderService;
use Modules\Payments\app\Transformers\OrderResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Create a new order (ticket purchase or reservation).
     */
    public function store(Request $request, string $eventUuid): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.ticket_type_uuid' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.attendee_name' => 'nullable|string|max:255',
            'items.*.attendee_email' => 'nullable|email|max:255',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $event = Event::where('uuid', $eventUuid)->orWhere('slug', $eventUuid)->firstOrFail();

        try {
            $order = $this->orderService->createOrder(
                buyer: $request->user(),
                event: $event,
                itemsData: $validated['items'],
                customerData: [
                    'customer_name' => $validated['customer_name'] ?? null,
                    'customer_email' => $validated['customer_email'] ?? null,
                    'customer_phone' => $validated['customer_phone'] ?? null,
                ],
                notes: $validated['notes'] ?? null
            );

            return ApiResponse::success(
                'Order created successfully.',
                new OrderResource($order),
                201
            );
        } catch (OrderException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Get a specific order by UUID.
     */
    public function show(Request $request, string $orderUuid): JsonResponse
    {
        $order = $this->orderService->getOrder($orderUuid);

        // Ensure user is authorized to view this order (buyer or organizer member)
        $user = $request->user();
        if ($user && $order->user_id !== $user->id) {
            $isOrganizerMember = $order->organizer->members()->where('user_id', $user->id)->exists();
            if (! $isOrganizerMember && $order->organizer->user_id !== $user->id) {
                return ApiResponse::error('Unauthorized to view this order.', 403);
            }
        }

        return ApiResponse::success(
            'Order retrieved successfully.',
            new OrderResource($order)
        );
    }

    /**
     * Get orders for the authenticated customer.
     */
    public function userOrders(Request $request): JsonResponse
    {
        $orders = $this->orderService->getUserOrders($request->user(), $request->all());

        return ApiResponse::success(
            'Orders retrieved successfully.',
            OrderResource::collection($orders)->response()->getData(true)
        );
    }

    /**
     * Get orders for an organizer (optionally filtered by event).
     */
    public function organizerOrders(Request $request, string $organizerUuid, ?string $eventUuid = null): JsonResponse
    {
        $organizer = $request->attributes->get('active_organizer')
            ?? Organizer::where('uuid', $organizerUuid)->firstOrFail();

        $orders = $this->orderService->getOrganizerOrders($organizer, $eventUuid, $request->all());

        return ApiResponse::success(
            'Organizer orders retrieved successfully.',
            OrderResource::collection($orders)->response()->getData(true)
        );
    }

    /**
     * Export organizer orders as CSV.
     */
    public function exportOrganizerOrders(Request $request, string $organizerUuid, ?string $eventUuid = null): StreamedResponse
    {
        $organizer = $request->attributes->get('active_organizer')
            ?? Organizer::where('uuid', $organizerUuid)->firstOrFail();

        $csv = $this->orderService->exportOrdersCsv($organizer, $eventUuid);

        $filename = 'orders-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
