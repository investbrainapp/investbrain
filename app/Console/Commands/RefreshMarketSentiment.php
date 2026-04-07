<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Holding;
use App\Models\MarketSentiment;
use Illuminate\Console\Command;

class RefreshMarketSentiment extends Command
{
    protected $signature = 'refresh:market-sentiment
                            {--force : Ignore refresh delay}
                            {--user= : Limit refresh to user\'s holdings}';

    protected $description = 'Refresh optional market sentiment snapshots from Adanos';

    public function handle(): int
    {
        if (! MarketSentiment::enabled()) {
            $this->line('Skipping market sentiment refresh because ADANOS_API_KEY is not configured.');

            return self::SUCCESS;
        }

        $force = (bool) ($this->option('force') ?? false);

        $holdings = Holding::where('quantity', '>', 0)
            ->select(['symbol'])
            ->distinct();

        if ($this->option('user')) {
            $holdings->myHoldings($this->option('user'));
        }

        foreach ($holdings->get() as $holding) {
            $this->line('Refreshing sentiment '.$holding->symbol);

            try {
                MarketSentiment::getMarketSentiment($holding->symbol, $force);
            } catch (\Throwable $e) {
                $this->line('Could not refresh sentiment for '.$holding->symbol.' ('.$e->getMessage().')');
            }
        }

        return self::SUCCESS;
    }
}
