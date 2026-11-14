<?php

namespace App\Console\Commands;

use App\Enums\EscrowStatus;
use App\Exceptions\EscrowException;
use App\Models\Escrow;
use App\Services\Escrow\EscrowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoReleaseEscrows extends Command
{
    protected $signature = 'escrow:auto-release';

    protected $description = 'Release delivered escrows whose buyer window has passed without a dispute';

    public function handle(EscrowService $escrows): int
    {
        $released = 0;

        Escrow::where('status', EscrowStatus::Delivered->value)
            ->where('auto_release_at', '<=', now())
            ->chunkById(100, function ($chunk) use ($escrows, &$released) {
                foreach ($chunk as $escrow) {
                    try {
                        $escrows->autoRelease($escrow);
                        $released++;
                    } catch (EscrowException $e) {
                        // Lost a race with a dispute or a manual release: that is fine, skip it.
                        Log::info('Auto-release skipped', ['escrow' => $escrow->id, 'reason' => $e->getMessage()]);
                    }
                }
            });

        $this->info("Released {$released} escrow(s).");

        return self::SUCCESS;
    }
}
