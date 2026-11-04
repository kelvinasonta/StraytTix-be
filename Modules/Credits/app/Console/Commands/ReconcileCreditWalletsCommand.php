<?php

namespace Modules\Credits\app\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Credits\app\Models\OrganizerCreditWallet;
use Modules\Credits\app\Services\CreditLedgerService;

class ReconcileCreditWalletsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:reconcile {--wallet-id= : Optional specific wallet ID to reconcile}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile organizer credit wallet balances against the credit ledger audit trail';

    /**
     * Execute the console command.
     */
    public function handle(CreditLedgerService $service): int
    {
        $walletId = $this->option('wallet-id');

        $query = OrganizerCreditWallet::query();
        if ($walletId) {
            $query->where('id', $walletId);
        }

        $totalChecked = 0;
        $mismatches = 0;

        $this->info('Starting credit wallet reconciliation audit...');

        $query->chunkById(100, function ($wallets) use ($service, &$totalChecked, &$mismatches): void {
            /** @var OrganizerCreditWallet $wallet */
            foreach ($wallets as $wallet) {
                $totalChecked++;
                $reconciliation = $service->reconcileWallet($wallet);

                if (! $reconciliation['valid']) {
                    $mismatches++;
                    $message = sprintf(
                        'Credit mismatch detected on Wallet #%d (Organizer #%d): Stored balance = %d, Computed balance = %d (Difference: %d)',
                        $wallet->id,
                        $wallet->organizer_id,
                        $reconciliation['stored_balance'],
                        $reconciliation['computed_balance'],
                        $reconciliation['difference']
                    );

                    $this->error($message);
                    Log::channel('daily')->critical($message, [
                        'wallet_id' => $wallet->id,
                        'organizer_id' => $wallet->organizer_id,
                        'reconciliation' => $reconciliation,
                    ]);
                }
            }
        });

        if ($mismatches > 0) {
            $this->warn("Reconciliation finished with {$mismatches} mismatch(es) across {$totalChecked} wallet(s).");

            return self::FAILURE;
        }

        $this->info("Reconciliation audit completed successfully: all {$totalChecked} wallet(s) are balanced.");

        return self::SUCCESS;
    }
}
