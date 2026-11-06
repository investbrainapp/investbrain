<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Telemetry;
use Illuminate\Console\Command;

class SendTelemetry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telemetry:send
                                {--show : Print what would be sent without sending it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send anonymous usage statistics for this self-hosted instance';

    /**
     * Execute the console command. Always succeeds so a failed report can't stop the container from starting.
     */
    public function handle(): int
    {
        if ($this->option('show')) {
            $this->line((string) json_encode(Telemetry::payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if (! Telemetry::enabled()) {
            $this->line('Telemetry is turned off.');

            return self::SUCCESS;
        }

        $sent = Telemetry::send();

        $this->line($sent
            ? 'Sent anonymous usage statistics (version, environment, enabled features and aggregate counts).'
            : 'Could not send anonymous usage statistics. Investbrain will keep starting normally.');
        $this->line('See exactly what is sent with "php artisan telemetry:send --show". Turn it off with TELEMETRY_ENABLED=false.');

        return self::SUCCESS;
    }
}
