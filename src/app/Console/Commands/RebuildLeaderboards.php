<?php

namespace App\Console\Commands;

use App\Services\LeaderboardService;
use Illuminate\Console\Command;

class RebuildLeaderboards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leaderboard:rebuild {--game_id= : Rebuild for a specific game_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuilds Redis leaderboards from MySQL shards.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(protected LeaderboardService $leaderboardService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $gameId = $this->option('game_id');

        $this->info("Starting leaderboard rebuild process...");

        try {
            if ($gameId) {
                $this->leaderboardService->rebuildLeaderboard($gameId);
                $this->info("Leaderboard for game_id {$gameId} rebuilt successfully.");
            } else {
                $this->leaderboardService->rebuildAllLeaderboards();
                $this->info("All leaderboards rebuilt successfully.");
            }
        } catch (\Exception $e) {
            $this->error("Error rebuilding leaderboards: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
