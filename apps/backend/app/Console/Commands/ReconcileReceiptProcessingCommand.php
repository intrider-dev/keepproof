<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Documents\Actions\ReconcileReceiptProcessing;
use Illuminate\Console\Command;

final class ReconcileReceiptProcessingCommand extends Command
{
    /** @var string */
    protected $signature = 'keepproof:receipts:reconcile';

    /** @var string */
    protected $description = 'Redispatch stale database-backed receipt processing work';

    public function handle(ReconcileReceiptProcessing $reconcile): int
    {
        $result = $reconcile->execute();
        $this->components->info(sprintf(
            'Receipt reconciliation dispatched %d job(s); %d dispatch(es) failed.',
            $result['dispatched'],
            $result['failed'],
        ));

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
