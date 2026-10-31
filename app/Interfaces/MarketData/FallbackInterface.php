<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData;

use App\Events\MarketDataCallCompleted;
use Illuminate\Support\Facades\Log;

class FallbackInterface
{
    protected string $latest_error;

    public function __call($method, $arguments)
    {

        $providers = explode(',', config('investbrain.provider', 'yahoo'));

        foreach ($providers as $attempt => $provider) {

            $provider = trim($provider);
            $symbol = $arguments[0];
            $started_at = hrtime(true);

            try {
                Log::info("Calling method {$method} for {$symbol} ({$provider})");

                if (! in_array($provider, array_keys(config('investbrain.interfaces', [])))) {

                    throw new \Exception("Provider [{$provider}] is not a valid market data interface.");
                }

                $provider_class_name = config("investbrain.interfaces.$provider");

                $result = app()->make($provider_class_name)->$method(...$arguments);

            } catch (\Throwable $e) {

                $this->latest_error = $e->getMessage();

                Log::error("Failed calling method {$method} for {$symbol} ({$provider}): {$this->latest_error}");

                MarketDataCallCompleted::dispatch($provider, $method, (string) $symbol, (hrtime(true) - $started_at) / 1e6, false, $attempt, $this->latest_error);

                continue;
            }

            MarketDataCallCompleted::dispatch($provider, $method, (string) $symbol, (hrtime(true) - $started_at) / 1e6, true, $attempt);

            return $result;
        }

        // don't need to throw error if calling exists method...
        if ($method == 'exists') {

            // symbol prob just doesn't exist
            return false;
        }

        throw new \Exception("Could not get market data calling method {$method}: {$this->latest_error}");
    }
}
