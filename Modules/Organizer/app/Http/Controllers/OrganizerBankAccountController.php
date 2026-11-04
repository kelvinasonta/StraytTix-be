<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerBankAccounts;

class OrganizerBankAccountController extends Controller
{
    /**
     * Get list of supported banks (Nigeria).
     * Supports Flutterwave if credentials are provided, or Paystack / cached list.
     */
    public function getBanks(): JsonResponse
    {
        $banks = Cache::remember('nigerian_banks_list', 86400, function () {
            // 1. Try Flutterwave if secret key is configured
            $flwSecret = config('services.flutterwave.secret_key') ?: env('FLW_SECRET_KEY') ?: env('FLUTTERWAVE_SECRET_KEY');
            if ($flwSecret) {
                try {
                    $response = Http::withToken($flwSecret)->timeout(10)->get('https://api.flutterwave.com/v3/banks/NG');
                    if ($response->successful() && ! empty($response->json('data'))) {
                        return collect($response->json('data'))->map(function ($item) {
                            return [
                                'name' => $item['name'],
                                'code' => (string) ($item['code'] ?? $item['id']),
                                'slug' => strtolower(str_replace(' ', '-', $item['name'])),
                            ];
                        })->sortBy('name')->values()->all();
                    }
                } catch (\Exception $e) {
                    // fall back
                }
            }

            // 2. Try Paystack if secret key is configured
            $paystackKey = config('paystack.secret_key');
            if ($paystackKey) {
                try {
                    $response = Http::withToken($paystackKey)->timeout(10)->get(config('paystack.secret_key').'/bank');
                    if ($response->successful() && ! empty($response->json('data'))) {
                        return collect($response->json('data'))->map(function ($item) {
                            return [
                                'name' => $item['name'],
                                'code' => (string) $item['code'],
                                'slug' => $item['slug'] ?? strtolower(str_replace(' ', '-', $item['name'])),
                            ];
                        })->sortBy('name')->values()->all();
                    }
                } catch (\Exception $e) {
                    // fall back
                }
            }

            // 3. Fallback popular banks list
            return [
                ['name' => 'Access Bank', 'code' => '044', 'slug' => 'access-bank'],
                ['name' => 'Access Bank (Diamond)', 'code' => '063', 'slug' => 'access-bank-diamond'],
                ['name' => 'Ecobank Nigeria', 'code' => '050', 'slug' => 'ecobank-nigeria'],
                ['name' => 'Fidelity Bank', 'code' => '070', 'slug' => 'fidelity-bank'],
                ['name' => 'First Bank of Nigeria', 'code' => '011', 'slug' => 'first-bank-of-nigeria'],
                ['name' => 'First City Monument Bank (FCMB)', 'code' => '214', 'slug' => 'first-city-monument-bank'],
                ['name' => 'Guaranty Trust Bank (GTBank)', 'code' => '058', 'slug' => 'guaranty-trust-bank'],
                ['name' => 'Heritage Bank', 'code' => '030', 'slug' => 'heritage-bank'],
                ['name' => 'Keystone Bank', 'code' => '082', 'slug' => 'keystone-bank'],
                ['name' => 'Kuda Bank', 'code' => '50211', 'slug' => 'kuda-bank'],
                ['name' => 'Moniepoint MFB', 'code' => '50515', 'slug' => 'moniepoint-mfb'],
                ['name' => 'OPay (PayCom)', 'code' => '999992', 'slug' => 'opay'],
                ['name' => 'Palmpay', 'code' => '999991', 'slug' => 'palmpay'],
                ['name' => 'Polaris Bank', 'code' => '076', 'slug' => 'polaris-bank'],
                ['name' => 'Stanbic IBTC Bank', 'code' => '221', 'slug' => 'stanbic-ibtc-bank'],
                ['name' => 'Standard Chartered Bank', 'code' => '068', 'slug' => 'standard-chartered-bank'],
                ['name' => 'Sterling Bank', 'code' => '232', 'slug' => 'sterling-bank'],
                ['name' => 'Union Bank of Nigeria', 'code' => '032', 'slug' => 'union-bank-of-nigeria'],
                ['name' => 'United Bank for Africa (UBA)', 'code' => '033', 'slug' => 'united-bank-for-africa'],
                ['name' => 'Unity Bank', 'code' => '215', 'slug' => 'unity-bank'],
                ['name' => 'Wema Bank', 'code' => '035', 'slug' => 'wema-bank'],
                ['name' => 'Zenith Bank', 'code' => '057', 'slug' => 'zenith-bank'],
            ];
        });

        return ApiResponse::success('Banks retrieved successfully.', $banks);
    }

    /**
     * Resolve account number to retrieve account name.
     */
    public function resolveAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_number' => 'required|string|size:10',
            'bank_code' => 'required|string',
        ]);

        $accountNumber = $validated['account_number'];
        $bankCode = $validated['bank_code'];

        // 1. Try Flutterwave verification if secret key is present
        $flwSecret = config('services.flutterwave.secret_key') ?: env('FLW_SECRET_KEY') ?: env('FLUTTERWAVE_SECRET_KEY');
        if ($flwSecret) {
            try {
                $flwRes = Http::withToken($flwSecret)->timeout(10)->post('https://api.flutterwave.com/v3/accounts/resolve', [
                    'account_number' => $accountNumber,
                    'account_bank' => $bankCode,
                ]);

                if ($flwRes->successful() && ! empty($flwRes->json('data.account_name'))) {
                    return ApiResponse::success('Account name resolved.', [
                        'account_number' => $accountNumber,
                        'account_name' => $flwRes->json('data.account_name'),
                        'bank_code' => $bankCode,
                    ]);
                }
            } catch (\Exception $e) {
                // proceed to paystack
            }
        }

        // 2. Try Paystack verification
        $paystackKey = config('services.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY');
        if ($paystackKey) {
            try {
                // If using test code 001 or standard bank resolution
                $paystackRes = Http::withToken($paystackKey)->timeout(10)->get('https://api.paystack.co/bank/resolve', [
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                ]);

                if ($paystackRes->successful() && ! empty($paystackRes->json('data.account_name'))) {
                    return ApiResponse::success('Account name resolved.', [
                        'account_number' => $accountNumber,
                        'account_name' => $paystackRes->json('data.account_name'),
                        'bank_code' => $bankCode,
                    ]);
                }

                // If test mode error or invalid test bank code in dev, provide clean simulated test account
                if ($paystackRes->status() === 429 || $paystackRes->json('code') === 'unknown') {
                    $mockRes = Http::withToken($paystackKey)->timeout(5)->get('https://api.paystack.co/bank/resolve', [
                        'account_number' => $accountNumber,
                        'bank_code' => '001',
                    ]);
                    if ($mockRes->successful() && ! empty($mockRes->json('data.account_name'))) {
                        return ApiResponse::success('Account name resolved (Test Mode).', [
                            'account_number' => $accountNumber,
                            'account_name' => $mockRes->json('data.account_name'),
                            'bank_code' => $bankCode,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // fall through
            }
        }

        return ApiResponse::error('Could not resolve account name. Please verify the account number and bank.', 422);
    }

    /**
     * Get organizer connected settlement bank account.
     */
    public function show(string $organizerUuid): JsonResponse
    {
        $organizer = Organizer::where('uuid', $organizerUuid)->firstOrFail();
        $bank = $organizer->bank;

        if (! $bank) {
            return ApiResponse::success('No settlement bank account configured.', null);
        }

        return ApiResponse::success('Settlement bank account retrieved.', [
            'bank_name' => $bank->bank_name,
            'bank_code' => $bank->bank_code,
            'account_number' => $bank->account_number,
            'account_name' => $bank->account_name,
            'is_default' => (bool) $bank->is_default,
            'verified_at' => $bank->verified_at,
        ]);
    }

    /**
     * Save or update organizer settlement bank account.
     */
    public function store(Request $request, string $organizerUuid): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'required|string|max:50',
            'account_number' => 'required|string|size:10',
            'account_name' => 'required|string|max:255',
        ]);

        $organizer = Organizer::where('uuid', $organizerUuid)->firstOrFail();

        $bank = OrganizerBankAccounts::updateOrCreate(
            ['organizer_id' => $organizer->id],
            [
                'bank_name' => $validated['bank_name'],
                'bank_code' => $validated['bank_code'],
                'account_number' => $validated['account_number'],
                'account_name' => $validated['account_name'],
                'is_default' => true,
                'verified_at' => now(),
            ]
        );

        return ApiResponse::success('Settlement bank account saved successfully.', [
            'bank_name' => $bank->bank_name,
            'bank_code' => $bank->bank_code,
            'account_number' => $bank->account_number,
            'account_name' => $bank->account_name,
            'is_default' => (bool) $bank->is_default,
            'verified_at' => $bank->verified_at,
        ]);
    }
}
