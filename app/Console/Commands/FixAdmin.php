<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Makes the oldest user an admin when no admin exists';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('investbrain.self_hosted')) {
            $this->error('This command is only available on self-hosted instances.');

            return self::FAILURE;
        }

        $existingAdmin = User::where('admin', true)->oldest()->first();

        if ($existingAdmin !== null) {
            $this->line("Nothing to fix, {$existingAdmin->email} is already an admin.");

            return self::SUCCESS;
        }

        $oldestUser = User::oldest()->orderBy('id')->first();

        if ($oldestUser === null) {
            $this->error('There are no users to make an admin.');

            return self::FAILURE;
        }

        $oldestUser->admin = true;
        $oldestUser->save();

        $this->line("Done! {$oldestUser->email} is now an admin.");

        return self::SUCCESS;
    }
}
