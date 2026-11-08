<?php

namespace Modules\Credits\app\Console\Commands;

use Illuminate\Console\Command;
use Modules\Credits\app\Services\CreditLedgerService;

class ReapExpiredReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:reap-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reap expired pending credit reservations and restore reserved balances';

    /**
     * Execute the console command.
     */
    public function handle(CreditLedgerService $service): int
    {
        $this->info('Starting zombie credit reservation reaping process...');

        $reaped = $service->reapExpiredReservations();

        $this->info("Successfully reaped {$reaped} expired reservation(s).");

        return self::SUCCESS;
    }
}
