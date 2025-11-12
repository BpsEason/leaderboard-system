<?php

namespace App\Services;

use App\Models\PlayerScore;
use App\Repositories\Contracts\PlayerScoreRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ScoreService
{
    public function __construct(
        protected PlayerScoreRepositoryInterface $playerScoreRepository,
        protected LeaderboardService $leaderboardService
    ) {
        //
    }

    /**
     * Store a player's score and update the Redis leaderboard.
     *
     * @param int $playerId
     * @param int $gameId
     * @param int $score
     * @return PlayerScore
     */
    public function storeScore(int $playerId, int $gameId, int $score): PlayerScore
    {
        // Use a transaction to ensure atomicity for DB write and Redis update (best effort)
        // In a high-concurrency scenario, consider using a message queue for Redis updates
        // to decouple and buffer operations, or use Redis Lua scripts for transactional updates.
        // For this prototype, we'll keep it synchronous but wrapped in a DB transaction.
        return DB::transaction(function () use ($playerId, $gameId, $score) {
            // 1. Update score in MySQL (sharded)
            $playerScore = $this->playerScoreRepository->updateOrCreate($playerId, $gameId, $score);

            // 2. Update score in Redis leaderboard
            $this->leaderboardService->updateScore($gameId, $playerId, $score);

            return $playerScore;
        });
    }
}
