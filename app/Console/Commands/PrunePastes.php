<?php

namespace App\Console\Commands;

use App\Models\Paste;
use Illuminate\Console\Command;

class PrunePastes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pastes:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete pastes whose retention period has passed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = Paste::query()->expired()->delete();

        $this->info("Pruned {$deleted} expired paste(s).");

        return self::SUCCESS;
    }
}
