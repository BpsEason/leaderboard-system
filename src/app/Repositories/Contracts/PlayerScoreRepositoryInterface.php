<?php

namespace App\Repositories\Contracts;

use App\Models\PlayerScore;
use Illuminate\Support\Collection;

interface PlayerScoreRepositoryInterface
{
    /**
     * Find a player's score by player_id and game_id.
     *
     * @param int $playerId
     * @param int $gameId
     * @return PlayerScore|null
     */
    public function findByPlayerAndGame(int $playerId, int $gameId): ?PlayerScore;

    /**
     * Create or update a player's score.
     *
     * @param int $playerId
     * @param int $gameId
     * @param int $score
     * @return PlayerScore
     */
    public function updateOrCreate(int $playerId, int $gameId, int $score): PlayerScore;

    /**
     * Get all scores for a specific game across all shards.
     * This method is primarily for rebuilding leaderboards and should be used with caution due to potential performance implications.
     *
     * @param int $gameId
     * @return Collection<PlayerScore>
     */
    public function getAllScoresForGame(int $gameId): Collection;

    /**
     * Get all unique game IDs from all shards.
     * @return Collection<int>
     */
    public function getAllGameIds(): Collection;
}
