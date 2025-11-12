<?php

namespace App\Services;

use App\Exceptions\LeaderboardException;
use App\Repositories\Contracts\PlayerScoreRepositoryInterface;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Collection;

class LeaderboardService
{
    protected RedisFactory $redis;
    protected PlayerScoreRepositoryInterface $playerScoreRepository;

    public function __construct(RedisFactory $redis, PlayerScoreRepositoryInterface $playerScoreRepository)
    {
        $this->redis = $redis;
        $this->playerScoreRepository = $playerScoreRepository;
    }

    /**
     * Get the Redis client instance.
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    protected function getRedisClient(): \Illuminate\Redis\Connections\Connection
    {
        return $this->redis->connection('default');
    }

    /**
     * Get the Redis key for a specific game's leaderboard.
     *
     * @param int $gameId
     * @return string
     */
    protected function getLeaderboardKey(int $gameId): string
    {
        return "leaderboard:game:{$gameId}";
    }

    /**
     * Update a player's score in the Redis leaderboard.
     *
     * @param int $gameId
     * @param int $playerId
     * @param int $score
     * @return bool
     */
    public function updateScore(int $gameId, int $playerId, int $score): bool
    {
        try {
            return (bool) $this->getRedisClient()->zadd($this->getLeaderboardKey($gameId), $score, $playerId);
        } catch (\Exception $e) {
            throw new LeaderboardException("Failed to update score in Redis: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the leaderboard for a specific game.
     *
     * @param int $gameId
     * @param int $offset
     * @param int $limit
     * @return array<array{player_id: int, score: int, rank: int}>
     */
    public function getLeaderboard(int $gameId, int $offset = 0, int $limit = 100): array
    {
        try {
            $key = $this->getLeaderboardKey($gameId);
            $leaderboard = $this->getRedisClient()->zrevrange($key, $offset, $offset + $limit - 1, 'WITHSCORES');

            $formattedLeaderboard = [];
            $rank = $offset + 1;
            foreach (array_chunk($leaderboard, 2) as $entry) {
                $formattedLeaderboard[] = [
                    'player_id' => (int) $entry[0],
                    'score' => (int) $entry[1],
                    'rank' => $rank++,
                ];
            }
            return $formattedLeaderboard;
        } catch (\Exception $e) {
            throw new LeaderboardException("Failed to get leaderboard from Redis: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get a player's rank and score in a specific game's leaderboard.
     *
     * @param int $gameId
     * @param int $playerId
     * @return array{rank: int|null, score: int|null}
     */
    public function getPlayerRank(int $gameId, int $playerId): array
    {
        try {
            $key = $this->getLeaderboardKey($gameId);
            $rank = $this->getRedisClient()->zrevrank($key, $playerId); // 0-indexed rank
            $score = $this->getRedisClient()->zscore($key, $playerId);

            return [
                'rank' => $rank !== null ? (int) $rank + 1 : null, // 1-indexed rank
                'score' => $score !== null ? (int) $score : null,
            ];
        } catch (\Exception $e) {
            throw new LeaderboardException("Failed to get player rank from Redis: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Rebuild the leaderboard for a specific game from MySQL shards.
     *
     * @param int $gameId
     * @return void
     */
    public function rebuildLeaderboard(int $gameId): void
    {
        \Log::info("Rebuilding leaderboard for game_id: {$gameId}");

        try {
            $key = $this->getLeaderboardKey($gameId);
            $this->getRedisClient()->del($key); // Clear existing leaderboard

            $scores = $this->playerScoreRepository->getAllScoresForGame($gameId);

            if ($scores->isEmpty()) {
                \Log::info("No scores found for game_id: {$gameId}. Leaderboard remains empty.");
                return;
            }

            $pipeline = $this->getRedisClient()->pipeline();
            foreach ($scores as $scoreEntry) {
                $pipeline->zadd($key, $scoreEntry->score, $scoreEntry->player_id);
            }
            $pipeline->execute();

            \Log::info("Leaderboard for game_id: {$gameId} rebuilt with " . $scores->count() . " entries.");
        } catch (\Exception $e) {
            throw new LeaderboardException("Failed to rebuild leaderboard for game_id {$gameId}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Rebuild all leaderboards from MySQL shards.
     *
     * @return void
     */
    public function rebuildAllLeaderboards(): void
    {
        \Log::info("Rebuilding all leaderboards.");

        try {
            $gameIds = $this->playerScoreRepository->getAllGameIds();

            if ($gameIds->isEmpty()) {
                \Log::info("No game IDs found to rebuild leaderboards.");
                return;
            }

            foreach ($gameIds as $gameId) {
                $this->rebuildLeaderboard($gameId);
            }

            \Log::info("All leaderboards rebuilt successfully for " . $gameIds->count() . " games.");
        } catch (\Exception $e) {
            throw new LeaderboardException("Failed to rebuild all leaderboards: " . $e->getMessage(), 0, $e);
        }
    }
}
