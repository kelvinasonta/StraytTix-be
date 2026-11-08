<?php

declare(strict_types=1);

namespace Modules\Payments\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organizer\app\Enum\OrganizerPermission;
use Modules\Organizer\app\Models\Organizer;
use Modules\Payments\app\Enums\OrderStatus;
use Modules\Payments\app\Models\Order;

class PayoutController extends Controller
{
    /**
     * Retrieve payout summary, settlement bank account, and payout history for an organizer.
     */
    public function index(Request $request, ?string $organizerUuid = null): JsonResponse
    {
        $user = $request->user();

        $organizer = $request->attributes->get('active_organizer');

        if (! $organizer instanceof Organizer) {
            $requestedUuid = $organizerUuid
                ?? $request->query('organizerUuid')
                ?? $request->query('organizer_uuid')
                ?? $request->header('X-Organizer-UUID');

            if (is_string($requestedUuid) && $requestedUuid !== '') {
                $organizer = Organizer::with(['members', 'bank'])
                    ->where('uuid', $requestedUuid)
                    ->firstOrFail();
            } else {
                $organizer = $user?->resolveActiveOrganizer();
                $organizer?->loadMissing(['members', 'bank']);
            }
        } else {
            $organizer->loadMissing(['members', 'bank']);
        }

        if (! $organizer) {
            return ApiResponse::error('No active organizer found for this account.', 404);
        }

        if (! $user || ! $organizer->userHasPermission($user, OrganizerPermission::FINANCE_VIEW)) {
            return ApiResponse::error(
                'You do not have the required permission (org.finance.view) for this organization.',
                403
            );
        }

        $paidOrdersQuery = Order::query()
            ->where('organizer_id', $organizer->id)
            ->where('status', OrderStatus::Paid);

        $grossRevenue = (float) (clone $paidOrdersQuery)->sum('total');
        $subtotal = (float) (clone $paidOrdersQuery)->sum('subtotal');
        $discount = (float) (clone $paidOrdersQuery)->sum('discount');
        $serviceFees = (float) (clone $paidOrdersQuery)->sum('service_fee');
        $paidOrdersCount = (int) (clone $paidOrdersQuery)->count();

        $netRevenue = max(0.0, round($subtotal - $discount, 2));
        $totalPaidOut = 0.0;
        $availableBalance = max(0.0, round($netRevenue - $totalPaidOut, 2));
        $pendingBalance = 0.0;

        $currency = (string) ((clone $paidOrdersQuery)->latest('id')->value('currency') ?? 'NGN');

        $bank = $organizer->bank;
        $bankAccount = $bank ? [
            'bank_name' => $bank->bank_name,
            'bank_code' => $bank->bank_code,
            'account_name' => $bank->account_name,
            'account_number' => $bank->account_number,
            'is_default' => (bool) $bank->is_default,
        ] : null;

        $summary = [
            'gross_revenue' => round($grossRevenue, 2),
            'net_revenue' => $netRevenue,
            'total_revenue' => $netRevenue,
            'service_fees' => round($serviceFees, 2),
            'total_orders' => $paidOrdersCount,
            'total_paid_out' => $totalPaidOut,
            'available_balance' => $availableBalance,
            'pending_balance' => $pendingBalance,
            'currency' => $currency,
        ];

        return ApiResponse::success('Payouts retrieved successfully.', [
            'organizer_uuid' => $organizer->uuid,
            ...$summary,
            'summary' => $summary,
            'bank_account' => $bankAccount,
            'payouts' => [],
            'items' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0,
            ],
        ]);
    }
}
